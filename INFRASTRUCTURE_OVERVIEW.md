# GoonsGear Infrastructure Overview

**Generated:** Mar 31, 2026  
**Environment:** Staging Server (goonsgear.macaw.studio)

---

## Database Infrastructure

### Main Database: `goonsgearDB`
- **Tables:** 38 total
- **Key Tables:**
  - `products` (imported from legacy)
  - `product_variants` 
  - `product_media` - **923 records** (all AVIF converted)
  - `categories`, `tags`, `orders`, `order_items`
  - `users` (customers)
  - Import tracking: `import_legacy_products`, `import_legacy_variants`

### Legacy Database: `LEGACYgoonsgearDB`
- **Type:** WordPress/WooCommerce database
- **Connection:** Configured in `config/database.php` as `'legacy'`
- **Purpose:** Source for import operations
- **Import Mapping:**
  - **1,011** products mapped
  - **2,133** variants mapped

---

## Media Storage System

### File Storage Breakdown

#### Laravel Public Storage (`storage/app/public/products/`)
- **Total Files:** 7,202 media files
- **AVIF Files:** 3,202 (44%)
- **WebP Files:** 3,200 (44%)
- **Structure:** `/products/{product-slug}/fallback/` and `/gallery/`

**Sample Product Directory Structure:**
```
storage/app/public/products/
├── absztrakkt-bodhiguard-cd-shirt-box/
│   ├── fallback/
│   │   └── original-image.jpg
│   └── gallery/
│       ├── image-gallery-600x600.avif
│       ├── image-gallery-600x600.webp
│       ├── image-thumbnail-200x200.avif
│       └── image-hero-1200x600.avif
```

#### Legacy WordPress Media (`storage/app/legacy-uploads/`)
- **Location:** `storage/app/legacy-uploads/uploads_extracted/`
- **Purpose:** Original WordPress/WooCommerce media from client's old site
- **Usage:** Source for import and conversion operations

### Database Media Records
**`product_media` table:**
- **Total Records:** 923
- **AVIF Files:** 923 (100%)
- **WebP Files:** 0
- **Converted:** 923 (100% - `is_converted = true`)
- **Schema:**
  ```
  - product_id (FK)
  - product_variant_id (FK, nullable)
  - disk ('public')
  - path (e.g., 'products/slug/gallery/image.avif')
  - mime_type ('image/avif', 'image/webp', etc.)
  - is_converted (boolean)
  - converted_to ('avif', 'webp')
  - width, height
  - is_primary, position
  ```

---

## AVIF Conversion System

### Image Processing Stack

**PHP Extensions Available:**
- ✅ **GD** - Basic image manipulation
- ✅ **Imagick** - Advanced format support including AVIF

**System Utilities:**
- ❌ `convert` (ImageMagick CLI) - Not available
- ❌ `avifenc` - Not available
- ✅ PHP `imageavif()` function (via GD)
- ✅ Imagick AVIF support

### Conversion Configuration

**Image Sizes** (`config/images.php`):
```php
'sizes' => [
    'thumbnail' => ['width' => 200, 'height' => 200],  // Cart, search
    'gallery' => ['width' => 600, 'height' => 600],    // Product detail
    'hero' => ['width' => 1200, 'height' => 600],      // Homepage
]
```

**Responsive Breakpoints:**
- Mobile: 480px (max-width: 640px)
- Tablet: 768px (max-width: 1024px)
- Desktop: 1200px (min-width: 1025px)

### Conversion Controllers

#### `FallbackMediaController.php`
**Purpose:** Manage original fallback images after optimization
**Features:**
- Lists all fallback images with optimization status
- Tracks which products use AVIF vs WebP vs fallback
- Cleanup operations for verified optimizations
- Conversion methods: `convertImageToFormat()`
  - Supports WebP and AVIF output
  - Uses GD `imageavif()` or Imagick fallback
  - Quality: 62 for AVIF

#### `ProductController.php` (Admin)
**Features:**
- On-the-fly image conversion during product creation
- Generates AVIF and WebP variants
- Creates multiple size variants (thumbnail, gallery, hero)

---

## Artisan Commands

### Import & Media Commands

#### `import:legacy-data`
**Purpose:** Import from WordPress/WooCommerce legacy database  
**Imports:**
- Categories
- Tags  
- Products (simple)
- Product variants
- Customers/users
- Orders and order items

