<?php

namespace App\Models;

use Database\Factories\ProductMediaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductMedia extends Model
{
    /** @use HasFactory<ProductMediaFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (ProductMedia $media): void {
            if (empty($media->alt_text) && $media->product_id) {
                $productName = Product::where('id', $media->product_id)->value('name');

                if ($productName) {
                    $media->alt_text = $productName;
                }
            }
        });
    }

    /**
     * @var list<string>
     */
    protected $fillable = [
        'product_id',
        'product_variant_id',
        'disk',
        'path',
        'mime_type',
        'is_converted',
        'converted_to',
        'thumbnail_path',
        'gallery_path',
        'zoom_path',
        'width',
        'height',
        'alt_text',
        'is_primary',
        'position',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'is_converted' => 'boolean',
        ];
    }

    public function getThumbnailPath(): string
    {
        if ($this->thumbnail_path !== null && $this->thumbnail_path !== '') {
            return $this->thumbnail_path;
        }

        return $this->computeVariantPath('thumbnail');
    }

    public function getGalleryPath(): string
    {
        if ($this->gallery_path !== null && $this->gallery_path !== '') {
            return $this->gallery_path;
        }

        return $this->computeVariantPath('gallery');
    }

    public function getZoomPath(): string
    {
        if ($this->zoom_path !== null && $this->zoom_path !== '') {
            return $this->zoom_path;
        }

        return $this->path;
    }

    public function computeVariantPath(string $variant): string
    {
        $pathInfo = pathinfo($this->path);
        $dirname = $pathInfo['dirname'];
        $filename = $pathInfo['filename'];
        $extension = $pathInfo['extension'];

        foreach (['-thumbnail-200x200', '-hero-1200x600', '-gallery-600x600'] as $suffix) {
            if (str_ends_with($filename, $suffix)) {
                $filename = substr($filename, 0, -strlen($suffix));
                break;
            }
        }

        $variantSuffix = match ($variant) {
            'thumbnail' => '-thumbnail-200x200',
            'gallery' => '-gallery-600x600',
            'hero' => '-hero-1200x600',
            default => '',
        };

        return $dirname.'/'.$filename.$variantSuffix.'.'.$extension;
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
