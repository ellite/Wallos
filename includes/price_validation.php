<?php
/*
 * Subscription price precision policy (#1155).
 *
 * Prices are stored as REAL, so there is no schema-level precision. The rule
 * applied on input is:
 *   - the value must be a plain decimal number greater than zero;
 *   - at most 8 fractional digits are accepted (satoshi granularity);
 *   - trailing fractional zeros do not count towards that limit.
 *
 * The value is returned rounded to 8 decimal places, or null when invalid.
 */
function normalizeSubscriptionPrice($raw)
{
    if (is_int($raw) || is_float($raw)) {
        $value = (float) $raw;
        if (!is_finite($value) || $value <= 0) {
            return null;
        }

        $rounded = round($value, 8);
        // Reject values that need more than 8 decimals (e.g. 1e-9).
        if (abs($value - $rounded) > 1e-12) {
            return null;
        }

        return $rounded > 0 ? $rounded : null;
    }

    $raw = trim((string) $raw);
    if ($raw === '' || !preg_match('/^(?:\d+(?:\.\d*)?|\.\d+)$/', $raw)) {
        return null;
    }

    if ((float) $raw <= 0) {
        return null;
    }

    $dot = strpos($raw, '.');
    $fraction = $dot === false ? '' : substr($raw, $dot + 1);
    if (strlen(rtrim($fraction, '0')) > 8) {
        return null;
    }

    $value = round((float) $raw, 8);

    return $value > 0 ? $value : null;
}
