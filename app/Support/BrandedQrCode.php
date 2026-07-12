<?php

namespace App\Support;

use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QRGdImagePNG;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use GdImage;

/**
 * Central QR renderer for the app. Error correction is fixed at H (30%) so
 * the ~22% logo overlay never affects scannability. Pass `logo: false` for
 * QR codes belonging to entities that aren't part of the PROCTAD corps
 * (e.g. Other Examination Personnel) — the ProCTAD program logo shouldn't
 * appear on their IDs.
 */
class BrandedQrCode
{
    public static function dataUri(string $data, bool $logo = true): string
    {
        return 'data:image/png;base64,'.base64_encode(self::png($data, $logo));
    }

    public static function png(string $data, bool $logo = true): string
    {
        $image = $logo ? self::withLogo(self::renderMatrix($data)) : self::renderMatrix($data);

        ob_start();
        imagepng($image);
        $bytes = ob_get_clean();
        imagedestroy($image);

        return $bytes;
    }

    private static function renderMatrix(string $data): GdImage
    {
        $options = new QROptions([
            'outputInterface' => QRGdImagePNG::class,
            'eccLevel' => EccLevel::H,
            'scale' => 8,
            'imageTransparent' => false,
            'returnResource' => true,
        ]);

        return (new QRCode($options))->render($data);
    }

    private static function withLogo(GdImage $qr): GdImage
    {
        // A small pre-scaled copy — decoding the full ~2500px source bitmap on
        // every QR render is wasteful and can exhaust CLI memory limits.
        $logoFile = public_path('images/brand/proctad-logo-qr.png');

        if (! is_file($logoFile)) {
            return $qr;
        }

        $logo = @imagecreatefrompng($logoFile);

        if ($logo === false) {
            return $qr;
        }

        $qrSize = imagesx($qr);
        $logoTarget = (int) round($qrSize * 0.22);

        $srcW = imagesx($logo);
        $srcH = imagesy($logo);
        $ratio = min($logoTarget / $srcW, $logoTarget / $srcH);
        $dstW = (int) round($srcW * $ratio);
        $dstH = (int) round($srcH * $ratio);

        $padding = (int) round($logoTarget * 0.12);
        $boxSize = max($dstW, $dstH) + $padding * 2;
        $boxX = (int) round(($qrSize - $boxSize) / 2);
        $boxY = $boxX;

        $white = imagecolorallocate($qr, 255, 255, 255);
        imagefilledrectangle($qr, $boxX, $boxY, $boxX + $boxSize, $boxY + $boxSize, $white);

        $dstX = (int) round(($qrSize - $dstW) / 2);
        $dstY = (int) round(($qrSize - $dstH) / 2);

        imagecopyresampled($qr, $logo, $dstX, $dstY, 0, 0, $dstW, $dstH, $srcW, $srcH);
        imagedestroy($logo);

        return $qr;
    }
}
