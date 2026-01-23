<?php

$lang = "en";
if (isset($_COOKIE['language'])) {
    $selectedLanguage = $_COOKIE['language'];

    if (array_key_exists($selectedLanguage, $languages)) {
        $lang = $selectedLanguage;
    }
}

function translate($text, $translations, $variables = [])
{
    if (array_key_exists($text, $translations)) {
        $translation = $translations[$text];
    } else {
        require 'en.php';
        if (isset($i18n[$text])) {
            $translation = $i18n[$text];
        } else {
            return "[i18n String Missing]";
        }
    }

    if (!empty($variables)) {
        foreach ($variables as $key => $value) {
            $translation = str_replace([':' . $key, '{' . $key . '}'], $value, $translation);
        }
    }

    return $translation;
}

?>