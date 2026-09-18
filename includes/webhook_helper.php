<?php

/**
 * Escapes a value for safe interpolation into a JSON string field via plain
 * str_replace() templating (the webhook payload template uses
 * "{{placeholder}}" substitution, not real JSON building).
 *
 * Notes can now be multi-line Markdown containing quotes and backslashes, which
 * would otherwise break the payload's JSON structure. json_encode() quotes and
 * escapes the value correctly; the template already supplies the surrounding
 * quotes, so they have to come off again.
 *
 * The two characters json_encode() added, and only those. trim($encoded, '"')
 * strips *every* leading and trailing quote character, so a value ending in a
 * quotation mark lost the closing quote of its own \" escape and left a bare
 * backslash at the end of the payload's string - which stops being JSON. A note
 * reading He said "hi" is enough; it came out as He said \"hi\ and every
 * webhook for that subscription was sent as a body the receiver cannot parse.
 *
 * @param string $value
 * @return string
 */
function webhookJsonEscape($value)
{
    $encoded = json_encode((string) $value);

    // json_encode() answers false only for input it cannot represent, which a
    // string this function has already cast cannot be - but an empty value is
    // the safe answer rather than the literal "false".
    return $encoded === false ? '' : substr($encoded, 1, -1);
}

?>
