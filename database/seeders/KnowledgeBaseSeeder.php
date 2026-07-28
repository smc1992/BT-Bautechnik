<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\KnowledgeDocument;
use App\Services\KnowledgeBaseService;
use Illuminate\Support\Facades\Log;

class KnowledgeBaseSeeder extends Seeder
{
    /**
     * Seed initial construction knowledge base documents and vector embeddings.
     */
    public function run(): void
    {
        $service = app(KnowledgeBaseService::class);

        $documents = [
            [
                'title' => 'VOB/B & DIN 18299 - Abrechnung, Abnahme & Nachtragsmanagement',
                'category' => 'VOB & Normen',
                'content' => <<<EOT
VOB/B (Vergabe- und Vertragsordnung für Bauleistungen - Teil B) stellt das rechtliche Fundament für Bauverträge in Deutschland dar.

1. ABNAHME VON BAULEISTUNGEN (§ 12 VOB/B):
- Die Abnahme ist innerhalb von 12 Werktagen nach schriftlicher Fertigstellungsmitteilung durchzuführen.
- Mit der Abnahme kehrt sich die Beweislast bezüglich Mängeln um, die Gefahr geht auf den Auftraggeber über und die Verjährungsfrist für Mängelansprüche beginnt.
- Stillschweigende (fiktive) Abnahme tritt ein, wenn der Auftraggeber die Leistung nach Nutzung von 12 Werktagen oder nach Ablauf von 6 Werktagen nach Aufforderung nicht ausdrücklich verweigert.

2. GEWÄHRLEISTUNG & MÄNGELANSPRÜCHE (§ 13 VOB/B):
- Die Verjährungsfrist für Mängelansprüche beträgt bei Bauwerken 4 Jahre (sofern im Vertrag nichts abweichendes vereinbart wurde).
- Bei Feuerungsanlagen und feuerberührten Teilen beträgt die Frist 2 Jahre.
- Bei Mängeln ist dem Auftragnehmer eine angemessene Frist zur Nacherfüllung zu setzen.

3. NACHTRAGSMANAGEMENT & BEHINDERUNG (§ 6 & § 2 VOB/B):
- Leistungsänderungen oder zusätzliche Leistungen durch den Auftraggeber berechtigen den Auftragnehmer zu einem Nachtragsangebot nach § 2 Abs. 5 bzw. Abs. 6 VOB/B.
- Behinderungsanzeige (§ 6 VOB/B): Wenn die Ausführung von Bauleistungen durch Umstände gestört wird, die vom Auftraggeber zu vertreten sind (z.B. fehlende Baugenehmigungen, Verzug von Vorwerken), muss unverzüglich schriftlich Behinderung angezeigt werden.

4. DIN 18299 - ALLGEMEINE REGELN FÜR BAURECHNUNGEN:
- Übermessungsregeln: Aussparungen bis 0,1 m² (bei Flächenberechnung) bzw. 0,5 m³ (bei Rauminhalten) werden übermessen.
- Abrechnung erfolgt auf Basis von örtlichem Aufmaß oder anhand von Ausführungszeichnungen.
EOT,
            ],
            [
                'title' => 'DIN 1045-2 & DIN EN 206 - Betonarbeiten, Nachbehandlung & Expositionsklassen',
                'category' => 'Bautechnik & Normen',
                'content' => <<<EOT
Richtlinien zur Ausführung von Beton-, Stahlbeton- und Spannbetonarbeiten gemäß DIN 1045-2 und DIN EN 206.

1. EXPOSITIONSKLASSEN FÜR BETON:
- XC (X-Corrosion/Carbonatation): Bewehrungskorrosionsrisiko durch Karbonatisierung. (XC1 trocken/ständig nass bis XC4 wechselnd nass und trocken).
- XD (X-Deicing): Korrosion durch Chloride aus Tausalzen (z.B. Tiefgaragen, Fahrbahnen).
- XF (X-Frost): Frostangriff mit oder ohne Auftaumittel (XF1 leicht bis XF4 hohe Sättigung mit Tausalz).
- XA (X-Acid): Chemischer Angriff auf Beton (z.B. Abwasseranlagen, landwirtschaftliche Bauten).

2. MINDESTBETONDECKUNG c_nom:
- Vorhaltemaß Δc_dev beträgt in der Regel 10 mm.
- Bei XC1 mindestens c_nom = 25 mm. Bei XC4/XF1 mindestens c_nom = 35 mm. Bei Tiefgaragen (XD3) mindestens c_nom = 45 mm.

3. NACHBEHANDLUNG VON BETON (§ 8.5 DIN 1045-3):
- Der Beton muss sofort nach dem Einbringen und Verdichten vor zu raschem Austrocknen, extremen Temperaturen und Erschütterungen geschützt werden.
- Mindestnachbehandlungsdauer richtet sich nach Festigkeitsentwicklung und Oberflächentemperatur (z.B. bei W-Entwicklung min. 3 bis 7 Tage).
- Verfahren: Belassen in der Schalung, Abdecken mit Folien, Aufsprühen von Nachbehandlungsmitteln (Curing) oder ständiges Feuchthalten.
EOT,
            ],
            [
                'title' => 'SOP - BT Bautechnik Bautagebuch, Mängeldokumentation & Tagesberichte',
                'category' => 'Interne Abläufe',
                'content' => <<<EOT
Standard Operating Procedure (SOP) für die tägliche Baustellendokumentation bei BT Bautechnik.

1. TÄGLICHE BAUTAGEBUCHFÜHRUNG:
- Jeder Bauleiter / Polier führt täglich bis spätestens 17:00 Uhr einen Bautagesbericht im BT Bautechnik CRM durch.
- Pflichtangaben: Datum, Baustelle, Wetterbedingungen (Temperatur min/max, Niederschlag), eigener Personalbestand (Facharbeiter, Helfer), eingesetzte Nachunternehmer (Firma, Mitarbeiterzahl), Arbeitsfortschritt & wesentliche Leistungen.

2. FOTODOKUMENTATION:
- Verdeckte Bauteile (z.B. Kellerwandabdichtung vor Verfüllung, Bewehrung vor Betoniervorgang, Fußbodenheizungsrohre vor Estrichguss) MÜSSEN fotodokumentiert werden.
- Fotos werden direkt über die Smartphone-Kamera im CRM hochgeladen und mit Baustellenbezug abgelegt.

3. ERFASSUNG VON MÄNGELN:
- Mängel werden sofort bei Feststellung mit Foto, Standort/Raumangabe, Fristsetzung und Verantwortlichkeit (Eigenleistung oder Nachunternehmer) im Mängel-Manager angelegt.
- Nach Behebung durch den Nachunternehmer erfolgt die Abnahmeprüfung vor Ort und Statusänderung auf 'Behoben'.

4. BEHINDERUNGSANZEIGEN:
- Störungen auf der Baustelle sind unverzüglich dem Projektleiter zu melden, damit innerhalb von 24 Stunden eine formelle Behinderungsanzeige nach § 6 VOB/B an den Auftraggeber versendet werden kann.
EOT,
            ],
            [
                'title' => 'DGUV Vorschrift 38 - Arbeitssicherheit auf Baustellen & PSA-Tragepflicht',
                'category' => 'Arbeitsschutz & BG',
                'content' => <<<EOT
Unfallverhütungsvorschriften der Deutschen Gesetzlichen Unfallversicherung (DGUV Vorschrift 38 / bisher BGV C22).

1. PERSÖNLICHE SCHUTZAUSRÜSTUNG (PSA):
- Auf allen Baustellen von BT Bautechnik gilt eine uneingeschränkte Tragepflicht für:
  a) Schutzhelm (DIN EN 397)
  b) Sicherheitsschuhe der Kategorie S3 mit Durchtrittschutz und Zehenschutzkappe
  c) Warnweste (DIN EN ISO 20471 Class 2) bei Fahrverkehr oder Kranarbeiten
  d) Gehörschutz ab einem Tages-Lärmexpositionspegel von 85 dB(A)

