<?php

/**
 * Convert hero feature card icons from PNG to AVIF using GD + Imagick.
 * GD reads/resizes PNG, saves intermediate. Imagick converts to AVIF.
 */
$sourceDir = __DIR__.'/temporary_import';
$targetDir = __DIR__.'/public/images';

$icons = [
    'globe-free-img.png' => 'worldwide-shipping-icon',
    'vinyl.png' => 'vinyl-record-icon',
    'tag-free-img.png' => 'wholesale-price-tag-icon',
    'lock-free-img.png' => 'secure-payment-lock-icon',
];

foreach ($icons as $sourceFile => $targetName) {
    $sourcePath = $sourceDir.'/'.$sourceFile;

    if (! is_file($sourcePath)) {
        echo "SKIP: {$sourceFile} not found\n";

        continue;
    }

    // Read with GD
    $original = imagecreatefrompng($sourcePath);
    $origW = imagesx($original);
    $origH = imagesy($original);

    // Resize to max 80px, keeping aspect ratio
    $size = 80;
    $ratio = min($size / $origW, $size / $origH);
    $newW = (int) round($origW * $ratio);
    $newH = (int) round($origH * $ratio);

    $resized = imagecreatetruecolor($newW, $newH);
    imagesavealpha($resized, true);
    imagealphablending($resized, false);
    $transparent = imagecolorallocatealpha($resized, 0, 0, 0, 127);
    imagefill($resized, 0, 0, $transparent);
    imagealphablending($resized, true);
    imagecopyresampled($resized, $original, 0, 0, 0, 0, $newW, $newH, $origW, $origH);

    // Save PNG fallback
    $pngTarget = $targetDir.'/'.$targetName.'.png';
    imagesavealpha($resized, true);
    imagepng($resized, $pngTarget, 9);
    echo "PNG: {$targetName}.png (".filesize($pngTarget)." bytes)\n";

    imagedestroy($resized);
    imagedestroy($original);

    // Convert PNG to AVIF using Imagick (read from the saved PNG)
    $avifTarget = $targetDir.'/'.$targetName.'.avif';
    $imagick = new Imagick($pngTarget);
    $imagick->setImageFormat('avif');
    $imagick->setImageCompressionQuality(62);
    $imagick->stripImage();
    $imagick->writeImage($avifTarget);
    echo "AVIF: {$targetName}.avif (".filesize($avifTarget)." bytes)\n";
    $imagick->clear();
    $imagick->destroy();

    echo "\n";
}

echo "Done.\n";
