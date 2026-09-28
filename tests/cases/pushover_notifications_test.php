<?php

require_once WALLOS_ROOT . '/includes/pushover_notifications.php';

function pushover_test_subscription(array $changes = []): array
{
    return $changes + [
        'name' => 'Stratechery Plus', 'amount' => 150, 'currency_code' => 'EUR',
        'date' => '2026-10-16', 'days' => 18, 'payment_method' => 'Visa &amp; Bank',
        'auto_renew' => 1, 'cycle' => 4,
    ];
}

wallos_test('pushover shows the complete invoice and payment method in the account language', function () {
    $payload = wallos_pushover_messages([pushover_test_subscription()], 'de', 'Daniel')[0];
    assert_same('Stratechery Plus · Zahlung in 18 Tagen', $payload['title'], 'localized title');
    assert_contains('150,00', $payload['message'], 'annual amount is not divided by twelve');
    assert_contains('€', $payload['message'], 'invoice currency');
    assert_contains('16.10.2026', $payload['message'], 'full localized date');
    assert_contains('Zahlungsmethode: Visa & Bank', $payload['message'], 'stored method is decoded');
    assert_contains('Verlängerung: automatisch', $payload['message'], 'automatic renewal');
    assert_contains('Daniel', $payload['message'], 'payer context survives');
    assert_same(0, $payload['priority'], 'normal priority');
    assert_true(!isset($payload['html']), 'plain text avoids interpreting stored names');
});

wallos_test('pushover locale cannot leak between consecutive accounts', function () {
    $de = wallos_pushover_messages([pushover_test_subscription()], 'de')[0];
    $en = wallos_pushover_messages([pushover_test_subscription()], 'en')[0];
    $deAgain = wallos_pushover_messages([pushover_test_subscription()], 'de-DE')[0];
    assert_contains('Payment in 18 days', $en['title'], 'second account uses English');
    assert_contains('150.00', $en['message'], 'second account uses English numbers');
    assert_same($de, $deAgain, 'third account returns to German without cached English format');
    $fallback = wallos_pushover_messages([pushover_test_subscription()], '../../bad')[0];
    assert_same($en, $fallback, 'untrusted language cannot become a file path or bad locale');
    $fr = wallos_pushover_messages([pushover_test_subscription()], 'fr')[0];
    assert_contains('Payment in 18 days', $fr['title'], 'untranslated new keys fall back to English');
    assert_contains('150,00', $fr['message'], 'existing locale still formats amounts');
});

wallos_test('pushover distinguishes today tomorrow manual renewal and one-time payments', function () {
    $today = wallos_pushover_messages([pushover_test_subscription(['days' => 0, 'auto_renew' => 0])], 'en')[0];
    assert_contains('Payment today', $today['title'], 'today');
    assert_contains('Manual renewal required', $today['message'], 'manual action');
    $tomorrow = wallos_pushover_messages([pushover_test_subscription(['days' => 1, 'cycle' => 5])], 'de')[0];
    assert_contains('Zahlung morgen', $tomorrow['title'], 'tomorrow');
    assert_contains('Einmalig', $tomorrow['message'], 'one-time purchase');
    assert_not_contains('Verlängerung', $tomorrow['message'], 'one-time purchase does not claim renewal');
    $missing = wallos_pushover_messages([pushover_test_subscription(['payment_method' => ''])], 'en')[0];
    assert_not_contains('Payment Method:', $missing['message'], 'missing method does not invent a value');
});

wallos_test('pushover keeps multiple subscriptions and a period summary together', function () {
    $subscriptions = [pushover_test_subscription(), pushover_test_subscription(['name' => 'Music', 'days' => 1])];
    $payloads = wallos_pushover_messages($subscriptions, 'en', 'Alice', 'Budget: €300');
    assert_same(1, count($payloads), 'small batch stays one push');
    assert_same('2 upcoming payments', $payloads[0]['title'], 'bundle title');
    foreach (['Stratechery Plus', 'Music', 'Payment tomorrow', 'Alice', 'Budget: €300'] as $text) {
        assert_contains($text, $payloads[0]['message'], 'batch retains ' . $text);
    }
    assert_same([], wallos_pushover_messages([], 'en'), 'no empty notification');
    $summary = wallos_pushover_messages([], 'en', 'Alice', 'Budget: €300');
    assert_same(1, count($summary), 'summary-only notification survives');
    assert_contains('Budget: €300', $summary[0]['message'], 'summary-only content survives');
});

