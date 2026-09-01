<?php

// For the "theme" cookie, which stores the display mode and can be "automatic".
function sanitize_theme_mode($value, $default = "light")
{
    $allowed = ["light", "dark", "automatic"];
    return in_array($value, $allowed, true) ? $value : $default;
}

// For the "inUseTheme" cookie, which stores automatic mode's resolved light/dark value.
function sanitize_resolved_theme($value, $default = "light")
{
    $allowed = ["light", "dark"];
    return in_array($value, $allowed, true) ? $value : $default;
}

function sanitize_color_theme($value, $default = "blue")
{
    $allowed = ["blue", "red", "green", "yellow", "purple"];
    return in_array($value, $allowed, true) ? $value : $default;
}

?>
