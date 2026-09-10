<?php

/**
 * Escapes a value for safe interpolation into a JSON string field via plain
 * str_replace() templating (the webhook payload template uses
 * "{{placeholder}}" substitution, not real JSON building).
 *
 * Notes can now be multi-line Markdown containing quotes/backslashes, which
 * would otherwise break the payload's JSON structure - json_encode() quotes
 * and escapes the value correctly; the surrounding quotes it adds are
 * trimmed off since the template already supplies them.
 *
 * @param string $value
 * @return string
 */
function webhookJsonEscape($value)
{
    return trim(json_encode((string) $value), '"');
}

?>
