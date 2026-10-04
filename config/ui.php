<?php

return [
    'navigation' => [
        'Baustellen' => [
            ['dashboard', 'Übersicht', 'grid'],
            ['daily-logs', 'Bautagebuch', 'book'],
            ['defects', 'Mängel', 'warning'],
            ['supplements', 'Nachträge', 'document'],
            ['measurements', 'Aufmaße', 'ruler'],
            ['project-plans', 'Baupläne', 'folder'],
            ['work-schedule', 'Einsatzplanung', 'users'],
            ['planning', 'Bauzeitenplan', 'calendar'],
            ['equipment', 'Geräte & Fahrzeuge', 'truck'],
        ],
        'Finanzen' => [
            ['invoices', 'Rechnungen & Angebote', 'document'],
            ['subcontractor-invoices', 'Baukosten', 'chart'],
            ['time-tracking', 'Zeiterfassung', 'clock'],
            ['materials', 'Materialkatalog', 'box'],
            ['analytics', 'Auswertung', 'chart'],
        ],
        'Kontakte & Firma' => [
            ['contacts', 'Kunden & Partner', 'users'],
            ['company-settings', 'Firmeneinstellungen', 'settings'],
        ],
        'Assistenz' => [
            ['ai-agent', 'KI-Assistent', 'sparkles'],
            ['knowledge-base', 'Wissen', 'book'],
        ],
    ],
];
