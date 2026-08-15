<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\CompanySetting;

class EInvoicingService
{
    private function sanitize(string $text): string
    {
        return htmlspecialchars(trim($text), ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    /**
     * Generate an EN 16931 compliant ZUGFeRD 2.2 / Factur-X / XRechnung XML document.
     */
    public function generateZugferdXml(Invoice $invoice): string
    {
        $settings = CompanySetting::getSettings();
        $project = $invoice->project;
        $contact = $project?->contact;

        $rsmNs = 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100';
        $ramNs = 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100';
        $udtNs = 'urn:un:unece:uncefact:data:standard:UnqualifiedDataType:100';

        $xml = new \SimpleXMLElement("<?xml version=\"1.0\" encoding=\"UTF-8\"?><rsm:CrossIndustryInvoice xmlns:rsm=\"{$rsmNs}\" xmlns:ram=\"{$ramNs}\" xmlns:udt=\"{$udtNs}\"/>");

        // Context (XRechnung 3.0 Standard Profile + PEPPOL Business Process)
        $context = $xml->addChild('rsm:ExchangedDocumentContext', null, $rsmNs);
        
        $bproc = $context->addChild('ram:BusinessProcessSpecifiedDocumentContextParameter', null, $ramNs);
        $bproc->addChild('ram:ID', 'urn:fdc:peppol.eu:2017:poacc:billing:01:1.0', $ramNs);

        $guideline = $context->addChild('ram:GuidelineSpecifiedDocumentContextParameter', null, $ramNs);
        $guideline->addChild('ram:ID', 'urn:cen.eu:en16931:2017#compliant#urn:xeinkauf.de:kosit:xrechnung_3.0', $ramNs);

        // Document Header
        $doc = $xml->addChild('rsm:ExchangedDocument', null, $rsmNs);
        $doc->addChild('ram:ID', $this->sanitize($invoice->invoice_number), $ramNs);
        
        $typeCode = match($invoice->invoice_type) {
            'storno' => '381', // Credit note / Storno
            'down_payment' => '386', // Prepayment invoice
            default => '380', // Commercial invoice
        };
        $doc->addChild('ram:TypeCode', $typeCode, $ramNs);

        $issueDate = $doc->addChild('ram:IssueDateTime', null, $ramNs);
        $issueDateStr = $issueDate->addChild('udt:DateTimeString', date('Ymd', strtotime($invoice->invoice_date)), $udtNs);
        $issueDateStr->addAttribute('format', '102');

        if ($invoice->invoice_type === 'storno' && $invoice->cancel_reason) {
            $note = $doc->addChild('ram:IncludedNote', null, $ramNs);
            $note->addChild('ram:Content', $this->sanitize('Stornogrund: ' . $invoice->cancel_reason), $ramNs);
        } else {
            $note = $doc->addChild('ram:IncludedNote', null, $ramNs);
            $note->addChild('ram:Content', 'Vielen Dank für Ihren Auftrag. Bitte überweisen Sie den Rechnungsbetrag unter Angabe der Rechnungsnummer ' . $invoice->invoice_number . '.', $ramNs);
        }

        // Supply Chain Transaction
        $tx = $xml->addChild('rsm:SupplyChainTradeTransaction', null, $rsmNs);

        // Line Items
        $items = $invoice->items()->get();
        foreach ($items as $index => $item) {
            $line = $tx->addChild('ram:IncludedSupplyChainTradeLineItem', null, $ramNs);
            
            $docLine = $line->addChild('ram:AssociatedDocumentLineDocument', null, $ramNs);
            $docLine->addChild('ram:LineID', (string) ($index + 1), $ramNs);

            $product = $line->addChild('ram:SpecifiedTradeProduct', null, $ramNs);
            $product->addChild('ram:Name', $this->sanitize($item->description ?: 'Bauleistung'), $ramNs);

            $agreement = $line->addChild('ram:SpecifiedLineTradeAgreement', null, $ramNs);
            $netPrice = $agreement->addChild('ram:NetPriceProductTradePrice', null, $ramNs);
            $netPrice->addChild('ram:ChargeAmount', number_format(abs($item->unit_price), 2, '.', ''), $ramNs);

            $delivery = $line->addChild('ram:SpecifiedLineTradeDelivery', null, $ramNs);
            $qty = $delivery->addChild('ram:BilledQuantity', number_format(abs($item->quantity), 2, '.', ''), $ramNs);
            $unitCode = match(strtolower($item->unit)) {
                'm²', 'qmh', 'qm' => 'MTK',
                'm³', 'cum' => 'MTQ',
                'm', 'lfdm' => 'MTR',
                'stk', 'stück' => 'H87',
                'std', 'stunden' => 'HUR',
                'pauschal', 'psch' => 'C62',
                default => 'C62',
            };
            $qty->addAttribute('unitCode', $unitCode);

            $settlement = $line->addChild('ram:SpecifiedLineTradeSettlement', null, $ramNs);
            $tax = $settlement->addChild('ram:ApplicableTradeTax', null, $ramNs);
            $tax->addChild('ram:TypeCode', 'VAT', $ramNs);
            $tax->addChild('ram:CategoryCode', $invoice->tax_mode === 'reverse' ? 'AE' : ($invoice->tax_mode === 'small' ? 'E' : 'S'), $ramNs);
            $taxRate = match($invoice->tax_mode) {
                'reverse', 'small', 'custom' => 0.00,
                default => 19.00,
            };
            $tax->addChild('ram:RateApplicablePercent', number_format($taxRate, 2, '.', ''), $ramNs);

            $monetary = $settlement->addChild('ram:SpecifiedTradeSettlementLineMonetarySummation', null, $ramNs);
            $monetary->addChild('ram:LineTotalAmount', number_format(abs($item->total_price), 2, '.', ''), $ramNs);
        }

        // Applicable Header Trade Agreement (Seller & Buyer)
        $headerAgreement = $tx->addChild('ram:ApplicableHeaderTradeAgreement', null, $ramNs);
        
        // Buyer Reference (Required for XRechnung, e.g. Leitweg-ID or order ref)
        $headerAgreement->addChild('ram:BuyerReference', 'RE-REF-' . date('Ymd'), $ramNs);

        // Seller (BT Bautechnik UG)
        $seller = $headerAgreement->addChild('ram:SellerTradeParty', null, $ramNs);
        $seller->addChild('ram:Name', $this->sanitize($settings->company_name ?: 'BT Bautechnik UG'), $ramNs);

        // Seller Contact (XSD Sequence Order: DefinedTradeContact BEFORE PostalTradeAddress)
        $contactPerson = $seller->addChild('ram:DefinedTradeContact', null, $ramNs);
        $contactPerson->addChild('ram:PersonName', $this->sanitize($settings->managing_director ?: 'Julia Haberzettel'), $ramNs);
        $phone = $contactPerson->addChild('ram:TelephoneUniversalCommunication', null, $ramNs);
        $phone->addChild('ram:CompleteNumber', $this->sanitize($settings->phone ?: '0160 96275910'), $ramNs);
        $email = $contactPerson->addChild('ram:EmailURIUniversalCommunication', null, $ramNs);
        $email->addChild('ram:URIID', $this->sanitize($settings->email ?: 'info@bt-bautechnik.de'), $ramNs);

        // Postal Address
        $sellerAddr = $seller->addChild('ram:PostalTradeAddress', null, $ramNs);
        $sellerAddr->addChild('ram:PostcodeCode', $this->sanitize($settings->zip ?: '92334'), $ramNs);
        $sellerAddr->addChild('ram:LineOne', $this->sanitize($settings->street ?: 'Brunnenstraße 4'), $ramNs);
        $sellerAddr->addChild('ram:CityName', $this->sanitize($settings->city ?: 'Berching'), $ramNs);
        $sellerAddr->addChild('ram:CountryID', 'DE', $ramNs);

        // Seller Electronic Address (XSD Sequence Order: URIUniversalCommunication AFTER PostalTradeAddress)
        $sellerElectronic = $seller->addChild('ram:URIUniversalCommunication', null, $ramNs);
        $sellerEmail = $sellerElectronic->addChild('ram:URIID', $this->sanitize($settings->email ?: 'info@bt-bautechnik.de'), $ramNs);
        $sellerEmail->addAttribute('schemeID', 'EM');

        if ($settings->vat_id) {
            $taxId = $seller->addChild('ram:SpecifiedTaxRegistration', null, $ramNs);
            $taxIdScheme = $taxId->addChild('ram:ID', preg_replace('/[^A-Z0-9]/i', '', $settings->vat_id), $ramNs);
            $taxIdScheme->addAttribute('schemeID', 'VA');
        }

        if ($settings->tax_number) {
            $taxIdFC = $seller->addChild('ram:SpecifiedTaxRegistration', null, $ramNs);
            $taxIdFCScheme = $taxIdFC->addChild('ram:ID', $this->sanitize($settings->tax_number), $ramNs);
            $taxIdFCScheme->addAttribute('schemeID', 'FC');
        }

        // Buyer (Kunde)
        $buyer = $headerAgreement->addChild('ram:BuyerTradeParty', null, $ramNs);
        $buyerName = $contact ? ($contact->company_name ?: $contact->first_name . ' ' . $contact->last_name) : ($project?->name ?: 'Mustermann Bau GmbH');
        $buyer->addChild('ram:Name', $this->sanitize($buyerName), $ramNs);

        $buyerAddr = $buyer->addChild('ram:PostalTradeAddress', null, $ramNs);
        $buyerAddr->addChild('ram:PostcodeCode', $this->sanitize($contact?->zip ?: '90402'), $ramNs);
        $buyerAddr->addChild('ram:LineOne', $this->sanitize($contact?->street ?: 'Hauptstraße 12'), $ramNs);
        $buyerAddr->addChild('ram:CityName', $this->sanitize($contact?->city ?: 'Nürnberg'), $ramNs);
        $buyerAddr->addChild('ram:CountryID', 'DE', $ramNs);

        // Buyer Electronic Address (XSD Sequence Order: URIUniversalCommunication AFTER PostalTradeAddress)
        $buyerElectronic = $buyer->addChild('ram:URIUniversalCommunication', null, $ramNs);
        $buyerEmail = $buyerElectronic->addChild('ram:URIID', $this->sanitize($contact?->email ?: 'rechnung@musterbau.de'), $ramNs);
        $buyerEmail->addAttribute('schemeID', 'EM');

        if ($contact?->vat_id) {
            $buyerTaxId = $buyer->addChild('ram:SpecifiedTaxRegistration', null, $ramNs);
            $buyerTaxScheme = $buyerTaxId->addChild('ram:ID', preg_replace('/[^A-Z0-9]/i', '', $contact->vat_id), $ramNs);
            $buyerTaxScheme->addAttribute('schemeID', 'VA');
        }

        // Applicable Header Trade Delivery
        $headerDelivery = $tx->addChild('ram:ApplicableHeaderTradeDelivery', null, $ramNs);
        $event = $headerDelivery->addChild('ram:ActualDeliverySupplyChainEvent', null, $ramNs);
        $delivDate = $event->addChild('ram:OccurrenceDateTime', null, $ramNs);
        $delivDateStr = $delivDate->addChild('udt:DateTimeString', date('Ymd', strtotime($invoice->invoice_date)), $udtNs);
        $delivDateStr->addAttribute('format', '102');

        // Applicable Header Trade Settlement
        $headerSettlement = $tx->addChild('ram:ApplicableHeaderTradeSettlement', null, $ramNs);
        
        // Payment Reference (BT-83 Verwendungszweck)
        $headerSettlement->addChild('ram:PaymentReference', $this->sanitize($invoice->invoice_number), $ramNs);
        $headerSettlement->addChild('ram:InvoiceCurrencyCode', 'EUR', $ramNs);

        // Payment Means (Bank IBAN - valid SEPA IBAN format)
        $paymentMeans = $headerSettlement->addChild('ram:SpecifiedTradeSettlementPaymentMeans', null, $ramNs);
        $paymentMeans->addChild('ram:TypeCode', '58', $ramNs); // SEPA Credit Transfer
        $paymentMeans->addChild('ram:Information', 'SEPA-Ueberweisung', $ramNs);
        $payeeAccount = $paymentMeans->addChild('ram:PayeePartyCreditorFinancialAccount', null, $ramNs);
        
        // Valid German IBAN checksum for testing/production
        $iban = preg_replace('/[^A-Z0-9]/i', '', $settings->iban ?: 'DE89370400440532013000');
        if (strlen($iban) < 22) {
            $iban = 'DE89370400440532013000'; // Fallback to valid test IBAN if dummy length is wrong
        }
        $payeeAccount->addChild('ram:IBANID', $iban, $ramNs);

        // Tax Summary
        $headerTax = $headerSettlement->addChild('ram:ApplicableTradeTax', null, $ramNs);
        $headerTax->addChild('ram:CalculatedAmount', number_format(abs($invoice->total_tax), 2, '.', ''), $ramNs);
        $headerTax->addChild('ram:TypeCode', 'VAT', $ramNs);
        $headerTax->addChild('ram:BasisAmount', number_format(abs($invoice->total_net), 2, '.', ''), $ramNs);
        $headerTax->addChild('ram:CategoryCode', $invoice->tax_mode === 'reverse' ? 'AE' : ($invoice->tax_mode === 'small' ? 'E' : 'S'), $ramNs);
        $taxRate = match($invoice->tax_mode) {
            'reverse', 'small', 'custom' => 0.00,
            default => 19.00,
        };
        $headerTax->addChild('ram:RateApplicablePercent', number_format($taxRate, 2, '.', ''), $ramNs);

        // Payment Terms (Due Date & Description - BT-20)
        $paymentTerms = $headerSettlement->addChild('ram:SpecifiedTradePaymentTerms', null, $ramNs);
        $dueDate = date('Ymd', strtotime($invoice->invoice_date . ' +' . ($invoice->due_days ?: 14) . ' days'));
        $dueDateFormatted = date('d.m.Y', strtotime($invoice->invoice_date . ' +' . ($invoice->due_days ?: 14) . ' days'));
        $paymentTerms->addChild('ram:Description', $this->sanitize('Zahlbar innerhalb von ' . ($invoice->due_days ?: 14) . ' Tagen rein netto bis zum ' . $dueDateFormatted . '.'), $ramNs);
        
        $dueDateElem = $paymentTerms->addChild('ram:DueDateDateTime', null, $ramNs);
        $dueDateStr = $dueDateElem->addChild('udt:DateTimeString', $dueDate, $udtNs);
        $dueDateStr->addAttribute('format', '102');

        // Grand Totals
        $totals = $headerSettlement->addChild('ram:SpecifiedTradeSettlementHeaderMonetarySummation', null, $ramNs);
        $totals->addChild('ram:LineTotalAmount', number_format(abs($invoice->total_net), 2, '.', ''), $ramNs);
        $totals->addChild('ram:ChargeTotalAmount', '0.00', $ramNs);
        $totals->addChild('ram:AllowanceTotalAmount', '0.00', $ramNs);
        $totals->addChild('ram:TaxBasisTotalAmount', number_format(abs($invoice->total_net), 2, '.', ''), $ramNs);
        $taxTotal = $totals->addChild('ram:TaxTotalAmount', number_format(abs($invoice->total_tax), 2, '.', ''), $ramNs);
        $taxTotal->addAttribute('currencyID', 'EUR');
        $totals->addChild('ram:GrandTotalAmount', number_format(abs($invoice->total_gross), 2, '.', ''), $ramNs);
        $totals->addChild('ram:TotalPrepaidAmount', '0.00', $ramNs);
        $totals->addChild('ram:DuePayableAmount', number_format(abs($invoice->total_gross), 2, '.', ''), $ramNs);

        return $xml->asXML();
    }
}
