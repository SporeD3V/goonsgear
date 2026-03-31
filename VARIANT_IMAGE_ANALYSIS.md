# Product-Variant-Image Deep Analysis

**Date:** Mar 31, 2026  
**Purpose:** Understand image-variant relationships to ensure correct display on staging

---

## WordPress/WooCommerce Structure

### Product Types in Legacy DB
WooCommerce has several product types:
- **Simple products** - Single item, no variations
- **Variable products** - Parent product with variations (e.g., sizes, colors)
- **Product variations** - Child of variable product with specific attributes

### Variation Image Handling in WooCommerce

**Variation-Specific Images:**
```
wp_posts.post_type = 'product_variation'
wp_postmeta.meta_key = '_thumbnail_id'
wp_postmeta.meta_value = [attachment_id]
```

**Key Insight:** In WooCommerce:
- Some variations have their own `_thumbnail_id` (different image)
- Some variations inherit from parent product (just size/color differences)
- Variations with specific images: Used when visual representation differs (e.g., different colored shirt)
- Variations without images: Used for size-only variants (e.g., S/M/L of same product)

---

## Laravel System Structure

### Database Schema

**`product_media` table:**
```sql
- id
- product_id (FK) - Always present
- product_variant_id (FK, nullable) - Present if variant-specific
- disk ('public')
- path ('products/slug/gallery/image.avif')
- mime_type
- is_converted
- converted_to
- width, height
- is_primary
- position
```

**Relationship Logic:**
1. **Product-level media** (`product_variant_id = NULL`)
   - Shared across all variants
   - Used as fallback when variant has no specific image
   - Primary image always at product level

2. **Variant-specific media** (`product_variant_id = [variant_id]`)
   - Only shown when that variant is selected
   - Overrides product-level images
   - Used for variants with visual differences

### Model Relationships

**Product Model:**
```php
public function media(): HasMany
{
    return $this->hasMany(ProductMedia::class);
}

public function variants(): HasMany
{
    return $this->hasMany(ProductVariant::class);
}
```

**ProductVariant Model:**
```php
public function media(): HasMany
{
    return $this->hasMany(ProductMedia::class);
}
```

**ProductMedia Model:**
```php
public function product(): BelongsTo
{
    return $this->belongsTo(Product::class);
}

public function variant(): BelongsTo
{
    return $this->belongsTo(ProductVariant::class, 'product_variant_id');
}
```

---

## Import Logic Analysis

### `media:associate-legacy` Command Flow

**Step 1: Product-Level Images**
```php
// Line 115-136: Associate product gallery images
$productAttachmentIds = $this->legacyProductAttachmentIds($legacy, $legacyProductId);

foreach ($productAttachmentIds as $attachmentId) {
    $this->syncAttachmentForProduct(
        product: $product,
        attachmentId: $attachmentId,
        variantId: null,  // ← Product-level (shared)
        position: $position,
        isPrimary: $position === 0
    );
}
```

**Step 2: Variant-Specific Images**
```php
// Line 138-174: Check each variant for _thumbnail_id
$variantMappings = DB::table('import_legacy_variants')
    ->where('product_variants.product_id', $product->id)
    ->get();

foreach ($variantMappings as $variantMapping) {
    $variantThumbnail = $legacy->table('wp_postmeta')
        ->where('post_id', $variantMapping->legacy_wp_post_id)
        ->where('meta_key', '_thumbnail_id')
        ->value('meta_value');
    
    if ($variantThumbnailId > 0) {
        $this->syncAttachmentForProduct(
            product: $product,
            attachmentId: $variantThumbnailId,
            variantId: $variantMapping->product_variant_id,  // ← Variant-specific
            position: $position,
            isPrimary: false
        );
    }
}
```

**Key Methods:**
- `legacyProductAttachmentIds()` - Gets `_thumbnail_id` + `_product_image_gallery` from parent product
- `syncAttachmentForProduct()` - Converts and creates `product_media` record

---

## File System Structure

### Directory Layout
```
storage/app/public/products/{product-slug}/
├── fallback/
│   └── legacy-{wp_attachment_id}-{original-name}.jpg
└── gallery/
    ├── legacy-{wp_attachment_id}-{name}.avif
    ├── legacy-{wp_attachment_id}-{name}.webp
    ├── legacy-{wp_attachment_id}-{name}-thumbnail-200x200.avif
    ├── legacy-{wp_attachment_id}-{name}-thumbnail-200x200.webp
    ├── legacy-{wp_attachment_id}-{name}-gallery-600x600.avif
    ├── legacy-{wp_attachment_id}-{name}-gallery-600x600.webp
    ├── legacy-{wp_attachment_id}-{name}-hero-1200x600.avif
    └── legacy-{wp_attachment_id}-{name}-hero-1200x600.webp
```

### File Count Math
- **923 AVIF records in DB** (base images)
- **5,158 cropped files** (thumbnail + gallery + hero variants)
- **3,200 WebP files** (base + cropped)
- **~800 fallback originals**
- **Total: 7,202 files** ✓

**Explanation:** Each original image generates:
- 1 base AVIF
- 3 cropped sizes × 2 formats (AVIF + WebP) = 6 files
- 1 fallback original
- **= 8 files per original image**

---

## Frontend Display Logic

### Product Page Gallery (`shop/show.blade.php`)

**Variant Filter:**
```blade
@if ($product->variants->isNotEmpty() && $product->media->count() > 1)
    <select id="media-variant-filter" data-media-variant-filter>
        <option value="all">All variants</option>
        @foreach ($product->variants as $variant)
            <option value="{{ $variant->id }}">{{ $variant->name }}</option>
        @endforeach
    </select>
@endif
```

