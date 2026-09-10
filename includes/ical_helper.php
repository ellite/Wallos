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

/**
 * Folds a content line to RFC 5545's 75-octet limit: continuation lines
 * are joined with CRLF + a single leading space. Splits only on UTF-8
 * character boundaries so accented/multi-byte text is never cut mid-
 * character. Pass the whole line including its "PROPERTY:" prefix.
 *
 * Notes can now be multi-paragraph Markdown, so DESCRIPTION (built from
 * price/category/payment method/payer/notes) can genuinely exceed 75
 * octets, where before it rarely did - stricter calendar clients can
 * mis-parse an unfolded long line.
 *
 * @param string $line
 * @return string
 */
function icalFold($line)
{
    $limit = 75;

    if (strlen($line) <= $limit) {
        return $line;
    }

    $characters = preg_split('//u', $line, -1, PREG_SPLIT_NO_EMPTY);
    if ($characters === false) {
        // Not valid UTF-8 (shouldn't happen for our text); fall back to a
        // plain byte split rather than leaving the line unfolded.
        return chunk_split($line, $limit, "\r\n ");
    }

    $folded = '';
    $lineLength = 0;

    foreach ($characters as $character) {
        $characterLength = strlen($character);
        if ($lineLength + $characterLength > $limit) {
            $folded .= "\r\n ";
            $lineLength = 0;
        }
        $folded .= $character;
        $lineLength += $characterLength;
    }

    return $folded;
}

?>
