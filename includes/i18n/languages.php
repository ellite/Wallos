<?php
// File Name => Language Name
$languages = [
    // English first
    "en" => ["name" => "English", "dir" => "ltr"],
    "ar" => ["name" => "العربية", "dir" => "rtl"],
    // Remaining sorted alphabetically by language code
    "az" => ["name" => "Azərbaycan dili", "dir" => "ltr"],
    "ca" => ["name" => "Català", "dir" => "ltr"],
    "cs" => ["name" => "Čeština", "dir" => "ltr"],
    "da" => ["name" => "Dansk", "dir" => "ltr"],
    "de" => ["name" => "Deutsch", "dir" => "ltr"],
    "el" => ["name" => "Ελληνικά", "dir" => "ltr"],
    "es" => ["name" => "Español", "dir" => "ltr"],
    "fr" => ["name" => "Français", "dir" => "ltr"],
    "hu" => ["name" => "Magyar", "dir" => "ltr"],
    "id" => ["name" => "bahasa indonesia", "dir" => "ltr"],
    "it" => ["name" => "Italiano", "dir" => "ltr"],
    "ja" => ["name" => "日本語", "dir" => "ltr"],
    "ko" => ["name" => "한국어", "dir" => "ltr"],
    "nl" => ["name" => "Nederlands", "dir" => "ltr"], 
    "pl" => ["name" => "Polski", "dir" => "ltr"],
    "pt" => ["name" => "Português", "dir" => "ltr"],
    "pt_br" => ["name" => "Português Brasileiro", "dir" => "ltr"],
    "ro" => ["name" => "Română", "dir" => "ltr"],
    "ru" => ["name" => "Русский", "dir" => "ltr"],
    "sl" => ["name" => "Slovenščina", "dir" => "ltr"],
    "sr_lat" => ["name" => "Srpski", "dir" => "ltr"],
    "sr" => ["name" => "Српски", "dir" => "ltr"],
    "tr" => ["name" => "Türkçe", "dir" => "ltr"],
    "uk" => ["name" => "Українська", "dir" => "ltr"],
    "vi" => ["name" => "Tiếng Việt", "dir" => "ltr"],
    "zh_cn" => ["name" => "简体中文", "dir" => "ltr"],
    "zh_tw" => ["name" => "繁體中文", "dir" => "ltr"],
];

$langname_corrections = [
    "jp" => "ja",
];

/**
 * Turns any language value into one of the keys of $languages above.
 *
 * The keys are file names - "pt_br", "zh_cn", "sr_lat" - and everything that
 * hands Wallos a language hands it a BCP-47 tag instead: browsers send "de-DE"
 * in Accept-Language, identity providers send "pt-BR" in the standard OIDC
 * "locale" claim. Matching those against the keys directly fails, and the
 * failure is silent - the account is simply English, and the person it happened
 * to never chose that.
 *
 * What it accepts, in order:
 *
 *   an exact file name                      "de", "pt_br"
 *   a name this project used before         "jp"      -> "ja"
 *   a tag whose region names a file         "pt-BR"   -> "pt_br"
 *   a tag whose script names a file         "zh-Hans" -> "zh_cn"
 *   a tag whose language alone names a file "de-DE"   -> "de"
 *
 * Anything else answers "en", which is what the callers did before.
 *
 * @param string|null $value
 * @return string a key of $languages
 */
function resolve_language($value)
{
    global $languages, $langname_corrections;

    $normalized = strtolower(str_replace('-', '_', trim((string) $value)));

    if ($normalized === '') {
        return 'en';
    }

    if (isset($langname_corrections[$normalized])) {
        $normalized = $langname_corrections[$normalized];
    }

    if (isset($languages[$normalized])) {
        return $normalized;
    }

    // "zh-Hant" and "zh-Hans" name a script rather than a region, and the two
    // Chinese files are keyed by the region their script is used in. Without
    // this, traditional Chinese falls back to English whenever the sender
    // spells it by script - and "zh" alone is not a file either.
    $scripts = [
        'zh_hans' => 'zh_cn',
        'zh_hant' => 'zh_tw',
        'sr_latn' => 'sr_lat',
    ];

    if (isset($scripts[$normalized])) {
        return $scripts[$normalized];
    }

    // "de-DE", "en-GB", "pt-PT": the subtag after the language is a region
    // nothing here is keyed by, so the language alone decides. "pt-BR" never
    // reaches this line, because it matched a file name two checks ago.
    $language = strtok($normalized, '_');

    if ($language !== false && isset($languages[$language])) {
        return $language;
    }

    return 'en';
}
