#!/usr/bin/env python3
"""Deep analysis of product-variant-image relationships"""
import paramiko
import sys

HOST = '91.98.230.33'
PORT = 1221
USER = 'spored3v'
PASSWORD = 'HNjp0cfsKOZ9PoJltRvU'
BASE_PATH = '/home/macaw-goonsgear/htdocs/goonsgear.macaw.studio'

def run_cmd(client, cmd, label=None):
    if label:
        print(f"\n{'='*70}\n{label}\n{'='*70}")
    print(f"$ {cmd}\n")
    stdin, stdout, stderr = client.exec_command(cmd, timeout=90)
    out = stdout.read().decode().strip()
    if out:
        print(out)
    return out

def main():
    try:
        client = paramiko.SSHClient()
        client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
        client.connect(HOST, port=PORT, username=USER, password=PASSWORD, timeout=15)
        print(f"✓ Connected to {HOST}:{PORT}\n")
        
        # === WORDPRESS VARIANT STRUCTURE ===
        
        run_cmd(client,
                f'cd {BASE_PATH} && php artisan tinker --execute="'
                'try {{ '
                '  echo \\\"=== WooCommerce Product Types ===\\\" . PHP_EOL; '
                '  $types = DB::connection(\\\"legacy\\\")->table(\\\"wp_posts\\\")'
                '    ->where(\\\"post_type\\\", \\\"product\\\")'
                '    ->join(\\\"wp_postmeta\\\", \\\"wp_posts.ID\\\", \\\"=\\\", \\\"wp_postmeta.post_id\\\")'
                '    ->where(\\\"wp_postmeta.meta_key\\\", \\\"_product_type\\\")'
                '    ->select(\\\"wp_postmeta.meta_value as type\\\", DB::raw(\\\"COUNT(*) as count\\\"))'
                '    ->groupBy(\\\"type\\\")'
                '    ->get(); '
                '  foreach($types as $t) {{ '
                '    echo \\\"  \\\" . str_pad($t->type, 20) . \\\": \\\" . $t->count . PHP_EOL; '
                '  }} '
                '}} catch(Exception $e) {{ '
                '  echo \\\"ERROR: \\\" . $e->getMessage() . PHP_EOL; '
                '}}"',
                'WooCommerce Product Types in Legacy DB')
        
        run_cmd(client,
                f'cd {BASE_PATH} && php artisan tinker --execute="'
                'try {{ '
                '  echo \\\"=== WooCommerce Variations Sample ===\\\" . PHP_EOL; '
                '  $variations = DB::connection(\\\"legacy\\\")->table(\\\"wp_posts\\\")'
                '    ->where(\\\"post_type\\\", \\\"product_variation\\\")'
                '    ->where(\\\"post_status\\\", \\\"publish\\\")'
                '    ->limit(5)'
                '    ->get([\\\"ID\\\", \\\"post_parent\\\", \\\"post_title\\\", \\\"post_name\\\"]); '
                '  foreach($variations as $v) {{ '
                '    echo \\\"\\\\nVariation ID: \\\" . $v->ID . \\\", Parent: \\\" . $v->post_parent . PHP_EOL; '
                '    $meta = DB::connection(\\\"legacy\\\")->table(\\\"wp_postmeta\\\")'
                '      ->where(\\\"post_id\\\", $v->ID)'
                '      ->whereIn(\\\"meta_key\\\", [\\\"_thumbnail_id\\\", \\\"attribute_pa_size\\\", \\\"attribute_pa_color\\\", \\\"_sku\\\"])'
                '      ->get(); '
                '    foreach($meta as $m) {{ '
                '      echo \\\"  \\\" . str_pad($m->meta_key, 20) . \\\": \\\" . $m->meta_value . PHP_EOL; '
                '    }} '
                '  }} '
                '}} catch(Exception $e) {{ '
                '  echo \\\"ERROR: \\\" . $e->getMessage() . PHP_EOL; '
                '}}"',
                'Sample WooCommerce Variations with Metadata')
        
        # === LARAVEL VARIANT STRUCTURE ===
        
        run_cmd(client,
                f'cd {BASE_PATH} && php artisan tinker --execute="'
                'echo \\\"=== Laravel Product Variants Stats ===\\\" . PHP_EOL; '
                'echo \\\"Total products: \\\" . \\App\\Models\\Product::count() . PHP_EOL; '
                'echo \\\"Total variants: \\\" . \\App\\Models\\ProductVariant::count() . PHP_EOL; '
                'echo \\\"Products with variants: \\\" . \\App\\Models\\Product::has(\\\"variants\\\")->count() . PHP_EOL; '
                'echo \\\"Variants per product (avg): \\\" . round(\\App\\Models\\ProductVariant::count() / \\App\\Models\\Product::count(), 2) . PHP_EOL; '
                '"',
                'Laravel Product-Variant Statistics')
        
        run_cmd(client,
                f'cd {BASE_PATH} && php artisan tinker --execute="'
                'echo \\\"=== Product-Variant-Media Relationships ===\\\" . PHP_EOL; '
                'echo \\\"Media linked to products only: \\\" . \\App\\Models\\ProductMedia::whereNull(\\\"product_variant_id\\\")->count() . PHP_EOL; '
                'echo \\\"Media linked to specific variants: \\\" . \\App\\Models\\ProductMedia::whereNotNull(\\\"product_variant_id\\\")->count() . PHP_EOL; '
                'echo \\\"\\\\nSample Product with Multiple Variants:\\\" . PHP_EOL; '
                '$product = \\App\\Models\\Product::has(\\\"variants\\\", \\\">\\\", 1)->with([\\\"variants\\\", \\\"media\\\"])->first(); '
                'if($product) {{ '
                '  echo \\\"Product: \\\" . $product->name . \\\" (ID: \\\" . $product->id . \\\")\\\" . PHP_EOL; '
                '  echo \\\"Variants (\\\" . $product->variants->count() . \\\"):\\\" . PHP_EOL; '
                '  foreach($product->variants as $v) {{ '
                '    $variantMedia = \\App\\Models\\ProductMedia::where(\\\"product_variant_id\\\", $v->id)->count(); '
                '    echo \\\"  - \\\" . $v->sku . \\\" (ID: \\\" . $v->id . \\\") - Media: \\\" . $variantMedia . PHP_EOL; '
                '  }} '
                '  echo \\\"Product-level media: \\\" . $product->media->whereNull(\\\"product_variant_id\\\")->count() . PHP_EOL; '
                '}}"',
                'Product-Variant-Media Relationship Example')
        
        # === IMPORT MAPPING ANALYSIS ===
        
        run_cmd(client,
                f'cd {BASE_PATH} && php artisan tinker --execute="'
                'echo \\\"=== Import Mapping: Variants ===\\\" . PHP_EOL; '
                '$mappings = DB::table(\\\"import_legacy_variants as map\\\")'
                '  ->join(\\\"product_variants as v\\\", \\\"v.id\\\", \\\"=\\\", \\\"map.variant_id\\\")'
                '  ->join(\\\"products as p\\\", \\\"p.id\\\", \\\"=\\\", \\\"v.product_id\\\")'
                '  ->select(\\\"map.legacy_wp_variation_id\\\", \\\"v.sku\\\", \\\"v.id as variant_id\\\", \\\"p.name as product_name\\\")'
                '  ->limit(10)'
                '  ->get(); '
                'foreach($mappings as $m) {{ '
                '  echo \\\"Legacy Var ID: \\\" . $m->legacy_wp_variation_id . \\\" -> Laravel Variant: \\\" . $m->sku . \\\" (ID: \\\" . $m->variant_id . \\\")\\\" . PHP_EOL; '
                '  echo \\\"  Product: \\\" . $m->product_name . PHP_EOL; '
                '}}"',
                'Import Mapping: Legacy WP Variations -> Laravel Variants')
        
        # === IMAGE ATTACHMENT ANALYSIS ===
        
        run_cmd(client,
                f'cd {BASE_PATH} && php artisan tinker --execute="'
                'try {{ '
                '  echo \\\"=== WooCommerce Variation Images ===\\\" . PHP_EOL; '
                '  $varsWithImages = DB::connection(\\\"legacy\\\")->table(\\\"wp_postmeta\\\")'
                '    ->where(\\\"meta_key\\\", \\\"_thumbnail_id\\\")'
                '    ->whereIn(\\\"post_id\\\", function($q) {{ '
                '      $q->select(\\\"ID\\\")->from(\\\"wp_posts\\\")->where(\\\"post_type\\\", \\\"product_variation\\\"); '
                '    }})'
                '    ->count(); '
                '  echo \\\"Variations with _thumbnail_id: \\\" . $varsWithImages . PHP_EOL; '
                '  echo \\\"\\\\nSample variation images:\\\" . PHP_EOL; '
                '  $samples = DB::connection(\\\"legacy\\\")->table(\\\"wp_postmeta as pm\\\")'
                '    ->join(\\\"wp_posts as p\\\", \\\"p.ID\\\", \\\"=\\\", \\\"pm.post_id\\\")'
                '    ->where(\\\"pm.meta_key\\\", \\\"_thumbnail_id\\\")'
                '    ->where(\\\"p.post_type\\\", \\\"product_variation\\\")'
                '    ->select(\\\"p.ID as variation_id\\\", \\\"p.post_parent\\\", \\\"pm.meta_value as attachment_id\\\")'
                '    ->limit(5)'
                '    ->get(); '
                '  foreach($samples as $s) {{ '
                '    echo \\\"  Variation \\\" . $s->variation_id . \\\" (parent: \\\" . $s->post_parent . \\\") -> Attachment: \\\" . $s->attachment_id . PHP_EOL; '
                '  }} '
                '}} catch(Exception $e) {{ '
                '  echo \\\"ERROR: \\\" . $e->getMessage() . PHP_EOL; '
                '}}"',
                'WooCommerce Variation Image Attachments')
        
        # === CROPPED IMAGE ANALYSIS ===
        
        run_cmd(client,
                f'find {BASE_PATH}/storage/app/public/products -name "*-thumbnail-*" -o -name "*-gallery-*" -o -name "*-hero-*" | wc -l',
                'Count of Cropped/Sized Images (thumbnail, gallery, hero)')
        
        run_cmd(client,
                f'cd {BASE_PATH}/storage/app/public/products && ls -d */ | head -5 | while read dir; do echo "\\n=== $dir ===" && find "$dir" -type f | head -10; done',
                'Sample Product Directory Contents (First 5 Products)')
        
        print("\n" + "="*70)
        print("✓ Variant-Image analysis completed")
        print("="*70)
        
        client.close()
        return 0
        
    except Exception as e:
        print(f"\n✗ Error: {e}", file=sys.stderr)
        import traceback
        traceback.print_exc()
        return 1

if __name__ == '__main__':
    sys.exit(main())