**Tracking Tables:**
- `import_legacy_products` (1,011 records)
- `import_legacy_variants` (2,133 records)

#### `media:associate-legacy`
**Purpose:** Associate legacy WordPress attachments to imported products  
**Options:**
- `--dry-run` - Preview without writing
- `--limit=N` - Process only N products
- `--product=ID` - Process specific product
- `--legacy-root=PATH` - Path to legacy uploads (default: `storage/app/legacy-uploads/uploads_extracted`)
- `--clear-existing` - Remove existing media before associating

**Workflow:**
1. Reads `import_legacy_products` mapping
2. Finds legacy WP attachments in legacy DB
3. Locates files in `legacy-uploads/`
4. Converts to AVIF/WebP
5. Creates `product_media` records

#### `media:fallback`
**Purpose:** List or clean original fallback images after optimization  
**Features:**
- Shows which products still use fallback (non-optimized) images
- Filters by optimization status, product state
- Safe cleanup after AVIF/WebP verification

#### `media:sync-from-storage`
**Purpose:** Sync `product_media` records from files in `storage/app/public/products`  
**Use Case:** When files exist but database records are missing

---

## Current State Analysis

### ✅ What's Working
- Laravel app connected to both main and legacy databases
- 1,011 products imported with mapping preserved
- 2,133 variants imported
- Media conversion system operational (923 AVIF files generated)
- PHP image extensions available (GD + Imagick)
- Storage structure organized by product slug

### 📊 Current Stats
- **Database Records:** 923 product_media entries (all AVIF)
- **File System:** 7,202 total files (3,202 AVIF + 3,200 WebP + ~800 fallbacks)
- **Conversion Rate:** 100% of database records marked as converted

### ⚠️ Observations
1. **Database vs Filesystem Mismatch:**
   - Database: 923 records
   - Filesystem: 7,202 files
   - **Gap:** ~6,300 files not tracked in database
   - Suggests: Batch conversion created files but didn't all register in DB

2. **WebP Files Present but Not Tracked:**
   - 3,200 WebP files on disk
   - 0 WebP records in `product_media` table
   - All 923 DB records are AVIF only

3. **Legacy DB Connection:**
   - Configured but needs verification for attachment queries
   - Original WordPress media preserved in `legacy-uploads/`

---

## Media Workflow Summary

### Import Process Flow
```
1. WordPress/WooCommerce DB (legacy)
   ↓
2. Import Products/Variants (import:legacy-data)
   ↓ (creates mapping in import_legacy_* tables)
3. Extract WP Media to legacy-uploads/
   ↓
4. Associate Media (media:associate-legacy)
   ↓ (reads legacy DB wp_posts attachments)
5. Convert to AVIF/WebP
   ↓
6. Store in storage/app/public/products/{slug}/
   ↓
7. Create product_media records
```

### File Naming Convention
```
products/{product-slug}/
  ├── fallback/
  │   └── {original-name}.{jpg|png}
  └── gallery/
      ├── {base-name}-{size}-{width}x{height}.avif
      └── {base-name}-{size}-{width}x{height}.webp
```

**Example:**
```
products/cunninlynguist-oneirology-vinyl/
  ├── fallback/cover.jpg
  └── gallery/
      ├── cover-thumbnail-200x200.avif
      ├── cover-thumbnail-200x200.webp
      ├── cover-gallery-600x600.avif
      └── cover-hero-1200x600.avif
```

---

## Technology Stack

### Conversion Methods
1. **Primary:** PHP GD `imageavif()` - quality 62
2. **Fallback:** Imagick AVIF - quality 62
3. **WebP:** `imagewebp()` - standard quality

### Storage
- **Disk:** `public` (Laravel filesystem)
- **Path:** `storage/app/public/products/`
- **Symlink:** `public/storage` → `storage/app/public`

---

## Ready for Next Steps

All infrastructure components are in place and operational:
- ✅ Both databases accessible
- ✅ Legacy WordPress media preserved
- ✅ Conversion system functional
- ✅ Import commands available
- ✅ Storage organized and ready

**Awaiting user instructions on:**
- Further media processing requirements
- Database sync needs
- Additional conversions or imports
- Cleanup operations
