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

/** Only canonical decimal integers are accepted; never coerce request input first. */
function validate_frequency($value, $min = 1, $max = 366)
{
    if (!is_int($value) && !(is_string($value) && preg_match('/^[0-9]+$/D', $value))) {
        return false;
    }
    return $value >= $min && $value <= $max;
}

function wallos_valid_payment_date($value)
{
    if (!is_string($value) || !preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D', $value)) {
        return false;
    }
    $date = DateTime::createFromFormat('!Y-m-d', $value);
    return $date !== false && $date->format('Y-m-d') === $value;
}

?>