**Media Thumbnails with Variant Attribution:**
```blade
@foreach ($product->media as $media)
    <button
        data-media-thumb
        data-media-variant-id="{{ $media->product_variant_id ?? '' }}"
        ...
    >
        <img src="{{ route('media.show', ['path' => $media->path]) }}" ...>
    </button>
@endforeach
```

**JavaScript Behavior:**
- When variant selected: Show only media where `data-media-variant-id` matches OR is empty (product-level)
- "All variants" option: Show all media
- Product-level media (no variant_id): Always visible as fallback

---

## Current State Analysis

### Database Statistics
- **Total product_media records:** 923
- **Product-level media:** 923 (all current records)
- **Variant-specific media:** 0

### Issue Identified
**All 923 media records have `product_variant_id = NULL`**

This means:
1. ✅ Product-level images imported correctly
2. ❌ Variant-specific images NOT imported
3. ❌ WooCommerce variations with `_thumbnail_id` were not associated

---

## Root Cause Analysis

### Why Variant Images Weren't Imported

Looking at `AssociateLegacyMedia` command logic (lines 138-174):

**The command DOES attempt to import variant images**, but they may not exist because:

1. **Legacy DB access issue:** The script tries to query `legacy` connection
2. **Mapping missing:** Variants without `import_legacy_variants` mapping won't be checked
3. **WP variations without images:** Many WP variations don't have `_thumbnail_id` (size-only variants)

### Expected Behavior

**For a variable product with 3 variants:**

**Scenario A: Color variants (visual differences)**
```
Product: "Band T-Shirt"
Variants:
  - Black (has _thumbnail_id → variant-specific image)
  - White (has _thumbnail_id → variant-specific image)  
  - Gray (has _thumbnail_id → variant-specific image)

Expected product_media:
  - Product-level: 0 records (or 1 generic)
  - Variant-specific: 3 records (one per color)
```

**Scenario B: Size variants (no visual difference)**
```
Product: "Vinyl Record"
Variants:
  - Standard (no _thumbnail_id)
  - Deluxe (no _thumbnail_id)

Expected product_media:
  - Product-level: 3-5 records (gallery images)
  - Variant-specific: 0 records (all variants show same images)
```

---

## Frontend Implications

### Current Display Behavior

**With current data (all product-level):**
```blade
// Product page shows ALL media for ALL variants
// No filtering happens because no media has product_variant_id
```

**Example Product Page:**
- User selects "Black T-Shirt" variant → Shows all 5 gallery images
- User selects "White T-Shirt" variant → Shows same 5 gallery images
- **Problem:** If variants have different images, they all appear mixed together

### Correct Display Behavior

**With proper variant-specific media:**
```blade
// Variant "Black T-Shirt" selected
→ Show: Product-level media + Black variant media

// Variant "White T-Shirt" selected  
→ Show: Product-level media + White variant media

// "All variants" selected
→ Show: All media (product-level + all variant-specific)
```

---

## Action Items for Correct Image Display

### 1. Verify Legacy DB Variant Image Count

**Check how many WP variations actually have images:**
```sql
SELECT COUNT(*) 
FROM wp_postmeta pm
JOIN wp_posts p ON p.ID = pm.post_id
WHERE pm.meta_key = '_thumbnail_id'
  AND p.post_type = 'product_variation'
  AND p.post_status = 'publish'
```

### 2. Re-run Media Association with Variant Images

**Command:**
```bash
php artisan media:associate-legacy --dry-run
```

**Check output for:**
- "Missing legacy sources" count
- Whether variant images are being processed
- Any errors accessing legacy DB

### 3. Verify Import Mappings Exist

**Ensure all WP variations are mapped:**
```php
// Check import_legacy_variants table
$mappedVariants = DB::table('import_legacy_variants')->count();
$totalVariants = ProductVariant::count();
// Should match or be close
```

### 4. Manual Test Case

**Find a product with color/style variants:**
1. Check WP DB: Does variation have `_thumbnail_id`?
2. Check Laravel: Is there a `product_media` record with that `product_variant_id`?
3. Frontend: Does variant selector filter gallery correctly?

---

## Recommendations

### Immediate Actions

1. **Test legacy DB connection** in media:associate-legacy command
2. **Check variant mapping table** completeness  
3. **Identify sample products** with visual variant differences (colors, designs)
4. **Re-run association** for those products with `--clear-existing`

### Expected Outcomes

**After proper variant image association:**
- `product_media` table: Mix of NULL and specific `product_variant_id` values
- Frontend: Variant selector filters gallery images correctly
- Only relevant images show for each variant selection

### Data Validation Query

```php
// Products with multiple variants and their media distribution
$analysis = Product::withCount(['variants', 'media'])
    ->having('variants_count', '>', 1)
    ->get()
    ->map(function($p) {
        return [
            'product' => $p->name,
            'variants' => $p->variants_count,
            'total_media' => $p->media_count,
            'product_level' => ProductMedia::where('product_id', $p->id)
                ->whereNull('product_variant_id')->count(),
            'variant_specific' => ProductMedia::where('product_id', $p->id)
                ->whereNotNull('product_variant_id')->count(),
        ];
    });
```

---

## Summary

### System Design (Correct)
✅ Database schema supports variant-specific images  
✅ Import command has logic for variant images  
✅ Frontend has variant filtering UI  
✅ File structure accommodates multiple images per product  

### Current State (Needs Attention)
❌ All media records are product-level (no variant specificity)  
❌ Variant-specific WP images may not have been imported  
⚠️ Legacy DB connection needs verification  

### Next Steps
1. Verify WP variant image count in legacy DB
2. Check import mapping completeness
3. Re-run media:associate-legacy with monitoring
4. Test variant image filtering on frontend
5. Document which product types need variant-specific vs shared images
