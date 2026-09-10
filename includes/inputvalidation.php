<?php

function validate($value)
{
    $value = trim($value);
    $value = stripslashes($value);
    $value = htmlspecialchars($value);
    return $value;
}

/**
 * Like validate(), but for fields rendered through render_notes_markdown()
 * (includes/markdown.php) rather than echoed directly: HTML-escaping happens
 * at render time there, so it must not also happen at storage time here.
 *
 * @param string $value
 * @return string
 */
function validate_markdown($value)
{
    $value = trim($value);
    $value = stripslashes($value);
    return $value;
}

?>