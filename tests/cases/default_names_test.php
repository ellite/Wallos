<?php
/*
  A new account's categories and payment methods are in that account's language.

  Every path that creates an account carried its own copy of the same English
  list, so somebody who registered in German got an application that was German
  everywhere except in its own data — on the dashboard, which is the first
  screen they ever see. The lists are held as translation keys now and resolved
  once, at the moment the account is created.

  Two properties are worth guarding rather than glancing at:

    - the fallback. There are thirty language files and these keys cannot be
      translated into all of them at once, so a file without them has to answer
      with the English string and never with the key itself.

    - the first account. It is the one account whose rows exist before a
      language does: createdatabase.php seeds them while the database is being
      created, and migration 000020 hands them to user 1. Renaming them at
      registration is the only moment the language is known and the rows are
      still untouched.
*/

require_once WALLOS_ROOT . '/includes/default_names.php';

/**
 * The languages Wallos ships a translation file for.
 *
 * @return string[]
 */
function default_names_test_languages()
{
    $languages = [];

    foreach (glob(WALLOS_ROOT . '/includes/i18n/*.php') as $file) {
        $name = basename($file, '.php');

        if ($name !== 'languages' && $name !== 'getlang') {
            $languages[] = $name;
        }
    }

    return $languages;
}

/**
 * The raw translation table of one language file, read without going through
 * the code under test.
 *
 * @param string $language
 * @return array<string, string>
 */
function default_names_test_table($language)
{
    $i18n = [];
    require WALLOS_ROOT . '/includes/i18n/' . $language . '.php';

    return $i18n;
}

/**
 * The names of one user's rows, in insertion order.
 *
 * @param SQLite3 $db
 * @param string  $table 'categories' or 'payment_methods'
 * @param int     $userId
 * @return string[]
 */
function default_names_test_rows($db, $table, $userId)
{
    $statement = $table === 'categories'
        ? $db->prepare('SELECT name FROM categories WHERE user_id = :userId ORDER BY id')
        : $db->prepare('SELECT name FROM payment_methods WHERE user_id = :userId ORDER BY id');

    $statement->bindValue(':userId', $userId, SQLITE3_INTEGER);
    $result = $statement->execute();

    $names = [];
    while ($result && $row = $result->fetchArray(SQLITE3_ASSOC)) {
        $names[] = $row['name'];
    }

    return $names;
}

wallos_test('the default categories are the account language, not English', function () {
    $english = default_categories('en');
    $german = default_categories('de');

    assert_same(17, count($english), 'English still has every default category');
    assert_same(17, count($german), 'German has every default category');

    assert_same('No category', $english[0], 'the first category is the placeholder');
    assert_same('Keine Kategorie', $german[0], 'and it is translated too');
    assert_same('Entertainment', $english[1], 'English second category');
    assert_same('Unterhaltung', $german[1], 'German second category');
    assert_same('Essen & Trinken', $german[4], 'an ampersand name is translated whole');

    assert_true($german !== $english, 'the two languages are not the same list');
});

wallos_test('the default payment methods translate the generic ones and keep the brands', function () {
    $english = default_payment_methods('en');
    $german = default_payment_methods('de');

    assert_same(31, count($english), 'every default payment method is there');
    assert_same(31, count($german), 'in every language');

    assert_same('PayPal', $english[0]['name'], 'a brand in English');
    assert_same('PayPal', $german[0]['name'], 'is the same brand in German');
    assert_same('Credit Card', $english[1]['name'], 'a generic method in English');
    assert_same('Kreditkarte', $german[1]['name'], 'is translated in German');
    assert_same('Bargeld', $german[4]['name'], 'and so is the last generic one');

    foreach ($english as $index => $method) {
        assert_same($method['icon'], $german[$index]['icon'],
            'the icon of ' . $method['name'] . ' does not depend on the language');
        assert_true(strpos($method['icon'], 'images/uploads/icons/') === 0,
            $method['name'] . ' keeps the icon path the rows are stored with');
    }
});

wallos_test('a language that has not translated a name yet answers in English', function () {
    // The property that makes this shippable without inventing translations:
    // every language file that does not carry a key answers with the English
    // string, and never with the key itself. Checked against the raw language
    // file rather than against a fixed expectation, so it keeps holding as
    // translations arrive.
    $english = default_categories('en');
    $englishMethods = default_payment_methods('en');
    $languages = default_names_test_languages();

    foreach ($languages as $language) {
        $table = default_names_test_table($language);
        $categories = default_categories($language);
        $methods = default_payment_methods($language);

        assert_same(17, count($categories), $language . ' has every default category');

        foreach (DEFAULT_CATEGORY_KEYS as $index => $key) {
            $expected = isset($table[$key]) ? $table[$key] : $english[$index];

            assert_same($expected, $categories[$index],
                $language . '/' . $key . ' is its own translation or the English one');
            assert_true(!in_array($categories[$index], DEFAULT_CATEGORY_KEYS, true),
                $language . '/' . $key . ' resolved to a name rather than to the key');
        }

        foreach (DEFAULT_PAYMENT_METHODS as $index => $method) {
            if (!isset($method['key'])) {
                assert_same($method['name'], $methods[$index]['name'],
                    $language . ' leaves the brand ' . $method['name'] . ' alone');

                continue;
            }

            $expected = isset($table[$method['key']])
                ? $table[$method['key']]
                : $englishMethods[$index]['name'];

            assert_same($expected, $methods[$index]['name'],
                $language . '/' . $method['key'] . ' is its own translation or the English one');
        }
    }

    assert_true(count($languages) >= 29,
        'every shipped language was checked (' . count($languages) . ')');
});

