<?php

// Per-user date display format. 'auto' keeps the locale-driven output Wallos
// has always had; every other key maps to a fixed ICU pattern. Only these keys
// are ever stored or accepted, never a raw pattern from the client.
const DATE_FORMATS = [
    'auto' => null,
    'iso' => 'yyyy-MM-dd',
    'iso_basic' => 'yyyyMMdd',
    'mdy_slash' => 'MM/dd/yyyy',
    'dmy_dot' => 'dd.MM.yyyy',
    'd_mmm_y' => 'd MMM yyyy',
    'mdy_long' => 'MMMM d, yyyy',
];

// Standard names shown beside a format's example in the settings dropdown.
// Not translated: they are the names of the standard itself.
const DATE_FORMAT_LABELS = [
    'iso' => 'ISO 8601',
    'iso_basic' => 'ISO 8601 basic',
];

/**
 * Return the key when it is a supported date format, null otherwise.
 *
 * @param mixed $format
 * @return string|null
 */
function parse_date_format($format)
{
    return is_string($format) && array_key_exists($format, DATE_FORMATS) ? $format : null;
}

/**
 * Return a supported date format key, falling back to 'auto'.
 *
 * @param mixed $format
 * @return string
 */
function normalize_date_format($format)
{
    return parse_date_format($format) ?? 'auto';
}

/**
 * Format a date for display according to the user's chosen format.
 *
 * With 'auto' the output is "MMM d" for the current year and "MMM yyyy"
 * otherwise, in the given locale.
 *
 * @param string|int|DateTimeInterface $date   Date string, timestamp or DateTime.
 * @param string                       $lang   Locale, e.g. "en" or "de_DE".
 * @param string                       $format A DATE_FORMATS key.
 * @param DateTimeInterface|null       $now    Reference "today" (for tests).
 * @return string
 */
function format_user_date($date, $lang, $format = 'auto', $now = null)
{
    if ($date instanceof DateTimeInterface) {
        $dateTime = $date;
    } elseif (is_int($date)) {
        $dateTime = (new DateTime())->setTimestamp($date);
    } else {
        $dateTime = new DateTime($date);
    }

    $format = normalize_date_format($format);
    $pattern = DATE_FORMATS[$format];

    if ($pattern === null) {
        $currentYear = ($now ?? new DateTime())->format('Y');
        $pattern = ($currentYear == $dateTime->format('Y')) ? 'MMM d' : 'MMM yyyy';
    }

    // Fall back to English if the locale can't be used.
    foreach ([$lang, 'en'] as $locale) {
        try {
            $formatter = new IntlDateFormatter($locale, IntlDateFormatter::SHORT, IntlDateFormatter::NONE, null, null, $pattern);
            $formatted = $formatter->format($dateTime);
            if ($formatted !== false) {
                return $formatted;
            }
        } catch (Throwable $e) {
            // try the next locale
        }
    }

    return $dateTime->format('Y-m-d');
}
