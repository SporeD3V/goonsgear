-- Populate category_product pivot table from WordPress data

INSERT INTO category_product (product_id, category_id, position, created_at, updated_at)
SELECT DISTINCT
    ilp.product_id,
    ilc.category_id,
    0 as position,
    NOW() as created_at,
    NOW() as updated_at
FROM import_legacy_products ilp
CROSS JOIN (
    SELECT 
        object_id as wp_post_id,
        term_id as wp_term_id
    FROM LEGACYgoonsgearDB.wp_term_relationships wtr
    JOIN LEGACYgoonsgearDB.wp_term_taxonomy wtt ON wtt.term_taxonomy_id = wtr.term_taxonomy_id
    WHERE wtt.taxonomy = 'product_cat'
) wp_cats ON wp_cats.wp_post_id = ilp.legacy_wp_post_id
JOIN import_legacy_categories ilc ON ilc.legacy_term_id = wp_cats.wp_term_id
WHERE ilp.product_id IS NOT NULL
  AND ilc.category_id IS NOT NULL
ON DUPLICATE KEY UPDATE updated_at = NOW();

-- Populate product_tag pivot for artist categories mapped as tags

INSERT INTO product_tag (product_id, tag_id, created_at, updated_at)
SELECT DISTINCT
    ilp.product_id,
    ilt.tag_id,
    NOW() as created_at,
    NOW() as updated_at
FROM import_legacy_products ilp
CROSS JOIN (
    SELECT 
        object_id as wp_post_id,
        term_id as wp_term_id
    FROM LEGACYgoonsgearDB.wp_term_relationships wtr
    JOIN LEGACYgoonsgearDB.wp_term_taxonomy wtt ON wtt.term_taxonomy_id = wtr.term_taxonomy_id
    WHERE wtt.taxonomy = 'product_cat'
) wp_cats ON wp_cats.wp_post_id = ilp.legacy_wp_post_id
JOIN import_legacy_tags ilt ON ilt.legacy_term_id = wp_cats.wp_term_id
WHERE ilp.product_id IS NOT NULL
  AND ilt.tag_id IS NOT NULL
ON DUPLICATE KEY UPDATE updated_at = NOW();
