<?php
/*
  Tests for OIDC callback endpoint and Nginx routing rules.
*/

wallos_test('nginx configurations exclude oidc directory from includes deny rule', function () {
    $configs = [
        WALLOS_ROOT . '/nginx.conf',
        WALLOS_ROOT . '/nginx.default.conf',
    ];

    foreach ($configs as $configPath) {
        assert_true(file_exists($configPath), 'Nginx config exists: ' . basename($configPath));
        $content = file_get_contents($configPath);

        assert_contains('^/includes/(?!oidc/).*\.php$', $content,
            'Nginx config ' . basename($configPath) . ' excludes oidc from deny rule');
    }
});

wallos_test('oidc callback script establishes database connection and root working directory', function () {
    $callbackScript = WALLOS_ROOT . '/includes/oidc/handle_oidc_callback.php';
    assert_true(file_exists($callbackScript), 'Callback script exists');

    $content = file_get_contents($callbackScript);
    assert_contains("chdir(dirname(__DIR__, 2))", $content,
        'Callback script switches to root working directory');
    assert_contains("require_once __DIR__ . '/../connect.php'", $content,
        'Callback script requires database connection');
});
