<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| PROCESS SALE
|--------------------------------------------------------------------------
|
| Canonical checkout endpoint. A duplicate used to live at
| /app/Controllers/process_sale.php and that is where pos.js pointed,
| so this module had no guard of its own and nothing about it sat next
| to the rest of the POS code.
|
| Prior code relied on the controller deriving cashier_id from the
| session, which made an anonymous POST fail by accident rather than by
| design: a later refactor that trusted the request body would have
| turned it into an open checkout endpoint. The guard below is explicit
| so that cannot happen quietly.
|
| Cashiers legitimately ring up sales, so this requires a signed-in
| account rather than the Administrator role.
|
*/

/*
| Path depths from /app/dashboard/pos:
|   ../../../  -> project root      (config/ lives here)
|   ../../     -> app/              (Middleware/ and Controllers/ live here)
*/

require_once __DIR__ . '/../../../config/session.php';
require_once __DIR__ . '/../../Middleware/AuthMiddleware.php';
require_once __DIR__ . '/../../Helpers/Csrf.php';

header('Content-Type: application/json; charset=utf-8');

AuthMiddleware::requireAuthenticatedApi();

/*
| A sale is a write: it commits sales, sale_items and payments and moves
| stock. The cashier's cookie is attached by the browser to any cross-site
| post, so without this a page elsewhere on the internet could ring up sales
| on their account.
*/
Csrf::requireValid();

require_once __DIR__ . '/../../Controllers/POSController.php';

$controller = new POSController();

$controller->processSale();