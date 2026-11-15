<?php

use App\Contracts\AiService;
use App\Models\Audit;
use App\Models\Certificate;
use App\Models\Directorate;
use App\Models\Finding;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Executive Dashboard')] class extends Component {

    public string $reportPeriod = 'last_30_days';

    public string $reportDirectorateId = '';

    public string $reportMarkdown = '';

    public string $reportHtml = '';

    public bool $reportLoading = false;

    public function mount(): void
    {
        if (! auth()->user()->hasAnyRole(['system_admin', 'lead_auditor', 'head_of_audit', 'director'])) {
            abort(403);
        }
    }

    public function with(): array
    {
        // ── KPI Cards ──────────────────────────────────────────────────────────
        $auditsThisMonth = Audit::where(function ($q) {
            $q->whereBetween('scheduled_for', [now()->startOfMonth(), now()->endOfMonth()])
                ->orWhereBetween('start_date', [now()->startOfMonth(), now()->endOfMonth()])
                ->orWhereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()]);
        })->count();

        $inProgress = Audit::where('status', 'in_progress')->count();

        $awaitingApproval = Audit::whereIn('status', ['submitted', 'reviewed', 'head_approved'])->count();

        $certificates = Certificate::count();

        $openNcrs = Finding::where('status', 'open')->count();

        $overdueCaps = Finding::where('status', 'open')
            ->where(function ($q) {
                $q->where('cap_due_at', '<', now())
                    ->orWhere('due_date', '<', now());
            })->count();

        // ── Chart 1: Audits by status (doughnut) ──────────────────────────────
        $statusCounts = Audit::selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        // ── Chart 2: NCRs by directorate (bar) ────────────────────────────────
        $directorates = Directorate::orderBy('code')->get();
        $ncrsByDir = [];
        foreach ($directorates as $dir) {
            $ncrsByDir[$dir->code] = Finding::where(function ($q) use ($dir) {
                $q->whereHas('audit', fn ($aq) => $aq->where('directorate_id', $dir->id))
                    ->orWhereHas('unit.department', fn ($dq) => $dq->where('directorate_id', $dir->id));
            })->count();
        }

        // ── Chart 3: Completed audits last 6 months (line) ────────────────────
        $completedLabels = [];
        $completedByMonth = [];
        for ($i = 5; $i >= 0; $i--) {
            $m = now()->subMonths($i);
            $completedLabels[] = $m->format('M Y');
            $completedByMonth[] = Audit::where('status', 'director_approved')
                ->whereBetween('updated_at', [$m->copy()->startOfMonth(), $m->copy()->endOfMonth()])
                ->count();
        }

        // ── Upcoming routine audits (next 30 days) ────────────────────────────
        $upcomingAudits = Audit::with(['process', 'directorate', 'leadAuditor'])
            ->where('type', 'routine')
            ->where('status', 'scheduled')
            ->whereBetween('scheduled_for', [now()->startOfDay(), now()->addDays(30)->endOfDay()])
            ->orderBy('scheduled_for')
            ->get();

        // ── Overdue CAPs table ────────────────────────────────────────────────
        $overdueCapsTable = Finding::with(['audit.directorate', 'unit'])
            ->where('status', 'open')
            ->where(function ($q) {
                $q->where('cap_due_at', '<', now())
                    ->orWhere('due_date', '<', now());
            })
            ->orderBy('cap_due_at')
            ->get();

        // ── Recent certificates ───────────────────────────────────────────────
        $recentCerts = Certificate::with(['audit.directorate', 'issuer'])
            ->latest('issued_at')
            ->take(5)
            ->get();

        // ── Directorate heat list ─────────────────────────────────────────────
        $heatList = $directorates->map(function ($dir) {
            $count = Finding::where('status', 'open')
                ->where(function ($q) use ($dir) {
                    $q->whereHas('audit', fn ($aq) => $aq->where('directorate_id', $dir->id))
                        ->orWhereHas('unit.department', fn ($dq) => $dq->where('directorate_id', $dir->id));
                })->count();

            return [
                'id' => $dir->id,
                'code' => $dir->code,
                'name' => $dir->name,
                'open_ncrs' => $count,
                'badge' => $count >= 3 ? 'red' : ($count >= 1 ? 'amber' : 'green'),
            ];
        });

        return compact(
            'auditsThisMonth', 'inProgress', 'awaitingApproval', 'certificates',
            'openNcrs', 'overdueCaps', 'statusCounts', 'ncrsByDir',
            'completedLabels', 'completedByMonth',
            'upcomingAudits', 'overdueCapsTable', 'recentCerts',
            'heatList', 'directorates',
        );
    }

    public function generateReport(): void
    {
        $userId = auth()->id() ?? request()->ip();
        $executed = \Illuminate\Support\Facades\RateLimiter::attempt(
            'ai-actions:'.$userId,
            10,
            function () {},
            60
        );

        if (! $executed) {
            $seconds = \Illuminate\Support\Facades\RateLimiter::availableIn('ai-actions:'.$userId);
            $this->reportMarkdown = "AI rate limit reached. Please wait {$seconds} seconds before generating another report.";
            $this->reportHtml = '<p class="text-rose-600 font-semibold">' . htmlspecialchars($this->reportMarkdown) . '</p>';
            return;
        }

        $this->reportLoading = true;

        $period = match ($this->reportPeriod) {
            'last_30_days' => 'Last 30 Days',
            'last_quarter' => 'Last Quarter',
            'this_year' => 'Year to Date '.now()->year,
            default => 'Last 30 Days',
        };

        $context = "Period: {$period}. ".
            'Total audits: '.Audit::count().'. '.
            'Open NCRs: '.Finding::where('status', 'open')->count().'. '.
            'Overdue CAPs: '.Finding::where('status', 'open')
                ->where(fn ($q) => $q->where('cap_due_at', '<', now())->orWhere('due_date', '<', now()))
                ->count().'. '.
            'Certificates issued (total): '.Certificate::count().'. '.
            'Audits this month: '.Audit::where(fn ($q) => $q
                ->whereBetween('scheduled_for', [now()->startOfMonth(), now()->endOfMonth()])
                ->orWhereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
            )->count().'. '.
            'In-progress audits: '.Audit::where('status', 'in_progress')->count().'. '.
            'Awaiting approval: '.Audit::whereIn('status', ['submitted', 'reviewed', 'head_approved'])->count().'. ';

        if ($this->reportDirectorateId) {
            $dir = Directorate::find($this->reportDirectorateId);
            if ($dir) {
                $dirNcrs = Finding::where('status', 'open')
                    ->whereHas('audit', fn ($q) => $q->where('directorate_id', $dir->id))
                    ->count();
                $context .= "Directorate focus: {$dir->name} ({$dir->code}). Open NCRs in {$dir->code}: {$dirNcrs}. ";
            }
        }

        $this->reportMarkdown = app(AiService::class)->generateReport($context);
        $this->reportHtml = Str::markdown($this->reportMarkdown);
        $this->reportLoading = false;
    }

    public function downloadReport(): mixed
    {
        if (empty($this->reportMarkdown)) {
            return null;
        }

        $period = match ($this->reportPeriod) {
            'last_30_days' => 'Last 30 Days',
            'last_quarter' => 'Last Quarter',
            'this_year' => 'Year to Date '.now()->year,
            default => 'Last 30 Days',
        };

        $pdf = Pdf::loadView('reports.executive_report_pdf', [
            'date' => now()->format('F d, Y'),
            'period' => $period,
            'renderedHtml' => $this->reportHtml,
        ])->setPaper('a4', 'portrait');

        return response()->streamDownload(
            fn () => print($pdf->output()),
            'executive-report-'.now()->format('Y-m-d').'.pdf',
            ['Content-Type' => 'application/pdf'],
        );
    }
}; ?>

