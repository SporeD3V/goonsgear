# Image Optimization System - Size Variants

## Overview

This system extends the existing image conversion (WebP/AVIF) to also generate **responsive size variants**. Rather than serving high-resolution images to all devices, we create multiple sizes and serve the appropriate one - resulting in faster load times and less bandwidth.

## Storage Structure

```
storage/app/public/products/{product-slug}/
├── fallback/
│   └── {base-filename}.{jpg|png}          # Original upload (archived for reference)
└── gallery/
    ├── {base-filename}.webp                # Main converted image (WebP)
    ├── {base-filename}.avif                # Alternate format (AVIF, if supported)
    ├── {base-filename}-thumbnail-200x200.webp    # Mobile thumbnail
    ├── {base-filename}-gallery-600x600.webp      # Product detail
    └── {base-filename}-hero-1200x600.webp        # Hero banner
```

## Configuration

**File**: `config/images.php`

This defines all responsive size variants. When your theme changes, **only update this file**:

```php
'sizes' => [
    'thumbnail' => ['width' => 200, 'height' => 200],     // Cart,search
    'gallery' => ['width' => 600, 'height' => 600],        // Detail view
    'hero' => ['width' => 1200, 'height' => 600],          // Banners
],

'responsive' => [
    'mobile' => 480,              // Mobile breakpoint
    'tablet' => 768,              // Tablet breakpoint  
    'desktop' => 1200,            // Desktop breakpoint
],
```

### Why Multiple Sizes?

A single 1200x1200px image might be 800KB. With size variants:
- **Mobile (200x200)**: ~50KB - serves to phones
- **Tablet (600x600)**: ~150KB - serves to tablets
- **Desktop (1200x1200)**: ~800KB - serves to desktops

Result: 90% smaller downloads on mobile, 80% smaller on tablets. ✓ **Much faster load times**

## How It Works

### 1. Image Upload

When a product image is uploaded:

1. **Upload** → Stored in `/fallback/` as archive
2. **Convert Format** → `convertImageToFormat()` creates WebP (or AVIF if available)
3. **Create Variants** → `createImageVariants()` creates all size variants as WebP:
   - `product-123-thumbnail-200x200.webp`
   - `product-123-gallery-600x600.webp`
   - `product-123-hero-1200x600.webp`
4. **Store Reference** → `product_media` table tracks the main converted image
5. **Variants** → Stored alongside, available on-demand

### 2. Browser Request

When a browser requests an image:

1. **Accept Header** → Browser sends `Accept: image/avif,image/webp,image/png`
2. **Format Negotiation** → MediaController serves best format:
   - AVIF (if browser supports AND file exists)
   - WebP (if browser supports AND file exists)  
   - Original (fallback)
3. **Cache Headers** → 1-year immutable cache (safe due to filename timestamps)

### 3. Responsive Serving (Frontend)

In your Blade templates, use different size URLs for different contexts:

```blade
{{-- Small images for mobile/search results --}}
<img src="{{ route('media.show', ['path' => $product->media->first()->path]) }}?size=thumbnail"
     alt="Product">

{{-- Medium images for product gallery --}}
<img src="{{ route('media.show', ['path' => $product->media->first()->path]) }}?size=gallery"
     alt="Product">

{{-- Full resolution for hero banners --}}
<img src="{{ route('media.show', ['path' => $product->media->first()->path]) }}?size=hero"
     alt="Hero">
```

**Wait - how does MediaController know about size variants?**

Currently it doesn't - the size naming convention is just for you to reference. To make it automatic, you'd need to:
1. Pass size as query parameter: `/media/path?size=thumbnail`
2. MediaController resolves to: `{base}-thumbnail-200x200.webp`

## Implementation Notes

### Size Variants in ProductController

**Method**: `createImageVariants()`

- Called after main image conversion succeeds
- Reads config sizes from `config/images.php`
- For each variant, creates center-cropped WebP variant
- Gracefully skips if GD not available

**Center-Crop Logic**:
```
ratio = max(variant_width / src_width, variant_height / src_height)
Calculate temporary larger image at that ratio
Crop center portion to exact variant size
Preserve transparency with PNG support
```

