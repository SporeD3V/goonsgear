SELECT v.name, v.variant_type 
FROM products p 
JOIN product_variants v ON v.product_id = p.id 
WHERE p.slug = 'onyx-all-white-madface-shirt' 
ORDER BY v.position 
LIMIT 12;
