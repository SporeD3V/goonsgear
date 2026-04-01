#!/usr/bin/env python3
"""Check actual variant-image mappings on staging"""
import paramiko
import sys

HOST = '91.98.230.33'
PORT = 1221
USER = 'spored3v'
PASSWORD = 'REDACTED_SET_GOONSGEAR_SSH_PASSWORD'
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
        print(f"✓ Connected\n")
        
        # Check products with multiple variants and their image distribution
        run_cmd(client,
                f'cd {BASE_PATH} && php artisan tinker --execute="'
                '$products = \\App\\Models\\Product::withCount([\\\"variants\\\", \\\"media\\\"])'
                '  ->having(\\\"variants_count\\\", \\\">\\\", 1)'
                '  ->orderByDesc(\\\"media_count\\\")'
                '  ->limit(10)'
                '  ->get(); '
                'echo \\\"Products with Multiple Variants (Top 10 by media count):\\\" . PHP_EOL . PHP_EOL; '
                'foreach($products as $p) {{ '
                '  $variantMedia = \\App\\Models\\ProductMedia::where(\\\"product_id\\\", $p->id)'
                '    ->whereNotNull(\\\"product_variant_id\\\")->count(); '
                '  $productMedia = \\App\\Models\\ProductMedia::where(\\\"product_id\\\", $p->id)'
                '    ->whereNull(\\\"product_variant_id\\\")->count(); '
                '  echo $p->name . PHP_EOL; '
                '  echo \\\"  Variants: \\\" . $p->variants_count . \\\" | Total Media: \\\" . $p->media_count . PHP_EOL; '
                '  echo \\\"  Product-level: \\\" . $productMedia . \\\" | Variant-specific: \\\" . $variantMedia . PHP_EOL . PHP_EOL; '
                '}}"',
                'Products with Multiple Variants - Image Distribution')
        
        # Deep dive into one product with variants
        run_cmd(client,
                f'cd {BASE_PATH} && php artisan tinker --execute="'
                '$product = \\App\\Models\\Product::withCount(\\\"variants\\\")'
                '  ->having(\\\"variants_count\\\", \\\">\\\", 1)'
                '  ->with([\\\"variants\\\", \\\"media\\\"])'
                '  ->first(); '
                'if($product) {{ '
                '  echo \\\"=== DETAILED ANALYSIS ===\\\" . PHP_EOL; '
                '  echo \\\"Product: \\\" . $product->name . \\\" (ID: \\\" . $product->id . \\\")\\\" . PHP_EOL . PHP_EOL; '
                '  echo \\\"VARIANTS:\\\" . PHP_EOL; '
                '  foreach($product->variants as $v) {{ '
                '    echo \\\"  [\\\" . $v->id . \\\"] \\\" . $v->sku; '
                '    if($v->option_values) {{ '
                '      echo \\\" - \\\" . json_encode($v->option_values); '
                '    }} '
                '    echo PHP_EOL; '
                '    $vMedia = $product->media->where(\\\"product_variant_id\\\", $v->id); '
                '    if($vMedia->count() > 0) {{ '
                '      foreach($vMedia as $m) {{ '
                '        echo \\\"      -> \\\" . basename($m->path) . \\\" (\\\" . $m->mime_type . \\\")\\\" . PHP_EOL; '
                '      }} '
                '    }} else {{ '
                '      echo \\\"      (no variant-specific media)\\\" . PHP_EOL; '
                '    }} '
                '  }} '
                '  echo PHP_EOL . \\\"PRODUCT-LEVEL MEDIA (shared across variants):\\\" . PHP_EOL; '
                '  $sharedMedia = $product->media->whereNull(\\\"product_variant_id\\\"); '
                '  foreach($sharedMedia as $m) {{ '
                '    echo \\\"  - \\\" . basename($m->path) . \\\" (\\\" . $m->mime_type . \\\", primary: \\\" . ($m->is_primary ? \\\"YES\\\" : \\\"no\\\") . \\\")\\\" . PHP_EOL; '
                '  }} '
                '}}"',
                'Detailed Variant-Image Mapping Example')
        
        # Check WP variation images that were imported
        run_cmd(client,
                f'cd {BASE_PATH} && php artisan tinker --execute="'
                'try {{ '
                '  $wpVarsWithImages = DB::connection(\\\"legacy\\\")->table(\\\"wp_postmeta as pm\\\")'
                '    ->join(\\\"wp_posts as p\\\", \\\"p.ID\\\", \\\"=\\\", \\\"pm.post_id\\\")'
                '    ->where(\\\"pm.meta_key\\\", \\\"_thumbnail_id\\\")'
                '    ->where(\\\"p.post_type\\\", \\\"product_variation\\\")'
                '    ->where(\\\"p.post_status\\\", \\\"publish\\\")'
                '    ->count(); '
                '  echo \\\"WP Variations with _thumbnail_id: \\\" . $wpVarsWithImages . PHP_EOL; '
                '  $laravelVarsWithMedia = \\App\\Models\\ProductMedia::whereNotNull(\\\"product_variant_id\\\")->count(); '
                '  echo \\\"Laravel Variants with media: \\\" . $laravelVarsWithMedia . PHP_EOL; '
                '  echo \\\"\\\\nDifference: \\\" . ($wpVarsWithImages - $laravelVarsWithMedia) . \\\" variant images not imported\\\" . PHP_EOL; '
                '}} catch(Exception $e) {{ '
                '  echo \\\"ERROR: \\\" . $e->getMessage(); '
                '}}"',
                'WP vs Laravel Variant Image Count')
        
        print("\n" + "="*70)
        print("✓ Variant-image mapping check completed")
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
