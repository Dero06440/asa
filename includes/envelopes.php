<?php

require_once __DIR__ . '/simple_pdf.php';

function envelopeSenderLines(): array
{
    return [
        'A.S.A.',
        'Arrosants et Riverains du Paillon',
        '672 Avenue Hôtel de Ville',
        '06440 PEILLON',
    ];
}

function envelopeAddressLines(array $row, array $fieldMap = []): array
{
    $defaults = [
        'civilite' => 'civilite',
        'nom' => 'nom',
        'adresse_1' => 'rue',
        'adresse_2' => 'adresse2',
        'code_postal' => 'code_postal',
        'ville' => 'ville',
    ];
    $map = array_merge($defaults, $fieldMap);

    $lines = [];

    $name = trim(implode(' ', array_filter([
        trim((string) ($row[$map['civilite']] ?? '')),
        trim((string) ($row[$map['nom']] ?? '')),
    ])));
    if ($name !== '') {
        $lines[] = $name;
    }

    $adresse1 = trim((string) ($row[$map['adresse_1']] ?? ''));
    if ($adresse1 !== '') {
        $lines[] = $adresse1;
    }

    $adresse2 = trim((string) ($row[$map['adresse_2']] ?? ''));
    if ($adresse2 !== '') {
        $lines[] = $adresse2;
    }

    $cityLine = trim(
        trim((string) ($row[$map['code_postal']] ?? '')) . ' ' . trim((string) ($row[$map['ville']] ?? ''))
    );
    if ($cityLine !== '') {
        $lines[] = strtoupper($cityLine);
    }

    return $lines;
}

function envelopeFontSizeForLines(array $lines, float $targetWidthMm): float
{
    $maxChars = 1;
    foreach ($lines as $line) {
        $length = mb_strlen((string) $line, 'UTF-8');
        if ($length > $maxChars) {
            $maxChars = $length;
        }
    }

    $targetWidthPt = $targetWidthMm * 72 / 25.4;
    $estimatedPt = $targetWidthPt / max($maxChars * 0.52, 1);

    return max(10.0, min(14.0, $estimatedPt));
}

function renderEnvelopePage(SimplePdfDocument $pdf, array $recipientLines): void
{
    if (empty($recipientLines)) {
        return;
    }

    $pdf->addPage();
    $pdf->setFontSize(9, 4.8 * 72 / 25.4);
    $pdf->textBlock(34, 15, envelopeSenderLines());

    $fontSize = envelopeFontSizeForLines($recipientLines, 65);
    $lineHeight = 10 * 72 / 25.4;
    $pdf->setFontSize($fontSize, $lineHeight);
    $pdf->textBlock(130, 65, $recipientLines);
}