wallos_test('a language value that names no translation is English, not an error', function () {
    $english = default_categories('en');

    // The value arrives from a form field, an OIDC claim or the user table and
    // it decides a file name, so a path must never come out the other end.
    foreach (['', ' ', 'xx', 'not a language', '../i18n/en', 'en.php', '../../../etc/passwd', null] as $value) {
        assert_same($english, default_categories($value),
            var_export($value, true) . ' falls back to English');
    }
});

wallos_test('a language written as a BCP-47 tag finds its file', function () {
    // What a browser and an identity provider send. The file names use an
    // underscore, and a language nobody can reach is a language nobody gets.
    // "username" is a string the two Portuguese translations spell differently,
    // so it says which file was loaded.
    assert_same(default_names_test_table('pt_br')['username'],
        default_names_translations('pt-BR')['username'], 'pt-BR is the pt_br file');
    assert_same(default_names_test_table('pt')['username'],
        default_names_translations('pt')['username'], 'and pt is still pt');
    assert_true(default_names_test_table('pt')['username'] !== default_names_test_table('pt_br')['username'],
        'the two spellings really are different, so the check above can fail');
});

wallos_test('the seeding paths no longer carry an English list of their own', function () {
    // Three copies of the same list is how the English one survived being
    // fixed twice before. The names live in one file now.
    foreach (['registration.php', 'endpoints/admin/adduser.php', 'includes/oidc/oidc_create_user.php'] as $file) {
        $source = file_get_contents(WALLOS_ROOT . '/' . $file);

        assert_contains('default_categories($language)', $source,
            $file . ' seeds the categories in the account language');
        assert_contains('default_payment_methods($language)', $source,
            $file . ' seeds the payment methods in the account language');
        assert_not_contains("'Food & Beverages'", $source,
            $file . ' has no English category list left');
        assert_not_contains("'Charity & Donations'", $source,
            $file . ' has no English category list left');
        assert_not_contains("'Direct Debit'", $source,
            $file . ' has no English payment method list left');
    }

    // The first account is the one the loop above cannot describe: it is not
    // seeded at all, it is renamed.
    assert_contains('localize_default_names($db, $userId, $language)',
        file_get_contents(WALLOS_ROOT . '/registration.php'),
        'registration.php localizes the rows the first account inherits');
});

wallos_test('the first account is renamed into its language, rows and all', function () {
    // createdatabase.php seeded these rows and migration 000020 gave them to
    // user 1; the test database is built by running both, so this is the real
    // state a first registration finds.
    $db = wallos_test_open_database();
    wallos_test_create_user($db, 1, 'alice');

    $before = default_names_test_rows($db, 'categories', 1);
    assert_same(17, count($before), 'the installation seeded its categories');
    assert_same('No category', $before[0], 'in English');
    assert_same('Entertainment', $before[1], 'in English');

    $renamed = localize_default_names($db, 1, 'de');

    $after = default_names_test_rows($db, 'categories', 1);
    assert_same(default_categories('de'), $after, 'every category now reads in German');

    $methods = default_names_test_rows($db, 'payment_methods', 1);
    assert_same('PayPal', $methods[0], 'the brand is untouched');
    assert_same('Kreditkarte', $methods[1], 'the generic method is German');
    assert_same('Überweisung', $methods[2], 'and so is the next one');

    // 16 categories — "Software" is the same word in both languages, so that
    // row is not written at all — and the 4 generic payment methods.
    assert_same(20, $renamed, 'it reports what it renamed');

    $db->close();
});

wallos_test('renaming leaves English accounts and other people\'s rows alone', function () {
    $db = wallos_test_open_database();
    wallos_test_create_user($db, 1, 'alice');

    assert_same(0, localize_default_names($db, 1, 'en'),
        'an English account has nothing to rename');
    assert_same('Entertainment', default_names_test_rows($db, 'categories', 1)[1],
        'and its rows are untouched');

    // A second account's rows are its own, whatever language the first picks.
    wallos_test_create_user($db, 2, 'bob');
    $statement = $db->prepare('INSERT INTO categories (name, "order", user_id) VALUES (:name, 1, 2)');
    $statement->bindValue(':name', 'Entertainment', SQLITE3_TEXT);
    $statement->execute();

    localize_default_names($db, 1, 'de');

    assert_same(['Entertainment'], default_names_test_rows($db, 'categories', 2),
        "the other account's row is not renamed");

    $db->close();
});

wallos_test('a name somebody chose themselves is never rewritten', function () {
    $db = wallos_test_open_database();
    wallos_test_create_user($db, 1, 'alice');

    $statement = $db->prepare("UPDATE categories SET name = 'Filme' WHERE user_id = 1 AND name = 'Entertainment'");
    $statement->execute();

    localize_default_names($db, 1, 'de');

    $names = default_names_test_rows($db, 'categories', 1);
    assert_same('Filme', $names[1], 'a renamed row keeps the name it was given');
    assert_same('Musik', $names[2], 'while the untouched rows next to it are localized');

    $db->close();
});

wallos_test('every key the seeding uses exists in English', function () {
    // A typo in a key is invisible at runtime: it falls back to English, and
    // the English table does not have it either, so the account is seeded with
    // the key as its category name.
    $english = default_names_test_table('en');

    foreach (DEFAULT_CATEGORY_KEYS as $key) {
        assert_true(isset($english[$key]) && $english[$key] !== '',
            $key . ' has an English string');
    }

    foreach (DEFAULT_PAYMENT_METHODS as $method) {
        if (isset($method['key'])) {
            assert_true(isset($english[$method['key']]) && $english[$method['key']] !== '',
                $method['key'] . ' has an English string');
        }
    }
});
