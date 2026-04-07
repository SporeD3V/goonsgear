<?php

namespace App\Concerns;

use App\Models\ProductMedia;
use App\Models\ProductVariant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

trait ResolvesProductDisplay
{
    /**
     * @return array{
     *   groups: array<string, array{label: string, values: array<int, string>}>,
     *   variantAttributesById: array<int, array<string, string>>,
     *   attributeOrder: array<int, string>
     * }
     */
    private function buildVariantSelectorData(Collection $variants, string $productName = ''): array
    {
        $rawVariantAttributes = [];
        $groupValues = [];

        foreach ($variants as $variant) {
            $attributes = $this->extractVariantAttributes($variant, $productName);
            $rawVariantAttributes[$variant->id] = $attributes;

            foreach ($attributes as $key => $value) {
                $canonicalKey = $this->canonicalAttributeKey($key);

                if (! isset($groupValues[$canonicalKey])) {
                    $groupValues[$canonicalKey] = [];
                }

                if ($value !== '' && ! in_array($value, $groupValues[$canonicalKey], true)) {
                    $groupValues[$canonicalKey][] = $value;
                }
            }
        }

        $attributeKeys = collect($groupValues)
            ->filter(fn (array $values) => count($values) > 1)
            ->keys()
            ->sortBy(fn (string $key) => match ($key) {
                'size' => '00-size',
                'color' => '01-color',
                default => '10-'.$key,
            })
            ->values()
            ->all();

        $groups = [];
        foreach ($attributeKeys as $key) {
            $values = $groupValues[$key] ?? [];

            if ($key === 'size') {
                $values = $this->sortSizes($values);
            } else {
                natcasesort($values);
                $values = array_values($values);
            }

            $groups[$key] = [
                'label' => $this->attributeLabelFromKey($key),
                'values' => $values,
            ];
        }

        $variantAttributesById = [];
        foreach ($rawVariantAttributes as $variantId => $attributes) {
            $normalizedAttributes = [];

            foreach ($attributes as $attributeKey => $attributeValue) {
                $canonicalKey = $this->canonicalAttributeKey($attributeKey);

                if (! in_array($canonicalKey, $attributeKeys, true)) {
                    continue;
                }

                $value = trim($attributeValue);
                if ($value === '' || isset($normalizedAttributes[$canonicalKey])) {
                    continue;
                }

                $normalizedAttributes[$canonicalKey] = $value;
            }

            $variantAttributesById[$variantId] = $normalizedAttributes;
        }

        return [
            'groups' => $groups,
            'variantAttributesById' => $variantAttributesById,
            'attributeOrder' => $attributeKeys,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function extractVariantAttributes($variant, string $productName = ''): array
    {
        $attributes = [];

        if (is_array($variant->option_values) && $variant->option_values !== []) {
            foreach ($variant->option_values as $rawKey => $rawValue) {
                if (! is_scalar($rawValue)) {
                    continue;
                }

                $value = trim((string) $rawValue);
                if ($value === '') {
                    continue;
                }

                $key = $this->normalizeAttributeKey((string) $rawKey, $value);
                $attributes[$key] = $value;
            }

            if ($attributes !== []) {
                return $attributes;
            }
        }

        $rawName = trim((string) $variant->name);
        if ($rawName === '' || strcasecmp($rawName, 'Default') === 0) {
            return [];
        }

        $explicitVariantType = strtolower((string) ($variant->variant_type ?? ''));
        if (in_array($explicitVariantType, ['size', 'color'], true)) {
            $typedValue = $rawName;

            if ($productName !== '') {
                $escapedProductName = preg_quote(trim($productName), '/');
                $typedValue = preg_replace('/^'.$escapedProductName.'\s*[\-|\|,\/]\s*/i', '', $typedValue) ?? $typedValue;
            }

            $typedValue = trim($typedValue);
            if ($typedValue !== '') {
                if (ProductVariant::detectTypeFromName($typedValue) === $explicitVariantType) {
                    return [$explicitVariantType => $typedValue];
                }
            }
        }

        $nameForSplit = $rawName;
        if ($productName !== '') {
            $escapedProductName = preg_quote(trim($productName), '/');
            $stripped = preg_replace('/^'.$escapedProductName.'\s*[\-|\|,\/]\s*/i', '', $rawName);
            if ($stripped !== null && $stripped !== $rawName) {
                $nameForSplit = $stripped;
            }
        }

        $parts = preg_split('/\s*[\|,\/-]\s*/', $nameForSplit) ?: [];
        $parts = array_values(array_filter(array_map(fn (string $part) => trim($part), $parts), fn (string $part) => $part !== ''));

        $parts = $this->stripProductNameLeadingParts($parts, $productName);

        if ($parts === []) {
            return [];
        }

        foreach ($parts as $index => $value) {
            $baseKey = $this->classifyAttributeKey($value, (string) ($variant->variant_type ?? ''), $index);
            $key = $baseKey;
            $suffix = 2;

            while (array_key_exists($key, $attributes)) {
                $key = $baseKey.'_'.$suffix;
                $suffix++;
            }

            $attributes[$key] = $value;
        }

        return $attributes;
    }

    /**
     * @param  array<int, string>  $parts
     * @return array<int, string>
     */
    private function stripProductNameLeadingParts(array $parts, string $productName): array
    {
        if ($productName === '' || count($parts) <= 1) {
            return $parts;
        }

        $normalizedProductName = $this->normalizeComparisonValue($productName);
        if ($normalizedProductName === '') {
            return $parts;
        }

        $matchedPartCount = 0;
        $combinedPrefix = '';

        foreach ($parts as $index => $part) {
            $normalizedPart = $this->normalizeComparisonValue($part);
            if ($normalizedPart === '') {
                break;
            }

            $combinedPrefix = trim($combinedPrefix.' '.$normalizedPart);

            if ($combinedPrefix === $normalizedProductName) {
                $matchedPartCount = $index + 1;
                break;
            }

            if (! str_starts_with($normalizedProductName, $combinedPrefix.' ')) {
                break;
            }
        }

        if ($matchedPartCount > 0 && $matchedPartCount < count($parts)) {
            return array_slice($parts, $matchedPartCount);
        }

        return $parts;
    }

    private function normalizeComparisonValue(string $value): string
    {
        return Str::of($value)
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/i', ' ')
            ->squish()
            ->toString();
    }

    private function normalizeAttributeKey(string $rawKey, string $value): string
    {
        $normalized = Str::of($rawKey)
            ->replace(['attribute_', 'pa_'], '')
            ->snake()
            ->toString();

        $normalized = $this->canonicalAttributeKey($normalized);

        if (in_array($normalized, ['colour', 'farbe', 'couleur'], true)) {
            return 'color';
        }

        if (in_array($normalized, ['groesse', 'taille'], true)) {
            return 'size';
        }

        if ($normalized !== '') {
            return $normalized;
        }

        return $this->classifyAttributeKey($value, '', 0);
    }

    private function canonicalAttributeKey(string $key): string
    {
        return match (true) {
            preg_match('/^(color|size)_\d+$/', $key) === 1 => preg_replace('/_\d+$/', '', $key) ?? $key,
            default => $key,
        };
    }

    private function classifyAttributeKey(string $value, string $variantType, int $index): string
    {
        $trimmedValue = trim($value);

        if ($trimmedValue === '') {
            return 'option_'.($index + 1);
        }

        return match (ProductVariant::detectTypeFromName($trimmedValue)) {
            'size' => 'size',
            'color' => 'color',
            default => 'option_'.($index + 1),
        };
    }

    /**
     * @param  array<int, string>  $values
     * @return array<int, string>
     */
    private function sortSizes(array $values): array
    {
        $sizeOrder = [
            'xxs', 'xs', 's', 'm', 'l', 'xl', 'xxl', 'xxxl', 'xxxxl', 'xxxxxl',
            '2xl', '3xl', '4xl', '5xl',
        ];

        usort($values, function (string $left, string $right) use ($sizeOrder): int {
            $leftIndex = array_search(strtolower($left), $sizeOrder, true);
            $rightIndex = array_search(strtolower($right), $sizeOrder, true);

            if ($leftIndex !== false && $rightIndex !== false) {
                return $leftIndex <=> $rightIndex;
            }

            if ($leftIndex !== false) {
                return -1;
            }

            if ($rightIndex !== false) {
                return 1;
            }

            return strnatcasecmp($left, $right);
        });

        return $values;
    }

    private function attributeLabelFromKey(string $key): string
    {
        $canonicalKey = $this->canonicalAttributeKey($key);

        return match ($canonicalKey) {
            'size' => 'Size',
            'color' => 'Color',
            default => 'Product options',
        };
    }

    private function resolveZoomPath(ProductMedia $media): string
    {
        if (str_starts_with((string) $media->mime_type, 'video/')) {
            return $media->path;
        }

        $path = (string) $media->path;
        $disk = (string) ($media->disk ?: 'public');
        $pathInfo = pathinfo($path);
        $directory = $pathInfo['dirname'] ?? '';
        $filename = $pathInfo['filename'];

        if ($filename === '') {
            return $path;
        }

        $baseName = $filename;
        foreach (['-thumbnail-200x200', '-hero-1200x600', '-gallery-600x600'] as $suffix) {
            if (str_ends_with($baseName, $suffix)) {
                $baseName = substr($baseName, 0, -strlen($suffix));
                break;
            }
        }

        $basePath = ($directory !== '' && $directory !== '.' ? $directory.'/' : '').$baseName;
        $candidates = [
            $basePath.'.avif',
            $basePath.'.webp',
            $path,
        ];

        if (str_contains($path, '/fallback/')) {
            $galleryBasePath = str_replace('/fallback/', '/gallery/', $basePath);
            array_unshift($candidates, $galleryBasePath.'.avif', $galleryBasePath.'.webp');
        }

        foreach ($candidates as $candidate) {
            if (Storage::disk($disk)->exists($candidate)) {
                return $candidate;
            }
        }

        return $path;
    }

    private function resolveGalleryPath(ProductMedia $media): string
    {
        return $this->resolveSizedPath($media, $media->getGalleryPath());
    }

    private function resolveThumbnailPath(ProductMedia $media): string
    {
        return $this->resolveSizedPath($media, $media->getThumbnailPath());
    }

    private function resolveSizedPath(ProductMedia $media, string $preferredPath): string
    {
        if (str_starts_with((string) $media->mime_type, 'video/')) {
            return $media->path;
        }

        $disk = (string) ($media->disk ?: 'public');
        $candidates = [$preferredPath];

        if (str_contains($preferredPath, '/fallback/')) {
            $galleryPreferredPath = str_replace('/fallback/', '/gallery/', $preferredPath);
            array_unshift($candidates, $galleryPreferredPath);
        }

        $candidates[] = $media->path;

        foreach (array_values(array_unique($candidates)) as $candidate) {
            if (Storage::disk($disk)->exists($candidate)) {
                return $candidate;
            }
        }

        return $media->path;
    }

    /**
     * Apply size-matching constraints to a variant query (active + in-stock + size match).
     *
     * @param  array<int, string>  $sizes
     */
    private function applySizeMatch($variantQuery, array $sizes, bool $checkStock = true): void
    {
        $variantQuery->where('is_active', true);

        if ($checkStock) {
            $variantQuery->where(function ($stockQuery): void {
                $stockQuery->where('track_inventory', false)
                    ->orWhere('allow_backorder', true)
                    ->orWhere('is_preorder', true)
                    ->orWhere('stock_quantity', '>', 0);
            });
        }

        $variantQuery->where(function ($sizeQuery) use ($sizes): void {
            // Match typed size variants by exact name
            $sizeQuery->where(function ($q) use ($sizes): void {
                $q->where('variant_type', 'size')
                    ->whereIn('name', $sizes);
            });

            // Match option_values JSON
            foreach ($sizes as $size) {
                $sizeQuery->orWhere('option_values->size', $size);
            }

            // Match size embedded in variant name after delimiters
            foreach ($sizes as $size) {
                $escapedSize = str_replace(['%', '_'], ['\\%', '\\_'], $size);

                $sizeQuery->orWhere('name', $size);

                foreach ([', ', '- ', '/ ', '| '] as $delimiter) {
                    $sizeQuery->orWhere('name', 'LIKE', '%'.$delimiter.$escapedSize);
                    $sizeQuery->orWhere('name', 'LIKE', '%'.$delimiter.$escapedSize.',%');
                }
            }
        });
    }
}
