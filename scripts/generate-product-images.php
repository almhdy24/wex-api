<?php

$dir = __DIR__ . '/../public/uploads/products';
if (!is_dir($dir)) {
    mkdir($dir, 0755, true);
}

$products = [
    [
        'slug' => 'dell-latitude-7490',
        'title' => 'Dell Latitude 7490',
        'type' => 'LAPTOP',
        'badge' => 'Dell Business',
        'specs' => 'Core i5-8350U | 8GB RAM | 256GB SSD',
        'bg1' => [235, 240, 248],
        'bg2' => [210, 222, 242],
        'accent' => [11, 63, 191], // Royal blue
    ],
    [
        'slug' => 'lenovo-thinkpad-t480',
        'title' => 'ThinkPad T480',
        'type' => 'LAPTOP',
        'badge' => 'Lenovo Legendary',
        'specs' => 'Core i5-8250U | 16GB RAM | Dual Battery',
        'bg1' => [240, 240, 242],
        'bg2' => [215, 218, 222],
        'accent' => [198, 40, 40], // ThinkPad red
    ],
    [
        'slug' => 'hp-elitebook-840-g5',
        'title' => 'HP EliteBook 840 G5',
        'type' => 'LAPTOP',
        'badge' => 'HP Aluminum',
        'specs' => 'Core i5-8250U | Bang & Olufsen Audio',
        'bg1' => [245, 246, 248],
        'bg2' => [225, 230, 238],
        'accent' => [0, 110, 180],
    ],
    [
        'slug' => 'hp-probook-450-g6',
        'title' => 'HP ProBook 450 G6',
        'type' => 'LAPTOP',
        'badge' => '15.6" Big Screen',
        'specs' => 'Core i5-8265U | Numeric Keypad',
        'bg1' => [240, 244, 248],
        'bg2' => [218, 226, 236],
        'accent' => [15, 76, 129],
    ],
    [
        'slug' => 'dell-inspiron-15-3580',
        'title' => 'Dell Inspiron 15 3580',
        'type' => 'LAPTOP',
        'badge' => 'Dell Storage',
        'specs' => 'Core i5-8265U | 1TB Storage | 15.6"',
        'bg1' => [238, 242, 248],
        'bg2' => [215, 225, 240],
        'accent' => [6, 26, 85],
    ],
    [
        'slug' => 'acer-aspire-5',
        'title' => 'Acer Aspire 5',
        'type' => 'LAPTOP',
        'badge' => 'Gen 10 Speed',
        'specs' => 'Core i5-10210U | 512GB NVMe SSD',
        'bg1' => [242, 248, 242],
        'bg2' => [218, 235, 220],
        'accent' => [46, 125, 50],
    ],
    [
        'slug' => 'macbook-air-2019',
        'title' => 'Apple MacBook Air 2019',
        'type' => 'LAPTOP',
        'badge' => 'Retina Display',
        'specs' => 'Intel Core i5 | Touch ID | Ultra Slim',
        'bg1' => [248, 248, 250],
        'bg2' => [228, 230, 235],
        'accent' => [90, 95, 105],
    ],
    [
        'slug' => 'asus-tuf-fx505',
        'title' => 'Asus TUF FX505',
        'type' => 'LAPTOP',
        'badge' => 'Gaming & Design',
        'specs' => 'Ryzen 5 | GTX 1650 4GB | 144Hz FHD',
        'bg1' => [248, 242, 235],
        'bg2' => [238, 218, 200],
        'accent' => [216, 75, 15],
    ],
    [
        'slug' => 'logitech-wireless-mouse-b170',
        'title' => 'Logitech Mouse B170',
        'type' => 'ACCESSORY',
        'badge' => 'Wireless 2.4G',
        'specs' => '10m Range | 12-Month Battery Life',
        'bg1' => [240, 246, 250],
        'bg2' => [220, 232, 242],
        'accent' => [0, 136, 145],
    ],
    [
        'slug' => 'laptop-backpack-15-waterproof',
        'title' => 'Laptop Backpack 15.6"',
        'type' => 'ACCESSORY',
        'badge' => 'Water Resistant',
        'specs' => 'Padded Laptop Sleeve | Multiple Pockets',
        'bg1' => [242, 242, 245],
        'bg2' => [220, 222, 228],
        'accent' => [30, 41, 59],
    ],
    [
        'slug' => 'original-laptop-charger-65w',
        'title' => 'Laptop Charger 65W',
        'type' => 'ACCESSORY',
        'badge' => '65 Watt',
        'specs' => '19.5V / 3.34A | Power Cable Included',
        'bg1' => [245, 245, 248],
        'bg2' => [225, 228, 235],
        'accent' => [109, 40, 217],
    ],
    [
        'slug' => 'kingston-ssd-256gb',
        'title' => 'Kingston SSD 256GB',
        'type' => 'ACCESSORY',
        'badge' => 'High Speed Storage',
        'specs' => 'SATA III 2.5" | 500MB/s Read Speed',
        'bg1' => [250, 242, 242],
        'bg2' => [235, 215, 215],
        'accent' => [185, 28, 28],
    ],
    [
        'slug' => 'laptop-ram-8gb-ddr4',
        'title' => 'Laptop RAM 8GB DDR4',
        'type' => 'ACCESSORY',
        'badge' => 'Speed Upgrade',
        'specs' => 'DDR4 2666MHz | Low Voltage SODIMM',
        'bg1' => [240, 248, 245],
        'bg2' => [215, 235, 225],
        'accent' => [13, 148, 136],
    ],
    [
        'slug' => 'laptop-cooling-pad-dual-fans',
        'title' => 'Dual Fan Cooling Pad',
        'type' => 'ACCESSORY',
        'badge' => 'Quiet Cooling',
        'specs' => '2 Silent LED Fans | Adjustable Stand',
        'bg1' => [238, 245, 252],
        'bg2' => [212, 228, 245],
        'accent' => [2, 132, 199],
    ],
];

