<?php

$i = new Imagick;
$formats = $i->queryFormats();
$wanted = ['PNG', 'AVIF', 'WEBP', 'JPG', 'JPEG'];
foreach ($formats as $f) {
    if (in_array(strtoupper($f), $wanted)) {
        echo $f."\n";
    }
}
