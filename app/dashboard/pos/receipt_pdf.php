<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Printable receipt as a PDF download
|--------------------------------------------------------------------------
|
| Path depths from /app/dashboard/pos:
|   ../../../  -> project root   (vendor/ lives here)
|   ../../     -> app/           (Middleware/ and Controllers/ live here)
|
| Same data and same guard as receipt.php, so the two views can never
| disagree about who is allowed to read a transaction.
|
*/

$vendorAutoload = __DIR__ . '/../../../vendor/autoload.php';

if (is_file($vendorAutoload)) {
    require_once $vendorAutoload;
}

require_once __DIR__ . '/../../../config/session.php';
require_once __DIR__ . '/../../Middleware/AuthMiddleware.php';

/*
| Receipts belong to whichever account rang the sale up, the same rule
| receipt.php uses: an Administrator-only check would lock cashiers out of
| the receipts they just printed. This is a real navigation to a file, not
| an XHR call, so a redirect on failure is the right refusal.
*/
AuthMiddleware::check();

/*
| Tell the user exactly what is missing rather than emitting a fatal.
|
| The endpoint is reached from a link on the receipt, so a blank page or a
| stray HTML file in the Downloads folder tells nobody anything. This used
| to be reachable-but-broken exactly this way.
*/
$missing = [];

if (!is_file($vendorAutoload)) {
    $missing[] = 'vendor/autoload.php is missing';
}

if (is_file($vendorAutoload) && !class_exists('\Dompdf\Dompdf')) {
    $missing[] = 'dompdf is not installed';
}

// dompdf needs these; composer will refuse to install it without them, so
// a partial install is worth naming precisely.
foreach (['dom', 'mbstring', 'gd'] as $extension) {
    if (!extension_loaded($extension)) {
        $missing[] = 'PHP extension "' . $extension . '" is not loaded';
    }
}

if ($missing) {

    http_response_code(503);

    header('Content-Type: text/plain; charset=utf-8');

    die(
        "This receipt cannot be produced yet.\n\n"
        . "Missing:\n  - " . implode("\n  - ", $missing) . "\n\n"
        . "On the server run:\n\n"
        . "    cd /var/www/html/ && composer require dompdf/dompdf\n"
    );

}

require_once __DIR__ . '/../../Controllers/SalesController.php';

$saleId = (int) ($_GET['sale_id'] ?? 0);

if ($saleId <= 0) {
    die('Invalid Sale.');
}

$salesController = new SalesController();

$details = $salesController->getSaleDetails($saleId);

if (!$details) {
    die('Sale not found.');
}

$header = $details[0];

/*
| Roll widths in millimetres. 58 is what the POS-58 uses; 80 is accepted
| because swapping the roll is a two-minute job and reprinting a queue of
| receipts on the wrong paper is not. Anything else falls back to 58.
*/
$rollMm = (int) ($_GET['width'] ?? 58);

if (!in_array($rollMm, [58, 80], true)) {
    $rollMm = 58;
}

/* 1mm = 2.834645pt. The height is deliberately long: a thermal roll is
   continuous, and one receipt must land on one sheet. */
$pageWidthPt = $rollMm * 2.834645;
$pageHeightPt = 500 * 2.834645;

/*
| U+20B1 is covered by the DejaVu fonts dompdf bundles, so the real peso
| sign prints instead of the "PHP" fallback. It stays in one closure so that
| if a future font swap drops the glyph, one line changes it.
*/
$peso = static fn($amount): string => "\u{20B1}" . number_format((float) ($amount ?? 0), 2);

/*
| dompdf has no flexbox, no grid and no CSS custom properties, so this
| template is tables and block elements only. DejaVu Sans Mono is bundled
| with dompdf and covers U+20B1, which is why the peso sign is safe here
| without bundling a font.
*/
$itemsHtml = '';

foreach ($details as $item) {

    $itemsHtml .= '<tr>'
        . '<td>' . htmlspecialchars((string) ($item['product_name'] ?? '')) . '</td>'
        . '<td class="r">' . (int) $item['quantity'] . '</td>'
        . '<td class="r">' . $peso($item['subtotal'] ?? 0) . '</td>'
        . '</tr>'
        . '<tr><td colspan="3" class="batch">'
        . htmlspecialchars((string) ($item['batch_allocations'] ?? ''))
        . '</td></tr>';

}

$html = '<!DOCTYPE html><html><head><meta charset="utf-8"><style>
@page { margin: 0; }

body{
    font-family:"DejaVu Sans Mono", monospace;
    font-size:9pt;
    color:#000;
    margin:0;
    padding:4mm 3mm;
}

