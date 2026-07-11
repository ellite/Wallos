<?php
/*
  GD fallback equivalent of Imagick::transparentPaintImage() with fuzz:
  makes every pixel within the tolerance of the given color fully transparent.
  Used by the logo/icon upload endpoints when the imagick extension is missing.
*/
function gdRemoveBackgroundColor($image, $backgroundRed, $backgroundGreen, $backgroundBlue, $fuzz = 0.1)
{
    // On palette images imagecolorat() returns palette indexes, not RGB values
    if (!imageistruecolor($image)) {
        imagepalettetotruecolor($image);
    }

    $width = imagesx($image);
    $height = imagesy($image);

    imagealphablending($image, false);
    imagesavealpha($image, true);

    $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
    $maxChannelDelta = (int) round(255 * $fuzz);
    $maxDistanceSquared = 3 * $maxChannelDelta * $maxChannelDelta;

    for ($y = 0; $y < $height; $y++) {
        for ($x = 0; $x < $width; $x++) {
            $color = imagecolorat($image, $x, $y);
            $red = ($color >> 16) & 0xFF;
            $green = ($color >> 8) & 0xFF;
            $blue = $color & 0xFF;

            $distanceSquared = ($red - $backgroundRed) ** 2
                + ($green - $backgroundGreen) ** 2
                + ($blue - $backgroundBlue) ** 2;

            if ($distanceSquared <= $maxDistanceSquared) {
                imagesetpixel($image, $x, $y, $transparent);
            }
        }
    }
}

function gdCropTransparent($image, $padding = 2)
{
    if (!imageistruecolor($image)) {
        imagepalettetotruecolor($image);
    }

    $width = imagesx($image);
    $height = imagesy($image);

    $top = null;
    $bottom = null;
    $left = null;
    $right = null;

    for ($y = 0; $y < $height; $y++) {
        for ($x = 0; $x < $width; $x++) {
            $color = imagecolorat($image, $x, $y);
            $alpha = ($color >> 24) & 0x7F; // 0 is fully opaque, 127 is fully transparent
            if ($alpha < 125) { // Pixel is not fully transparent (threshold 125 out of 127)
                if ($top === null) {
                    $top = $y;
                }
                $bottom = $y;
                if ($left === null || $x < $left) {
                    $left = $x;
                }
                if ($right === null || $x > $right) {
                    $right = $x;
                }
            }
        }
    }

    if ($top === null) {
        // Image is completely transparent, do nothing
        return $image;
    }

    $newWidth = $right - $left + 1;
    $newHeight = $bottom - $top + 1;

    $left = max(0, $left - $padding);
    $top = max(0, $top - $padding);
    $newWidth = min($width - $left, $newWidth + ($padding * 2));
    $newHeight = min($height - $top, $newHeight + ($padding * 2));

    $cropped = imagecrop($image, [
        'x' => $left,
        'y' => $top,
        'width' => $newWidth,
        'height' => $newHeight
    ]);

    if ($cropped !== false) {
        imagealphablending($cropped, false);
        imagesavealpha($cropped, true);
        imagedestroy($image);
        return $cropped;
    }

    return $image;
}