2. ABSTURZSICHERUNG:
- Absturzsicherungen (Seitenschutz aus Bordbrett, Zwischenholm und Geländerholm) sind zwingend erforderlich ab:
  - 1,00 m Absturzhöhe an Freileitungen und Wandöffnungen.
  - 2,00 m Absturzhöhe an allen übrigen Arbeitsplätzen und Verkehrswegen.
  - 3,00 m Absturzhöhe bei Dacharbeiten.

3. GERÜSTBAU & FREIGABE:
- Gerüste dürfen erst nach Übergabeprüfung durch den Gerüstersteller und Anbringen des grünen Freigabescheins betreten werden.
- Eigenmächtige Veränderungen am Gerüst (z.B. Entfernen von Verankerungen oder Durchstiegen) sind strengstens untersagt.

4. ELEKTRISCHE BETRIEBSMITTEL:
- Baustromverteiler müssen mit Fehlerstrom-Schutzeinrichtungen (RCD / FI-Schutzschalter mit I_Δn ≤ 30 mA) ausgerüstet sein.
- Sämtliche elektrischen Handwerkzeuge müssen alle 6 Monate gemäß DGUV Vorschrift 3 geprüft werden.
EOT,
            ],
            [
                'title' => 'Leitfaden § 13b UStG (Reverse Charge) & Bauabzugssteuer (§ 48 EStG)',
                'category' => 'Abrechnung & Recht',
                'content' => <<<EOT
Steuerrechtliche Vorgaben für Bauleistungen und Abrechnung von Nachunternehmern bei BT Bautechnik.

1. REVERSE CHARGE VERFAHREN (§ 13b UStG):
- Werden Bauleistungen von einem Unternehmer an einen anderen Bauunternehmer erbracht, geht die Steuerschuldnerzuweisung auf den Leistungsempfänger über.
- Nachunternehmerrechnungen müssen NETTO ausgestellt werden mit dem verpflichtenden Hinweis: 'Steuerschuldnerschaft des Leistungsempfängers gemäß § 13b UStG'.
- Gültige USt 1 TG Bescheinigung des Finanzamts muss bei BT Bautechnik vorliegen.

2. BAUABZUGSSTEUER (§ 48 bis § 48d EStG):
- Empfänger von Bauleistungen in Deutschland sind verpflichtet, vom Rechnungsbetrag einen Steuerabzug von 15 % für Rechnung des leistenen Nachunternehmers vorzunehmen und an das Finanzamt abzuführen.
- Abzugsentfall: Ein Abzug muss NICHT vorgenommen werden, wenn der Nachunternehmer eine im Zeitpunkt der Zahlung gültige Freistellungsbescheinigung nach § 48b EStG vorlegt.
- Prüfung der Freistellungsbescheinigung: Jede Bescheinigung ist vor Freigabe der Zahlung im BT Bautechnik CRM zu erfassen und beim Bundeszentralamt für Steuern (BZSt) online auf Gültigkeit zu prüfen.

3. PFLICHTANGABEN AUF EINGANGSRECHNUNGEN (§ 14 UStG):
- Vollständiger Name und Anschrift von Leistendem und Leistungsempfänger.
- Steuernummer oder USt-IdNr. des Nachunternehmers.
- Ausstellungsdatum und fortlaufende Rechnungsnummer.
- Leistungszeitraum oder Fertigstellungsdatum.
- Beschreibung der erbrachten Bauleistung nach Art und Umfang (Projektbezug).
EOT,
            ],
            [
                'title' => 'DIN 18181 & DIN 4109 - Trockenbau, Schalldämmung & Brandschutz',
                'category' => 'Bautechnik & Normen',
                'content' => <<<EOT
Ausführungsrichtlinien für Gipsplatten-Systeme im Innenausbau gemäß DIN 18181 und Schallschutz im Hochbau nach DIN 4109.

1. MONTAGE VON GIPSKARTONPLATTEN (DIN 18181):
- Unterkonstruktion aus verzinkten C- und U-Stahlprofilen (Profilstärke min. 0,6 mm).
- Ständerabstand max. 625 mm bei Plattendicke 12,5 mm.
- Versatz von Plattenstößen: Querstöße müssen um mindestens 400 mm versetzt werden. Kreuzfugen sind unzulässig.
- Befestigung mit Schnellbauschrauben (Abstand an Wänden max. 250 mm, an Decken max. 170 mm).

2. BRANDSCHUTZ-KLASSIFIZIERUNG (DIN 4102 / DIN EN 13501):
- F30-A (feuerhemmend): Einfache Beplankung mit 1x 12,5 mm Knauf Feuerschutzplatte GKF / DF oder Knauf Diamant.
- F60-A (hochfeuerhemmend): Zweilagige Beplankung 2x 12,5 mm GKF.
- F90-A (feuerbeständig): Zweilagige Beplankung 2x 15 mm GKF oder Spezial-Brandschutzplatten.
- Mineralwolle-Dämmstoff der Baustoffklasse A (Schmelzpunkt ≥ 1000 °C) im Hohlraum erforderlich.

3. SCHALLSCHUTZ (DIN 4109):
- Trennwände zwischen Wohnräumen erfordern ein bewertetes Schalldämm-Maß R'w ≥ 53 dB.
- Anschlüsse von Unterkonstruktionen an angrenzende Bauteile (Boden, Decke, Massivwand) müssen dauerelastisch mit Dichtungsbändern entkoppelt werden.
EOT,
            ],
            [
                'title' => 'DIN 18560 - Estricharbeiten, Belegreife & CM-Feuchtemessung',
                'category' => 'Bautechnik & Normen',
                'content' => <<<EOT
Richtlinien zur Verlegung von Estrichen im Bauwesen nach DIN 18560.

1. ESTRICHARTEN & ANWENDUNG:
- Zementestrich (CT): Unempfindlich gegen Feuchtigkeit, geeignet für Innen- und Außenbereiche sowie Nassräume. Erfordert 28 Tage Erhärtungszeit.
- Calciumsulfatestrich / Anhydritestrich (CA/CAF): Nahezu schwund- und spannungsarm, hohe Ebenheit, ideal für Fußbodenheizungen. Empfindlich gegen Feuchtigkeit.

2. DICHKEIT & DÄMMUNG:
- Estrich auf Dämmschicht (schwimmender Estrich): Trennlage aus PE-Folie (mind. 0,2 mm dick) über der Trittschalldämmung.
- Randdämmstreifen (mind. 8 mm dick) müssen an allen aufsteigenden Bauteilen (Wände, Stützen) angebracht werden, um Schallbrücken und Zwangspannungen zu vermeiden.

3. BELEGREIFE & CM-MESSUNG:
- Vor dem Verlegen von Bodenbelägen (Parkett, Fliesen, Vinyl) MUSS eine Restfeuchtemessung nach der Calciumcarbid-Methode (CM-Messung) durchgeführt werden.
- Grenzwert Zementestrich (CT):
  - Ungeheizt: max. 2,0 CM-%
  - Geheizt (Fussbodenheizung): max. 1,8 CM-%
- Grenzwert Calciumsulfatestrich (CA):
  - Ungeheizt: max. 0,5 CM-%
  - Geheizt: max. 0,3 CM-%
EOT,
            ],
            [
                'title' => 'Glossar - Bauabrechnung, Aufmaß & Massenermittlung (VOB/B & DIN 18299)',
                'category' => 'Kalkulation & Abrechnung',
                'content' => <<<EOT
GLOSSAR DER WICHTIGSTEN BEGRIFFE DER BAUABRECHNUNG & KALKULATION:

1. MASSENERMITTLUNG (auch Massenberechnung):
- Das mathematische Ermitteln von Längen (m), Flächen (m²) und Volumen (m³ - Kubikmeter) aus den Ausführungs- und Bauplänen.
- Nach DIN 18299 gilt: Aussparungen und Durchbrüche bis 0,1 m² Einzelfläche bei Flächenberechnungen bzw. bis 0,5 m³ Einzelvolumen bei Volumenberechnungen werden übermessen und nicht abgezogen.

2. LEISTUNGSVERZEICHNIS (LV):
- Die strukturierte Liste aller nötigen Teilleistungen und Arbeiten einer Baumaßnahme.
- Hier steht bei Beton-, Erd- oder Abdichtungsarbeiten die Menge als konkrete Maßeinheit (z. B. m³, m², lfm, Stk) sowie der kalkulierte Einheitspreis (€/Einheit).

3. EINHEITSPREISVERTRAG:
- Die bevorzugte Vertragsart nach VOB/B, bei der der Endpreis nicht starr ist, sondern exakt nach der tatsächlich auf der Baustelle eingebauten und per Aufmaß nachgewiesenen Menge (Volumen/Fläche) abgerechnet wird.

4. AUFMASS:
- Das präzise Ausmessen der fertiggestellten Bauteile und erbrachten Bauleistungen direkt vor Ort auf der Baustelle oder anhand geprüfter As-Built-Pläne, um die finale Abrechnungsmenge für die Abschlags- oder Schlussrechnung festzulegen.
EOT,
            ],
        ];

        foreach ($documents as $docData) {
            $existing = KnowledgeDocument::where('title', $docData['title'])->first();
            if ($existing) {
                $this->command->info("Dokument '{$docData['title']}' existiert bereits. Überspringe.");
                continue;
            }

            try {
                $service->storeDocument(
                    $docData['title'],
                    $docData['category'],
                    $docData['content']
                );
                $this->command->info("✅ Wissensdokument '{$docData['title']}' erfolgreich geseeded & gechunked!");
            } catch (\Exception $e) {
                Log::error("Fehler beim Seeden von '{$docData['title']}': " . $e->getMessage());
                $this->command->error("❌ Fehler bei '{$docData['title']}': " . $e->getMessage());
            }
        }
    }
}