wallos_test('pushover splits long Unicode batches within provider limits without losing names', function () {
    $name = str_repeat('長😀', 700);
    $summary = str_repeat('Résumé ', 180);
    $payloads = wallos_pushover_messages([pushover_test_subscription(['name' => $name])], 'de', '', $summary);
    assert_true(count($payloads) > 1, 'oversized reminder split');
    $all = '';
    foreach ($payloads as $index => $payload) {
        assert_true(preg_match('//u', $payload['message']) === 1, 'message has valid UTF-8');
        assert_true(count(wallos_pushover_characters($payload['message'])) <= 1024, 'message limit');
        assert_true(count(wallos_pushover_characters($payload['title'])) <= 250, 'title limit');
        assert_contains('(' . ($index + 1) . '/' . count($payloads) . ')', $payload['title'], 'part number');
        $all .= $payload['message'];
    }
    assert_contains($name, $all, 'the full long name survives in message chunks');
    assert_contains($summary, $all, 'full long summary survives');

    $many = [];
    for ($i = 0; $i < 30; $i++) {
        $many[] = pushover_test_subscription(['name' => 'Subscription-' . $i]);
    }
    $messages = wallos_pushover_messages($many, 'en');
    $body = implode("\n", array_column($messages, 'message'));
    foreach ($many as $subscription) {
        assert_contains($subscription['name'] . ' ·', $body, 'all batched subscriptions retained');
    }
});

wallos_test('pushover links use the configured HTTP base path and tests are clearly marked', function () {
    $payload = wallos_pushover_messages([pushover_test_subscription()], 'de', '', '', 'https://wallos.example/home/', true)[0];
    assert_same('https://wallos.example/home/subscriptions.php', $payload['url'], 'subpath preserved');
    assert_same('Wallos öffnen', $payload['url_title'], 'localized link');
    assert_contains('Test · ', $payload['title'], 'sample clearly identified');
    assert_same('http://192.168.42.2:8282/subscriptions.php',
        wallos_pushover_subscriptions_url('http://192.168.42.2:8282'), 'local instance supported');
    foreach (['', 'javascript:alert(1)', 'ftp://wallos.example', 'https://user:secret@wallos.example',
        'https://wallos.example/?x=1', 'https://wallos.example/#fragment'] as $url) {
        $payload = wallos_pushover_messages([pushover_test_subscription()], 'en', '', '', $url)[0];
        assert_true(!isset($payload['url']), 'unusable or credential-bearing base omitted: ' . $url);
    }
});

wallos_test('pushover payment methods never expose another account names', function () {
    $db = wallos_test_open_database();
    $db->exec("DELETE FROM payment_methods");
    $db->exec("INSERT INTO payment_methods (id, name, user_id, enabled) VALUES
        (1, 'Alice Visa', 1, 0), (2, 'Bob Bank', 2, 1)");
    assert_same([1 => 'Alice Visa'], wallos_pushover_payment_methods($db, 1), 'owner only, including disabled in-use method');
    assert_same([2 => 'Bob Bank'], wallos_pushover_payment_methods($db, 2), 'second account only');
    assert_same([], wallos_pushover_payment_methods($db, 3), 'empty account');
    $db->close();
});

wallos_test('pushover confirms provider acceptance instead of only transport success', function () {
    assert_true(wallos_pushover_response_success(200, '{"status":1,"request":"test"}'), 'successful API response');
    foreach ([[0, false], [200, false], [200, 'invalid'], [200, 'null'], [200, '1'],
        [200, '{"status":0}'], [200, '{"status":"1"}'], [400, '{"status":1}'],
        [429, '{"status":0}'], [500, '{"status":1}']] as [$status, $body]) {
        assert_true(!wallos_pushover_response_success($status, $body), 'rejected or ambiguous response fails');
    }
});

wallos_test('pushover reuses established language aliases', function () {
    foreach (['zh-Hant' => 'zh_tw', 'zh-Hans' => 'zh_cn', 'sr-Latn' => 'sr_lat', 'jp' => 'ja', 'pt-BR' => 'pt_br'] as $tag => $language) {
        assert_same($language, wallos_pushover_language($tag), 'established alias ' . $tag);
        assert_same(wallos_pushover_messages([pushover_test_subscription()], $language),
            wallos_pushover_messages([pushover_test_subscription()], $tag), 'same rendering for ' . $tag);
    }
});

wallos_test('pushover repeats payer context in every part of a household batch', function () {
    $subscriptions = [];
    for ($i = 0; $i < 20; $i++) {
        $subscriptions[] = pushover_test_subscription(['name' => 'Household subscription ' . $i]);
    }
    $messages = wallos_pushover_messages($subscriptions, 'de', 'Alice &amp; Bob');
    assert_true(count($messages) > 1, 'batch needs multiple pushes');
    foreach ($messages as $message) {
        assert_contains('Alice & Bob', $message['message'], 'every part identifies the payer');
        assert_true(count(wallos_pushover_characters($message['message'])) <= 1024, 'payer included in limit');
    }
});

wallos_test('pushover budget summary uses the account locale including remaining budget', function () {
    $de = wallos_pushover_period_summary(150.5, 300, 'EUR', 'de');
    assert_contains('150,50', $de, 'German amount');
    assert_contains('149,50', $de, 'remaining budget');
    $en = wallos_pushover_period_summary(150.5, 100, 'USD', 'en');
    assert_contains('$150.50', $en, 'English amount and original currency');
    assert_contains('$0.00', $en, 'remaining budget cannot be negative');
    assert_not_contains(' | ', wallos_pushover_period_summary(150, 0, 'EUR', 'de'), 'no budget means no remaining budget');
});
