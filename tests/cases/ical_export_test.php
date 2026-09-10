<?php
/*
  Notes can now be multi-paragraph Markdown instead of a single short line,
  so the calendar export's DESCRIPTION line (built from price/category/
  payment method/payer/notes) can genuinely exceed RFC 5545's 75-octet
  line-length limit, where before it rarely did. icalFold() (includes/
  ical_helper.php) folds long lines so stricter calendar clients don't
  mis-parse them.

  Also asserts endpoints/subscription/exportcalendar.php no longer decodes
  notes as HTML entities before escaping them: notes stopped being stored
  HTML-escaped, so that decode is now wrong (it would mangle a literal "&"
  a user typed in a note).
*/

require_once WALLOS_ROOT . '/includes/ical_helper.php';

wallos_test('a short line is returned unfolded', function () {
    assert_same('SUMMARY:Netflix', icalFold('SUMMARY:Netflix'),
        'nothing to fold, nothing changes');
});

wallos_test('a long line is folded at 75 octets, with a leading space on continuations', function () {
    $line = 'DESCRIPTION:' . str_repeat('a', 100);
    $folded = icalFold($line);
    $parts = explode("\r\n", $folded);

    assert_true(count($parts) > 1, 'the line was actually split');
    foreach ($parts as $i => $part) {
        assert_true(strlen($part) <= 76, "part $i is within the fold budget (75 content + 1 leading space)");
        if ($i > 0) {
            assert_same(' ', $part[0], "continuation part $i starts with the RFC 5545 single leading space");
        }
    }
});

wallos_test('folding a line and stripping the fold markers recovers the original bytes', function () {
    $line = 'DESCRIPTION:Price: 28.00 EUR\\nNotes: ' . str_repeat('Família ', 20);
    $folded = icalFold($line);
    $unfolded = str_replace("\r\n ", '', $folded);

    assert_same($line, $unfolded, 'folding is lossless');
});

wallos_test('folding never splits a multi-byte UTF-8 character across lines', function () {
    // Every character here is a 2-byte UTF-8 sequence, chosen so a naive
    // byte-count split would land mid-character somewhere in 200 bytes.
    $line = 'DESCRIPTION:' . str_repeat('é', 100);
    $folded = icalFold($line);

    foreach (explode("\r\n", $folded) as $part) {
        $part = ltrim($part, ' ');
        assert_true(json_encode($part) !== false, 'each folded chunk is still valid UTF-8 on its own (json_encode rejects a truncated multi-byte sequence)');
    }
});

wallos_test('the two ICS export code paths no longer HTML-decode notes before escaping them', function () {
    foreach (['endpoints/subscription/exportcalendar.php', 'api/subscriptions/get_ical_feed.php'] as $file) {
        $source = file_get_contents(WALLOS_ROOT . '/' . $file);

        assert_not_contains("html_entity_decode(\$subscription['notes']", $source,
            $file . ': notes are no longer stored HTML-escaped, so decoding them here would corrupt a literal & or \' a user typed');
        assert_contains("icalEscape(\$subscription['notes'])", $source,
            $file . ': notes still go through icalEscape() directly');
        assert_contains("icalFold('DESCRIPTION:' . \$description)", $source,
            $file . ': the DESCRIPTION line is folded to the RFC 5545 limit');
    }
});
