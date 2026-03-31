# Image Size Variant Usage Analysis

## Frontend Image Rendering

**Product Detail Page (`shop/show.blade.php`):**
```blade
<img src="{{ route('media.show', ['path' => $media->path]) }}" ...>
```

**Product Listing (`shop/index.blade.php`):**
```blade
// Uses $product->media->first()->path directly
```

**Cart/Checkout:**
```blade
<img src="{{ route('media.show', ['path' => $thumbnailPath]) }}" ...>
```

## Backend Image Serving

**`MediaController::show()`:**
- Takes the path from DB (`$media->path`)
- Does content negotiation (AVIF vs WebP based on Accept header)
- **DOES NOT** select different size variants
- Returns the exact file path from database

**`ShopController::resolveZoomPath()`:**
- Lines 110-114: **STRIPS** size suffixes (-thumbnail-200x200, -gallery-600x600, -hero-1200x600)
- Gets the BASE filename
- Returns full-size image for zoom

## Critical Finding

**NO responsive image tags:**
- No `<picture>` elements
- No `srcset` attributes
- No size-based URL parameters

**What happens:**
1. Database stores: `products/shirt/gallery/legacy-123-image.avif`
2. Frontend requests: `route('media.show', ['path' => 'products/shirt/gallery/legacy-123-image.avif'])`
3. MediaController serves: The full-size AVIF file
4. Browser: Resizes with CSS

**The thumbnail/gallery/hero variants are NEVER requested by any code.**

## Files Generated But Never Used

Per source image:
- ✅ `legacy-123-image.avif` (main) - **USED**
- ✅ `legacy-123-image.webp` (main) - **USED** (fallback)
- ❌ `legacy-123-image-thumbnail-200x200.avif` - **NEVER USED**
- ❌ `legacy-123-image-thumbnail-200x200.webp` - **NEVER USED**
- ❌ `legacy-123-image-gallery-600x600.avif` - **NEVER USED**
- ❌ `legacy-123-image-gallery-600x600.webp` - **NEVER USED**
- ❌ `legacy-123-image-hero-1200x600.avif` - **NEVER USED**
- ❌ `legacy-123-image-hero-1200x600.webp` - **NEVER USED**

**Wasted:** 6 out of 8 files per image = 75% waste

## Calculation

- Source images: 2,968
- Files generated: 2,968 × 8 = 23,744 files
- Files actually used: 2,968 × 2 = 5,936 files (main AVIF + WebP)
- **Wasted files: 17,808 files (75% of all generated files)**

## Why This Happens

`AssociateLegacyMedia::createImageVariants()` generates all size variants defined in `config/images.php`, but the frontend code was never implemented to use them.

The system generates responsive variants but serves only full-size images resized by CSS.
