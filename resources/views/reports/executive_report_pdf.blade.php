<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Executive QA & Internal Audit Report - {{ $date }}</title>
    <style>
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 12px;
            line-height: 1.5;
            color: #1e293b;
            padding: 24px;
        }
        .header {
            border-bottom: 2px solid #008751;
            padding-bottom: 12px;
            margin-bottom: 20px;
        }
        .title {
            font-size: 20px;
            font-weight: 800;
            color: #2a1770;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .meta {
            font-size: 11px;
            color: #64748b;
            margin-top: 4px;
        }
        .content {
            margin-top: 15px;
        }
        h2 {
            font-size: 14px;
            font-weight: bold;
            color: #0f172a;
            border-bottom: 1px solid #cbd5e1;
            padding-bottom: 4px;
            margin-top: 18px;
            margin-bottom: 8px;
        }
        ul {
            margin-top: 4px;
            margin-bottom: 10px;
            padding-left: 20px;
        }
        li {
            margin-bottom: 4px;
        }
        p {
            margin-top: 4px;
            margin-bottom: 8px;
        }
        .footer {
            margin-top: 30px;
            border-top: 1px solid #e2e8f0;
            padding-top: 10px;
            font-size: 10px;
            color: #94a3b8;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="header">
        <table style="width: 100%;">
            <tr>
                <td style="width: 60px; vertical-align: middle;">
                    <img src="{{ public_path('images/ncaa-logo.png') }}" width="48" height="48" alt="NCAA Logo">
                </td>
                <td style="vertical-align: middle;">
                    <div class="title">Nigeria Civil Aviation Authority — DSD QA/IA System &bull; Executive Report</div>
                    <div class="meta">Directorate of Special Duties | Quality Assurance & Internal Audit Division</div>
                    <div class="meta">Report Generation Date: {{ $date }} | Coverage: {{ $period }}</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="content">
        {!! $renderedHtml !!}
    </div>

    <div class="footer">
        Confidential &bull; Nigeria Civil Aviation Authority &bull; For internal executive oversight only.
    </div>
</body>
</html>
