<?php

// resolve_language() is strictly more permissive than the two lookups this
// used to do: every value that worked before still answers the same file, and a
// tag that did not - "de-DE" from a browser, "pt-BR" from an identity provider -
// now answers one instead of silently falling back to English.
$lang = isset($_COOKIE['language']) ? resolve_language($_COOKIE['language']) : "en";

function translate($text, $translations)
{
    if (array_key_exists($text, $translations)) {
        return $translations[$text];
    } else {
        require 'en.php';
        if (array_key_exists($text, $i18n)) {
            return $i18n[$text];
        } else {
            return "[i18n String Missing]";
        }
    }
}

?>