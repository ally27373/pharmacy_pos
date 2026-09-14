/*
====================================================
PHARMACY POS SYSTEM
functions.sql
Reusable SQL Functions
====================================================
*/

USE pharmacy_pos;

DELIMITER $$

/*
====================================================
FUNCTION 1
Calculate VAT
====================================================
*/

CREATE FUNCTION fn_calculate_vat(
    amount DECIMAL(10,2)
)
RETURNS DECIMAL(10,2)
DETERMINISTIC

BEGIN

    RETURN amount * 0.12;

END$$

/*
====================================================
FUNCTION 2
Calculate Total with VAT
====================================================
*/

CREATE FUNCTION fn_total_with_vat(
    amount DECIMAL(10,2)
)
RETURNS DECIMAL(10,2)
DETERMINISTIC

BEGIN

    RETURN amount + (amount * 0.12);

END$$

/*
====================================================
FUNCTION 3
Check Low Stock
====================================================
*/

CREATE FUNCTION fn_is_low_stock(
    stock INT,
    minimum_stock INT
)
RETURNS BOOLEAN
DETERMINISTIC

BEGIN

    RETURN stock <= minimum_stock;

END$$

/*
====================================================
FUNCTION 4
Days Before Expiration
====================================================
*/

CREATE FUNCTION fn_days_before_expiration(
    expirationDate DATE
)
RETURNS INT
DETERMINISTIC

BEGIN

    RETURN DATEDIFF(expirationDate, CURDATE());

END$$

/*
====================================================
FUNCTION 5
Medicine Expired?
====================================================
*/

CREATE FUNCTION fn_is_expired(
    expirationDate DATE
)
RETURNS BOOLEAN
DETERMINISTIC

BEGIN

    RETURN expirationDate < CURDATE();

END$$

/*
====================================================
FUNCTION 6
Generate Today's Date String
Example:
20260628
====================================================
*/

CREATE FUNCTION fn_today()
RETURNS VARCHAR(20)
DETERMINISTIC

BEGIN

    RETURN DATE_FORMAT(CURDATE(), '%Y%m%d');

END$$

DELIMITER ;