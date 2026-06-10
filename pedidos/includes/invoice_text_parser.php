<?php

function invoiceTextNumber($value) {
    $value = trim((string)$value);
    $value = str_replace(["\xc2\xa0", ' '], '', $value);

    if (str_contains($value, ',') && str_contains($value, '.')) {
        $value = str_replace('.', '', $value);
    }
    $value = str_replace(',', '.', $value);

    if ($value === '' || $value === '-') {
        return 0.0;
    }
    return (float)$value;
}

function invoiceTextDate($value) {
    $value = trim((string)$value);
    if (preg_match('/^(\d{2})[\/-](\d{2})[\/-](\d{4})$/', $value, $matches)) {
        return $matches[3] . '-' . $matches[2] . '-' . $matches[1];
    }
    return $value;
}

function invoiceProviderProfiles() {
    return [
        ['name' => 'VISIONIS DISTRIBUCIÓN S.L', 'pattern' => '/VISIONIS\s+DISTRIBUCI[ÓO]N/u'],
        ['name' => 'ALCON HEALTHCARE, S.A.', 'pattern' => '/ALCON\s+HEALTHCARE/u'],
    ];
}

function invoiceTextProvider($text) {
    foreach (invoiceProviderProfiles() as $profile) {
        if (preg_match($profile['pattern'], $text)) {
            return $profile['name'];
        }
    }

    if (preg_match('/([A-ZÁÉÍÓÚÑ0-9][A-ZÁÉÍÓÚÑ0-9 .,&-]{4,}(?:S\.L\.|S\.A\.|SL|SA))/u', $text, $matches)) {
        return trim($matches[1]);
    }
    return '';
}

function invoiceTextBaseProduct($description) {
    $base = trim((string)$description);
    $base = preg_replace('/^\[[^\]]+\]\s*/', '', $base);
    $base = preg_replace('/\|.*$/', '', $base);
    $base = preg_replace('/\s+Pedido\s+BOD.*$/i', '', $base);
    $base = preg_replace('/\s+Paciente:.*$/i', '', $base);
    $base = preg_replace('/\s+\[[A-Z]{2}\].*$/u', '', $base);
    $base = preg_replace('/\s+\((?:[+-]?\d+[,.]\d+|ADD|LOW|HIGH|MED|,\s*|-|\+|\d+)+\).*$/iu', '', $base);
    $base = preg_replace('/\s+[A-ZÁÉÍÓÚÑ][A-Za-zÁÉÍÓÚÑáéíóúñ\s.-]+-\d{8}$/u', '', $base);
    $base = preg_replace('/\s+OTHER\s+-\s+[A-Z0-9]+\s+-.*$/i', '', $base);
    $base = preg_replace('/\s+OTHER\s+Edging.*$/i', '', $base);
    $base = preg_replace('/\s+PRECAL.*$/i', '', $base);
    $base = trim(preg_replace('/\s+/', ' ', $base));
    return $base !== '' ? $base : trim((string)$description);
}

function invoiceTextGraduation($description) {
    $description = (string)$description;
    if (preg_match('/\(([^)]*(?:[+-]\d+[,.]\d+|ADD|LOW|HIGH|MED)[^)]*)\)/iu', $description, $matches)) {
        return trim($matches[1]);
    }
    if (preg_match('/OJO\s+(?:DERECHO|IZQUIERDO)\s+-\s+([^|]+?)(?:\s+ANTIREFLEX|\s+OTHER|\s+PCS|$)/iu', $description, $matches)) {
        return trim($matches[1]);
    }
    return '';
}

function invoiceTextCleanDescription($description) {
    $description = trim((string)$description);
    $description = preg_replace('/^Factura\s+[A-Z0-9\/.-]+\s+/iu', '', $description);
    $description = preg_replace('/^(?:DESCRIPCI[ÓO]N\s+CANTIDAD\s+PRECIO\s+DESC\.\s+\(%\)\s+IMPUESTOS\s+IMPORTE\s*)+/iu', '', $description);
    $description = preg_replace('/^Subtotal:\s*[-\d,.]+\s*€?\s*/iu', '', $description);
    $description = preg_replace('/^Albarán:\s*\d{2}\/\d{2}\/\d{4}\s+[A-Z]\/OUT\/\d+\s+Pedido:\s*\[[^\]]+\]\s+Cliente:\s*\d+\s*/iu', '', $description);
    $description = preg_replace('/^\(MALDEOJO.*$/iu', '', $description);
    return trim(preg_replace('/\s+/', ' ', $description));
}

