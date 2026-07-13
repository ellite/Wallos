<?php
/*
  Detects whether a logo's ink is plain black/near-black or white/near-white
  text (as opposed to a colorful icon mark, which is left untouched), and
  generates a themed variant with just that ink recolored for the opposite
  theme -- so a black-text logo stays legible on the dark theme, and a
  white-text logo stays legible on the light theme.

  Colored pixels (a logo's icon mark) are never touched -- only pixels close
  to black or close to white get remapped, and each such pixel is blended
  toward its OWN color inversion (not a single flat target color), which
  keeps whatever anti-aliasing/gradient variation the original ink had
  instead of flattening it into one solid tone.
*/

const LOGO_THEME_BLACK_LIGHTNESS_MAX = 60;   // 0-255; at/below this is "near black"
const LOGO_THEME_WHITE_LIGHTNESS_MIN = 200;  // 0-255; at/above this is "near white"
const LOGO_THEME_MAX_CHANNEL_SPREAD = 20;    // max(r,g,b) - min(r,g,b); low spread = grayscale-ish
const LOGO_THEME_SIGNIFICANT_RATIO = 0.05;   // at least 5% of opaque pixels to count as "has text"
const LOGO_THEME_DOMINANCE_RATIO = 0.3;      // if both black & white are present, min/max above this = genuinely ambiguous

/**
 * Classifies a logo's ink color by sampling its opaque pixels.
 *
 * @return 'dark'|'light'|null 'dark' if it has near-black text (light-theme
 *   native), 'light' if it has near-white text (dark-theme native), or null
 *   if neither is significant, or both are (ambiguous -- e.g. a
 *   deliberately two-tone logo we shouldn't mangle).
 */
function classifyLogoTextColor($image)
{
    $width = imagesx($image);
    $height = imagesy($image);

    $blackPixels = 0;
    $whitePixels = 0;
    $opaquePixels = 0;

    for ($y = 0; $y < $height; $y++) {
        for ($x = 0; $x < $width; $x++) {
            $color = imagecolorat($image, $x, $y);
            $alpha = ($color >> 24) & 0x7F; // 0 = fully opaque, 127 = fully transparent
            if ($alpha >= 125) {
                continue;
            }

            $opaquePixels++;

            $red = ($color >> 16) & 0xFF;
            $green = ($color >> 8) & 0xFF;
            $blue = $color & 0xFF;

            $spread = max($red, $green, $blue) - min($red, $green, $blue);
            if ($spread > LOGO_THEME_MAX_CHANNEL_SPREAD) {
                continue; // saturated/colored pixel: part of an icon mark, leave alone
            }

            $lightness = ($red + $green + $blue) / 3;
            if ($lightness <= LOGO_THEME_BLACK_LIGHTNESS_MAX) {
                $blackPixels++;
            } elseif ($lightness >= LOGO_THEME_WHITE_LIGHTNESS_MIN) {
                $whitePixels++;
            }
        }
    }

    if ($opaquePixels === 0) {
        return null;
    }

    $isDark = ($blackPixels / $opaquePixels) >= LOGO_THEME_SIGNIFICANT_RATIO;
    $isLight = ($whitePixels / $opaquePixels) >= LOGO_THEME_SIGNIFICANT_RATIO;

    if (!$isDark && !$isLight) {
        return null; // colorful logo, no significant black/white ink at all
    }

    if ($isDark && $isLight) {
        // Both present: background removal often leaves a thin, fully-opaque
        // residue of dark (or light) edge pixels that don't get cleared
        // because they fall just outside the fuzz tolerance -- that's noise,
        // not a second ink color. Only treat this as a genuinely ambiguous
        // two-tone logo (skip) when the smaller amount is comparable in size
        // to the larger one; otherwise the dominant color wins.
        $minCount = min($blackPixels, $whitePixels);
        $maxCount = max($blackPixels, $whitePixels);
        if ($minCount / $maxCount > LOGO_THEME_DOMINANCE_RATIO) {
            return null;
        }
    }

    return $blackPixels > $whitePixels ? 'dark' : 'light';
}

