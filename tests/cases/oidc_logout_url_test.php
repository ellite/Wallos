<?php
require_once WALLOS_ROOT . '/includes/oidc/logout_url.php';

wallos_test('OIDC logout URL carries id_token_hint, client_id and the return address (#891)', function () {
    $url = oidc_build_logout_url('https://idp.example/realms/x/protocol/openid-connect/logout', 'https://wallos.example/', 'a.b.c', 'wallos');
    $query = [];
    parse_str(parse_url($url, PHP_URL_QUERY), $query);
    assert_same('a.b.c', $query['id_token_hint'], 'hint');
    assert_same('wallos', $query['client_id'], 'client id');
    assert_same('https://wallos.example/', $query['post_logout_redirect_uri'], 'return');
    assert_true(strpos($url, 'https://idp.example/realms/x/protocol/openid-connect/logout?') === 0, 'base kept');
});

wallos_test('OIDC logout URL tolerates a missing token and a URL that already has a query', function () {
    assert_same('https://idp/logout?post_logout_redirect_uri=https%3A%2F%2Fw%2F',
        oidc_build_logout_url('https://idp/logout', 'https://w/', null, null), 'no token');
    assert_same('https://idp/logout?a=1&client_id=c', oidc_build_logout_url('https://idp/logout?a=1', '', '', 'c'), 'existing query');
    assert_same('https://idp/logout', oidc_build_logout_url('https://idp/logout', '', null, null), 'nothing to add');
});

wallos_test('logout.php and the OIDC login keep the ID token for the logout', function () {
    assert_true(strpos(file_get_contents(WALLOS_ROOT . '/includes/oidc/oidc_login.php'), "\$_SESSION['oidc_id_token']") !== false, 'stored at login');
    assert_true(strpos(file_get_contents(WALLOS_ROOT . '/logout.php'), 'oidc_build_logout_url(') !== false, 'used at logout');
});