<div class="min-h-screen bg-zinc-50 dark:bg-zinc-900">
    <div class="max-w-screen-xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-8">

        {{-- ── Page Header ────────────────────────────────────────────────── --}}
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <flux:breadcrumbs>
                    <flux:breadcrumbs.item href="{{ route('home') }}">Home</flux:breadcrumbs.item>
                    <flux:breadcrumbs.item>Dashboard</flux:breadcrumbs.item>
                </flux:breadcrumbs>
                <flux:heading size="xl" class="mt-1">Executive Dashboard</flux:heading>
                <flux:subheading>Quality Assurance &amp; Internal Audit — NCAA Nigeria</flux:subheading>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <flux:modal.trigger name="ai-report">
                    <flux:button variant="primary" icon="sparkles" size="sm">
                        Generate AI Report
                    </flux:button>
                </flux:modal.trigger>
            </div>
        </div>

        {{-- ── KPI Cards ──────────────────────────────────────────────────── --}}
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
            {{-- Audits This Month --}}
            <a href="{{ route('audits.index', ['timeFilter' => 'this_month']) }}" wire:navigate
               class="bg-white dark:bg-zinc-800 rounded-2xl border border-zinc-200 dark:border-zinc-700 p-4 shadow-sm hover:shadow-md hover:border-emerald-300 dark:hover:border-emerald-600 transition group">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-semibold text-zinc-500 uppercase tracking-wide">This Month</span>
                    <flux:icon.calendar-days class="size-4 text-emerald-500" />
                </div>
                <div class="text-2xl font-bold text-zinc-900 dark:text-zinc-100 group-hover:text-emerald-600 transition">
                    {{ $auditsThisMonth }}
                </div>
                <div class="text-xs text-zinc-500 mt-0.5">Audits</div>
            </a>

            {{-- In Progress --}}
            <a href="{{ route('audits.index', ['statusFilter' => 'in_progress']) }}" wire:navigate
               class="bg-white dark:bg-zinc-800 rounded-2xl border border-zinc-200 dark:border-zinc-700 p-4 shadow-sm hover:shadow-md hover:border-amber-300 dark:hover:border-amber-600 transition group">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-semibold text-zinc-500 uppercase tracking-wide">In Progress</span>
                    <flux:icon.arrow-path class="size-4 text-amber-500" />
                </div>
                <div class="text-2xl font-bold text-zinc-900 dark:text-zinc-100 group-hover:text-amber-600 transition">
                    {{ $inProgress }}
                </div>
                <div class="text-xs text-zinc-500 mt-0.5">Audits</div>
            </a>

            {{-- Awaiting Approval --}}
            <a href="{{ route('audits.index', ['statusFilter' => 'awaiting_approval']) }}" wire:navigate
               class="bg-white dark:bg-zinc-800 rounded-2xl border border-zinc-200 dark:border-zinc-700 p-4 shadow-sm hover:shadow-md hover:border-blue-300 dark:hover:border-blue-600 transition group">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-semibold text-zinc-500 uppercase tracking-wide">Awaiting</span>
                    <flux:icon.clock class="size-4 text-blue-500" />
                </div>
                <div class="text-2xl font-bold text-zinc-900 dark:text-zinc-100 group-hover:text-blue-600 transition">
                    {{ $awaitingApproval }}
                </div>
                <div class="text-xs text-zinc-500 mt-0.5">Approval</div>
            </a>

            {{-- Certificates --}}
            <a href="{{ route('audits.index') }}" wire:navigate
               class="bg-white dark:bg-zinc-800 rounded-2xl border border-zinc-200 dark:border-zinc-700 p-4 shadow-sm hover:shadow-md hover:border-emerald-300 dark:hover:border-emerald-600 transition group">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-semibold text-zinc-500 uppercase tracking-wide">Certificates</span>
                    <flux:icon.shield-check class="size-4 text-emerald-600" />
                </div>
                <div class="text-2xl font-bold text-zinc-900 dark:text-zinc-100 group-hover:text-emerald-600 transition">
                    {{ $certificates }}
                </div>
                <div class="text-xs text-zinc-500 mt-0.5">Issued</div>
            </a>

            {{-- Open NCRs --}}
            <a href="{{ route('ncrs.index', ['statusFilter' => 'open']) }}" wire:navigate
               class="bg-white dark:bg-zinc-800 rounded-2xl border border-zinc-200 dark:border-zinc-700 p-4 shadow-sm hover:shadow-md hover:border-rose-300 dark:hover:border-rose-600 transition group">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-semibold text-zinc-500 uppercase tracking-wide">Open NCRs</span>
                    <flux:icon.exclamation-triangle class="size-4 text-rose-500" />
                </div>
                <div class="text-2xl font-bold text-zinc-900 dark:text-zinc-100 group-hover:text-rose-600 transition">
                    {{ $openNcrs }}
                </div>
                <div class="text-xs text-zinc-500 mt-0.5">Findings</div>
            </a>

            {{-- Overdue CAPs --}}
            <a href="{{ route('ncrs.index', ['overdueFilter' => '1']) }}" wire:navigate
               class="bg-white dark:bg-zinc-800 rounded-2xl border border-zinc-200 dark:border-zinc-700 p-4 shadow-sm hover:shadow-md hover:border-red-300 dark:hover:border-red-600 transition group">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-semibold text-zinc-500 uppercase tracking-wide">Overdue</span>
                    <flux:icon.fire class="size-4 text-red-500" />
                </div>
                <div class="text-2xl font-bold text-zinc-900 dark:text-zinc-100 group-hover:text-red-600 transition">
                    {{ $overdueCaps }}
                </div>
                <div class="text-xs text-zinc-500 mt-0.5">CAPs</div>
            </a>
        </div>

        {{-- ── Charts Row ─────────────────────────────────────────────────── --}}
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">

            {{-- Doughnut: Audits by Status --}}
            <div class="bg-white dark:bg-zinc-800 rounded-2xl border border-zinc-200 dark:border-zinc-700 p-5 shadow-sm">
                <flux:heading size="sm" class="mb-4">Audits by Status</flux:heading>
                <div wire:ignore class="relative h-48">
                    <canvas id="statusChart"></canvas>
                </div>
            </div>

            {{-- Bar: NCRs by Directorate --}}
            <div class="bg-white dark:bg-zinc-800 rounded-2xl border border-zinc-200 dark:border-zinc-700 p-5 shadow-sm">
                <flux:heading size="sm" class="mb-4">Open NCRs by Directorate</flux:heading>
                <div wire:ignore class="relative h-48">
                    <canvas id="ncrBarChart"></canvas>
                </div>
            </div>

            {{-- Line: Completed Audits Last 6 Months --}}
            <div class="bg-white dark:bg-zinc-800 rounded-2xl border border-zinc-200 dark:border-zinc-700 p-5 shadow-sm md:col-span-2 lg:col-span-1">
                <flux:heading size="sm" class="mb-4">Completed Audits — Last 6 Months</flux:heading>
                <div wire:ignore class="relative h-48">
                    <canvas id="completedLineChart"></canvas>
                </div>
            </div>
        </div>

        {{-- ── Tables + Heat Row ──────────────────────────────────────────── --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

            {{-- Upcoming Routine Audits --}}
            <div class="lg:col-span-2 bg-white dark:bg-zinc-800 rounded-2xl border border-zinc-200 dark:border-zinc-700 shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-zinc-200 dark:border-zinc-700 flex items-center justify-between">
                    <flux:heading size="sm">Upcoming Routine Audits <span class="text-zinc-400 font-normal text-xs">(next 30 days)</span></flux:heading>
                    <flux:button href="{{ route('audits.index', ['statusFilter' => 'scheduled']) }}" size="xs" variant="ghost" wire:navigate>View all</flux:button>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead class="bg-zinc-50 dark:bg-zinc-900/50 text-xs text-zinc-500 uppercase font-semibold">
                            <tr>
                                <th class="py-3 px-4">Audit</th>
                                <th class="py-3 px-4">Directorate</th>
                                <th class="py-3 px-4">Scheduled</th>
                                <th class="py-3 px-4">Lead</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                            @forelse($upcomingAudits as $audit)
                                <tr class="hover:bg-zinc-50/80 dark:hover:bg-zinc-750 cursor-pointer transition" onclick="window.location='{{ route('audits.show', $audit->id) }}'">
                                    <td class="py-3 px-4">
                                        <div class="font-semibold text-zinc-900 dark:text-zinc-100 text-sm truncate max-w-[180px]">{{ $audit->title }}</div>
                                        <div class="text-xs text-zinc-400 font-mono">{{ $audit->reference_number }}</div>
                                    </td>
                                    <td class="py-3 px-4 text-xs font-bold text-zinc-700 dark:text-zinc-300">{{ $audit->directorate?->code ?? '—' }}</td>
                                    <td class="py-3 px-4 text-xs font-mono text-zinc-700 dark:text-zinc-300">{{ $audit->scheduled_for?->format('d M Y') ?? '—' }}</td>
                                    <td class="py-3 px-4 text-xs text-zinc-600 dark:text-zinc-400 truncate max-w-[120px]">{{ $audit->leadAuditor?->name ?? '—' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="py-10 text-center">
                                        <div class="flex flex-col items-center gap-2 text-zinc-400">
                                            <flux:icon.calendar-days class="size-8 opacity-40" />
                                            <span class="text-sm">No routine audits scheduled in the next 30 days.</span>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Directorate Heat List --}}
            <div class="bg-white dark:bg-zinc-800 rounded-2xl border border-zinc-200 dark:border-zinc-700 shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-zinc-200 dark:border-zinc-700">
                    <flux:heading size="sm">Directorate NCR Heat</flux:heading>
                    <flux:subheading class="text-xs mt-0.5">Open NCRs per directorate</flux:subheading>
                </div>
                <div class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse($heatList as $item)
                        <div class="flex items-center justify-between px-5 py-3">
                            <div>
                                <div class="text-sm font-bold text-zinc-800 dark:text-zinc-200">{{ $item['code'] }}</div>
                                <div class="text-xs text-zinc-500 truncate max-w-[140px]">{{ $item['name'] }}</div>
                            </div>
                            <a href="{{ route('ncrs.index', ['directorateFilter' => $item['id'], 'statusFilter' => 'open']) }}" wire:navigate>
                                <flux:badge size="sm" :color="$item['badge'] === 'red' ? 'red' : ($item['badge'] === 'amber' ? 'amber' : 'green')">
                                    {{ $item['open_ncrs'] }} {{ Str::plural('NCR', $item['open_ncrs']) }}
                                </flux:badge>
                            </a>
                        </div>
                    @empty
                        <div class="py-8 text-center text-zinc-400 text-sm">No directorates found.</div>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- ── Overdue CAPs Table ─────────────────────────────────────────── --}}
        <div class="bg-white dark:bg-zinc-800 rounded-2xl border border-zinc-200 dark:border-zinc-700 shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-zinc-200 dark:border-zinc-700 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <flux:icon.fire class="size-4 text-red-500" />
                    <flux:heading size="sm">Overdue Corrective Action Plans</flux:heading>
                </div>
                <flux:button href="{{ route('ncrs.index', ['overdueFilter' => '1']) }}" size="xs" variant="ghost" wire:navigate>View all</flux:button>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="bg-zinc-50 dark:bg-zinc-900/50 text-xs text-zinc-500 uppercase font-semibold">
                        <tr>
                            <th class="py-3 px-4">NCR Reference</th>
                            <th class="py-3 px-4">Audit</th>
                            <th class="py-3 px-4">Directorate</th>
                            <th class="py-3 px-4">CAP Due</th>
                            <th class="py-3 px-4">Days Overdue</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                        @forelse($overdueCapsTable as $ncr)
                            <tr class="hover:bg-red-50/60 dark:hover:bg-red-950/20 cursor-pointer transition" onclick="window.location='{{ route('ncrs.show', $ncr->id) }}'">
                                <td class="py-3 px-4 font-mono text-xs font-semibold text-rose-700 dark:text-rose-400">{{ $ncr->reference_number }}</td>
                                <td class="py-3 px-4 text-xs text-zinc-700 dark:text-zinc-300">{{ $ncr->audit?->title ?? '—' }}</td>
                                <td class="py-3 px-4 text-xs font-bold text-zinc-700 dark:text-zinc-300">{{ $ncr->audit?->directorate?->code ?? '—' }}</td>
                                <td class="py-3 px-4 text-xs font-mono text-red-600 dark:text-red-400">
                                    {{ ($ncr->cap_due_at ?? $ncr->due_date)?->format('d M Y') ?? '—' }}
                                </td>
                                <td class="py-3 px-4 text-xs font-bold text-red-700 dark:text-red-400">
                                    {{ ($ncr->cap_due_at ?? $ncr->due_date)?->diffInDays(now()) }} days
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-10 text-center">
                                    <div class="flex flex-col items-center gap-2 text-zinc-400">
                                        <flux:icon.check-circle class="size-8 opacity-40" />
                                        <span class="text-sm">No overdue corrective action plans. All on track!</span>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- ── Recent Certificates ────────────────────────────────────────── --}}
        <div class="bg-white dark:bg-zinc-800 rounded-2xl border border-zinc-200 dark:border-zinc-700 shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-zinc-200 dark:border-zinc-700">
                <flux:heading size="sm">Recent Compliance Certificates</flux:heading>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="bg-zinc-50 dark:bg-zinc-900/50 text-xs text-zinc-500 uppercase font-semibold">
                        <tr>
                            <th class="py-3 px-4">Certificate No.</th>
                            <th class="py-3 px-4">Audit</th>
                            <th class="py-3 px-4">Directorate</th>
                            <th class="py-3 px-4">Issued</th>
                            <th class="py-3 px-4">Expires</th>
                            <th class="py-3 px-4 text-right">Download</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                        @forelse($recentCerts as $cert)
                            <tr class="hover:bg-zinc-50/80 dark:hover:bg-zinc-750 transition">
                                <td class="py-3 px-4 font-mono text-xs font-semibold text-emerald-700 dark:text-emerald-400">{{ $cert->number }}</td>
                                <td class="py-3 px-4 text-xs text-zinc-700 dark:text-zinc-300">{{ $cert->audit?->title ?? '—' }}</td>
                                <td class="py-3 px-4 text-xs font-bold text-zinc-700 dark:text-zinc-300">{{ $cert->audit?->directorate?->code ?? '—' }}</td>
                                <td class="py-3 px-4 text-xs font-mono text-zinc-500">{{ $cert->issued_at?->format('d M Y') ?? '—' }}</td>
                                <td class="py-3 px-4 text-xs font-mono text-zinc-500">
                                    @if($cert->expires_at)
                                        <span class="{{ $cert->expires_at->isPast() ? 'text-red-500 font-semibold' : '' }}">
                                            {{ $cert->expires_at->format('d M Y') }}
                                        </span>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="py-3 px-4 text-right">
                                    <flux:button href="{{ route('certificates.download', $cert->audit_id) }}" size="xs" variant="ghost" icon="arrow-down-tray" target="_blank">
                                        PDF
                                    </flux:button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-10 text-center">
                                    <div class="flex flex-col items-center gap-2 text-zinc-400">
                                        <flux:icon.shield-check class="size-8 opacity-40" />
                                        <span class="text-sm">No certificates have been issued yet.</span>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    </div>{{-- /max-w container --}}

    {{-- ── AI Report Modal ──────────────────────────────────────────────────── --}}
    <flux:modal name="ai-report" class="w-full max-w-2xl">
        <div class="p-6 space-y-5">
            <div>
                <flux:heading size="lg">Generate Executive AI Report</flux:heading>
                <flux:subheading class="mt-1">Select a period and optional directorate focus, then click Generate.</flux:subheading>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <flux:select wire:model="reportPeriod" label="Reporting Period">
                    <flux:select.option value="last_30_days">Last 30 Days</flux:select.option>
                    <flux:select.option value="last_quarter">Last Quarter</flux:select.option>
                    <flux:select.option value="this_year">Year to Date {{ now()->year }}</flux:select.option>
                </flux:select>

                <flux:select wire:model="reportDirectorateId" label="Directorate (optional)">
                    <flux:select.option value="">All Directorates</flux:select.option>
                    @foreach($directorates as $dir)
                        <flux:select.option value="{{ $dir->id }}">{{ $dir->code }} — {{ $dir->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            <div class="flex items-center gap-3">
                <flux:button wire:click="generateReport" variant="primary" icon="sparkles" :disabled="$reportLoading">
                    {{ $reportLoading ? 'Generating…' : 'Generate Report' }}
                </flux:button>
                @if($reportMarkdown)
                    <flux:button wire:click="downloadReport" variant="ghost" icon="arrow-down-tray" :disabled="$reportLoading">
                        Download PDF
                    </flux:button>
                    <button
                        type="button"
                        x-data
                        @click="navigator.clipboard.writeText($wire.reportMarkdown); $dispatch('notify', {message: 'Copied to clipboard!'})"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-zinc-700 dark:text-zinc-300 hover:text-emerald-600 transition">
                        <flux:icon.clipboard-document class="size-4" /> Copy Markdown
                    </button>
                @endif
            </div>

            @if($reportLoading)
                <div class="flex items-center gap-3 text-sm text-zinc-500 py-6 justify-center">
                    <svg class="animate-spin size-5 text-emerald-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                    Generating report with AI…
                </div>
            @elseif($reportHtml)
                <div class="bg-zinc-50 dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-700 p-5 overflow-y-auto max-h-96 prose prose-sm dark:prose-invert max-w-none">
                    {!! $reportHtml !!}
                </div>
            @endif
        </div>
    </flux:modal>
</div>

{{-- ── Chart.js Initialization ─────────────────────────────────────────── --}}
@script
<script>
    // Status doughnut
    (function() {
        const labels = @json(array_keys($statusCounts));
        const data   = @json(array_values($statusCounts));
        const colors = ['#10b981','#f59e0b','#3b82f6','#8b5cf6','#06b6d4','#f43f5e','#64748b'];

        new Chart(document.getElementById('statusChart'), {
            type: 'doughnut',
            data: {
                labels: labels.map(l => l.replace(/_/g,' ').replace(/\b\w/g,c=>c.toUpperCase())),
                datasets: [{ data, backgroundColor: colors.slice(0, data.length), borderWidth: 2 }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom', labels: { font: { size: 11 }, boxWidth: 12 } } }
            }
        });
    })();

    // NCR bar chart
    (function() {
        const labels = @json(array_keys($ncrsByDir));
        const data   = @json(array_values($ncrsByDir));

        new Chart(document.getElementById('ncrBarChart'), {
            type: 'bar',
            data: {
                labels,
                datasets: [{
                    label: 'Open NCRs',
                    data,
                    backgroundColor: data.map(v => v >= 3 ? '#f43f5e' : v >= 1 ? '#f59e0b' : '#10b981'),
                    borderRadius: 6,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
            }
        });
    })();

    // Completed audits line chart
    (function() {
        const labels = @json($completedLabels);
        const data   = @json($completedByMonth);

        new Chart(document.getElementById('completedLineChart'), {
            type: 'line',
            data: {
                labels,
                datasets: [{
                    label: 'Completed Audits',
                    data,
                    borderColor: '#10b981',
                    backgroundColor: 'rgba(16,185,129,0.1)',
                    tension: 0.4,
                    fill: true,
                    pointRadius: 4,
                    pointBackgroundColor: '#10b981',
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
            }
        });
    })();
</script>
@endscript
