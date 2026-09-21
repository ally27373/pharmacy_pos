# Defect Report — Inventory & Products

Filed by: test-team/inventory-products
Branch: test-team/inventory-products
Date: 2026-09-21

Test run summary: 84 tests, 83 passed, 1 failed (intentional — documents DEFECT-1 below).

---

## DEFECT-1: Products whose only remaining stock has expired are reported as "Out of Stock" instead of "Expired"

- **File / function**: `app/Models/Inventory.php` — `syncProductAggregate()` (private,
  lines 812–875), and the structurally identical aggregate subquery in
  `getProducts()` (lines 111–138).
- **Test**: `tests/Unit/InventoryProducts/InventoryTest.php::testExpiredOnlyStockIsMisreportedAsOutOfStockInsteadOfExpired`
- **Severity**: medium
- **Expected behavior**: A product whose physical stock is sitting in a
  batch that has passed its `expiration_date` (and has not been sold/zeroed
  out) should be reported with `product_status = 'Expired'`, distinct from
  a product that genuinely has zero units on hand. The code clearly intends
  this distinction — both `syncProductAggregate()` and the `getProducts()`
  listing query have an explicit `'Expired'` branch/CASE arm for it, and
  pharmacy staff need this signal to know stock must be written off rather
  than simply reordered.
- **Actual behavior**: The `'Expired'` branch can never be reached. It is
  effectively dead code.
- **Steps to reproduce**: See the cited test. It drives `Inventory::adjustStock()`
  through a normal "Stock In" call, with the aggregate query's mocked
  response set to exactly what the real (unmodified) SQL would return for
  a product whose only quantity-bearing batch has an `expiration_date` in
  the past: `total_quantity = 0`, `nearest_expiration = null`. The test
  then asserts the final `products` UPDATE is issued with
  `product_status = 'Expired'`. It fails — the code actually computes
  `'Out of Stock'`.
- **Why this looks like a real bug (not a bad test assumption)**: The
  aggregate SQL in `syncProductAggregate()` is:

  ```sql
  SELECT
      COALESCE(SUM(CASE
          WHEN quantity > 0
               AND (expiration_date IS NULL OR expiration_date >= CURDATE())
          THEN quantity ELSE 0 END), 0) AS total_quantity,
      MIN(CASE
          WHEN quantity > 0
               AND (expiration_date IS NULL OR expiration_date >= CURDATE())
          THEN expiration_date ELSE NULL END) AS nearest_expiration,
      ...
  FROM product_batches
  WHERE product_id = :product_id
  ```

  Both `total_quantity` (SUM) and `nearest_expiration` (MIN) are computed
  under the **identical** `CASE WHEN quantity > 0 AND (expiration_date IS
  NULL OR expiration_date >= CURDATE())` filter. Any batch whose
  `expiration_date` is in the past is excluded from *both* aggregates —
  its `expiration_date` never contributes to the `MIN()`. As a direct
  consequence, `nearest_expiration` can only ever be `NULL` or a date
  `>= CURDATE()`; it can never be a past date. The PHP branch that checks
  for it:

  ```php
  } elseif ($expiration && strtotime($expiration) < strtotime(date('Y-m-d'))) {
      $status = 'Expired';
  }
  ```

  therefore never evaluates true. Worse, once every quantity-bearing batch
  for a product has expired, `total_quantity` sums to `0` (because the same
  filter excludes those expired batches from the SUM too), so the code
  takes the `$quantity <= 0` branch first and reports `'Out of Stock'` —
  which is indistinguishable, from the UI's point of view, from a product
  that was simply never restocked. The same root-cause pattern (aggregating
  quantity and nearest-expiration under one shared "not expired" filter) is
  present in the `batch_totals` subquery inside `Inventory::getProducts()`
  (lines 111–138), which feeds the products-listing page's `product_status`
  CASE expression with the same `'Expired'` WHEN-arm that can never fire
  for the same reason; that call site was not separately unit-tested here
  since it requires executing the raw SQL against a real engine to observe
  directly, but the query structure is identical to the reproduced case.

  A minimal fix would compute `nearest_expiration` (and a flag such as
  "has expired batch with qty > 0") from an *unfiltered-by-expiry* view of
  quantity-bearing batches, so an expired nearest-batch date can actually
  surface, while still computing `total_quantity` as "usable" (non-expired)
  stock for reorder-threshold purposes.

---

## Dev team resolution

**Verdict: DEFECT-1 confirmed valid and fixed.**

Traced the SQL myself in both `syncProductAggregate()` (then lines 811-874)
and the `batch_totals` subquery in `getProducts()` (then lines 110-137) and
confirmed the write-up: `total_quantity`/`batch_quantity` and
`nearest_expiration`/`nearest_expiration_date` were computed under the
identical `quantity > 0 AND (expiration_date IS NULL OR expiration_date >=
CURDATE())` filter, so `nearest_expiration` could never be a past date.

Also found the defect ran one level deeper than the write-up stated: even
if `nearest_expiration` *could* surface a past date, the PHP branch order in
`syncProductAggregate()` checked `$quantity <= 0` first and returned
`'Out of Stock'` unconditionally before the `elseif` expiration check ever
ran — same short-circuit shape in `getProducts()`'s CASE expression
(`batch_quantity <= 0` arm before the `nearest_expiration_date < CURDATE()`
arm). Fixing only the filter without fixing the branch/arm order would have
left the bug in place.

