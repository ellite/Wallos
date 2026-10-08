<?php
/*
  The date format is a per-user choice from a fixed list.

  Only the keys in DATE_FORMATS are ever stored or accepted; the ICU pattern
  behind a key never comes from the client. "auto" is the format Wallos always
  had and must not change for anybody who never touches the setting.
*/

require_once WALLOS_ROOT . '/includes/date_format.php';

wallos_test('only whitelisted date format keys are accepted', function () {
    foreach (array_keys(DATE_FORMATS) as $key) {
        assert_same($key, parse_date_format($key), "'$key' is a valid date format");
    }

    foreach (['', null, 'yyyy', 'yyyy-MM-dd', 'AUTO', 1, true, ['iso']] as $invalid) {
        assert_same(null, parse_date_format($invalid), 'rejects ' . var_export($invalid, true));
        assert_same('auto', normalize_date_format($invalid), 'normalizes ' . var_export($invalid, true) . ' to auto');
    }
});

wallos_test('presets format a date as named', function () {
    $date = '2026-03-05';
    $expected = [
        'iso' => '2026-03-05',
        'dmy_slash' => '05/03/2026',
        'mdy_slash' => '03/05/2026',
        'dmy_dot' => '05.03.2026',
        'd_mmm_y' => '5 Mar 2026',
    ];

    foreach ($expected as $key => $output) {
        assert_same($output, format_user_date($date, 'en', $key), "preset $key");
    }

    $timestamp = strtotime($date);
    assert_same('2026-03-05', format_user_date($timestamp, 'en', 'iso'), 'accepts a timestamp');
    assert_same('2026-03-05', format_user_date(new DateTime($date), 'en', 'iso'), 'accepts a DateTime');
});

wallos_test('month names follow the locale', function () {
    $english = format_user_date('2026-03-05', 'en', 'd_mmm_y');
    $german = format_user_date('2026-03-05', 'de', 'd_mmm_y');

    assert_true($english !== $german, "de differs from en ($german vs $english)");
    assert_same('05.03.2026', format_user_date('2026-03-05', 'de', 'dmy_dot'), 'numeric presets are locale independent');
});

wallos_test('auto keeps the year-aware short format', function () {
    $now = new DateTime('2026-06-15');

    assert_same('Mar 5', format_user_date('2026-03-05', 'en', 'auto', $now), 'same year drops the year');
    assert_same('Mar 2027', format_user_date('2027-03-05', 'en', 'auto', $now), 'other year shows month and year');
    assert_same('Mar 5', format_user_date('2026-03-05', 'en', 'bogus', $now), 'unknown key behaves as auto');
});

wallos_test('an unusable locale falls back to English', function () {
    $output = format_user_date('2026-03-05', 'not a locale!!', 'iso');

    assert_same('2026-03-05', $output, 'falls back instead of throwing');
});

wallos_test('every display path goes through the date format setting', function () {
    $source = function ($path) {
        return file_get_contents(WALLOS_ROOT . '/' . $path);
    };

    foreach (['subscriptions.php', 'endpoints/subscriptions/get.php'] as $path) {
        assert_true(strpos($source($path), 'format_user_date(') !== false, "$path uses format_user_date");
    }

    foreach (['index.php', 'includes/list_subscriptions.php'] as $path) {
        assert_true(strpos($source($path), 'format_user_date(') !== false, "$path formatDate uses format_user_date");
        assert_true(strpos($source($path), 'new IntlDateFormatter') === false, "$path has no private date formatter");
    }

    foreach (['endpoints/settings/date_format.php', 'api/settings/set_settings.php'] as $path) {
        assert_true(strpos($source($path), 'parse_date_format(') !== false, "$path validates with parse_date_format");
    }
});

wallos_test('every language file names the setting', function () {
    foreach (glob(WALLOS_ROOT . '/includes/i18n/*.php') as $file) {
        $name = basename($file, '.php');

        if ($name === 'languages' || $name === 'getlang') {
            continue;
        }

        $i18n = [];
        require $file;

        foreach (['date_format', 'date_format_automatic'] as $key) {
            assert_true(!empty($i18n[$key]), "$name defines $key");
        }
    }
});

wallos_test('the date format migration defaults existing users to auto', function () {
    $db = wallos_test_open_database();

    assert_same('auto', $db->querySingle('SELECT date_format FROM settings WHERE user_id = 1'),
        'existing users keep the automatic format');

    $db->exec("UPDATE settings SET date_format = 'yyyy' WHERE user_id = 1");
    require WALLOS_ROOT . '/migrations/000060.php';
    assert_same('auto', $db->querySingle('SELECT date_format FROM settings WHERE user_id = 1'),
        'invalid stored values are reset to auto');

    $db->exec("UPDATE settings SET date_format = 'iso' WHERE user_id = 1");
    require WALLOS_ROOT . '/migrations/000060.php';
    assert_same('iso', $db->querySingle('SELECT date_format FROM settings WHERE user_id = 1'),
        'a valid choice survives a second run');

    $db->close();
});
