<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Aufmaßblatt {{ $measurement->measurement_number }} - {{ $project->name }}</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 10mm 12mm 15mm 12mm;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 9.5px;
            line-height: 1.35;
            color: #1e293b;
            margin: 0;
            padding: 0;
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
        }
        .header-table td {
            vertical-align: top;
        }
        .logo-img {
            max-height: 45px;
            max-width: 180px;
        }
        .company-info {
            text-align: right;
            font-size: 8px;
            color: #64748b;
            line-height: 1.25;
        }
        .doc-title {
            font-size: 15px;
            font-weight: 800;
            color: #0f172a;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 2px;
            border-bottom: 2px solid #0891b2;
            padding-bottom: 3px;
        }
        .doc-subtitle {
            font-size: 9px;
            color: #64748b;
            margin-bottom: 10px;
        }
        .info-grid {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }
        .info-grid td {
            padding: 5px 8px;
            border: 1px solid #e2e8f0;
            background-color: #f8fafc;
            font-size: 9px;
        }
        .label {
            color: #64748b;
            font-weight: bold;
            font-size: 8px;
            text-transform: uppercase;
            display: block;
        }
        .value {
            color: #0f172a;
            font-weight: bold;
            font-size: 9.5px;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
        }
        .items-table th {
            background-color: #f1f5f9;
            padding: 5px 6px;
            font-size: 8.5px;
            text-align: left;
            border: 1px solid #cbd5e1;
            font-weight: bold;
            text-transform: uppercase;
        }
        .items-table td {
            padding: 5px 6px;
            border: 1px solid #e2e8f0;
            font-size: 9px;
        }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .font-mono { font-family: monospace; }
        .total-row td {
            background-color: #ecfeff;
            font-weight: bold;
            border-top: 2px solid #0891b2;
            font-size: 10px;
        }
        .signature-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 30px;
            page-break-inside: avoid;
        }
        .signature-table td {
            width: 50%;
            vertical-align: bottom;
            padding: 0 10px;
        }
        .signature-line {
            border-top: 1px solid #94a3b8;
            margin-top: 35px;
            padding-top: 4px;
            font-size: 8px;
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
                    <h2 style="color: #0891b2; margin:0;">BT BAUTECHNIK UG</h2>
                @endif
            </td>
            <td class="company-info">
                <strong>BT Bautechnik UG (haftungsbeschränkt)</strong><br>
                {{ $company['street'] ?? 'Musterstraße 1' }}, {{ $company['zip'] ?? '85049' }} {{ $company['city'] ?? 'Ingolstadt' }}<br>
                E-Mail: {{ $company['email'] ?? 'info@bautechnik-bt.de' }} | Tel: {{ $company['phone'] ?? '+49 841 00000' }}
            </td>
        </tr>
    </table>

    <div class="doc-title">AUFMAßBLATT & MENGENBERECHNUNG {{ $measurement->measurement_number }}</div>
    <div class="doc-subtitle">Mengenermittlung nach VOB/C / DIN 18299 – Gemeinsame Feststellung der ausgeführten Leistungen</div>

    <!-- Info Grid -->
    <table class="info-grid">
        <tr>
            <td style="width: 35%;">
                <span class="label">Bauvorhaben:</span>
                <span class="value">{{ $project->name }}</span>
            </td>
            <td style="width: 25%;">
                <span class="label">Bereich / Bauteil:</span>
                <span class="value">{{ $measurement->location_area ?: 'Gesamt' }}</span>
            </td>
            <td style="width: 20%;">
                <span class="label">Aufmaßdatum:</span>
                <span class="value">{{ $measurement->measurement_date->format('d.m.Y') }}</span>
            </td>
            <td style="width: 20%;">
                <span class="label">Aufmaß-Nr.:</span>
                <span class="value">{{ $measurement->measurement_number }}</span>
            </td>
        </tr>
    </table>

    <!-- Items Table -->
    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 8%;">Pos.</th>
                <th style="width: 15%;">Raum / Achse</th>
                <th style="width: 27%;">Leistungsbeschreibung</th>
                <th style="width: 6%; text-align: center;">Einh.</th>
                <th style="width: 7%; text-align: center;">L (m)</th>
                <th style="width: 7%; text-align: center;">B (m)</th>
                <th style="width: 6%; text-align: center;">Faktor</th>
                <th style="width: 6%; text-align: center;">Abzug</th>
                <th style="width: 8%; text-align: right;">Menge</th>
                <th style="width: 10%; text-align: right;">EP (€)</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($measurement->items as $idx => $it)
                <tr>
                    <td class="font-mono">{{ $it->item_code ?: str_pad((string)($idx+1), 2, '0', STR_PAD_LEFT) }}</td>
                    <td style="font-weight: bold;">{{ $it->room_or_axis ?: '-' }}</td>
                    <td>{{ $it->description }}</td>
                    <td class="text-center font-mono">{{ $it->unit }}</td>
                    <td class="text-center font-mono">{{ number_format($it->length, 2, ',', '.') }}</td>
                    <td class="text-center font-mono">{{ number_format($it->width, 2, ',', '.') }}</td>
                    <td class="text-center font-mono">{{ number_format($it->factor, 1, ',', '.') }}</td>
                    <td class="text-center font-mono" style="color: #e11d48;">{{ $it->deduction > 0 ? '-' . number_format($it->deduction, 2, ',', '.') : '-' }}</td>
                    <td class="text-right font-mono font-bold">{{ number_format($it->quantity, 2, ',', '.') }}</td>
                    <td class="text-right font-mono">{{ number_format($it->unit_price, 2, ',', '.') }} €</td>
                </tr>
            @endforeach
            <tr class="total-row">
                <td colspan="8" class="text-right">Gesamtbetrag Aufmaß (Netto):</td>
                <td colspan="2" class="text-right font-mono font-black" style="color: #0891b2;">
                    {{ number_format($measurement->total_amount_net, 2, ',', '.') }} €
                </td>
            </tr>
        </tbody>
    </table>

    <!-- Signatures -->
    <table class="signature-table">
        <tr>
            <td>
                <div class="signature-line">
                    Ort, Datum / Aufgestellt: {{ $measurement->inspector_name ?: 'Bauleiter BT Bautechnik' }}
                </div>
            </td>
            <td>
                <div class="signature-line">
                    Ort, Datum / Anerkannt & Geprüft: {{ $measurement->client_representative ?: 'Bauherr / Bauleitung AG' }}
                </div>
            </td>
        </tr>
    </table>

</body>
</html>
