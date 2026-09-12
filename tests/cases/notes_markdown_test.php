<?php
/*
  Subscription notes are Markdown source, rendered through
  render_notes_markdown() (includes/markdown.php) in Parsedown safe mode.
  Nothing typed by a user is ever trusted as HTML: literal markup is escaped
  as text, and link/image URLs are filtered against a scheme allowlist. This
  matters because subscriptions are visible to other household members, so a
  malicious note is a stored-XSS path, not just a theoretical one.

  These tests assert the real render function and the real migration, not
  copies of their logic.
*/

require_once WALLOS_ROOT . '/includes/markdown.php';
require_once WALLOS_ROOT . '/includes/inputvalidation.php';
require_once WALLOS_ROOT . '/includes/webhook_helper.php';

wallos_test('markdown syntax renders to the expected safe HTML', function () {
    assert_contains('<strong>bold</strong>', render_notes_markdown('**bold**'),
        'bold syntax renders as <strong>');
    assert_contains('<em>italic</em>', render_notes_markdown('_italic_'),
        'italic syntax renders as <em>');
    assert_contains('<li>one</li>', render_notes_markdown("- one\n- two"),
        'a dash list renders as <li> items');
    assert_contains('<a href="https://example.com">link</a>', render_notes_markdown('[link](https://example.com)'),
        'a markdown link renders with its href');
});

wallos_test('a literal newline is preserved as a line break', function () {
    assert_contains('one<br', render_notes_markdown("one\ntwo"),
        'breaks-enabled mode turns a single newline into a <br>, giving real multi-line notes');
});

wallos_test('raw HTML typed as "markdown" is escaped, never rendered as markup', function () {
    $html = render_notes_markdown('<script>alert(1)</script>');
    assert_not_contains('<script>', $html, 'a literal <script> tag is not passed through');
    assert_contains('&lt;script&gt;', $html, 'it is escaped to text instead');

    $imgHtml = render_notes_markdown('<img src=x onerror=alert(1)>');
    assert_not_contains('<img', $imgHtml, 'no live <img> element is emitted');
    assert_contains('&lt;img', $imgHtml, 'the tag is escaped to inert text instead, onerror included');
});

wallos_test('a javascript: link does not produce a javascript: href', function () {
    $html = render_notes_markdown('[click me](javascript:alert(1))');
    assert_not_contains('javascript:', $html, 'the scheme is not in the safe-mode allowlist, so the href is dropped');
});

wallos_test('validate_markdown() does not HTML-escape, unlike validate()', function () {
    assert_same('AT&T "great" deal', validate_markdown(' AT&T "great" deal '),
        'only trimmed, not escaped: escaping now happens at render time');
    assert_contains('&amp;', validate(' AT&T '),
        'validate() (used for other fields) still escapes, for contrast');
});

wallos_test('the notes migration decodes existing escaped notes, and is safe to run twice', function () {
    $db = wallos_test_open_database();
    wallos_test_create_user($db, 1, 'alice');

    // What validate() (the pre-Markdown behaviour) would have stored for:
    // AT&T "great" deal's
    $escaped = 'AT&amp;T &quot;great&quot; deal&#039;s';

    $stmt = $db->prepare("INSERT INTO subscriptions (user_id, name, price, currency_id, next_payment, cycle, frequency, inactive, notes)
                          VALUES (1, 'Netflix', 9.99, :currencyId, date('now', '+5 days'), 3, 1, 0, :notes)");
    $stmt->bindValue(':currencyId', wallos_test_currency_id(1, 0), SQLITE3_INTEGER);
    $stmt->bindValue(':notes', $escaped, SQLITE3_TEXT);
    $stmt->execute();

    require WALLOS_ROOT . '/migrations/000058.php';

    $decoded = $db->querySingle("SELECT notes FROM subscriptions WHERE user_id = 1");
    assert_same("AT&T \"great\" deal's", $decoded, 'the stored notes are decoded back to plain text');

    require WALLOS_ROOT . '/migrations/000058.php';

    $decodedAgain = $db->querySingle("SELECT notes FROM subscriptions WHERE user_id = 1");
    assert_same("AT&T \"great\" deal's", $decodedAgain, 'running it again does not double-decode');

    $db->close();
});

wallos_test('webhookJsonEscape() keeps a multi-line note from breaking the payload JSON', function () {
    $note = "Family Plan \"shared\"\nCancelled\\pending\n\n- Alice\n- Bob";
    $escaped = webhookJsonEscape($note);

    $payload = '{"notes": "' . $escaped . '"}';
    $decoded = json_decode($payload, true);

    assert_true($decoded !== null, 'the templated payload is valid JSON');
    assert_same($note, $decoded['notes'] ?? null, 'and decodes back to the exact original note');
});

wallos_test('a note that ends in a quotation mark survives the escape', function () {
    // The one the helper as written could not do. trim($encoded, '"') strips
    // every leading and trailing quote character, not the two json_encode()
    // added, so a value ending in a quotation mark lost the closing quote of
    // its own \\" escape and left the payload ending in a bare backslash. He
    // said "hi" is enough: it came out as He said \\"hi\\ and the body was no
    // longer JSON, so the receiver rejected every webhook for that
    // subscription. The first and last character are exactly where a "strip
    // the quotes" implementation goes wrong, so they are what this asks about.
    $notes = [
        'a note ending in a quote' => 'cancel "soon"',
        'a note that is one quote' => '"',
        'a note quoted end to end' => '"quoted"',
        'a note ending in a backslash' => 'path\\',
        'a note that is empty' => '',
    ];

    foreach ($notes as $label => $note) {
        $payload = '{"notes": "' . webhookJsonEscape($note) . '"}';
        $decoded = json_decode($payload, true);

        assert_true($decoded !== null, $label . ' produces valid JSON: ' . $payload);
        assert_same($note, $decoded['notes'] ?? null,
            $label . ' decodes back to exactly what was written');
    }
});

wallos_test('both notification crons use the shared webhookJsonEscape() helper for notes', function () {
    foreach (['endpoints/cronjobs/sendnotifications.php', 'endpoints/cronjobs/sendcancellationnotifications.php'] as $file) {
        $source = file_get_contents(WALLOS_ROOT . '/' . $file);

        assert_contains('webhookJsonEscape($subscription[\'notes\'])', $source,
            $file . ' escapes notes before templating them into the webhook payload');
    }
});
