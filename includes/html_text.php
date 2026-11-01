<?php

/**
 * Names and labels reach the templates as HTML (they are stored HTML-escaped and
 * echoed as-is). JSON carries plain text, which the client escapes once itself.
 */
function htmlToText($value)
{
    return html_entity_decode((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * A stored name made safe for HTML output. Stored values may or may not already
 * be escaped, so decode first and escape exactly once: this neither double-escapes
 * ordinary names nor lets a raw payload through.
 */
function htmlSafe($value)
{
    return htmlspecialchars(htmlToText($value), ENT_QUOTES, 'UTF-8');
}

?>
