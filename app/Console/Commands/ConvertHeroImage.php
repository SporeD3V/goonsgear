<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ConvertHeroImage extends Command
{
    protected $signature = 'media:convert-hero {--quality=50 : AVIF compression quality (1-100)}';

    protected $description = 'Convert the hero image from JPG to AVIF using Imagick';

    public function handle(): int
    {
        $source = public_path('images/hero-goonsgear.jpg');
        $target = public_path('images/hero-goonsgear.avif');

        if (! is_file($source)) {
            $this->error("Source not found: {$source}");

            return self::FAILURE;
        }

        if (! class_exists('Imagick')) {
            $this->error('Imagick extension is not available.');

            return self::FAILURE;
        }

        $quality = (int) $this->option('quality');

        $this->info("Converting hero image to AVIF (quality: {$quality})...");

        $imagick = new \Imagick($source);
        $this->info("Source: {$imagick->getImageWidth()}x{$imagick->getImageHeight()} (".round(filesize($source) / 1024).' KiB)');

        $imagick->setImageFormat('avif');
        $imagick->setImageCompressionQuality($quality);
        $imagick->stripImage();
        $imagick->writeImage($target);
        $imagick->clear();
        $imagick->destroy();

        $this->info('AVIF saved: '.round(filesize($target) / 1024)." KiB → {$target}");

        return self::SUCCESS;
    }
}