### Database

**No new tables needed.** The `product_media` table tracks:
- `path` → Main image (WebP or AVIF)
- `is_converted` → Whether converted from original
- `converted_to` → Format name (webp/avif)
- `width`, `height` → Original image dimensions

Size variants are stored by naming convention, not tracked in DB.

### Storage Folder Strategy

Why keep original in `/fallback/`?

1. **Safety** - Can reprocess/regenerate variants if needed
2. **Quality** - Always regenerate from original, never downsampled variants
3. **Cleanup** - `php artisan media:fallback clean` removes after verification
4. **Audit** - Track what was originally uploaded

## Redacting for Theme Changes

When your theme redesign requires different breakpoints:

### Before (Old Theme)

```php
'sizes' => [
    'thumbnail' => ['width' => 200, 'height' => 200],
    'gallery' => ['width' => 600, 'height' => 600],
],
```

### After (New Theme)

```php
'sizes' => [
    'thumbnail' => ['width' => 150, 'height' => 150],      // Smaller now
    'gallery' => ['width' => 800, 'height' => 800],        // Larger now
    'preview' => ['width' => 400, 'height' => 400],        // NEW size
],
```

**That's it!** New uploads automatically use new sizes. Old images keep working (even if slightly wrong size) until you're ready to regenerate.

## Admin Tools

### Fallback Media Management

```bash
# List all original uploads
php artisan media:fallback list

# Verify conversion worked
php artisan media:fallback list | grep "has_optimized"

# Clean up originals after verification (safe)
php artisan media:fallback clean --dry-run    # Preview
php artisan media:fallback clean              # Execute
```

## Performance Impact

### Bandwidth Savings

| Device | Original (1200px) | With Variants | Savings |
|--------|------------------|---------------|---------|
| Mobile | 800KB | 50KB | **93%** ⬇️ |
| Tablet | 800KB | 150KB | **81%** ⬇️ |
| Desktop | 800KB | 800KB | 0% (matches) |

### Page Load Speed

- Faster initial render (smaller image downloads)
- Better browser caching (resized variants cached separately)
- Reduced network congestion for mobile users
- Better Core Web Vitals scores (LCP improvement)

## Troubleshooting

### Variants Not Created

1. Check if GD extension loaded:
   ```bash
   php -r "echo (extension_loaded('gd') ? 'GD: YES' : 'GD: NO');"
   ```

2. Check logs: `storage/logs/laravel.log`

3. Verify config: `config/images.php` has sizes defined

### AVIF Still Not Converting

1. Check PHP version (AVIF requires PHP 8.1+)
2. Check GD compiled with libavif: `php -i | grep AVIF`
3. If not available, fall back to WebP (still good compression)

### Images Not Using Variants

1. Check variant files exist in `/gallery/` directory
2. Verify naming: `{base}-{variant-name}-{width}x{height}.webp`
3. Check MediaController receives correct paths

## Future: Automatic Size Selection

To make size selection automatic via MediaController:

1. Add size query param to image URLs
2. MediaController's `resolveBestImagePathForClient()` checks for variant 
3. Browser `srcset` attribute picks best variant for device

Example:

```blade
<picture>
    <source media="(max-width: 640px)" srcset="{{ route('media.show', ['path' => $path]) }}?size=thumbnail">
    <source media="(max-width: 1024px)" srcset="{{ route('media.show', ['path' => $path]) }}?size=gallery">
    <img src="{{ route('media.show', ['path' => $path]) }}" alt="...">
</picture>
```

Then enhance MediaController to resolve `?size=thumbnail` → `-thumbnail-200x200.webp`

## Summary

✓ **Backward Compatible** - Existing system continues working  
✓ **Easy to Redact** - Single config file for theme changes  
✓ **Fast Loading** - Multiple sizes = appropriate bandwidth per device  
✓ **Simple Storage** - Naming convention, no complex DB structure  
✓ **Graceful Failure** - Works fine even if GD/ImageMagick not available  
