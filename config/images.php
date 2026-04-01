<?php

return [
    /*
    | Image size variants for responsive serving
    | Stored as: {base-filename}-{variant-name}-{width}x{height}.{avif|webp}
    | Usage: Single source of truth for theme breakpoints - update when theme changes
    */
    'sizes' => [
        'thumbnail' => ['width' => 200, 'height' => 200],     // Cart items, search results
        'gallery' => ['width' => 600, 'height' => 600],        // Product detail gallery
    ],

    'responsive' => [
        'mobile' => 480,              // (max-width: 640px)
        'tablet' => 768,              // (max-width: 1024px)
        'desktop' => 1200,            // (min-width: 1025px)
    ],
];
