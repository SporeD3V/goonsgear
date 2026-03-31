<?php
// Quick check of Onyx All White MadFace variants
require '/home/macaw-goonsgear/htdocs/goonsgear.macaw.studio/vendor/autoload.php';
$app = require_once '/home/macaw-goonsgear/htdocs/goonsgear.macaw.studio/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$product = \App\Models\Product::where('slug', 'onyx-all-white-madface-shirt')->first();
if (!$product) {
    echo "Product not found\n";
    exit(1);
}

echo "Product: {$product->name}\n";
echo "Variants:\n";
echo str_repeat('=', 70) . "\n";

foreach ($product->variants()->orderBy('position')->get() as $v) {
    printf("%-30s | %-10s | Active: %s\n", 
        $v->name, 
        $v->variant_type ?? 'NULL', 
        $v->is_active ? 'Yes' : 'No'
    );
}

echo str_repeat('=', 70) . "\n";
echo "\nCounts:\n";
echo "Size: " . $product->variants()->where('variant_type', 'size')->count() . "\n";
echo "Color: " . $product->variants()->where('variant_type', 'color')->count() . "\n";
echo "Custom: " . $product->variants()->where('variant_type', 'custom')->count() . "\n";
echo "NULL: " . $product->variants()->whereNull('variant_type')->count() . "\n";