.center{ text-align:center; }

h1{ font-size:13pt; margin:0; letter-spacing:1px; }
h2{ font-size:9pt; margin:2mm 0 0; font-weight:normal; }

hr{
    border:none;
    border-top:1px dashed #000;
    margin:2.5mm 0;
}

table{ width:100%; border-collapse:collapse; }

th{
    font-size:8pt;
    text-align:left;
    border-bottom:1px solid #000;
    padding:1mm 0;
}

td{ padding:1mm 0; vertical-align:top; }

.r{ text-align:right; white-space:nowrap; }

.batch{ font-size:7pt; color:#333; padding-top:0; }

.totals td{ padding:0.5mm 0; }
.totals .grand{ font-weight:bold; border-top:1px solid #000; border-bottom:1px solid #000; padding:1.5mm 0; }

.foot{ margin-top:3mm; font-size:8pt; }
</style></head><body>'

    . '<div class="center">'
    . '<h1>NICAXANDRA PHARMACY</h1>'
    . '<h2>Official Receipt</h2>'
    . '</div>'

    . '<hr>'

    . '<table>'
    . '<tr><td>Invoice</td><td class="r">' . htmlspecialchars((string) ($header['invoice_number'] ?? '')) . '</td></tr>'
    . '<tr><td>Transaction</td><td class="r">' . htmlspecialchars((string) ($header['transaction_number'] ?? '')) . '</td></tr>'
    . '<tr><td>Date</td><td class="r">'
        . htmlspecialchars(toPhTime($header['created_at'] ?? null, 'M d, Y h:i A')) . '</td></tr>'
    . '</table>'

    . '<hr>'

    . '<table>'
    . '<tr><th>Medicine</th><th class="r">Qty</th><th class="r">Total</th></tr>'
    . $itemsHtml
    . '</table>'

    . '<hr>'

    . '<table class="totals">'
    . '<tr><td>Subtotal</td><td class="r">' . $peso($header['subtotal'] ?? 0) . '</td></tr>'
    . '<tr><td>Discount</td><td class="r">' . $peso($header['discount_amount'] ?? 0) . '</td></tr>'
    . '<tr class="grand"><td>TOTAL</td><td class="r">' . $peso($header['total_amount'] ?? 0) . '</td></tr>'
    . '<tr><td>Payment</td><td class="r">' . htmlspecialchars((string) ($header['payment_method'] ?? '')) . '</td></tr>';

if (!empty($header['reference_number'])) {
    $html .= '<tr><td>Reference</td><td class="r">'
        . htmlspecialchars((string) $header['reference_number']) . '</td></tr>';
}

$html .= '<tr><td>Paid</td><td class="r">' . $peso($header['amount_paid'] ?? 0) . '</td></tr>'
    . '<tr><td>Change</td><td class="r">' . $peso($header['change_amount'] ?? 0) . '</td></tr>'
    . '</table>'

    . '<div class="center foot">'
    . 'Thank you for your purchase!<br>'
    . 'Get Well Soon!'
    . '</div>'

    . '</body></html>';

$dompdf = new \Dompdf\Dompdf([
    'isRemoteEnabled' => false,
]);

$dompdf->setDefaultFont('DejaVu Sans Mono');
$dompdf->setPaper([0, 0, $pageWidthPt, $pageHeightPt]);
$dompdf->loadHtml($html);
$dompdf->render();

/*
| Render to a string and send the headers here rather than using
| dompdf->stream().
|
| stream() owns the header block, and if anything at all reached the
| output before it -- a stray newline, a warning from an extension, a BOM
| in a file this endpoint happens to include -- the response silently stops
| being a PDF and the browser saves a broken file instead. Owning the
| headers means a stray byte is visible rather than mysterious.
|
| Clearing the buffer first is the other half: any output that did happen
| gets discarded instead of being pasted in front of the %PDF- magic.
*/
while (ob_get_level() > 0) {
    ob_end_clean();
}

$pdf = $dompdf->output();

$invoice = preg_replace('/[^A-Za-z0-9_-]+/', '-', (string) ($header['invoice_number'] ?? ''));

$filename = 'receipt-' . ($invoice !== '' && $invoice !== null ? $invoice : (string) $saleId) . '.pdf';

/*
| attachment, not inline: the point of this endpoint is a file that lands
| in Downloads without a print dialog appearing. isRemoteEnabled is off
| above, so nothing here fetches an external resource while rendering.
*/
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($pdf));
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

echo $pdf;