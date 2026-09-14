USE pharmacy_pos;

CREATE VIEW vw_product_inventory AS

SELECT

p.product_id,

p.barcode,

p.product_name,

c.category_name,

pt.type_name,

s.supplier_name,

p.selling_price,

p.quantity,

p.expiration_date

FROM products p

LEFT JOIN categories c
ON p.category_id = c.category_id

LEFT JOIN product_types pt
ON p.product_type_id = pt.product_type_id

LEFT JOIN suppliers s
ON p.supplier_id = s.supplier_id;