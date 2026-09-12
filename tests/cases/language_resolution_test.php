<?php
/*
  A language value that is not already a file name still finds its translation.

  The keys of $languages are file names — "pt_br", "zh_cn", "sr_lat" — and
  nothing that hands Wallos a language hands it a file name. A browser sends
  "de-DE" in Accept-Language. An identity provider sends "pt-BR" in the standard
  OIDC "locale" claim. Matching either against the keys directly fails.

  The failure is silent, which is what makes it worth a test rather than a
  glance: there is no error, the account is simply in English. And for an
  account an identity provider created there is no second chance to notice,
  because the person never went through the registration form that would have
  asked them for a language.

  includes/oidc/oidc_create_user.php made that certain rather than likely: it
  hardcoded 'en' and never looked at the claim at all.
*/

require_once WALLOS_ROOT . '/includes/i18n/languages.php';

wallos_test('a file name resolves to itself', function () {
    foreach (['en', 'de', 'ar', 'pt', 'pt_br', 'zh_cn', 'zh_tw', 'sr', 'sr_lat'] as $name) {
        assert_same($name, resolve_language($name), $name . ' is already a translation file');
    }
});

wallos_test('the identifiers this project used before still work', function () {
    // The cookie of anybody who chose Japanese before it was renamed.
    assert_same('ja', resolve_language('jp'), 'jp is the old name for ja');
});

wallos_test('a tag with a region resolves to the language', function () {
    // What a browser sends in Accept-Language, and what most providers put in
    // the locale claim.
    $tags = [
        'de-DE' => 'de',
        'de-AT' => 'de',
        'de-CH' => 'de',
        'en-GB' => 'en',
        'en-US' => 'en',
        'pt-PT' => 'pt',
        'fr-CA' => 'fr',
        'es-MX' => 'es',
        // A tag can carry a variant and a private-use section and still be a
        // perfectly ordinary request for German.
        'de-DE-1996-x-private' => 'de',
    ];

    foreach ($tags as $tag => $expected) {
        assert_same($expected, resolve_language($tag), $tag . ' is ' . $expected);
    }
});

wallos_test('a region that has its own translation keeps it', function () {
    // pt-BR must not collapse to pt: both files exist and they are different
    // translations, so answering the wrong one is worse than answering English.
    assert_same('pt_br', resolve_language('pt-BR'), 'pt-BR has its own file');
    assert_same('pt', resolve_language('pt'), 'and pt is still pt');
    assert_same('zh_cn', resolve_language('zh-CN'), 'zh-CN has its own file');
    assert_same('zh_tw', resolve_language('zh-TW'), 'zh-TW has its own file');
});

wallos_test('a tag that names a script rather than a region resolves too', function () {
    // "zh-Hans" and "zh-Hant" are how the two Chinese written forms are named
    // when the sender does not want to claim a country. Neither is a file, and
    // "zh" alone is not one either, so without this both are English.
    assert_same('zh_cn', resolve_language('zh-Hans'), 'simplified Chinese');
    assert_same('zh_tw', resolve_language('zh-Hant'), 'traditional Chinese');
    assert_same('sr_lat', resolve_language('sr-Latn'), 'Serbian in Latin script');
});

wallos_test('case and separator do not decide the answer', function () {
    foreach (['DE', 'de-de', 'DE-DE', 'de_DE', 'PT-br', 'zh_HANS'] as $tag) {
        $resolved = resolve_language($tag);

        assert_true($resolved !== 'en' || strtolower($tag) === 'en',
            $tag . ' resolves to a translation (got ' . $resolved . ')');
    }

    assert_same('de', resolve_language(' de-DE '), 'surrounding space is not part of the tag');
});

wallos_test('anything else answers English, as the callers did before', function () {
    foreach (['', null, 'xx', 'not a language', 'xx-YY', '../en', 'en.php'] as $value) {
        assert_same('en', resolve_language($value),
            var_export($value, true) . ' names no translation');
    }
});

wallos_test('every answer names a translation file that exists', function () {
    // The guarantee the callers rest on: whatever comes back is included as
    // 'i18n/' . $lang . '.php' a few lines later, so an answer that is not a
    // file is a fatal error on every page.
    global $languages;

    $checked = 0;

    foreach (array_keys($languages) as $name) {
        foreach ([$name, str_replace('_', '-', $name), strtoupper($name)] as $spelling) {
            $resolved = resolve_language($spelling);
            $checked++;

            assert_true(is_file(WALLOS_ROOT . '/includes/i18n/' . $resolved . '.php'),
                $spelling . ' resolved to ' . $resolved . ', which has a translation file');
            assert_true(is_file(WALLOS_ROOT . '/scripts/i18n/' . $resolved . '.js'),
                $spelling . ' resolved to ' . $resolved . ', which has a script file');
        }
    }

    // A guard that finds nothing passes every assertion above it.
    assert_true($checked >= 60, 'every registered language was tried three ways (' . $checked . ')');
});

wallos_test('an account created by an identity provider is not hardcoded to English', function () {
    // The defect this exists for. The claim is read, and 'en' is the fallback
    // rather than the answer.
    $source = file_get_contents(WALLOS_ROOT . '/includes/oidc/oidc_create_user.php');

    assert_contains("resolve_language(\$userInfo['locale']", $source,
        'the account language comes from the provider when it named one');
    assert_true(preg_match("/^\\s*\\\$language\\s*=\\s*'en'\\s*;/m", $source) !== 1,
        'and is not assigned a constant English');
});

wallos_test('the cookie goes through the same resolution', function () {
    // Two places decide a language and they have to agree: an account created
    // as "pt-BR" must not then be read back as English on the next request.
    $source = file_get_contents(WALLOS_ROOT . '/includes/i18n/getlang.php');

    assert_contains("resolve_language(\$_COOKIE['language'])", $source,
        'the language cookie is resolved rather than matched against file names');
});
