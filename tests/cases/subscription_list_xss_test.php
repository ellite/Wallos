<?php
/* The escaping helpers live in a dependency-free include. */
function list_xss_load_helpers() {
    if (function_exists('htmlSafe')) {
        return;
    }
    require_once WALLOS_ROOT . '/includes/html_text.php';
}

wallos_test('stored names are escaped exactly once, whether or not they were stored escaped', function () {
    list_xss_load_helpers();
    assert_same('Cloud &amp; Co', htmlSafe('Cloud & Co'), 'raw ampersand escaped');
    assert_same('Cloud &amp; Co', htmlSafe('Cloud &amp; Co'), 'already-escaped value not escaped twice');
    assert_same('&lt;img src=x onerror=&quot;alert(1)&quot;&gt;', htmlSafe('<img src=x onerror="alert(1)">'), 'raw tag neutralised');
    assert_same('&lt;img src=x onerror=&quot;alert(1)&quot;&gt;', htmlSafe('&lt;img src=x onerror=&quot;alert(1)&quot;&gt;'), 'escaped tag stays inert text');
    assert_same('&quot;&gt;&lt;svg onload=x&gt;', htmlSafe('"><svg onload=x>'), 'attribute breakout neutralised');
    assert_same('', htmlSafe(null), 'null is empty');
});

wallos_test('the subscription list page escapes every stored name it prints', function () {
    $patterns = [
        'includes/list_subscriptions.php' => ["htmlSafe(\$heading)", "htmlSafe(\$subscription['name'])", "htmlSafe(translate('payment_method'"],
        'subscriptions.php' => ["htmlSafe(\$category['name'])", "htmlSafe(\$payment['name'])", "htmlSafe(\$member['name'])"],
        'includes/filters_menu.php' => ["htmlSafe(\$member['name'])", "htmlSafe(\$category['name'])", "htmlSafe(\$payment['name'])"],
    ];
    foreach ($patterns as $file => $needles) {
        $source = file_get_contents(WALLOS_ROOT . '/' . $file);
        foreach ($needles as $needle) {
            assert_true(strpos($source, $needle) !== false, $file . ' escapes ' . $needle);
        }
        assert_true(!preg_match('/<\?= \$(subscription|member|category|payment)\[\'name\'\] \?>/', $source), $file . ' has no unescaped name echo');
    }
});

wallos_test('the statistics page and chart legend escape stored names', function () {
    $stats = file_get_contents(WALLOS_ROOT . '/stats.php');
    foreach (["htmlSafe(\$member['name'])", "htmlSafe(\$categoryName)", "htmlSafe(\$payment['name'])",
              "htmlSafe(\$mostExpensiveSubscription['name'])", "htmlSafe(\$cheapestSubscription['name'])", "htmlSafe(\$oldestSubscription['name'])"] as $needle) {
        assert_true(strpos($stats, $needle) !== false, 'stats.php escapes ' . $needle);
    }
    assert_true(!preg_match('/<\?= \$\w*Subscription\[\'name\'\] \?>/', $stats), 'stats.php has no unescaped subscription name echo');
    assert_true(!preg_match('/alt="\' \. \$\w+\[\'name\'\]/', $stats), 'stats.php has no unescaped name in alt/title attributes');
    $js = file_get_contents(WALLOS_ROOT . '/scripts/stats.js');
    assert_true(!preg_match('/innerHTML\s*=.*p\.label/', $js), 'the chart legend never writes labels with innerHTML');
});
