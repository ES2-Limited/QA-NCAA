<div>
    <!-- Top Auditor Welcome & Stats Banner -->
    <div class="mb-6 bg-gradient-to-r from-emerald-800 to-teal-900 rounded-2xl p-5 text-white shadow-lg relative overflow-hidden">
        <div class="absolute -right-6 -bottom-8 opacity-10 text-9xl select-none font-black">
            NCAA
        </div>
        <div class="relative z-10">
            <div class="inline-block px-2.5 py-1 bg-amber-400 text-emerald-950 font-bold text-xs uppercase rounded-full mb-2 tracking-wide">
                Directorate of Special Duties (DSD)
            </div>
            <h1 class="text-2xl font-black tracking-tight">QA / Internal Audit Field Operations</h1>
            <p class="text-sm text-emerald-100 mt-1 max-w-xl">
                Civil Aviation Quality Assurance, Safety Compliance & Surveillance Inspection Portal.
            </p>

            <!-- Quick Metrics Grid -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mt-4 pt-4 border-t border-emerald-700/60 text-center">
                <div class="bg-emerald-900/40 backdrop-blur rounded-xl p-2.5 border border-emerald-700/50">
                    <div class="text-2xl font-black text-white">{{ $stats['total'] }}</div>
                    <div class="text-xs text-emerald-200">Engagements</div>
                </div>
                <div class="bg-emerald-900/40 backdrop-blur rounded-xl p-2.5 border border-emerald-700/50">
                    <div class="text-2xl font-black text-amber-300">{{ $stats['in_progress'] }}</div>
                    <div class="text-xs text-emerald-200">Active In Field</div>
                </div>
                <div class="bg-emerald-900/40 backdrop-blur rounded-xl p-2.5 border border-emerald-700/50">
                    <div class="text-2xl font-black text-blue-300">{{ $stats['scheduled'] }}</div>
                    <div class="text-xs text-emerald-200">Scheduled</div>
                </div>
                <div class="bg-emerald-900/40 backdrop-blur rounded-xl p-2.5 border border-emerald-700/50">
                    <div class="text-2xl font-black text-emerald-300">{{ $stats['completed'] }}</div>
                    <div class="text-xs text-emerald-200">Closed Out</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter Buttons -->
    <div class="flex items-center justify-between mb-4 overflow-x-auto pb-1">
        <div class="inline-flex p-1 bg-white border border-slate-200 rounded-xl shadow-sm text-xs font-semibold">
            <button wire:click="$set('filter', 'all')" class="px-3 py-1.5 rounded-lg transition {{ $filter === 'all' ? 'bg-emerald-700 text-white' : 'text-slate-600 hover:text-emerald-700' }}">
                All Audits
            </button>
            <button wire:click="$set('filter', 'in_progress')" class="px-3 py-1.5 rounded-lg transition {{ $filter === 'in_progress' ? 'bg-emerald-700 text-white' : 'text-slate-600 hover:text-emerald-700' }}">
                In Progress ({{ $stats['in_progress'] }})
            </button>
            <button wire:click="$set('filter', 'scheduled')" class="px-3 py-1.5 rounded-lg transition {{ $filter === 'scheduled' ? 'bg-emerald-700 text-white' : 'text-slate-600 hover:text-emerald-700' }}">
                Scheduled ({{ $stats['scheduled'] }})
            </button>
            <button wire:click="$set('filter', 'completed')" class="px-3 py-1.5 rounded-lg transition {{ $filter === 'completed' ? 'bg-emerald-700 text-white' : 'text-slate-600 hover:text-emerald-700' }}">
                Completed
            </button>
        </div>

        <a href="/admin/audits/create" class="inline-flex items-center space-x-1.5 px-3 py-2 bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-bold rounded-xl shadow transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            <span>New Audit</span>
        </a>
    </div>

    <!-- Audits Cards List -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        @forelse($audits as $audit)
            <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm hover:shadow-md transition flex flex-col justify-between">
                <div>
                    <!-- Top row: Ref and Status -->
                    <div class="flex items-center justify-between mb-3">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-md text-xs font-mono font-bold bg-slate-100 text-slate-800 border border-slate-200">
                            {{ $audit->reference_number }}
                        </span>
                        @if($audit->status === 'in_progress')
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-amber-100 text-amber-800 border border-amber-300">
                                <span class="w-1.5 h-1.5 rounded-full bg-amber-500 mr-1.5 animate-ping"></span>
                                In Field
                            </span>
                        @elseif($audit->status === 'scheduled')
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 border border-blue-200">
                                Scheduled
                            </span>
                        @else
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800 border border-emerald-200">
                                Completed
                            </span>
                        @endif
                    </div>

                    <!-- Title & Target Directorate -->
                    <h3 class="text-base font-bold text-slate-900 leading-snug">
                        {{ $audit->title }}
                    </h3>
                    
                    <div class="mt-2 text-xs text-slate-600 flex items-center space-x-1.5">
                        <span class="font-bold text-emerald-800 uppercase bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200">
                            {{ $audit->directorate->code ?? 'NCAA' }}
                        </span>
                        <span class="text-slate-400">&bull;</span>
                        <span class="truncate">{{ $audit->directorate->name ?? 'General Directorate' }}</span>
                    </div>

                    <!-- Location & Date -->
                    <div class="mt-3 flex items-center justify-between text-xs text-slate-500 pt-3 border-t border-slate-100">
                        <div class="flex items-center space-x-1">
                            <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            <span>{{ $audit->location }}</span>
                        </div>
                        <div>
                            {{ $audit->start_date ? $audit->start_date->format('M d, Y') : 'TBD' }}
                        </div>
                    </div>

                    <!-- Compliance score & Findings -->
                    <div class="mt-3 bg-slate-50 rounded-xl p-3 border border-slate-100 flex items-center justify-between text-xs">
                        <div>
                            <span class="text-slate-500">Compliance:</span>
                            <span class="font-bold text-slate-800 ml-1">{{ $audit->compliance_score }}%</span>
                        </div>
                        <div class="flex items-center space-x-2">
                            <span class="text-slate-500">Findings:</span>
                            <span class="font-bold {{ $audit->findings->count() > 0 ? 'text-rose-600' : 'text-emerald-600' }}">
                                {{ $audit->findings->count() }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Action Button -->
                <div class="mt-4 pt-2">
                    <a href="{{ route('auditor.execute', $audit->id) }}" class="w-full flex items-center justify-center space-x-2 py-2.5 px-4 bg-emerald-700 hover:bg-emerald-800 text-white rounded-xl text-xs font-bold transition shadow-sm">
                        <span>Open Field Checklist</span>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                    </a>
                </div>
            </div>
        @empty
            <div class="col-span-full py-12 text-center bg-white rounded-2xl border border-slate-200">
                <svg class="w-12 h-12 text-slate-300 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                <div class="text-sm font-bold text-slate-700">No Audits Found</div>
                <div class="text-xs text-slate-500 mt-1">There are no audit engagements matching this filter.</div>
            </div>
        @endforelse
    </div>
</div>
