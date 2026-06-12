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

// SKU raíz del corchete inicial: "[OP2775]" → OP2775, "[15DOB.8,50.-8,00]" → 15DOB
// (los SKU de blísters llevan la graduación tras el primer punto/coma; el precio
// no depende de la graduación, así que la raíz identifica el producto)
function invoiceTextSkuRoot($description) {
    if (preg_match('/^\[([^\]]+)\]/', trim((string)$description), $m)) {
        $parts = preg_split('/[.,]/', $m[1]);
        $root = trim($parts[0]);
        return $root !== '' ? $root : trim($m[1]);
    }
    return null;
}

// Nombre del paciente/cliente final asociado a la línea (Visionis lo pone de
// tres formas distintas según el tipo de producto)
function invoiceTextClientRef($description) {
    $d = trim((string)$description);
    // BOD: "Paciente: Mr CUB B-65 Dani Molina OJO DERECHO"
    if (preg_match('/Paciente:\s*(.{2,60}?)\s+OJO\b/iu', $d, $m)) {
        $words = array_filter(explode(' ', $m[1]), function ($w) {
            if ($w === '' || preg_match('/\d/', $w)) return false; // códigos de montura (B-65)
            $lw = mb_strtolower(rtrim($w, '.'));
            if (in_array($lw, ['mr', 'mrs', 'sr', 'sra', 'd', 'dna', 'dña'], true)) return false;
            if (preg_match('/^\p{Lu}{2,4}$/u', $w)) return false; // siglas tipo CUB
            return true;
        });
        $name = trim(implode(' ', $words));
        if ($name !== '') return $name;
    }
    // Servicios: "BISELADO REMOTO OPTIMIZE REF JULENE GARCIA"
    if (preg_match('/\bREF\s+([\p{L} ]{3,40})$/u', $d, $m)) {
        return trim($m[1]);
    }
    // Blísters: nombre suelto tras la graduación: "(8,50, -8,00) Rosario de Juan"
    if (preg_match('/\)\s+([\p{L}]+(?:\s+[\p{L}]+){0,3})$/u', $d, $m)) {
        return trim($m[1]);
    }
    return '';
}

function invoiceTextBaseProduct($description) {
    $base = trim((string)$description);
    // Normalizar el corchete a la raíz del SKU: "[15DOB.8,50.-8,00] BLISTER..." → "[15DOB] BLISTER..."
    // así todas las graduaciones del mismo producto comparten nombre base (mismo precio)
    $skuRoot = invoiceTextSkuRoot($base);
    if ($skuRoot !== null) {
        $base = preg_replace('/^\[[^\]]+\]\s*/', '[' . $skuRoot . '] ', $base);
    }
    $base = preg_replace('/\|.*$/', '', $base);
    $base = preg_replace('/\s+Pedido\s+BOD.*$/i', '', $base);
    $base = preg_replace('/\s+Pedido\s+Optimize.*$/i', '', $base);
    $base = preg_replace('/\s+REF\s+[\p{L} ]{3,40}$/u', '', $base);
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

    // Bloques de albarán (Visionis agrupa líneas por albarán + pedido SO):
    // "Albarán: 20/04/2026 V/OUT/1137305 Pedido: ['SO840948'] Cliente: 29178"
    $bloques = [];
    if (preg_match_all('/Albar[áa]n:\s*(\d{2}\/\d{2}\/\d{4})\s+[A-Z]\/OUT\/\d+\s+Pedido:\s*\[\'?([A-Z0-9]+)\'?\]/iu', $text, $bm, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
        foreach ($bm as $b) {
            $bloques[] = [
                'offset' => $b[0][1],
                'orderDate' => invoiceTextDate($b[1][0]),
                'orderNumber' => $b[2][0],
            ];
        }
    }

    $pattern = '/(.{2,420}?)\s+(\d+(?:[,.]\d+)?)\s+Ud\(s\)\s+(-?\s?\d+(?:[,.]\d{2})|-)\s+(-?\s?\d+(?:[,.]\d{2}))\s+(?:IVA|VAT)\s+\d+%\s+(-?\s?\d+(?:[,.]\d{2}))\s*€/iu';
    if (preg_match_all($pattern, $text, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
        foreach ($matches as $idx => $match) {
            $rawDescription = $match[1][0];
            $description = invoiceTextCleanDescription($rawDescription);
            if ($description === '' || ($invoice['providerName'] !== '' && stripos($description, $invoice['providerName']) !== false)) {
                continue;
            }
            // Filtrar texto legal/pie de página capturado entre páginas
            $lowerDesc = mb_strtolower($description);
            if (str_contains($lowerDesc, 'consentimiento') || str_contains($lowerDesc, 'lopd')
                || str_contains($lowerDesc, 'registro mercantil') || str_contains($lowerDesc, 'protección de datos')
                || str_contains($lowerDesc, 'proteccion de datos') || str_contains($lowerDesc, 'tratamiento de')) {
                continue;
            }
            $quantity = invoiceTextNumber($match[2][0]);
            $total = invoiceTextNumber($match[5][0]);
            // Columnas Visionis: PRECIO (tarifa unitaria), DESC. (%), IMPORTE (neto tras descuento).
            // El precio de catálogo a comparar es el de TARIFA, no el neto (importe/cantidad):
            // así un descuento comercial puntual no dispara "cambio de precio".
            $grossUnit = invoiceTextNumber($match[3][0]);
            $discountPercent = invoiceTextNumber($match[4][0]);
            $unitPrice = $grossUnit > 0 ? $grossUnit : ($quantity > 0 ? round($total / $quantity, 4) : 0.0);

            // Bloque de albarán al que pertenece esta línea. Se compara con el FINAL del
            // match porque la cabecera del albarán queda capturada dentro de la descripción
            // de la primera línea de su bloque.
            $bloque = null;
            $itemOffset = $match[0][1] + strlen($match[0][0]);
            foreach ($bloques as $b) {
                if ($b['offset'] < $itemOffset) $bloque = $b;
                else break;
            }

            $invoice['items'][] = [
                'id' => 'text-' . ($pageNumber ?: 'p') . '-' . ($idx + 1),
                'description' => $description,
                'baseProductName' => invoiceTextBaseProduct($description),
                'sku' => invoiceTextSkuRoot($description),
                'graduation' => invoiceTextGraduation($description),
                'quantity' => $quantity,
                'unitPrice' => $unitPrice,
                'discountPercent' => $discountPercent > 0 ? $discountPercent : null,
                'total' => $total,
                'orderNumber' => $bloque['orderNumber'] ?? null,
                'orderDate' => $bloque['orderDate'] ?? null,
                'clientRef' => invoiceTextClientRef($rawDescription) ?: null,
            ];
        }
    }

    return $invoice;
}
