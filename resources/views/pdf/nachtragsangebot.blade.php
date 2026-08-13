<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Nachtragsangebot {{ $supplement->supplement_number }} - {{ $project->name }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 12mm 15mm 18mm 15mm;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 10px;
            line-height: 1.4;
            color: #1e293b;
            margin: 0;
            padding: 0;
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        .header-table td {
            vertical-align: top;
        }
        .logo-img {
            max-height: 50px;
            max-width: 200px;
        }
        .company-info {
            text-align: right;
            font-size: 8.5px;
            color: #64748b;
            line-height: 1.3;
        }
        .doc-title {
            font-size: 16px;
            font-weight: 800;
            color: #0f172a;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 3px;
            border-bottom: 2px solid #4f46e5;
            padding-bottom: 4px;
        }
        .doc-subtitle {
            font-size: 9.5px;
            color: #64748b;
            margin-bottom: 12px;
        }
        .info-grid {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        .info-grid td {
            padding: 6px 8px;
            border: 1px solid #e2e8f0;
            background-color: #f8fafc;
            font-size: 9.5px;
        }
        .label {
            color: #64748b;
            font-weight: bold;
            font-size: 8.5px;
            text-transform: uppercase;
            display: block;
        }
        .value {
            color: #0f172a;
            font-weight: bold;
            font-size: 10px;
        }
        .content-box {
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 12px;
            margin-bottom: 15px;
            background-color: #ffffff;
        }
        .box-title {
            font-size: 11px;
            font-weight: bold;
            color: #4f46e5;
            margin-bottom: 6px;
            text-transform: uppercase;
        }
        .calc-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        .calc-table th {
            background-color: #f1f5f9;
            padding: 6px 8px;
            font-size: 9px;
            text-align: left;
            border-bottom: 1px solid #cbd5e1;
        }
        .calc-table td {
            padding: 8px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 10px;
        }
        .total-box {
            margin-top: 15px;
            width: 50%;
            float: right;
            border-collapse: collapse;
        }
        .total-box td {
            padding: 4px 8px;
            font-size: 10px;
        }
        .total-highlight {
            font-size: 12px;
            font-weight: 800;
            color: #4f46e5;
            border-top: 2px solid #4f46e5;
        }
        .signature-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 50px;
            page-break-inside: avoid;
        }
        .signature-table td {
            width: 50%;
            vertical-align: bottom;
            padding: 0 10px;
        }
        .signature-line {
            border-top: 1px solid #94a3b8;
            margin-top: 40px;
            padding-top: 4px;
            font-size: 8.5px;
            color: #64748b;
        }
    </style>
</head>
<body>

    <!-- Header with Logo and Company Info -->
    <table class="header-table">
        <tr>
            <td>
                @if (file_exists(public_path('logo.png')))
                    <img src="{{ public_path('logo.png') }}" class="logo-img" alt="Logo">
                @else
                    <h2 style="color: #4f46e5; margin:0;">BT BAUTECHNIK UG</h2>
                @endif
            </td>
            <td class="company-info">
                <strong>BT Bautechnik UG (haftungsbeschränkt)</strong><br>
                {{ $company['street'] ?? 'Musterstraße 1' }}<br>
                {{ $company['zip'] ?? '85049' }} {{ $company['city'] ?? 'Ingolstadt' }}<br>
                E-Mail: {{ $company['email'] ?? 'info@bautechnik-bt.de' }} | Tel: {{ $company['phone'] ?? '+49 841 00000' }}
            </td>
        </tr>
    </table>

    <div class="doc-title">NACHTRAGSANGEBOT {{ $supplement->supplement_number }}</div>
    <div class="doc-subtitle">Gemäß VOB/B § 2 Abs. 5 / Abs. 6 – Leistungsänderung / Mehraufwand</div>

    <!-- Project & Client Info Grid -->
    <table class="info-grid">
        <tr>
            <td style="width: 50%;">
                <span class="label">Bauvorhaben:</span>
                <span class="value">{{ $project->name }}</span>
                @if ($project->location)
                    <div style="font-size: 8.5px; color: #64748b;">Standort: {{ $project->location }}</div>
                @endif
            </td>
            <td style="width: 50%;">
                <span class="label">Auftraggeber:</span>
                <span class="value">{{ $project->contact?->company_name ?: ($project->contact?->first_name . ' ' . $project->contact?->last_name) ?: 'Auftraggeber' }}</span>
            </td>
        </tr>
        <tr>
            <td>
                <span class="label">Nachtrags-Nummer:</span>
                <span class="value">{{ $supplement->supplement_number }}</span>
            </td>
            <td>
                <span class="label">Datum / Erstellt am:</span>
                <span class="value">{{ $supplement->submission_date ? $supplement->submission_date->format('d.m.Y') : date('d.m.Y') }}</span>
            </td>
        </tr>
    </table>

    <!-- Description & VOB Reason -->
    <div class="content-box">
        <div class="box-title">Gegenstand & Begründung der Mehrleistung</div>
        <div style="font-weight: bold; font-size: 11px; margin-bottom: 6px;">{{ $supplement->title }}</div>
        <div style="font-size: 9.5px; color: #334155; line-height: 1.45;">
            {!! nl2br(e($supplement->description ?: 'Ausführung von zusätzlichen, bauseits angeforderten Leistungen gemäß VOB/B.')) !!}
        </div>
    </div>

    <!-- Calculation Table -->
    <table class="calc-table">
        <thead>
            <tr>
                <th style="width: 10%;">Pos.</th>
                <th style="width: 60%;">Leistungsumfang</th>
                <th style="width: 30%; text-align: right;">Gesamtbetrag</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td style="font-weight: bold;">NT.01</td>
                <td>
                    <strong>{{ $supplement->title }}</strong>
                    <div style="font-size: 8.5px; color: #64748b;">Inkl. Fachgerechter Ausführung und Baustoffen</div>
                </td>
                <td style="text-align: right; font-weight: bold; font-family: monospace;">
                    {{ number_format($supplement->amount_net, 2, ',', '.') }} €
                </td>
            </tr>
        </tbody>
    </table>

    <!-- Totals Block -->
    <table class="total-box">
        <tr>
            <td style="text-align: right; color: #64748b;">Nettobetrag:</td>
            <td style="text-align: right; font-weight: bold; font-family: monospace;">{{ number_format($supplement->amount_net, 2, ',', '.') }} €</td>
        </tr>
        <tr>
            <td style="text-align: right; color: #64748b;">zzgl. {{ number_format($supplement->vat_rate, 0) }}% MwSt.:</td>
            <td style="text-align: right; font-weight: bold; font-family: monospace;">{{ number_format($supplement->amount_gross - $supplement->amount_net, 2, ',', '.') }} €</td>
        </tr>
        <tr class="total-highlight">
            <td style="text-align: right;">Gesamtbetrag Brutto:</td>
            <td style="text-align: right; font-family: monospace;">{{ number_format($supplement->amount_gross, 2, ',', '.') }} €</td>
        </tr>
    </table>

    <div style="clear: both;"></div>

    <!-- Signatures -->
    <table class="signature-table">
        <tr>
            <td>
                <div class="signature-line">
                    Ort, Datum / Unterschrift Auftragnehmer (BT Bautechnik)
                </div>
            </td>
            <td>
                <div class="signature-line">
                    Ort, Datum / Freigabe Auftraggeber / Bauherr
                </div>
            </td>
        </tr>
    </table>

</body>
</html>
