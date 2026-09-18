<?php

namespace App\Services\Zatca;

use App\Models\Order;
use DOMDocument;

/**
 * Builds ZATCA-compliant UBL 2.1 invoice XML (simplified + standard).
 * Covers the mandatory ZATCA fields: profile, UUID, ICV, issue date/time,
 * invoice type code, currency, supplier/buyer parties, delivery, payment,
 * tax totals, legal totals and line items.
 */
class UblInvoiceBuilder
{
    public const TYPE_STANDARD = '388';

    public const TYPE_SIMPLIFIED = '388';

    public const TYPE_CREDIT = '381';

    public const TYPE_DEBIT = '383';

    /**
     * @param  array<int, array{sku: string, name: string, quantity: int|float, unit_price: float, line_total: float, vat_rate: float, vat_amount: float}>  $lines
     */
    public function buildSimplified(
        Order $order,
        array $seller,
        array $lines,
        string $uuid,
        int $icv,
        string $previousHash,
        \DateTimeInterface $issuedAt,
    ): string {
        return $this->build(
            typeCode: self::TYPE_SIMPLIFIED,
            profileId: 'reporting:1.0',
            order: $order,
            seller: $seller,
            buyer: null,
            lines: $lines,
            uuid: $uuid,
            icv: $icv,
            previousHash: $previousHash,
            issuedAt: $issuedAt,
        );
    }

    /**
     * @param  array{name: string, vat_number: string, street: string, city: string, country: string}  $buyer
     * @param  array<int, array{sku: string, name: string, quantity: int|float, unit_price: float, line_total: float, vat_rate: float, vat_amount: float}>  $lines
     */
    public function buildStandard(
        Order $order,
        array $seller,
        array $buyer,
        array $lines,
        string $uuid,
        int $icv,
        string $previousHash,
        \DateTimeInterface $issuedAt,
    ): string {
        return $this->build(
            typeCode: self::TYPE_STANDARD,
            profileId: 'clearance:1.0',
            order: $order,
            seller: $seller,
            buyer: $buyer,
            lines: $lines,
            uuid: $uuid,
            icv: $icv,
            previousHash: $previousHash,
            issuedAt: $issuedAt,
        );
    }

    /**
     * @param  array{name: string, vat_number: string, street: string, city: string, country: string}|null  $buyer
     * @param  array<int, array{sku: string, name: string, quantity: int|float, unit_price: float, line_total: float, vat_rate: float, vat_amount: float}>  $lines
     */
    public function buildCreditNote(
        Order $order,
        array $seller,
        ?array $buyer,
        array $lines,
        string $uuid,
        int $icv,
        string $previousHash,
        \DateTimeInterface $issuedAt,
    ): string {
        return $this->build(
            typeCode: self::TYPE_CREDIT,
            profileId: $buyer ? 'clearance:1.0' : 'reporting:1.0',
            order: $order,
            seller: $seller,
            buyer: $buyer,
            lines: $lines,
            uuid: $uuid,
            icv: $icv,
            previousHash: $previousHash,
            issuedAt: $issuedAt,
        );
    }

    /**
     * @param  array{name: string, vat_number: string, street: string, city: string, country: string}|null  $buyer
     * @param  array<int, array{sku: string, name: string, quantity: int|float, unit_price: float, line_total: float, vat_rate: float, vat_amount: float}>  $lines
     */
    public function buildDebitNote(
        Order $order,
        array $seller,
        ?array $buyer,
        array $lines,
        string $uuid,
        int $icv,
        string $previousHash,
        \DateTimeInterface $issuedAt,
    ): string {
        return $this->build(
            typeCode: self::TYPE_DEBIT,
            profileId: $buyer ? 'clearance:1.0' : 'reporting:1.0',
            order: $order,
            seller: $seller,
            buyer: $buyer,
            lines: $lines,
            uuid: $uuid,
            icv: $icv,
            previousHash: $previousHash,
            issuedAt: $issuedAt,
        );
    }