/**
 * Generates a themed variant of $image by inverting every low-saturation
 * (grayscale-ish) pixel's color -- not just pixels near the dominant
 * black/white extreme. Anti-aliasing and background-removal residue can
 * leave faint ink-colored specks anywhere across the lightness range (not
 * only at the extremes); inverting the whole grayscale region flips those
 * along too, so nothing is left stuck in the old theme's tone. Colored
 * pixels (an icon mark) are identified the same way as in
 * classifyLogoTextColor() and are always copied through untouched; alpha
 * is preserved exactly.
 *
 * @return resource|GdImage a new true color image with alpha
 */
function generateThemedLogoVariant($image)
{
    $width = imagesx($image);
    $height = imagesy($image);

    $variant = imagecreatetruecolor($width, $height);
    imagealphablending($variant, false);
    imagesavealpha($variant, true);

    for ($y = 0; $y < $height; $y++) {
        for ($x = 0; $x < $width; $x++) {
            $color = imagecolorat($image, $x, $y);
            $alpha = ($color >> 24) & 0x7F;
            $red = ($color >> 16) & 0xFF;
            $green = ($color >> 8) & 0xFF;
            $blue = $color & 0xFF;

            if ($alpha < 125) {
                $spread = max($red, $green, $blue) - min($red, $green, $blue);
                if ($spread <= LOGO_THEME_MAX_CHANNEL_SPREAD) {
                    $red = 255 - $red;
                    $green = 255 - $green;
                    $blue = 255 - $blue;
                }
            }

            $newColor = imagecolorallocatealpha($variant, $red, $green, $blue, $alpha);
            imagesetpixel($variant, $x, $y, $newColor);
        }
    }

    return $variant;
}

/**
 * Renders a subscription's logo <img> tag(s), given already-resolved image
 * URLs. When a themed variant exists, renders both the original and the
 * variant image, each tagged with the theme the *original* already reads
 * well on (data-native-theme); a small CSS rule (see styles.css) shows only
 * the one matching the current theme so the swap works instantly, including
 * live theme toggles with no page reload -- see the body.dark/body.light
 * classes set in scripts/theme.js and scripts/common.js.
 *
 * @param string $originalSrc Resolved URL of the original logo (caller's own path convention)
 * @param string|null $variantSrc Resolved URL of the themed variant, or empty/null if none
 * @param string|null $textColor 'dark'/'light'/null, as stored in logo_text_color
 * @param string $class Classes to apply to the image(s) (e.g. sizing/positioning)
 * @param string $extraAttrs Extra raw HTML attributes (e.g. alt="", title="")
 * @return string HTML for the logo image(s), or '' if there's no original logo
 */
function renderThemedLogoImg($originalSrc, $variantSrc, $textColor, $class = '', $extraAttrs = '')
{
    if (empty($originalSrc)) {
        return '';
    }

    if (empty($textColor) || empty($variantSrc)) {
        return '<img src="' . htmlspecialchars($originalSrc) . '" class="' . htmlspecialchars($class) . '" ' . $extraAttrs . '>';
    }

    // The theme the ORIGINAL logo file already reads well on.
    $nativeTheme = $textColor === 'dark' ? 'light' : 'dark';

    $originalClass = htmlspecialchars(trim($class . ' logo-theme-original'));
    $variantClass = htmlspecialchars(trim($class . ' logo-theme-variant'));

    return '<img src="' . htmlspecialchars($originalSrc) . '" class="' . $originalClass . '" data-native-theme="' . $nativeTheme . '" ' . $extraAttrs . '>'
         . '<img src="' . htmlspecialchars($variantSrc) . '" class="' . $variantClass . '" data-native-theme="' . $nativeTheme . '" ' . $extraAttrs . '>';
}

?>
