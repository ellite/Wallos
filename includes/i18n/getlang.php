<?php

$lang = "en";
if (isset($_COOKIE['language'])) {
    $selectedLanguage = $_COOKIE['language'];

    if (array_key_exists($selectedLanguage, $languages)) {
        $lang = $selectedLanguage;
    }
}

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