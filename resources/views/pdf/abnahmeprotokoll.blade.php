<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Abnahmeprotokoll - {{ $project->name }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 12mm 12mm 18mm 12mm;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 10px;
            line-height: 1.35;
            color: #1e293b;
            background-color: #ffffff;
            margin: 0;
            padding: 0;
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
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
            line-height: 1.25;
        }
        .doc-title {
            font-size: 16px;
            font-weight: 800;
            color: #0f172a;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 3px;
            border-bottom: 2px solid #2563eb;
            padding-bottom: 4px;
        }
        .doc-subtitle {
            font-size: 9.5px;
            color: #64748b;
            margin-bottom: 12px;
        }
        .section-box {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 10px;
            margin-bottom: 10px;
        }
        .section-title {
            font-size: 10px;
            font-weight: 800;
            color: #0f172a;
            text-transform: uppercase;
            margin-bottom: 6px;
            letter-spacing: 0.3px;
        }
        .grid-2 {
            width: 100%;
            border-collapse: collapse;
        }
        .grid-2 td {
            width: 50%;
            vertical-align: top;
            padding-right: 6px;
        }
        .grid-3 {
            width: 100%;
            border-collapse: collapse;
        }
        .grid-3 td {
            width: 33.33%;
            vertical-align: top;
            padding-right: 6px;
        }
        .label {
            font-size: 8.5px;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            margin-bottom: 1px;
        }
        .value {
            font-size: 10px;
            font-weight: 600;
            color: #0f172a;
            margin-bottom: 6px;
        }
        .table-custom {
            width: 100%;
            border-collapse: collapse;
            margin-top: 4px;
            margin-bottom: 8px;
        }
        .table-custom th {
            background-color: #f1f5f9;
            color: #334155;
            font-size: 8.5px;
            font-weight: 800;
            text-transform: uppercase;
            padding: 5px 6px;
            border: 1px solid #cbd5e1;
            text-align: left;
        }
        .table-custom td {
            padding: 5px 6px;
            border: 1px solid #e2e8f0;
            font-size: 9.5px;
        }
        .badge {
            display: inline-block;
            padding: 2px 5px;
            border-radius: 3px;
            font-size: 8.5px;
            font-weight: 700;
        }
        .badge-red { background-color: #fef2f2; color: #991b1b; border: 1px solid #fca5a5; }
        .badge-green { background-color: #f0fdf4; color: #166534; border: 1px solid #86efac; }
        .badge-yellow { background-color: #fefce8; color: #854d0e; border: 1px solid #fef08a; }

        .result-card {
            border-radius: 6px;
            padding: 8px 10px;
            margin-bottom: 8px;
            border: 1px solid #e2e8f0;
        }
        .result-selected {
            background-color: #eff6ff;
            border: 2px solid #2563eb;
        }
        .result-option {
            margin-bottom: 2px;
            font-size: 10px;
        }
        .checkbox-symbol {
            font-size: 12px;
            font-weight: 800;
            margin-right: 4px;
        }

        .signatures-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .signatures-table td {
            vertical-align: top;
            padding-right: 12px;
        }
        .signature-box {
            border-top: 1px solid #94a3b8;
            padding-top: 4px;
            margin-top: 35px;
            font-size: 9.5px;
            color: #475569;
        }

        .footer-note {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 8px;
            color: #94a3b8;
            border-top: 1px solid #e2e8f0;
            padding-top: 4px;
        }
    </style>
</head>
<body>

    <!-- Header -->
    <table class="header-table">
        <tr>
            <td>
                @if($logoBase64)
                    <img src="{{ $logoBase64 }}" class="logo-img" alt="Company Logo">
                @else
                    <div style="font-size: 18px; font-weight: 900; color: #0f172a;">{{ $contractorName }}</div>
                    <div style="font-size: 9px; color: #2563eb; font-weight: 700;">BAUTECHNIK & BAUMANAGEMENT</div>
                @endif
            </td>
            <td class="company-info">
                <strong>{{ $contractorName }}</strong><br>
                {{ $company?->street ?: 'Bauhofstraße 12' }}<br>
                {{ $company?->zip ?: '80331' }} {{ $company?->city ?: 'München' }}<br>
                Tel: {{ $company?->phone ?: '+49 (0) 89 1234567' }} | Email: {{ $company?->email ?: 'info@bt-bautechnik.de' }}<br>
                USt-ID: {{ $company?->vat_id ?: 'DE 345 678 901' }}
            </td>
        </tr>
    </table>

    <!-- Title -->
    <div class="doc-title">
        @if($selectedSubcontractor)
            TEILABNAHMEPROTOKOLL SUBUNTERNEHMER (§ 12 ABS. 2 VOB/B)
        @else
            FÖRMLICHES ABNAHMEPROTOKOLL (§ 12 VOB/B / § 640 BGB)
        @endif
    </div>
    <div class="doc-subtitle">Gewerkabnahme, Bautagebuch-Zusammenfassung & Mängeldokumentation</div>

    <!-- Stammdaten & Parteien -->
    <div class="section-box">
        <div class="section-title">1. Vertragsparteien & Bauvorhaben</div>
        
        @if($selectedSubcontractor)
            <!-- 3-Party Grid when Subunternehmer is selected -->
            <table class="grid-3">
                <tr>
                    <td>
                        <div class="label">Auftraggeber (Bauherr):</div>
                        <div class="value">{{ $clientName ?: 'Kunde / Bauherr' }}</div>

                        <div class="label">Vertreten durch:</div>
                        <div class="value">{{ $clientRepresentative ?: 'Bauherr' }}</div>
                    </td>
                    <td>
                        <div class="label">Hauptauftragnehmer (GU):</div>
                        <div class="value">{{ $contractorName }}</div>

                        <div class="label">Vertreten durch:</div>
                        <div class="value">{{ $contractorRepresentative }}</div>
                    </td>
                    <td>
                        <div class="label">Subunternehmer / Nachunternehmer:</div>
                        <div class="value" style="color: #1e40af;">{{ $selectedSubcontractor->display_name }}</div>

                        <div class="label">Vertreten durch / Gewerk:</div>
                        <div class="value">{{ $selectedSubcontractor->name }} ({{ $selectedSubcontractor->vat_id ?: 'Gewerk-Partner' }})</div>
                    </td>
                </tr>
            </table>
        @else
            <!-- 2-Party Grid for Main Project Abnahme -->
            <table class="grid-2">
                <tr>
                    <td>
                        <div class="label">Auftraggeber (Bauherr):</div>
                        <div class="value">{{ $clientName ?: 'Kunde / Bauherr' }}</div>

                        <div class="label">Vertreten durch:</div>
                        <div class="value">{{ $clientRepresentative ?: 'Bauherr / Architekt' }}</div>
                    </td>
                    <td>
                        <div class="label">Auftragnehmer (Bauunternehmen):</div>
                        <div class="value">{{ $contractorName }}</div>

                        <div class="label">Vertreten durch:</div>
                        <div class="value">{{ $contractorRepresentative }}</div>
                    </td>
                </tr>
            </table>
        @endif

        <div style="border-top: 1px dashed #cbd5e1; margin-top: 6px; padding-top: 6px;">
            <div class="label">Bauvorhaben / Objektsituation:</div>
            <div class="value" style="font-size: 11px;">{{ $project->name }} ({{ $project->city_street ?: 'Baustellen-Adresse' }})</div>
            
            <div class="label">Abnahmedatum:</div>
            <div class="value">{{ date('d.m.Y', strtotime($acceptanceDate)) }}</div>
        </div>
    </div>

    <!-- Gegenstand der Abnahme -->
    <div class="section-box">
        <div class="section-title">
            2. Gegenstand der Abnahme & Ausgeführte Leistungen
            @if($selectedSubcontractor) (Subunternehmer: {{ $selectedSubcontractor->display_name }}) @endif
        </div>
        <div style="font-size: 9.5px; color: #334155; white-space: pre-wrap; line-height: 1.4; background: #ffffff; padding: 7px; border-radius: 5px; border: 1px solid #e2e8f0;">{{ $workScopeDescription }}</div>
    </div>

    <!-- Mängelliste -->
    <div class="section-box">
        <div class="section-title">3. Mängel- & Restarbeitenkatalog (Aggregiert aus System-Protokollen)</div>
        @if(count($defects) > 0)
            <table class="table-custom">
                <thead>
                    <tr>
                        <th style="width: 5%;">Nr.</th>
                        <th style="width: 25%;">Gewerk / Ort</th>
                        <th style="width: 40%;">Mängelbeschreibung & Restarbeit</th>
                        <th style="width: 15%;">Status</th>
                        <th style="width: 15%;">Beseitigungsfrist</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($defects as $idx => $def)
                        <tr>
                            <td><strong>{{ $idx + 1 }}</strong></td>
                            <td>
                                <strong>{{ $def->title }}</strong><br>
                                <span style="font-size: 8px; color: #64748b;">{{ $def->location ?: 'Baustelle' }}</span>
                            </td>
                            <td>
                                {{ $def->description }}
                                @if($def->assignedContact)
                                    <br><span style="font-size: 8px; color: #2563eb; font-weight: bold;">Zuständiger Subunternehmer: {{ $def->assignedContact->display_name }}</span>
                                @endif
                            </td>
                            <td>
                                @if($def->status === 'abgenommen')
                                    <span class="badge badge-green">Abgenommen</span>
                                @elseif($def->status === 'behoben')
                                    <span class="badge badge-yellow">Behoben</span>
                                @else
                                    <span class="badge badge-red">Offen</span>
                                @endif
                            </td>
                            <td>{{ $def->deadline ? date('d.m.Y', strtotime($def->deadline)) : date('d.m.Y', strtotime($defectRemediationDeadline)) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <div style="font-size: 9.5px; color: #166534; background: #f0fdf4; padding: 7px; border-radius: 5px; border: 1px solid #86efac; font-weight: 600;">
                ✅ Keine offenen Mängel oder Restarbeiten für dieses Gewerk im System registriert.
            </div>
        @endif
    </div>

    <!-- Ergebnis der Abnahme -->
    <div class="section-box">
        <div class="section-title">4. Erklärung & Ergebnis der Abnahme</div>

        <div class="result-card {{ $acceptanceResult === 'ohne_vorbehalt' ? 'result-selected' : '' }}">
            <div class="result-option">
                <span class="checkbox-symbol">{{ $acceptanceResult === 'ohne_vorbehalt' ? '☑' : '☐' }}</span>
                <strong>A) ABNAHME OHNE VORBEHALT:</strong> Die Werkleistung wird als vertragsgemäß und frei von wesentlichen Mängeln abgenommen.
            </div>
        </div>

        <div class="result-card {{ $acceptanceResult === 'mit_vorbehalt' ? 'result-selected' : '' }}">
            <div class="result-option">
                <span class="checkbox-symbol">{{ $acceptanceResult === 'mit_vorbehalt' ? '☑' : '☐' }}</span>
                <strong>B) ABNAHME MIT VORBEHALT Wegen Mängeln / Restarbeiten:</strong> Die Werkleistung wird vorbehaltlich der betriebsfertigen Beseitigung der oben unter Punkt 3 aufgeführten Mängel und Restarbeiten abgenommen.
            </div>
        </div>

        <div class="result-card {{ $acceptanceResult === 'verweigert' ? 'result-selected' : '' }}">
            <div class="result-option">
                <span class="checkbox-symbol">{{ $acceptanceResult === 'verweigert' ? '☑' : '☐' }}</span>
                <strong>C) ABNAHME VERWEIGERT:</strong> Die Abnahme wird wegen wesentlicher Mängel vorerst verweigert. Eine Nachabnahme wird nach Behebung vereinbart.
            </div>
        </div>
    </div>

    <!-- Fristen & Gewährleistung -->
    <div class="section-box">
        <div class="section-title">5. Fristen & Gewährleistung</div>
        <table class="grid-2">
            <tr>
                <td>
                    <div class="label">Mängelbeseitigungsfrist bis:</div>
                    <div class="value" style="color: #dc2626;">{{ date('d.m.Y', strtotime($defectRemediationDeadline)) }}</div>
                </td>
                <td>
                    <div class="label">Gewährleistungsdauer:</div>
                    <div class="value">{{ $warrantyPeriod }}</div>
                </td>
            </tr>
        </table>
        @if(!empty($notes))
            <div style="border-top: 1px dashed #cbd5e1; margin-top: 4px; padding-top: 4px;">
                <div class="label">Besondere Vereinbarungen / Anmerkungen:</div>
                <div class="value" style="font-weight: normal; font-size: 9.5px;">{{ $notes }}</div>
            </div>
        @endif
        <div style="font-size: 8px; color: #64748b; margin-top: 4px; font-style: italic;">
            Hinweis: Mit der Abnahme geht die Gefahr des zufälligen Untergangs der Leistung über (§ 644 BGB / § 12 VOB/B).
        </div>
    </div>

    <!-- Unterschriften -->
    <table class="signatures-table">
        <tr>
            @if($selectedSubcontractor)
                <!-- 3 Signatures: Client, General Contractor, Subcontractor -->
                <td style="width: 33%;">
                    <div class="signature-box">
                        <strong>Ort, Datum:</strong> ______________<br><br>
                        <strong>Auftraggeber (Bauherr):</strong><br>
                        <span style="font-size: 8.5px; color: #94a3b8;">{{ $clientName }}</span>
                    </div>
                </td>
                <td style="width: 33%;">
                    <div class="signature-box">
                        <strong>Ort, Datum:</strong> ______________<br><br>
                        <strong>Hauptauftragnehmer (BT Bautechnik):</strong><br>
                        <span style="font-size: 8.5px; color: #94a3b8;">{{ $contractorName }} ({{ $contractorRepresentative }})</span>
                    </div>
                </td>
                <td style="width: 33%;">
                    <div class="signature-box">
                        <strong>Ort, Datum:</strong> ______________<br><br>
                        <strong>Subunternehmer (Gewerk):</strong><br>
                        <span style="font-size: 8.5px; color: #1e40af; font-weight: bold;">{{ $selectedSubcontractor->display_name }}</span>
                    </div>
                </td>
            @else
                <!-- 2 Signatures: Client, General Contractor -->
                <td style="width: 50%;">
                    <div class="signature-box">
                        <strong>Ort, Datum:</strong> ________________________<br><br>
                        <strong>Auftraggeber (Bauherr / Architekt):</strong><br>
                        <span style="font-size: 9px; color: #94a3b8;">{{ $clientName }}</span>
                    </div>
                </td>
                <td style="width: 50%;">
                    <div class="signature-box">
                        <strong>Ort, Datum:</strong> ________________________<br><br>
                        <strong>Auftragnehmer (Bauunternehmen):</strong><br>
                        <span style="font-size: 9px; color: #94a3b8;">{{ $contractorName }} ({{ $contractorRepresentative }})</span>
                    </div>
                </td>
            @endif
        </tr>
    </table>

    <div class="footer-note">
        {{ $contractorName }} | Abnahmeprotokoll VOB/B & BGB | Erstellt mit BT Bautechnik Management
    </div>

</body>
</html>
