<?php

/**
 * Escapes a value for safe embedding in an iCalendar (RFC 5545) property value.
 *
 * Backslash must be escaped first, before the other substitutions introduce
 * new backslashes. Raw CRLF/LF are converted to a literal two-character
 * "\n" escape sequence — never leave a real newline in the output, since
 * iCalendar treats CRLF as a property delimiter: an unescaped newline lets
 * user-supplied text (e.g. a subscription name or notes) terminate the
 * current property and inject arbitrary calendar content (CWE-93).
 */
function icalEscape($value)
{
    $value = (string) $value;
    $value = str_replace('\\', '\\\\', $value);
    $value = str_replace(["\r\n", "\r", "\n"], '\\n', $value);
    $value = str_replace(',', '\\,', $value);
    $value = str_replace(';', '\\;', $value);
    return $value;
}

?>