    private function build(
        string $typeCode,
        string $profileId,
        Order $order,
        array $seller,
        ?array $buyer,
        array $lines,
        string $uuid,
        int $icv,
        string $previousHash,
        \DateTimeInterface $issuedAt,
    ): string {
        $subtotal = 0.0;
        $vatTotal = 0.0;
        $taxableBase = 0.0;
        $exemptBase = 0.0;

        foreach ($lines as $line) {
            $subtotal += $line['line_total'];
            $vatTotal += $line['vat_amount'];

            if (! empty($line['exempt'])) {
                $exemptBase += $line['line_total'];
            } else {
                $taxableBase += $line['line_total'];
            }
        }

        $subtotal = round($subtotal, 2);
        $vatTotal = round($vatTotal, 2);
        $taxableBase = round($taxableBase, 2);
        $exemptBase = round($exemptBase, 2);
        $payable = round($subtotal + $vatTotal, 2);

        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->formatOutput = false;

        $invoice = $doc->createElementNS('urn:oasis:names:specification:ubl:schema:xsd:Invoice-2', 'Invoice');
        $doc->appendChild($invoice);
        $invoice->setAttribute('xmlns:cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
        $invoice->setAttribute('xmlns:cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');

        $this->text($doc, $invoice, 'cbc:ProfileID', $profileId);
        $this->text($doc, $invoice, 'cbc:ID', $order->order_number);
        $this->text($doc, $invoice, 'cbc:UUID', $uuid);
        $this->text($doc, $invoice, 'cbc:IssueDate', $issuedAt->format('Y-m-d'));
        $this->text($doc, $invoice, 'cbc:IssueTime', $issuedAt->format('H:i:s'));
        $this->text($doc, $invoice, 'cbc:InvoiceTypeCode', $typeCode, ['name' => $buyer ? '0100000' : '0200000']);
        $this->text($doc, $invoice, 'cbc:DocumentCurrencyCode', 'SAR');
        $this->text($doc, $invoice, 'cbc:TaxCurrencyCode', 'SAR');
        $this->text($doc, $invoice, 'cbc:InvoiceCounterValue', (string) $icv);

        $ref = $doc->createElement('cac:AdditionalDocumentReference');
        $invoice->appendChild($ref);
        $this->text($doc, $ref, 'cbc:ID', 'PIH');
        $attachment = $doc->createElement('cac:Attachment');
        $ref->appendChild($attachment);
        $this->text($doc, $attachment, 'cbc:EmbeddedDocumentBinaryObject', $previousHash, ['mimeCode' => 'text/plain']);

        $invoice->appendChild($this->party($doc, 'AccountingSupplierParty', $seller, true));

        if ($buyer) {
            $invoice->appendChild($this->party($doc, 'AccountingCustomerParty', $buyer, false));
        }

        $delivery = $doc->createElement('cac:Delivery');
        $invoice->appendChild($delivery);
        $this->text($doc, $delivery, 'cbc:ActualDeliveryDate', $issuedAt->format('Y-m-d'));

        $paymentMeans = $doc->createElement('cac:PaymentMeans');
        $invoice->appendChild($paymentMeans);
        $this->text($doc, $paymentMeans, 'cbc:PaymentMeansCode', '10');

        $taxTotal = $doc->createElement('cac:TaxTotal');
        $invoice->appendChild($taxTotal);
        $this->amount($doc, $taxTotal, 'cbc:TaxAmount', $vatTotal);

        // Standard-rated and exempt bases get their own subtotals: lumping
        // exempt lines under S misstates the taxable amount and Fatoora
        // rejects the implied rate. Pure-taxable invoices keep the exact
        // previous shape (single S subtotal).
        if ($taxableBase > 0 || $exemptBase <= 0) {
            $subtotalNode = $doc->createElement('cac:TaxSubtotal');
            $taxTotal->appendChild($subtotalNode);
            $this->amount($doc, $subtotalNode, 'cbc:TaxableAmount', $taxableBase > 0 ? $taxableBase : $subtotal);
            $this->amount($doc, $subtotalNode, 'cbc:TaxAmount', $vatTotal);

            $category = $doc->createElement('cac:TaxCategory');
            $subtotalNode->appendChild($category);
            $this->text($doc, $category, 'cbc:ID', 'S');
            $this->text($doc, $category, 'cbc:Percent', number_format((float) config('zatca.vat_rate', 15.0), 2, '.', ''));

            $scheme = $doc->createElement('cac:TaxScheme');
            $category->appendChild($scheme);
            $this->text($doc, $scheme, 'cbc:ID', 'VAT');
        }

        if ($exemptBase > 0) {
            $exemptNode = $doc->createElement('cac:TaxSubtotal');
            $taxTotal->appendChild($exemptNode);
            $this->amount($doc, $exemptNode, 'cbc:TaxableAmount', $exemptBase);
            $this->amount($doc, $exemptNode, 'cbc:TaxAmount', 0.0);

            $exemptCategory = $doc->createElement('cac:TaxCategory');
            $exemptNode->appendChild($exemptCategory);
            $this->text($doc, $exemptCategory, 'cbc:ID', 'E');
            $this->text($doc, $exemptCategory, 'cbc:Percent', '0.00');

            $exemptScheme = $doc->createElement('cac:TaxScheme');
            $exemptCategory->appendChild($exemptScheme);
            $this->text($doc, $exemptScheme, 'cbc:ID', 'VAT');
        }

        $legal = $doc->createElement('cac:LegalMonetaryTotal');
        $invoice->appendChild($legal);
        $this->amount($doc, $legal, 'cbc:LineExtensionAmount', $subtotal);
        $this->amount($doc, $legal, 'cbc:TaxExclusiveAmount', $subtotal);
        $this->amount($doc, $legal, 'cbc:TaxInclusiveAmount', $payable);
        $this->amount($doc, $legal, 'cbc:PayableAmount', $payable);

        foreach (array_values($lines) as $index => $line) {
            $invoice->appendChild($this->invoiceLine($doc, $index + 1, $line));
        }

        return $doc->saveXML();
    }

    /**
     * @param  array{name?: string, name_ar?: string, vat_number?: string, street?: string, city?: string, country?: string}  $data
     */
    private function party(DOMDocument $doc, string $node, array $data, bool $supplier): \DOMElement
    {
        $displayName = $supplier
            ? ($data['name_ar'] ?? $data['name'] ?? '')
            : ($data['name'] ?? '');

        $wrapper = $doc->createElement("cac:{$node}");
        $party = $doc->createElement('cac:Party');
        $wrapper->appendChild($party);

        $name = $doc->createElement('cac:PartyName');
        $party->appendChild($name);
        $this->text($doc, $name, 'cbc:Name', $displayName);

        if (! empty($data['vat_number'])) {
            $taxScheme = $doc->createElement('cac:PartyTaxScheme');
            $party->appendChild($taxScheme);
            $this->text($doc, $taxScheme, 'cbc:CompanyID', $data['vat_number']);

            $scheme = $doc->createElement('cac:TaxScheme');
            $taxScheme->appendChild($scheme);
            $this->text($doc, $scheme, 'cbc:ID', 'VAT');
        }

        $address = $doc->createElement('cac:PostalAddress');
        $party->appendChild($address);
        $this->text($doc, $address, 'cbc:StreetName', $data['street'] ?? '');
        $this->text($doc, $address, 'cbc:CityName', $data['city'] ?? '');
        $this->text($doc, $address, 'cbc:PostalZone', $data['postal_code'] ?? '');
        $country = $doc->createElement('cac:Country');
        $address->appendChild($country);
        $this->text($doc, $country, 'cbc:IdentificationCode', $data['country'] ?? 'SA');

        $legal = $doc->createElement('cac:PartyLegalEntity');
        $party->appendChild($legal);
        $this->text($doc, $legal, 'cbc:RegistrationName', $displayName);

        return $wrapper;
    }

    /**
     * @param  array{sku: string, name: string, quantity: int|float, unit_price: float, line_total: float, vat_rate: float, vat_amount: float}  $line
     */
    private function invoiceLine(DOMDocument $doc, int $number, array $line): \DOMElement
    {
        $node = $doc->createElement('cac:InvoiceLine');
        $this->text($doc, $node, 'cbc:ID', (string) $number);
        $this->quantity($doc, $node, 'cbc:InvoicedQuantity', (float) $line['quantity']);
        $this->amount($doc, $node, 'cbc:LineExtensionAmount', round((float) $line['line_total'], 2));

        $item = $doc->createElement('cac:Item');
        $node->appendChild($item);
        $this->text($doc, $item, 'cbc:Name', $line['name']);

        $sellersId = $doc->createElement('cac:SellersItemIdentification');
        $item->appendChild($sellersId);
        $this->text($doc, $sellersId, 'cbc:ID', $line['sku']);

        $classified = $doc->createElement('cac:ClassifiedTaxCategory');
        $item->appendChild($classified);
        $this->text($doc, $classified, 'cbc:ID', ! empty($line['exempt']) ? 'E' : 'S');
        $this->text($doc, $classified, 'cbc:Percent', ! empty($line['exempt']) ? '0.00' : number_format((float) $line['vat_rate'], 2, '.', ''));

        $scheme = $doc->createElement('cac:TaxScheme');
        $classified->appendChild($scheme);
        $this->text($doc, $scheme, 'cbc:ID', 'VAT');

        $price = $doc->createElement('cac:Price');
        $node->appendChild($price);
        $this->amount($doc, $price, 'cbc:PriceAmount', round((float) $line['unit_price'], 2));

        return $node;
    }

    private function text(DOMDocument $doc, \DOMElement $parent, string $name, string $value, array $attributes = []): void
    {
        $node = $doc->createElement($name);
        $node->appendChild($doc->createTextNode($value));

        foreach ($attributes as $key => $attribute) {
            $node->setAttribute($key, $attribute);
        }

        $parent->appendChild($node);
    }

    private function amount(DOMDocument $doc, \DOMElement $parent, string $name, float $value, array $attributes = []): void
    {
        $this->text(
            $doc,
            $parent,
            $name,
            number_format($value, 2, '.', ''),
            array_merge(['currencyID' => 'SAR'], $attributes)
        );
    }

    private function quantity(DOMDocument $doc, \DOMElement $parent, string $name, float $value): void
    {
        $node = $doc->createElement($name);
        $node->appendChild($doc->createTextNode(number_format($value, 2, '.', '')));
        $node->setAttribute('unitCode', 'PCE');
        $parent->appendChild($node);
    }
}
