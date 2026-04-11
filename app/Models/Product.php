<?php

namespace App\Models;

use App\Concerns\GeneratesSlug;
use App\Concerns\HasEditHistory;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Product extends Model
{
    use GeneratesSlug;
    use HasEditHistory;

    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    private const ALLOWED_RICH_TEXT_TAGS = '<p><br><strong><em><ul><ol><li>';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'primary_category_id',
        'name',
        'slug',
        'status',
        'excerpt',
        'description',
        'meta_title',
        'meta_description',
        'is_featured',
        'is_preorder',
        'is_bundle_exclusive',
        'published_at',
        'preorder_available_from',
        'expected_ship_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_featured' => 'boolean',
            'is_preorder' => 'boolean',
            'is_bundle_exclusive' => 'boolean',
            'published_at' => 'datetime',
            'preorder_available_from' => 'datetime',
            'expected_ship_at' => 'datetime',
        ];
    }

    public function primaryCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'primary_category_id');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class)
            ->withPivot('position')
            ->withTimestamps();
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class)
            ->withTimestamps();
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function media(): HasMany
    {
        return $this->hasMany(ProductMedia::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function bundleDiscount(): HasOne
    {
        return $this->hasOne(BundleDiscount::class);
    }

    public function stockAlertSubscriptions()
    {
        return $this->hasManyThrough(
            StockAlertSubscription::class,
            ProductVariant::class,
            'product_id',
            'product_variant_id'
        );
    }

    public function formattedExcerpt(): string
    {
        return $this->sanitizeRichText($this->excerpt);
    }

    public function formattedDescription(): string
    {
        return $this->sanitizeRichText($this->description);
    }

    public function plainExcerpt(int $limit = 160): string
    {
        return Str::of(strip_tags((string) $this->excerpt))
            ->squish()
            ->limit($limit)
            ->toString();
    }

    private function sanitizeRichText(?string $value): string
    {
        $sanitized = preg_replace('#<(script|style)\b[^>]*>.*?</\1>#is', '', (string) $value);

        return trim(strip_tags($sanitized ?? '', self::ALLOWED_RICH_TEXT_TAGS));
    }
}
