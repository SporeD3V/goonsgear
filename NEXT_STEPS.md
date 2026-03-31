# Next Steps - GoonsGear Migration

## Status: Deployment in Progress

**Commit:** `454b94b` - Optimize media import to skip re-converting existing files

---

## After Deployment Completes

### 1. Run Media Import (5-10 min)

```bash
ssh spored3v@91.98.230.33 -p 1221
cd /home/macaw-goonsgear/htdocs/goonsgear.macaw.studio
php artisan media:associate-legacy --no-interaction
```

**Progress:** 936 / 2,968 images (31.5% complete)

**What it will do:**
- Reuse existing 12,175 AVIF/WebP files (no re-conversion)
- Create DB records for remaining ~2,000 images
- Associate images with products and variants

**Monitor:**
```bash
# Check progress
watch -n 5 'mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB -e "SELECT COUNT(*) FROM product_media;"'
```

---

### 2. Verify Shop Images Display

**Test URLs:**
- Shop listing: https://goonsgear.macaw.studio/shop
- Product: https://goonsgear.macaw.studio/shop/snowgoons-soft-patch-hoodie
- Categories: https://goonsgear.macaw.studio/shop?category=germanhiphop

**Check:**
- ✅ Products show images
- ✅ Category filters work (German Hip Hop shows 82 products)
- ✅ Variant images display correctly
- ✅ No 404 errors in browser console

---

### 3. Final Verification Checklist

**Data Integrity:**
- [ ] No merged products (check products with multiple WP IDs)
- [ ] Categories populated (1,931 relationships)
- [ ] All products have images
- [ ] Variant-specific images work

**Test Products:**
- [ ] Snowgoons Goon Bap Tape - correct images only
- [ ] Cap Hat Washer - correct images only
- [ ] Onyx Keychain - no duplicate images
- [ ] Products with color variants - variant images filter correctly

**Performance:**
- [ ] Images load quickly (AVIF format)
- [ ] Responsive variants available (thumbnail/gallery/hero)
- [ ] No missing image placeholders

---

## Complete Re-Import Results

**Products:** 1,002 ✓
**Variants:** 2,108 ✓
**Categories:** 20 ✓
**Category Relationships:** 1,931 ✓
**Media Records:** 936 / 2,968 (in progress)

**Fixes Applied:**
1. ✅ Product merging fixed (removed name matching)
2. ✅ Category pivot populated
3. ✅ Category query fixed (uses categories() relationship)
4. ✅ Variant filter hidden on simple products
5. ✅ Media import optimized (skip existing files)

---

## Responsive Images - Next Phase

**Current:** Basic `<img>` tags with CSS resizing

**Plan:** Implement responsive image markup using size variants

### Example Implementation

```blade
<picture>
  <source 
    media="(max-width: 640px)"
    srcset="{{ route('media.show', ['path' => $thumbnailPath]) }}"
  >
  <source 
    media="(max-width: 1024px)"
    srcset="{{ route('media.show', ['path' => $galleryPath]) }}"
  >
  <img 
    src="{{ route('media.show', ['path' => $mainPath]) }}"
    alt="{{ $product->name }}"
  >
</picture>
```

### Benefits
- Reduce page size by 60-75% on mobile
- Faster load times
- Better Core Web Vitals scores
- All variant files already generated

---

## Command Reference

```bash
# Check media import progress
mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB \
  -e "SELECT COUNT(*) FROM product_media;"

# Check category relationships
mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB \
  -e "SELECT COUNT(*) FROM category_product;"

# Sync categories manually
php artisan products:sync-categories

# Check for products with wrong categories
mysql -u goonsgearUSER -pTPCFRLvc96ufAdYd5Quy goonsgearDB \
  -e "SELECT p.id, p.name, COUNT(cp.category_id) as cats 
      FROM products p 
      LEFT JOIN category_product cp ON cp.product_id = p.id 
      GROUP BY p.id 
      HAVING cats = 0;"
```
