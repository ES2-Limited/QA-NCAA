<div>
    <!-- Back breadcrumb & Engagement header -->
    <div class="mb-4">
        <a href="{{ route('auditor.dashboard') }}" class="inline-flex items-center text-xs font-semibold text-emerald-800 hover:text-emerald-950 mb-2">
            &larr; Back to Audits Hub
        </a>
        <div class="bg-white rounded-2xl border border-slate-200 p-4 shadow-sm">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <div class="flex items-center space-x-2">
                        <span class="px-2 py-0.5 rounded text-xs font-mono font-bold bg-emerald-100 text-emerald-800 border border-emerald-200">
                            {{ $audit->reference_number }}
                        </span>
                        <span class="px-2 py-0.5 rounded text-xs font-semibold bg-slate-100 text-slate-700">
                            {{ $audit->directorate->code ?? 'DSD' }}
                        </span>
                        <span class="px-2 py-0.5 rounded text-xs font-semibold bg-slate-100 text-slate-700">
                            {{ $audit->location }}
                        </span>
                    </div>
                    <h1 class="text-xl font-black text-slate-900 mt-1.5">{{ $audit->title }}</h1>
                    <p class="text-xs text-slate-500 mt-0.5">
                        Lead Auditor: <span class="font-semibold text-slate-700">{{ $audit->leadAuditor->name ?? 'DSD Auditor' }}</span> &bull; 
                        Compliance Score: <span class="font-bold text-emerald-700">{{ $audit->compliance_score }}%</span>
                    </p>
                </div>

                <div class="flex items-center space-x-2">
                    <a href="{{ route('auditor.report-finding', $audit->id) }}" class="inline-flex items-center space-x-1.5 px-3 py-2 bg-rose-600 hover:bg-rose-700 text-white rounded-xl text-xs font-bold shadow-sm transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                        <span>+ Log Non-Conformance</span>
                    </a>
                </div>
            </div>

            <!-- Tab Switcher -->
            <div class="mt-4 pt-3 border-t border-slate-100 flex space-x-2">
                <button wire:click="$set('activeTab', 'checklist')" class="px-3.5 py-1.5 rounded-lg text-xs font-bold transition {{ $activeTab === 'checklist' ? 'bg-emerald-700 text-white shadow-sm' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
                    Field Checklist ({{ $audit->checklist->items->count() ?? 0 }})
                </button>
                <button wire:click="$set('activeTab', 'findings')" class="px-3.5 py-1.5 rounded-lg text-xs font-bold transition {{ $activeTab === 'findings' ? 'bg-emerald-700 text-white shadow-sm' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
                    Findings ({{ $audit->findings->count() }})
                </button>
                <button wire:click="$set('activeTab', 'summary')" class="px-3.5 py-1.5 rounded-lg text-xs font-bold transition {{ $activeTab === 'summary' ? 'bg-emerald-700 text-white shadow-sm' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
                    AI Executive Summary
                </button>
            </div>
        </div>
    </div>

    @if (session()->has('message'))
        <div class="mb-4 p-3 bg-emerald-50 border border-emerald-300 rounded-xl text-emerald-900 text-xs font-semibold flex items-center space-x-2">
            <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            <span>{{ session('message') }}</span>
        </div>
    @endif

    <!-- TAB 1: FIELD CHECKLIST -->
    @if($activeTab === 'checklist')
        <div class="space-y-3">
            @forelse($audit->checklist->items ?? [] as $index => $item)
                <div class="bg-white rounded-2xl border border-slate-200 p-4 shadow-sm">
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <div class="flex items-center space-x-2">
                                <span class="px-2 py-0.5 bg-slate-100 text-slate-700 text-xs font-mono font-bold rounded">
                                    {{ $item->item_code }}
                                </span>
                                @if($item->regulatory_reference)
                                    <span class="text-xs text-emerald-700 font-semibold bg-emerald-50 px-2 py-0.5 rounded border border-emerald-100">
                                        {{ $item->regulatory_reference }}
                                    </span>
                                @endif
                            </div>
                            <h4 class="text-sm font-bold text-slate-900 mt-1.5">
                                {{ $item->requirement }}
                            </h4>
                            @if($item->guidance)
                                <p class="text-xs text-slate-500 mt-1 italic">
                                    Guidance: {{ $item->guidance }}
                                </p>
                            @endif
                        </div>
                    </div>

                    <!-- Evaluation status toggles -->
                    <div class="mt-4 pt-3 border-t border-slate-100 flex flex-wrap items-center justify-between gap-3">
                        <div class="flex flex-wrap gap-1.5">
                            <!-- Compliant -->
                            <button 
                                wire:click="updateResponse({{ $item->id }}, 'compliant')"
                                class="px-3 py-1.5 rounded-lg text-xs font-bold transition flex items-center space-x-1 {{ ($responses[$item->id] ?? '') === 'compliant' ? 'bg-emerald-600 text-white shadow' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
                                <span>✓ Compliant</span>
                            </button>

                            <!-- Non-Compliant -->
                            <button 
                                wire:click="updateResponse({{ $item->id }}, 'non_compliant')"
                                class="px-3 py-1.5 rounded-lg text-xs font-bold transition flex items-center space-x-1 {{ ($responses[$item->id] ?? '') === 'non_compliant' ? 'bg-rose-600 text-white shadow' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
                                <span>✕ Non-Compliant</span>
                            </button>

                            <!-- Observation -->
                            <button 
                                wire:click="updateResponse({{ $item->id }}, 'observation')"
                                class="px-3 py-1.5 rounded-lg text-xs font-bold transition flex items-center space-x-1 {{ ($responses[$item->id] ?? '') === 'observation' ? 'bg-amber-500 text-white shadow' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
                                <span>▲ Observation</span>
                            </button>

                            <!-- N/A -->
                            <button 
                                wire:click="updateResponse({{ $item->id }}, 'not_applicable')"
                                class="px-3 py-1.5 rounded-lg text-xs font-bold transition flex items-center space-x-1 {{ ($responses[$item->id] ?? '') === 'not_applicable' ? 'bg-slate-700 text-white shadow' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
                                <span>— N/A</span>
                            </button>
                        </div>

                        @if(($responses[$item->id] ?? '') === 'non_compliant')
                            <a href="{{ route('auditor.report-finding', ['audit' => $audit->id, 'title' => 'Non-conformance: ' . $item->item_code . ' - ' . Str::limit($item->requirement, 40)]) }}" class="text-xs font-bold text-rose-600 hover:text-rose-800 bg-rose-50 px-2.5 py-1.5 rounded-lg border border-rose-200 flex items-center space-x-1">
                                <span>Create Finding &rarr;</span>
                            </a>
                        @endif
                    </div>

                    <!-- Notes input for this item -->
                    <div class="mt-3">
                        <div class="flex items-center space-x-2">
                            <input 
                                type="text" 
                                wire:model.defer="notes.{{ $item->id }}" 
                                placeholder="Add inspector notes or physical inspection remarks..."
                                class="w-full text-xs bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-slate-800 focus:outline-none focus:ring-1 focus:ring-emerald-500 focus:bg-white"
                            >
                            <button 
                                wire:click="saveNotes({{ $item->id }})"
                                class="px-3 py-2 bg-slate-200 hover:bg-slate-300 text-slate-700 rounded-lg text-xs font-semibold shrink-0">
                                Save
                            </button>
                        </div>
                        @if(session()->has('saved_' . $item->id))
                            <span class="text-[11px] text-emerald-600 font-semibold mt-1 inline-block">✓ Saved</span>
                        @endif
                    </div>
                </div>
            @empty
                <div class="p-8 text-center bg-white rounded-2xl border border-slate-200 text-slate-500 text-sm">
                    No checklist items assigned to this audit engagement.
                </div>
            @endforelse
        </div>
    @endif

    <!-- TAB 2: FINDINGS -->
    @if($activeTab === 'findings')
        <div class="space-y-4">
            <div class="flex justify-between items-center">
                <h3 class="text-sm font-bold text-slate-800">Recorded Non-Conformances & Deficiencies</h3>
                <a href="{{ route('auditor.report-finding', $audit->id) }}" class="text-xs font-bold bg-rose-600 hover:bg-rose-700 text-white px-3 py-1.5 rounded-lg shadow-sm">
                    + Log New Finding
                </a>
            </div>

            @forelse($audit->findings as $finding)
                <div class="bg-white rounded-2xl border border-slate-200 p-4 shadow-sm">
                    <div class="flex items-start justify-between">
                        <div>
                            <div class="flex items-center space-x-2">
                                <span class="px-2 py-0.5 rounded text-xs font-mono font-bold bg-rose-100 text-rose-800 border border-rose-200">
                                    {{ $finding->finding_number }}
                                </span>
                                <span class="px-2 py-0.5 rounded text-xs font-bold uppercase
                                    {{ $finding->severity === 'critical' ? 'bg-rose-600 text-white' : ($finding->severity === 'major' ? 'bg-amber-100 text-amber-800 border border-amber-300' : 'bg-blue-100 text-blue-800 border border-blue-200') }}">
                                    {{ $finding->severity }}
                                </span>
                                <span class="px-2 py-0.5 rounded text-xs font-semibold bg-slate-100 text-slate-700">
                                    Status: {{ str_replace('_', ' ', $finding->status) }}
                                </span>
                            </div>
                            <h4 class="text-base font-bold text-slate-900 mt-2">{{ $finding->title }}</h4>
                        </div>
                    </div>

                    <div class="mt-3 grid grid-cols-1 md:grid-cols-2 gap-3 text-xs bg-slate-50 p-3 rounded-xl border border-slate-100">
                        <div>
                            <strong class="text-slate-700 block">Condition (Deficiency):</strong>
                            <p class="text-slate-600 mt-0.5">{{ $finding->condition }}</p>
                        </div>
                        <div>
                            <strong class="text-slate-700 block">Criteria (Standard):</strong>
                            <p class="text-slate-600 mt-0.5">{{ $finding->criteria }}</p>
                        </div>
                        @if($finding->cause)
                            <div class="col-span-full">
                                <strong class="text-slate-700 block">Root Cause (AI / Auditor):</strong>
                                <p class="text-slate-600 mt-0.5">{{ $finding->cause }}</p>
                            </div>
                        @endif
                    </div>

                    @if($finding->cap)
                        <div class="mt-3 p-3 bg-emerald-50 rounded-xl border border-emerald-200 text-xs">
                            <div class="flex justify-between items-center mb-1">
                                <span class="font-bold text-emerald-900">Corrective Action Plan (CAP)</span>
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-200 text-emerald-800 uppercase">
                                    {{ $finding->cap->status }}
                                </span>
                            </div>
                            <p class="text-emerald-800"><strong>Action:</strong> {{ $finding->cap->proposed_action }}</p>
                            <p class="text-emerald-700 text-[11px] mt-1">Responsible: {{ $finding->cap->responsible_person }} &bull; Target: {{ $finding->cap->target_completion_date ? $finding->cap->target_completion_date->format('M d, Y') : 'N/A' }}</p>
                        </div>
                    @endif
                </div>
            @empty
                <div class="p-8 text-center bg-white rounded-2xl border border-slate-200 text-slate-500 text-sm">
                    No findings recorded. All audited items compliant.
                </div>
            @endforelse
        </div>
    @endif

    <!-- TAB 3: EXECUTIVE SUMMARY & AI REPORTING -->
    @if($activeTab === 'summary')
        <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 border-b border-slate-100 pb-4">
                <div>
                    <h3 class="text-lg font-bold text-slate-900">NCAA DSD Executive Quality Audit Report</h3>
                    <p class="text-xs text-slate-500">
                        Formal audit synthesis synthesized using the NCAA AI Compliance Intelligence Engine.
                    </p>
                </div>

                <button 
                    wire:click="generateAiExecutiveSummary" 
                    wire:loading.attr="disabled"
                    class="inline-flex items-center space-x-2 px-4 py-2 bg-emerald-700 hover:bg-emerald-800 text-white rounded-xl text-xs font-bold shadow-sm transition">
                    <svg class="w-4 h-4 text-amber-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    <span wire:loading.remove>Generate / Refresh AI Summary</span>
                    <span wire:loading>Processing AI Synthesis...</span>
                </button>
            </div>

            @if($audit->ai_executive_summary)
                <div class="bg-slate-50 border border-slate-200 rounded-xl p-5 text-sm leading-relaxed text-slate-800 font-mono whitespace-pre-wrap">
{{ $audit->ai_executive_summary }}
                </div>
            @else
                <div class="p-8 text-center text-slate-500 text-sm">
                    No executive summary generated yet. Click "Generate AI Summary" above to evaluate findings and compliance metrics automatically.
                </div>
            @endif
        </div>
    @endif
</div>
