<?php

/**
 * Render subscription notes (Markdown source) to safe HTML.
 *
 * Uses Parsedown's safe mode: any raw HTML the user typed is escaped as
 * text rather than rendered as markup, and link/image URLs are filtered
 * against a scheme allowlist (javascript: is not in it). The output is
 * restricted by construction to the tags Parsedown itself emits from real
 * Markdown syntax, so it's safe to insert with innerHTML.
 *
 * @param string|null $text
 * @return string
 */
function render_notes_markdown($text)
{
    require_once __DIR__ . '/../libs/Parsedown.php';

    static $parser;
    if ($parser === null) {
        $parser = new Parsedown();
        $parser->setSafeMode(true);
        $parser->setBreaksEnabled(true);
    }

    return $parser->text((string) $text);
}

?>
