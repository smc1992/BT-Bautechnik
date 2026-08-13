<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\SubcontractorInvoice;
use App\Models\CompanySetting;
use Illuminate\Support\Collection;

class DatevExportService
{
    /**
     * Generate DATEV-compliant Buchungsstapel CSV (SKR03 / SKR04).
     *
     * @param string $year
     * @param string $skr 'SKR03' | 'SKR04'
     * @return string
     */
    public function generateDatevCsv(string $year = 'all', string $skr = 'SKR03'): string
    {
        $settings = CompanySetting::getSettings();

        // 1. Fetch outgoing invoices
        $invoicesQuery = Invoice::query()->where('status', '!=', 'cancelled');
        if ($year !== 'all') {
            $invoicesQuery->whereYear('invoice_date', $year);
        }
        $invoices = $invoicesQuery->orderBy('invoice_date', 'asc')->get();

        // 2. Fetch incoming subcontractor invoices
        $subInvoicesQuery = SubcontractorInvoice::query()->where('status', '!=', 'rejected');
        if ($year !== 'all') {
            $subInvoicesQuery->whereYear('invoice_date', $year);
        }
        $subInvoices = $subInvoicesQuery->orderBy('invoice_date', 'asc')->get();

        // Account numbers based on SKR standard
        $revenueAccount19 = ($skr === 'SKR04') ? '4400' : '8400'; // Erlöse 19% USt
        $revenueAccountReverse = ($skr === 'SKR04') ? '4336' : '8337'; // Erlöse § 13b UStG
        $expenseAccountSubcontractors = ($skr === 'SKR04') ? '5900' : '3100'; // Fremdleistungen / Bauleistungen § 13b

        $csvRows = [];

        // DATEV Header (Format Header nach DATEV-Spezifikation)
        $csvRows[] = '"EXTF";700;21;"Buchungsstapel";12;"' . date('YmdHis') . '";"";"BT";"";"";"1000";"1000";"' . date('Y0101') . '";4;"' . date('Ymd') . '";"";"";"";"";"EUR"';

        // Column Headers (DATEV Standard Spalten)
        $csvRows[] = implode(';', [
            'Umsatz (ohne Soll/Haben-Kz)',
            'Soll/Haben-Kennzeichen',
            'WKZ',
            'Kurs',
            'Basis-Umsatz',
            'WKZ Basis-Umsatz',
            'Konto',
            'Gegenkonto (ohne BU-Schlüssel)',
            'BU-Schlüssel',
            'Belegdatum',
            'Belegfeld 1',
            'Belegfeld 2',
            'Skonto',
            'Buchungstext',
            'Postensperre',
            'Diverse Adressnummer',
            'Geschäftspartnerbank',
            'Sachverhalt',
            'Zinssperre',
            'Beleglink'
        ]);

        // Process Outgoing Invoices (Erlöse)
        foreach ($invoices as $inv) {
            $gross = abs((float) ($inv->total_gross ?: $inv->total_net));
            $net = abs((float) $inv->total_net);
            $amountFormatted = number_format($gross, 2, ',', '');
            $shKz = ($inv->total_gross < 0 || $inv->invoice_type === 'storno') ? 'H' : 'S';
            
            $docDate = date('dm', strtotime($inv->invoice_date));
            $docNumber = mb_substr(preg_replace('/[^a-zA-Z0-9_-]/', '', $inv->invoice_number), 0, 36);
            
            $clientAccount = '10000'; // Standard Debitor Sammelkonto
            $revenueAccount = ($inv->tax_mode === 'reverse') ? $revenueAccountReverse : $revenueAccount19;
            $buKey = ($inv->tax_mode === 'reverse') ? '68' : ''; // DATEV Steuerschlüssel für § 13b

            $clientName = $inv->project?->contact?->company_name ?: ($inv->project?->contact?->first_name . ' ' . $inv->project?->contact?->last_name) ?: ($inv->project?->name ?: 'Kunde');
            $bookingText = mb_substr('AR ' . $inv->invoice_number . ' ' . $clientName, 0, 60);

            $csvRows[] = implode(';', [
                '"' . $amountFormatted . '"',
                '"' . $shKz . '"',
                '"EUR"',
                '""',
                '""',
                '""',
                '"' . $clientAccount . '"',
                '"' . $revenueAccount . '"',
                '"' . $buKey . '"',
                '"' . $docDate . '"',
                '"' . $docNumber . '"',
                '""',
                '""',
                '"' . str_replace('"', '""', $bookingText) . '"',
                '""',
                '""',
                '""',
                '""',
                '""',
                '""'
            ]);
        }

        // Process Incoming Subcontractor Invoices (Aufwand)
        foreach ($subInvoices as $sub) {
            $gross = abs((float) $sub->gross_amount);
            $amountFormatted = number_format($gross, 2, ',', '');
            $shKz = 'S'; // Soll (Aufwand)
            
            $docDate = date('dm', strtotime($sub->invoice_date));
            $docNumber = mb_substr(preg_replace('/[^a-zA-Z0-9_-]/', '', $sub->invoice_number ?: 'ER-' . $sub->id), 0, 36);
            
            $creditorAccount = '70000'; // Standard Kreditor Sammelkonto
            $expenseAccount = $expenseAccountSubcontractors;
            $buKey = $sub->is_reverse_charge ? '68' : '';

            $subName = $sub->contact?->company_name ?: ($sub->contact?->first_name . ' ' . $sub->contact?->last_name) ?: 'Subunternehmer';
            $bookingText = mb_substr('ER ' . ($sub->invoice_number ?: 'Sub') . ' ' . $subName, 0, 60);

            $csvRows[] = implode(';', [
                '"' . $amountFormatted . '"',
                '"' . $shKz . '"',
                '"EUR"',
                '""',
                '""',
                '""',
                '"' . $expenseAccount . '"',
                '"' . $creditorAccount . '"',
                '"' . $buKey . '"',
                '"' . $docDate . '"',
                '"' . $docNumber . '"',
                '""',
                '""',
                '"' . str_replace('"', '""', $bookingText) . '"',
                '""',
                '""',
                '""',
                '""',
                '""',
                '""'
            ]);
        }

        return "\xEF\xBB\xBF" . implode("\r\n", $csvRows); // UTF-8 with BOM for Excel & DATEV
    }
}
