<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificate Verification &bull; NCAA Quality Assurance</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-100 min-h-screen flex items-center justify-center p-4">
    <div class="max-w-md w-full bg-white rounded-2xl shadow-xl overflow-hidden border border-slate-200">
        <!-- Header banner -->
        <div class="p-6 {{ $valid ? 'bg-emerald-700' : 'bg-rose-700' }} text-white text-center">
            <div class="w-12 h-12 mx-auto rounded-full bg-white/20 flex items-center justify-center text-2xl font-black mb-2">
                {{ $valid ? '✓' : '✕' }}
            </div>
            <h1 class="text-xl font-black">
                {{ $valid ? 'Authentic NCAA Certificate' : 'Invalid Certificate' }}
            </h1>
            <p class="text-xs text-white/80 mt-1">
                {{ $valid ? 'Official Directorate of Special Duties Internal Quality Audit Record' : 'This certificate token could not be verified in NCAA records.' }}
            </p>
        </div>

        @if($valid && $certificate)
            <div class="p-6 space-y-4 text-xs text-slate-700">
                <div class="bg-slate-50 p-3 rounded-xl border border-slate-200 space-y-2">
                    <div class="flex justify-between items-center">
                        <span class="text-slate-500 font-semibold uppercase">Certificate Number:</span>
                        <span class="font-mono font-bold text-slate-900">{{ $certificate->number }}</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-slate-500 font-semibold uppercase">Issue Date:</span>
                        <span class="font-bold text-slate-900">{{ $certificate->issued_at->format('M d, Y H:i') }}</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-slate-500 font-semibold uppercase">Status:</span>
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-800">VALID &amp; CERTIFIED</span>
                    </div>
                </div>

                <div class="space-y-2">
                    <div>
                        <span class="text-slate-500 uppercase font-semibold text-[10px]">Engagement Title:</span>
                        <div class="font-bold text-slate-900 text-sm">{{ $certificate->audit->title }}</div>
                        <div class="text-[11px] text-slate-500 font-mono">{{ $certificate->audit->reference_number }}</div>
                    </div>

                    <div>
                        <span class="text-slate-500 uppercase font-semibold text-[10px]">Audited Directorate / Unit:</span>
                        <div class="font-bold text-slate-900">{{ $certificate->audit->directorate->name ?? 'DSD' }} &bull; {{ $certificate->audit->process?->unit->name ?? 'All Units' }}</div>
                    </div>

                    <div>
                        <span class="text-slate-500 uppercase font-semibold text-[10px]">Compliance Conformance Score:</span>
                        <div class="font-bold text-emerald-600 text-sm">{{ $certificate->audit->compliance_score }}%</div>
                    </div>
                </div>

                <div class="pt-3 border-t border-slate-200 flex items-center justify-between">
                    <span class="text-[11px] text-slate-500">Nigeria Civil Aviation Authority</span>
                    <a href="{{ route('certificates.download', $certificate->audit_id) }}" class="px-3 py-1.5 rounded-lg bg-emerald-700 hover:bg-emerald-800 text-white font-bold text-xs shadow-sm">
                        Download PDF
                    </a>
                </div>
            </div>
        @else
            <div class="p-6 text-center text-xs text-slate-500 space-y-3">
                <p>The verification token provided does not match any official NCAA quality audit certificate on file.</p>
                <a href="/" class="inline-block text-emerald-700 font-bold hover:underline">Return to Home Portal</a>
            </div>
        @endif
    </div>
</body>
</html>
