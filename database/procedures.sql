USE pharmacy_pos;

DELIMITER $$

CREATE PROCEDURE GetProductByBarcode
(
    IN barcodeInput VARCHAR(100)
)

BEGIN

SELECT *

FROM products

WHERE barcode = barcodeInput;

END$$

DELIMITER ;