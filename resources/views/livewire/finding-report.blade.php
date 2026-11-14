<div>
    <!-- Back to Audit Execution Header -->
    <div class="mb-4">
        <a href="{{ route('auditor.execute', $audit->id) }}" class="inline-flex items-center text-xs font-semibold text-emerald-800 hover:text-emerald-950 mb-2">
            &larr; Back to Checklist ({{ $audit->reference_number }})
        </a>
        <div class="bg-white rounded-2xl border border-slate-200 p-4 shadow-sm flex items-center justify-between">
            <div>
                <div class="flex items-center space-x-2">
                    <span class="px-2 py-0.5 rounded text-xs font-mono font-bold bg-rose-100 text-rose-800 border border-rose-200">
                        {{ $findingNumber }}
                    </span>
                    <span class="text-xs text-slate-500">NCAA Deficient Finding Report</span>
                </div>
                <h1 class="text-lg font-black text-slate-900 mt-1">Log Non-Conformance / Deficiency</h1>
            </div>

            <!-- AI Assist Button -->
            <button 
                type="button"
                wire:click="triggerAiAnalysis" 
                wire:loading.attr="disabled"
                class="inline-flex items-center space-x-1.5 px-3.5 py-2 bg-gradient-to-r from-emerald-700 to-teal-800 hover:from-emerald-800 hover:to-teal-900 text-white rounded-xl text-xs font-bold shadow transition">
                <svg class="w-4 h-4 text-amber-300 animate-spin" wire:loading fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                <svg class="w-4 h-4 text-amber-300" wire:loading.remove fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                <span wire:loading.remove>Run AI Risk Analysis</span>
                <span wire:loading>Analyzing Safety Impact...</span>
            </button>
        </div>
    </div>

    @if (session()->has('ai_success'))
        <div class="mb-4 p-3 bg-emerald-50 border border-emerald-300 rounded-xl text-emerald-900 text-xs font-semibold flex items-center space-x-2">
            <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            <span>{{ session('ai_success') }}</span>
        </div>
    @endif

    <form wire:submit.prevent="saveFinding" class="space-y-4">
        <!-- Form Fields Card -->
        <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm space-y-4">
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Finding Title / Issue Summary *</label>
                <input 
                    type="text" 
                    wire:model="title" 
                    placeholder="e.g. Failure to maintain mandatory flight crew fatigue management records"
                    class="w-full text-xs sm:text-sm bg-slate-50 border border-slate-200 rounded-xl p-3 focus:bg-white focus:ring-2 focus:ring-emerald-500 focus:outline-none"
                    required
                >
                @error('title') <span class="text-rose-500 text-xs mt-1 block">{{ $message }}</span> @enderror
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Severity Rating *</label>
                    <select 
                        wire:model="severity"
                        class="w-full text-xs sm:text-sm bg-slate-50 border border-slate-200 rounded-xl p-3 focus:bg-white focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                        <option value="critical">Critical (Immediate Aviation Safety Hazard / Grounds Ops)</option>
                        <option value="major">Major (Systemic Non-Conformance to Nig.CARs)</option>
                        <option value="minor">Minor (Procedural Discrepancy)</option>
                        <option value="observation">Observation (Safety Improvement Opportunity)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">CAP Resolution Due Date *</label>
                    <input 
                        type="date" 
                        wire:model="dueDate"
                        class="w-full text-xs sm:text-sm bg-slate-50 border border-slate-200 rounded-xl p-3 focus:bg-white focus:ring-2 focus:ring-emerald-500 focus:outline-none"
                        required
                    >
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Condition (Physical Observation / What was found) *</label>
                <textarea 
                    wire:model="condition" 
                    rows="3"
                    placeholder="Describe specific factual evidence, dates, flight logs, aircraft tail numbers, or staff involved."
                    class="w-full text-xs sm:text-sm bg-slate-50 border border-slate-200 rounded-xl p-3 focus:bg-white focus:ring-2 focus:ring-emerald-500 focus:outline-none"
                    required></textarea>
                @error('condition') <span class="text-rose-500 text-xs mt-1 block">{{ $message }}</span> @enderror
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Criteria (Regulatory Standard / Nig.CARs Reference) *</label>
                <input 
                    type="text" 
                    wire:model="criteria" 
                    placeholder="e.g. Nig.CARs Part 8.4.1.2; ICAO Doc 9859 Safety Management"
                    class="w-full text-xs sm:text-sm bg-slate-50 border border-slate-200 rounded-xl p-3 focus:bg-white focus:ring-2 focus:ring-emerald-500 focus:outline-none"
                    required
                >
            </div>

            <!-- Root Cause & Effect with AI enrichment indicators -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Root Cause Analysis</label>
                    <textarea 
                        wire:model="cause" 
                        rows="2"
                        placeholder="Identified root cause (can be auto-populated by AI)"
                        class="w-full text-xs bg-slate-50 border border-slate-200 rounded-xl p-2.5 focus:bg-white focus:ring-1 focus:ring-emerald-500 focus:outline-none"></textarea>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Safety & Regulatory Effect</label>
                    <textarea 
                        wire:model="effect" 
                        rows="2"
                        placeholder="Risk exposure (can be auto-populated by AI)"
                        class="w-full text-xs bg-slate-50 border border-slate-200 rounded-xl p-2.5 focus:bg-white focus:ring-1 focus:ring-emerald-500 focus:outline-none"></textarea>
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Auditor Corrective Recommendation</label>
                <textarea 
                    wire:model="recommendation" 
                    rows="3"
                    placeholder="Immediate corrective steps expected from the audited Directorate"
                    class="w-full text-xs bg-slate-50 border border-slate-200 rounded-xl p-2.5 focus:bg-white focus:ring-1 focus:ring-emerald-500 focus:outline-none"></textarea>
            </div>
        </div>

        <!-- AI Guidance Card (when populated) -->
        @if($aiAnalysis)
            <div class="bg-gradient-to-br from-emerald-900 to-slate-900 text-white rounded-2xl p-5 shadow-lg space-y-3">
                <div class="flex items-center space-x-2 text-amber-300 text-xs font-bold uppercase tracking-wider">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    <span>NCAA AI Safety Intelligence Assessment</span>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                    <div class="bg-white/10 p-3 rounded-xl border border-white/10">
                        <strong class="text-amber-200 block mb-1">Recommended Severity:</strong>
                        <span class="font-bold uppercase text-white">{{ $aiAnalysis['severity_recommendation'] ?? 'N/A' }}</span>
                        <div class="text-[11px] text-slate-300 mt-1">{{ $aiAnalysis['risk_level'] ?? '' }}</div>
                    </div>
                    <div class="bg-white/10 p-3 rounded-xl border border-white/10">
                        <strong class="text-amber-200 block mb-1">Regulatory Context:</strong>
                        <span class="text-slate-200">{{ $aiAnalysis['regulatory_citations'] ?? 'Nig.CARs' }}</span>
                    </div>
                </div>
                <div class="text-xs text-slate-300 italic pt-1">
                    {{ $aiAnalysis['ai_generated_note'] ?? '' }}
                </div>
            </div>
        @endif

        <div class="flex items-center justify-end space-x-3 pt-2">
            <a href="{{ route('auditor.execute', $audit->id) }}" class="px-4 py-2.5 bg-slate-200 hover:bg-slate-300 text-slate-700 rounded-xl text-xs font-bold transition">
                Cancel
            </a>
            <button 
                type="submit" 
                class="px-6 py-2.5 bg-rose-600 hover:bg-rose-700 text-white rounded-xl text-xs font-bold shadow-md transition">
                Submit Non-Conformance Finding &rarr;
            </button>
        </div>
    </form>
</div>