function generateCardImage($prod, $width, $height, $isGallery = false) {
    $img = imagecreatetruecolor($width, $height);
    imageantialias($img, true);

    $bg1 = $prod['bg1'];
    $bg2 = $prod['bg2'];

    // Draw vertical gradient
    for ($y = 0; $y < $height; $y++) {
        $ratio = $y / $height;
        $r = (int) ($bg1[0] * (1 - $ratio) + $bg2[0] * $ratio);
        $g = (int) ($bg1[1] * (1 - $ratio) + $bg2[1] * $ratio);
        $b = (int) ($bg1[2] * (1 - $ratio) + $bg2[2] * $ratio);
        $color = imagecolorallocate($img, $r, $g, $b);
        imageline($img, 0, $y, $width, $y, $color);
    }

    $navy = imagecolorallocate($img, 6, 26, 85);
    $slate = imagecolorallocate($img, 100, 116, 139);
    $white = imagecolorallocate($img, 255, 255, 255);
    $accent = imagecolorallocate($img, $prod['accent'][0], $prod['accent'][1], $prod['accent'][2]);

    // Outer card border
    imagesetthickness($img, 2);
    imagerectangle($img, 10, 10, $width - 11, $height - 11, imagecolorallocate($img, 220, 228, 240));

    // Inner Device Stage Box
    $stagePaddingX = (int) ($width * 0.08);
    $stagePaddingY = (int) ($height * 0.08);
    $stageW = $width - ($stagePaddingX * 2);
    $stageH = $height - ($stagePaddingY * 2);

    imagefilledrectangle($img, $stagePaddingX, $stagePaddingY, $width - $stagePaddingX, $height - $stagePaddingY, $white);
    imagesetthickness($img, 1);
    imagerectangle($img, $stagePaddingX, $stagePaddingY, $width - $stagePaddingX, $height - $stagePaddingY, imagecolorallocate($img, 225, 232, 244));

    // Accent Pill Bar at top of stage
    $pillH = max(6, (int) ($height * 0.015));
    imagefilledrectangle($img, $stagePaddingX, $stagePaddingY, $width - $stagePaddingX, $stagePaddingY + $pillH, $accent);

    // Text placement
    $fontIdTitle = ($width >= 600) ? 5 : 3;
    $fontIdSub = ($width >= 600) ? 4 : 2;
    $fontIdBadge = ($width >= 600) ? 3 : 1;

    // Type / Badge
    $viewTag = $isGallery ? ' (Side Angle View)' : '';
    $badgeText = strtoupper($prod['badge'] . $viewTag);
    $badgeY = $stagePaddingY + (int) ($height * 0.12);
    imagestring($img, $fontIdBadge, $stagePaddingX + 24, $badgeY, $badgeText, $accent);

    // Product Title
    $titleY = $badgeY + (int) ($height * 0.12);
    imagestring($img, $fontIdTitle, $stagePaddingX + 24, $titleY, $prod['title'], $navy);

    // Specs
    $specsY = $titleY + (int) ($height * 0.10);
    imagestring($img, $fontIdSub, $stagePaddingX + 24, $specsY, $prod['specs'], $slate);

    // Device Silhouette representation in center/lower stage
    $silY = $specsY + (int) ($height * 0.10);
    $silH = max(20, (int) ($height * 0.28));
    $silW = max(50, (int) ($width * 0.65));
    $silX = $stagePaddingX + 24;

    if ($prod['type'] === 'LAPTOP') {
        // Laptop screen
        $screenH = (int) ($silH * 0.75);
        imagefilledrectangle($img, $silX + 30, $silY, $silX + $silW - 30, $silY + $screenH, imagecolorallocate($img, 240, 244, 250));
        imagerectangle($img, $silX + 30, $silY, $silX + $silW - 30, $silY + $screenH, $slate);
        // Base / keyboard
        imagefilledrectangle($img, $silX, $silY + $screenH, $silX + $silW, $silY + $silH, imagecolorallocate($img, 225, 230, 240));
        imagerectangle($img, $silX, $silY + $screenH, $silX + $silW, $silY + $silH, $navy);
    } else {
        // Accessory rounded box
        imagefilledrectangle($img, $silX + 20, $silY, $silX + $silW - 20, $silY + $silH, imagecolorallocate($img, 245, 247, 250));
        imagerectangle($img, $silX + 20, $silY, $silX + $silW - 20, $silY + $silH, $slate);
    }

    // Wex Tech watermark
    $brandY = $height - $stagePaddingY - 24;
    imagestring($img, $fontIdBadge, $stagePaddingX + 24, $brandY, 'WEX TECHNOLOGY - VERIFIED ORIGINAL', $slate);

    return $img;
}

foreach ($products as $p) {
    $slug = $p['slug'];

    // 1. Primary Image (800x600)
    $primaryImg = generateCardImage($p, 800, 600, false);
    imagejpeg($primaryImg, "$dir/{$slug}.jpg", 88);
    imagedestroy($primaryImg);

    // 2. Primary Thumb (300x225)
    $thumbImg = generateCardImage($p, 300, 225, false);
    imagejpeg($thumbImg, "$dir/{$slug}_thumb.jpg", 85);
    imagedestroy($thumbImg);

    // 3. Gallery Angle Image (800x600)
    $galleryImg = generateCardImage($p, 800, 600, true);
    imagejpeg($galleryImg, "$dir/{$slug}-2.jpg", 88);
    imagedestroy($galleryImg);

    // 4. Gallery Angle Thumb (300x225)
    $galleryThumbImg = generateCardImage($p, 300, 225, true);
    imagejpeg($galleryThumbImg, "$dir/{$slug}-2_thumb.jpg", 85);
    imagedestroy($galleryThumbImg);

    echo "Generated images for: {$slug}\n";
}

echo "All images successfully generated!\n";