function invoiceTextAccountingSummary($text) {
    $taxes = [];
    $taxPattern = '/(?:IVA|VAT)\s*(\d+(?:[,.]\d+)?)\s*%\s*(?:en|sobre|base)?\s*([\d., ]+)\s*€\s*([\d., ]+)\s*€/iu';
    if (preg_match_all($taxPattern, $text, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $match) {
            $taxes[] = [
                'rate' => invoiceTextNumber($match[1]),
                'base' => invoiceTextNumber($match[2]),
                'amount' => invoiceTextNumber($match[3]),
            ];
        }
    }

    $subtotal = 0.0;
    $total = 0.0;
    if (!empty($taxes)) {
        if (preg_match('/\bSubtotal\s*:?[ \t]*([\d., ]+)\s*€/iu', $text, $match)) {
            $subtotal = invoiceTextNumber($match[1]);
        }
        if (preg_match('/\bTotal\s*:?[ \t]*([\d., ]+)\s*€/iu', $text, $match)) {
            $total = invoiceTextNumber($match[1]);
        }
    }

    return [
        'subtotal' => $subtotal,
        'taxes' => $taxes,
        'taxTotal' => array_reduce($taxes, fn($sum, $tax) => $sum + $tax['amount'], 0.0),
        'total' => $total,
        'hasFiscalSummary' => !empty($taxes) && $subtotal > 0 && $total > 0,
    ];
}

function extractInvoiceFromPlainText($textContent, $pageNumber = null) {
    $text = trim(preg_replace('/\s+/', ' ', (string)$textContent));
    $accounting = invoiceTextAccountingSummary($text);
    $invoice = [
        'providerName' => invoiceTextProvider($text),
        'date' => '',
        'invoiceNumber' => '',
        'items' => [],
        'subtotal' => $accounting['subtotal'],
        'taxes' => $accounting['taxes'],
        'taxTotal' => $accounting['taxTotal'],
        'total' => $accounting['total'],
        'hasFiscalSummary' => $accounting['hasFiscalSummary'],
    ];

    if (preg_match('/(?:Factura|N[ºo°]?\s*Factura)\s*:?\s*([A-Z0-9\/.-]+)/iu', $text, $matches)) {
        $invoice['invoiceNumber'] = trim($matches[1]);
    }
    if (preg_match('/(?:Fecha\s+de\s+factura|Fecha)\s*:\s*(\d{2}[\/-]\d{2}[\/-]\d{4})/iu', $text, $matches)) {
        $invoice['date'] = invoiceTextDate($matches[1]);
    }

    $pattern = '/(.{2,420}?)\s+(\d+(?:[,.]\d+)?)\s+Ud\(s\)\s+(-?\s?\d+(?:[,.]\d{2})|-)\s+(-?\s?\d+(?:[,.]\d{2}))\s+(?:IVA|VAT)\s+\d+%\s+(-?\s?\d+(?:[,.]\d{2}))\s*€/iu';
    if (preg_match_all($pattern, $text, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $idx => $match) {
            $description = invoiceTextCleanDescription($match[1]);
            if ($description === '' || ($invoice['providerName'] !== '' && stripos($description, $invoice['providerName']) !== false)) {
                continue;
            }
            $invoice['items'][] = [
                'id' => 'text-' . ($pageNumber ?: 'p') . '-' . ($idx + 1),
                'description' => $description,
                'baseProductName' => invoiceTextBaseProduct($description),
                'graduation' => invoiceTextGraduation($description),
                'quantity' => invoiceTextNumber($match[2]),
                'unitPrice' => invoiceTextNumber($match[3]),
                'total' => invoiceTextNumber($match[5]),
            ];
        }
    }

    return $invoice;
}
