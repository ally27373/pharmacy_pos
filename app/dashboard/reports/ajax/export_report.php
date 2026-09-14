<?php

declare(strict_types=1);

require_once '../../../../config/session.php';
require_once '../../../../app/Middleware/AuthMiddleware.php';
require_once '../../../../app/Controllers/ReportsController.php';
require_once '../../../../app/Services/AuditLogger.php';

AuthMiddleware::check();

try {
    $format = strtolower((string) ($_GET['format'] ?? 'xlsx'));
    if (!in_array($format, ['xlsx', 'pdf'], true)) {
        throw new InvalidArgumentException('Unsupported report format.');
    }

    $filters = [
        'search' => (string) ($_GET['search'] ?? ''),
        'source' => (string) ($_GET['source'] ?? 'All'),
        'category' => (string) ($_GET['category'] ?? ''),
        'type' => (string) ($_GET['type'] ?? ''),
        'period' => (string) ($_GET['period'] ?? 'weekly'),
        'from' => (string) ($_GET['from'] ?? ''),
        'to' => (string) ($_GET['to'] ?? ''),
    ];

    $controller = new ReportsController();
    $rows = $controller->getExportRows($filters);

    $sourceLabel = $filters['source'] !== '' ? $filters['source'] : 'All';
    $auditDescription = sprintf(
        'Exported %s report as %s (%d rows).',
        $sourceLabel,
        strtoupper($format),
        count($rows)
    );
    AuditLogger::log('EXPORT', 'REPORTS', $auditDescription);

    $timestamp = date('Y-m-d_H-i-s');

    if ($format === 'xlsx') {
        $autoload = '../../../../vendor/autoload.php';
        if (!file_exists(__DIR__ . '/' . $autoload)) {
            throw new RuntimeException('PhpSpreadsheet is not installed. Run composer install in the project root.');
        }
        require_once __DIR__ . '/' . $autoload;

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Report');

        $headers = [
            'Date',
            'Source',
            'Product Name',
            'Code',
            'Type',
            'Category',
            'Quantity',
            'Unit Price',
            'Total',
            'Payment Method',
            'Transaction No.',
            'Invoice No.',
        ];

        $sheet->fromArray($headers, null, 'A1');

        $rowNumber = 2;
        foreach ($rows as $row) {
            $sheet->fromArray([
                date('m/d/Y H:i', strtotime((string) $row['report_date'])),
                $row['source'],
                $row['product_name'],
                $row['barcode'],
                $row['type_name'],
                $row['category_name'],
                (int) $row['quantity'],
                (float) $row['unit_price'],
                (float) $row['total_amount'],
                $row['payment_method'],
                $row['transaction_number'] ?? '—',
                $row['invoice_number'] ?? '—',
            ], null, 'A' . $rowNumber);
            $rowNumber++;
        }

        $sheet->getStyle('A1:L1')->getFont()->setBold(true);
        $sheet->freezePane('A2');
        $sheet->setAutoFilter('A1:L' . max(1, $rowNumber - 1));

        foreach (range('A', 'L') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $sheet->getStyle('H2:I' . max(2, $rowNumber - 1))
            ->getNumberFormat()
            ->setFormatCode('#,##0.00');

        $filename = 'pharmacy_report_' . $timestamp . '.xlsx';

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }

    // Lightweight PDF writer: avoids adding another Composer dependency.
    // It intentionally uses simple built-in PDF fonts and converts the peso sign
    // to "PHP" so the generated file remains valid on every local XAMPP setup.
    $lines = [];
    $lines[] = 'NICA XANDRA PHARMACY POS - REPORT';
    $lines[] = 'Period: ' . strtoupper((string) $filters['period']) . ' | Source: ' . $sourceLabel;
    $lines[] = 'Generated: ' . date('m/d/Y h:i A');
    $lines[] = str_repeat('-', 118);
    $lines[] = 'DATE       SOURCE     PRODUCT                         CODE        QTY   UNIT PRICE   TOTAL        PAYMENT';
    $lines[] = str_repeat('-', 118);

    foreach ($rows as $row) {
        $date = date('m/d/Y', strtotime((string) $row['report_date']));
        $product = preg_replace('/[^\x20-\x7E]/', '', (string) $row['product_name']);
        $product = substr($product, 0, 30);
        $code = substr((string) $row['barcode'], 0, 10);
        $source = substr((string) $row['source'], 0, 9);
        $qty = (string) ((int) $row['quantity']);
        $unit = number_format((float) $row['unit_price'], 2);
        $total = number_format((float) $row['total_amount'], 2);
        $payment = substr((string) $row['payment_method'], 0, 12);

        $lines[] = sprintf(
            "%-10s %-9s %-30s %-10s %5s %11s %12s %-12s",
            $date,
            $source,
            $product,
            $code,
            $qty,
            $unit,
            $total,
            $payment
        );
    }

    if (count($rows) === 0) {
        $lines[] = 'No report records matched the selected filters.';
    }

    $pdf = buildSimplePdf($lines);
    $filename = 'pharmacy_report_' . $timestamp . '.pdf';

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($pdf));
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');

    echo $pdf;
    exit;
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json; charset=UTF-8');
    error_log('Report export error: ' . $e->getMessage());

    echo json_encode([
        'success' => false,
        'message' => 'Report export failed: ' . $e->getMessage(),
    ]);
    exit;
}

function pdfEscape(string $text): string
{
    return str_replace(
        ['\\', '(', ')'],
        ['\\\\', '\\(', '\\)'],
        $text
    );
}

function buildSimplePdf(array $lines): string
{
    $pages = array_chunk($lines, 48);
    $objects = [];

    // 1 catalog, 2 pages, 3 font.
    $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
    $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Courier >>';

    $pageObjectIds = [];
    $nextId = 4;

    foreach ($pages as $pageLines) {
        $content = "BT\n/F1 7 Tf\n36 760 Td\n9 TL\n";
        foreach ($pageLines as $line) {
            $content .= '(' . pdfEscape(substr((string) $line, 0, 118)) . ") Tj\nT*\n";
        }
        $content .= "ET";

        $contentId = $nextId++;
        $pageId = $nextId++;
        $objects[$contentId] = '<< /Length ' . strlen($content) . " >>\nstream\n" . $content . "\nendstream";
        $objects[$pageId] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 3 0 R >> >> /Contents ' . $contentId . ' 0 R >>';
        $pageObjectIds[] = $pageId;
    }

    $kids = implode(' ', array_map(static fn (int $id): string => $id . ' 0 R', $pageObjectIds));
    $objects[2] = '<< /Type /Pages /Kids [' . $kids . '] /Count ' . count($pageObjectIds) . ' >>';

    ksort($objects);
    $pdf = "%PDF-1.4\n";
    $offsets = [0];

    foreach ($objects as $id => $body) {
        $offsets[$id] = strlen($pdf);
        $pdf .= $id . " 0 obj\n" . $body . "\nendobj\n";
    }

    $xrefOffset = strlen($pdf);
    $maxId = max(array_keys($objects));
    $pdf .= "xref\n0 " . ($maxId + 1) . "\n";
    $pdf .= "0000000000 65535 f \n";

    for ($id = 1; $id <= $maxId; $id++) {
        $pdf .= sprintf("%010d 00000 n \n", $offsets[$id] ?? 0);
    }

    $pdf .= "trailer\n<< /Size " . ($maxId + 1) . " /Root 1 0 R >>\n";
    $pdf .= "startxref\n" . $xrefOffset . "\n%%EOF";

    return $pdf;
}
