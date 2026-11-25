<?php

$dir = __DIR__ . '/../writable/uploads/proofs';
if (!is_dir($dir)) {
    mkdir($dir, 0755, true);
}

$width = 600;
$height = 800;

$img = imagecreatetruecolor($width, $height);
$bg = imagecolorallocate($img, 248, 250, 252);
imagefilledrectangle($img, 0, 0, $width, $height, $bg);

// Bankak Green Header
$green = imagecolorallocate($img, 16, 120, 60);
$darkGreen = imagecolorallocate($img, 10, 80, 40);
imagefilledrectangle($img, 0, 0, $width, 140, $green);

$white = imagecolorallocate($img, 255, 255, 255);
$navy = imagecolorallocate($img, 6, 26, 85);
$slate = imagecolorallocate($img, 100, 116, 139);
$border = imagecolorallocate($img, 226, 232, 240);

// Header text
imagestring($img, 5, 40, 30, 'BANK OF KHARTOUM - BANKAK RECEIPT', $white);
imagestring($img, 4, 40, 60, 'SUCCESSFUL FUNDS TRANSFER', $white);
imagestring($img, 3, 40, 95, 'Transaction Verified & Completed', $white);

// White Receipt Card in Center
imagefilledrectangle($img, 30, 160, $width - 30, $height - 40, $white);
imagerectangle($img, 30, 160, $width - 30, $height - 40, $border);

// Amount section
$amountY = 190;
imagestring($img, 3, 60, $amountY, 'TRANSFER AMOUNT:', $slate);
imagestring($img, 5, 60, $amountY + 25, '487,500 SDG', $darkGreen);

imageline($img, 60, $amountY + 65, $width - 60, $amountY + 65, $border);

// Key-value pairs
$details = [
    ['Beneficiary Name:', 'Wex Tech Store (Matjar Al-Tiqniya)'],
    ['Beneficiary Account:', '100200300 (Bankak Main Branch)'],
    ['Sender Name:', 'Omer Mohammed Ahmed'],
    ['Sender Account:', '248912345679'],
    ['Reference Number:', 'BOK-TRX-998241'],
    ['Payment Purpose / Order:', 'Order #WEX-1001 Laptop Purchase'],
    ['Date & Time:', gmdate('Y-m-d H:i:s') . ' UTC'],
    ['Status:', 'COMPLETED (SUCCESSFUL)'],
];

$y = $amountY + 90;
foreach ($details as $row) {
    imagestring($img, 3, 60, $y, $row[0], $slate);
    imagestring($img, 4, 60, $y + 20, $row[1], $navy);
    $y += 55;
}

// Watermark seal at bottom
imagestring($img, 2, 60, $height - 70, 'OFFICIAL ELECTRONIC RECEIPT - BANK OF KHARTOUM', $slate);

imagejpeg($img, "$dir/sample-receipt.jpg", 90);
imagedestroy($img);

echo "Sample receipt created at: $dir/sample-receipt.jpg\n";
