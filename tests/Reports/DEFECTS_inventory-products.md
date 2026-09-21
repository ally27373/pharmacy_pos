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

<!-- repeat one DEFECT-N block per defect found. If a test fails but the
     failure turns out to be a mistake in the test itself, fix the test
     directly instead of filing a defect for it — only file defects for
     failures that trace back to production code. -->
