<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Quality Audit Certificate - {{ $certificate->number }}</title>
    <style>
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            margin: 0;
            padding: 30px;
            color: #18181b;
        }
        .border-frame {
            border: 4px double #064e3b;
            padding: 24px;
            min-height: 940px;
            position: relative;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #047857;
            padding-bottom: 15px;
            margin-bottom: 25px;
        }
        .crest {
            font-size: 28px;
            font-weight: 900;
            color: #064e3b;
            letter-spacing: 2px;
        }
        .subtitle {
            font-size: 14px;
            font-weight: bold;
            color: #047857;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-top: 4px;
        }
        .cert-title {
            text-align: center;
            font-size: 24px;
            font-weight: 900;
            color: #0f172a;
            margin: 20px 0 10px 0;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .cert-number {
            text-align: center;
            font-size: 13px;
            font-family: monospace;
            color: #475569;
            margin-bottom: 25px;
        }
        .body-text {
            font-size: 13px;
            line-height: 1.6;
            margin-bottom: 20px;
            text-align: justify;
        }
        .table-data {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            font-size: 12px;
        }
        .table-data th, .table-data td {
            border: 1px solid #cbd5e1;
            padding: 8px 12px;
        }
        .table-data th {
            background-color: #f1f5f9;
            text-align: left;
            font-weight: bold;
            color: #334155;
            width: 35%;
        }
        .results-box {
            background-color: #f0fdf4;
            border: 1px solid #bbf7d0;
            border-radius: 8px;
            padding: 12px 16px;
            margin-bottom: 25px;
            font-size: 12px;
        }
        .footer-signatures {
            margin-top: 35px;
            width: 100%;
        }
        .sig-block {
            width: 48%;
            display: inline-block;
            vertical-align: top;
            font-size: 11px;
        }
        .qr-section {
            text-align: center;
            margin-top: 20px;
        }
        .qr-image {
            width: 80px;
            height: 80px;
        }
        .open-ncr-alert {
            color: #b91c1c;
            font-weight: bold;
            font-size: 11px;
            text-align: center;
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <div class="border-frame">
        <div class="header">
            <div style="text-align: center; margin-bottom: 8px;">
                <img src="{{ public_path('images/logo-placeholder.svg') }}" width="48" height="48" alt="Logo">
            </div>
            <div class="crest">DSD QA/IA SYSTEM</div>
            <div class="subtitle">Directorate of Special Duties (DSD) &bull; Internal Quality Assurance</div>
        </div>

        <div class="cert-title">Internal Quality Audit Certificate</div>
        <div class="cert-number">Certificate Number: {{ $certificate->number }}</div>

        <div class="body-text">
            This is to certify that an internal statutory quality assurance inspection and procedural audit was conducted in full accordance with the NCAA Quality Management Manual, statutory provisions of the Nigeria Civil Aviation Regulations (Nig.CARs), and ICAO Annex standards.
        </div>

        <table class="table-data">
            <tr>
                <th>Audit Engagement Title</th>
                <td><strong>{{ $audit->title }}</strong> (Ref: {{ $audit->reference_number }})</td>
            </tr>
            <tr>
                <th>Quality Process Template</th>
                <td>{{ $audit->process->name ?? 'Routine Surveillance Inspection' }}</td>
            </tr>
            <tr>
                <th>Audited Directorate & Unit</th>
                <td>{{ $audit->directorate->name ?? 'Directorate of Special Duties' }} ({{ $audit->process?->unit->name ?? 'All Units' }})</td>
            </tr>
            <tr>
                <th>Audit Execution Period</th>
                <td>{{ $audit->start_date ? $audit->start_date->format('M d, Y') : '—' }} to {{ $audit->end_date ? $audit->end_date->format('M d, Y') : $certificate->issued_at->format('M d, Y') }}</td>
            </tr>
            <tr>
                <th>Lead Auditor & Reviewer</th>
                <td>{{ $audit->leadAuditor->name ?? 'Lead Auditor' }} &bull; Reviewer: {{ $audit->reviewer->name ?? 'Quality Reviewer' }}</td>
            </tr>
        </table>

        <div class="results-box">
            <table style="width: 100%; border: none; font-size: 12px;">
                <tr>
                    <td style="border: none;"><strong>Checklist Questions Evaluated:</strong> {{ $totalQuestions }}</td>
                    <td style="border: none;"><strong>Quality Conformance Score:</strong> {{ $audit->compliance_score }}%</td>
                </tr>
                <tr>
                    <td style="border: none;"><strong>Total NCRs Raised:</strong> {{ $totalNcrs }}</td>
                    <td style="border: none;"><strong>Resolved & Closed NCRs:</strong> {{ $closedNcrs }} ({{ $openNcrs }} remaining open)</td>
                </tr>
            </table>
        </div>

        @if($openNcrs > 0)
            <div class="open-ncr-alert">
                Issued with {{ $openNcrs }} open Non-Conformance Report(s) subject to mandatory ongoing surveillance monitoring.
            </div>
        @endif

        <table class="footer-signatures">
            <tr>
                <td style="border: none; width: 50%;">
                    <div style="border-top: 1px solid #475569; width: 80%; padding-top: 4px; font-weight: bold; font-size: 11px;">
                        Head of Internal Audit
                    </div>
                    <div style="font-size: 10px; color: #64748b;">
                        Approved: {{ $headApproval?->created_at?->format('M d, Y H:i') ?? 'Verified' }}
                    </div>
                </td>
                <td style="border: none; width: 50%; text-align: right;">
                    <div style="border-top: 1px solid #475569; width: 80%; margin-left: 20%; padding-top: 4px; font-weight: bold; font-size: 11px;">
                        Director of Special Duties, NCAA
                    </div>
                    <div style="font-size: 10px; color: #64748b;">
                        Final Authorization: {{ $certificate->issued_at->format('M d, Y H:i') }}
                    </div>
                </td>
            </tr>
        </table>

        <div class="qr-section">
            <img src="{{ $qrCodeDataUri }}" class="qr-image" alt="Verification QR Code">
            <div style="font-size: 9px; color: #64748b; margin-top: 4px;">
                Scan or visit {{ url('/verify/' . $certificate->verify_token) }} to verify certificate authenticity.
            </div>
        </div>
    </div>
</body>
</html>
