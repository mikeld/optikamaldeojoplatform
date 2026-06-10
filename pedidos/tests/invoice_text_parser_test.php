<?php

require_once __DIR__ . '/../includes/invoice_text_parser.php';

function assertInvoiceValue($actual, $expected, $label) {
    if ($actual !== $expected) {
        fwrite(STDERR, "$label: expected " . var_export($expected, true) . ', got ' . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
}

$visionisSummary = 'Factura 26/028457 Fecha de factura: 31/05/2026 '
    . '[150HMC.-1,00.-1,00] 1.50 HMC (-1,00, -1,00) 1 Ud(s) 4,35 60,80 IVA 10% 1,71 € '
    . 'Subtotal 430,07 € IVA 21% en 136,79 € 28,73 € IVA 10% en 293,28 € 29,33 € Total 488,13 € '
    . 'VISIONIS DISTRIBUCIÓN S.L';

$invoice = extractInvoiceFromPlainText($visionisSummary, 18);
assertInvoiceValue($invoice['providerName'], 'VISIONIS DISTRIBUCIÓN S.L', 'provider');
assertInvoiceValue($invoice['invoiceNumber'], '26/028457', 'invoice number');
assertInvoiceValue($invoice['date'], '2026-05-31', 'invoice date');
assertInvoiceValue($invoice['subtotal'], 430.07, 'subtotal');
assertInvoiceValue($invoice['taxTotal'], 58.06, 'tax total');
assertInvoiceValue($invoice['total'], 488.13, 'invoice total');
assertInvoiceValue($invoice['hasFiscalSummary'], true, 'fiscal summary');
assertInvoiceValue(count($invoice['items']), 1, 'line count');
assertInvoiceValue($invoice['items'][0]['unitPrice'], 4.35, 'unit price');
assertInvoiceValue($invoice['items'][0]['total'], 1.71, 'line total');

$intermediateSubtotal = extractInvoiceFromPlainText('Subtotal: 149,57 € Albarán: 31/05/2026', 2);
assertInvoiceValue($intermediateSubtotal['total'], 0.0, 'intermediate subtotal is not invoice total');
assertInvoiceValue($intermediateSubtotal['hasFiscalSummary'], false, 'intermediate subtotal has no fiscal summary');

$unknownProvider = extractInvoiceFromPlainText('Producto genérico 2 Ud(s) 10,00 0,00 IVA 21% 20,00 €', 1);
assertInvoiceValue(count($unknownProvider['items']), 1, 'unknown provider keeps parsed lines');

echo "invoice_text_parser_test: OK\n";