Confirmed downstream wiring is real and currently dead for this scenario:
`product_status = 'Expired'` is consumed by `app/Models/POS.php` (excludes
expired products from being sellable), `app/Models/Reports.php` (excludes
expired products from inventory valuation), `app/Models/Notification.php`
and `app/Models/Dashboard.php` (exclude expired products from low-stock
alerts/counts). Before this fix, a product whose only stock had expired
would incorrectly keep triggering low-stock reorder alerts instead of being
flagged for write-off — a real, currently-live consequence of the bug, not
just a cosmetic label issue. `app/dashboard/inventory_management/index.php`
and `.../ajax/get_product.php` also branch on `'Expired'` (the latter via
per-batch `batch_status`, which was already correct and unaffected).

**Fix** (`app/Models/Inventory.php`):

- `syncProductAggregate()` (SQL ~lines 819-849, branching ~lines 853-881):
  added a new aggregate column, `expired_nearest_expiration` — `MIN`
  of `expiration_date` scoped to `quantity > 0` batches whose
  `expiration_date < CURDATE()` (i.e. expired batches that still have
  physical stock on the shelf, as opposed to already-zeroed-out ones).
  `total_quantity` and the existing (filtered) `nearest_expiration` are
  untouched — usable-stock math is unchanged. The status logic now checks:
  if `total_quantity <= 0`, use `expired_nearest_expiration` to distinguish
  `'Expired'` (physical stock exists but it's all past-dated) from
  `'Out of Stock'` (truly nothing there); only falls through to the
  Low Stock/Available reorder-level check when `total_quantity > 0`. This
  also surfaces the actual expired date into `products.expiration_date`
  instead of leaving it `NULL`, so staff can see what expired.

- `getProducts()` `batch_totals` subquery (SQL ~lines 110-143) and the main
  `product_status` CASE (~lines 99-105): mirrored the same fix — added
  `expired_nearest_expiration_date` to the subquery (same filter as above),
  and restructured the CASE so `'Expired'` is only reached when
  `batch_quantity <= 0 AND expired_nearest_expiration_date IS NOT NULL`,
  checked *before* the plain `'Out of Stock'` arm. The displayed
  `expiration_date` column now falls back to the expired date via
  `COALESCE(nearest_expiration_date, expired_nearest_expiration_date)`
  when there's no usable batch, for consistency with the synced cache value.

Deliberately did **not** broaden the existing `nearest_expiration`/
`nearest_expiration_date` fields to drop the expiry filter outright (the
seemingly simplest fix): for a product with a *mix* of an expired batch and
a still-valid batch, that would let `MIN()` surface the expired batch's
(chronologically earlier) date as "the" nearest expiration even while
`total_quantity` is still positive from the valid batch — misclassifying a
product with perfectly usable stock as `'Expired'`. Using a separate signal
that's only consulted when `total_quantity <= 0` avoids that regression
entirely while still fixing the reported defect.

Updated the pinning test,
`InventoryTest::testExpiredOnlyStockIsMisreportedAsOutOfStockInsteadOfExpired`,
to add `'expired_nearest_expiration' => '2020-01-15'` to its mocked
aggregate-query response and to assert `:expiration_date` is surfaced as
that date. This was necessary, not cosmetic: PDO is fully mocked in this
suite, so the test's hardcoded `fetch()` return array — not the SQL text —
is what the code under test actually sees. The original mock reproduced
exactly what the *unfixed* query returned (`total_quantity => 0,
nearest_expiration => null`), which is information-theoretically
indistinguishable from "no batches exist at all" — no SQL-only fix could
make that exact mock evaluate to `'Expired'`. The updated mock instead
reflects what the *fixed* query legitimately returns for the scenario the
test describes (a batch with physical quantity whose expiration has
passed), which is what the test's own narrative was already asserting.

**Suite result**: `InventoryProducts` — 84 tests, 271 assertions, **84
passed** (was 83/84, 270 assertions; +1 assertion from the added
`:expiration_date` check on the pinning test). No other test's mocked
aggregate data included the new `expired_nearest_expiration` key, so every
other `total_quantity => 0` scenario correctly still resolves to
`'Out of Stock'` (verified by reading each one — none assert a specific
`product_status` value that this change could have flipped, except the two
that explicitly assert `'Out of Stock'`/`'Available'`, both unaffected).

**Also verified** (per dev-team task, not a required fix): confirmed
`app/Controllers/ProductController.php`, `app/Models/Product.php`, and
`app/Services/ImportService.php` are genuinely 0-byte with zero references
anywhere in the codebase (`grep` for `ProductController`, `ImportService`,
`Product::`, `class Product` outside the files themselves turned up nothing
except this report and `tests/TESTING.md`'s domain table). Test team's
finding stands.

<!-- repeat one DEFECT-N block per defect found. If a test fails but the
     failure turns out to be a mistake in the test itself, fix the test
     directly instead of filing a defect for it — only file defects for
     failures that trace back to production code. -->
