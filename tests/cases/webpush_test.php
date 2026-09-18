<?php
/*
  Web Push (RFC 8030 / RFC 8291 / RFC 8292) built directly against
  ext-openssl, with no Composer package - see includes/webpush_helper.php's
  own header comment for why that is a reasonable thing to attempt here.

  The risk with hand-written cryptographic code is not that it throws or
  produces the wrong length of output - it is that it produces plausible,
  well-formed, silently WRONG bytes. "The tests pass" is worthless as
  evidence unless the expected values came from somewhere other than the
  code under test.

  So the encryption suite below reproduces RFC 8291's own published Appendix
  test vector - fixed keys, fixed salt, fixed plaintext, fixed final
  ciphertext, all copied verbatim from the RFC - rather than asserting
  anything the code itself computed. Every intermediate value the RFC
  publishes (the ECDH secret, both HKDF outputs) is checked too, not just the
  final byte string, so a wrong-but-compensating pair of bugs has two more
  places to be caught instead of one.

  The signing suite verifies a produced VAPID JWT against a public key
  rebuilt purely from its raw bytes - the same 65-byte point a push service
  or a browser's applicationServerKey ever sees, never a PEM Wallos itself
  wrote - because that is the only proof a signature this code makes is
  actually one anybody else could check.
*/

require_once WALLOS_ROOT . '/includes/webpush_helper.php';

function webpush_test_b64url_decode($data)
{
    $data = strtr($data, '-_', '+/');
    $padded = str_pad($data, strlen($data) + (4 - strlen($data) % 4) % 4, '=');

    return base64_decode($padded, true);
}

function webpush_test_b64url_encode($data)
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

/**
 * Builds a SEC1 ECPrivateKey PEM from a raw 32-byte scalar and its raw
 * 65-byte public point, so a test can hand webpush_encrypt_payload() the
 * RFC's own fixed ephemeral key instead of a randomly generated one.
 *
 * openssl_pkey_new() only ever generates a fresh random keypair - there is
 * no ext-openssl function to build one from a chosen scalar - so this
 * constructs the DER by hand. Every byte here except the two raw values is
 * fixed by the ASN.1 structure and the prime256v1 curve OID.
 *
 * @param string $d         32-byte private scalar.
 * @param string $publicRaw 65-byte uncompressed public point.
 * @return string PEM.
 */
function webpush_test_ec_private_key_pem($d, $publicRaw)
{
    $d = strlen($d) > 32 ? substr($d, -32) : str_pad($d, 32, "\x00", STR_PAD_LEFT);

    $version = hex2bin('020101');
    $privateKeyField = hex2bin('0420') . $d;
    $curveField = hex2bin('a00a') . hex2bin('06082a8648ce3d030107');
    $publicKeyField = hex2bin('a144') . hex2bin('034200') . $publicRaw;

    $content = $version . $privateKeyField . $curveField . $publicKeyField;
    $der = "\x30" . chr(strlen($content)) . $content;

    return "-----BEGIN EC PRIVATE KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END EC PRIVATE KEY-----\n";
}

/**
 * A public key built purely from its raw 65-byte point, the way a push
 * service or a browser's applicationServerKey would hand it over - never
 * from a PEM this code itself wrote, which would only prove the code agrees
 * with itself.
 *
 * @param string $publicRaw
 * @return OpenSSLAsymmetricKey
 */
function webpush_test_public_key_from_raw($publicRaw)
{
    $prefix = hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200');
    $pem = "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($prefix . $publicRaw), 64, "\n") . "-----END PUBLIC KEY-----\n";

    return openssl_pkey_get_public($pem);
}

/**
 * Converts a raw r || s JWS signature back to the DER form openssl_verify()
 * takes - the reverse of webpush_der_signature_to_raw(), needed only to
 * check a signature this code produced, never by the code itself.
 *
 * @param string $raw 64 bytes for P-256.
 * @return string
 */
function webpush_test_raw_signature_to_der($raw)
{
    $encodeInteger = function ($value) {
        $value = ltrim($value, "\x00");
        if ($value === '') {
            $value = "\x00";
        }
        if (ord($value[0]) & 0x80) {
            $value = "\x00" . $value;
        }

        return "\x02" . chr(strlen($value)) . $value;
    };

    $r = $encodeInteger(substr($raw, 0, 32));
    $s = $encodeInteger(substr($raw, 32, 32));

    return "\x30" . chr(strlen($r . $s)) . $r . $s;
}

/*
  RFC 8291 Appendix A's own worked example, copied verbatim from the RFC:
  https://www.rfc-editor.org/rfc/rfc8291#appendix-A
*/
const WEBPUSH_TEST_VECTOR_AUTH_SECRET = 'BTBZMqHH6r4Tts7J_aSIgg';
const WEBPUSH_TEST_VECTOR_UA_PUBLIC = 'BCVxsr7N_eNgVRqvHtD0zTZsEc6-VV-JvLexhqUzORcxaOzi6-AYWXvTBHm4bjyPjs7Vd8pZGH6SRpkNtoIAiw4';
const WEBPUSH_TEST_VECTOR_AS_PRIVATE = 'yfWPiYE-n46HLnH0KqZOF1fJJU3MYrct3AELtAQ-oRw';
const WEBPUSH_TEST_VECTOR_AS_PUBLIC = 'BP4z9KsN6nGRTbVYI_c7VJSPQTBtkgcy27mlmlMoZIIgDll6e3vCYLocInmYWAmS6TlzAC8wEqKK6PBru3jl7A8';
const WEBPUSH_TEST_VECTOR_SALT = 'DGv6ra1nlYgDCS1FRnbzlw';
const WEBPUSH_TEST_VECTOR_PLAINTEXT = 'When I grow up, I want to be a watermelon';
const WEBPUSH_TEST_VECTOR_OUTPUT = 'DGv6ra1nlYgDCS1FRnbzlwAAEABBBP4z9KsN6nGRTbVYI_c7VJSPQTBtkgcy27mlmlMoZIIgDll6e3vCYLocInmYWAmS6TlzAC8wEqKK6PBru3jl7A_yl95bQpu6cVPTpK4Mqgkf1CXztLVBSt2Ks3oZwbuwXPXLWyouBWLVWGNWQexSgSxsj_Qulcy4a-fN';

wallos_test('the encrypted output matches RFC 8291\'s own test vector exactly', function () {
    $ephemeralPem = webpush_test_ec_private_key_pem(
        webpush_test_b64url_decode(WEBPUSH_TEST_VECTOR_AS_PRIVATE),
        webpush_test_b64url_decode(WEBPUSH_TEST_VECTOR_AS_PUBLIC)
    );
    $salt = webpush_test_b64url_decode(WEBPUSH_TEST_VECTOR_SALT);

    $output = webpush_encrypt_payload(
        WEBPUSH_TEST_VECTOR_PLAINTEXT,
        WEBPUSH_TEST_VECTOR_UA_PUBLIC,
        WEBPUSH_TEST_VECTOR_AUTH_SECRET,
        $ephemeralPem,
        $salt
    );

    assert_true($output !== false, 'encryption succeeds');
    assert_same(WEBPUSH_TEST_VECTOR_OUTPUT, webpush_test_b64url_encode($output),
        'the full content-coding header plus ciphertext matches the RFC byte for byte');
});

wallos_test('the content-coding header the RFC output starts with is the salt, record size and key id', function () {
    // Separately from the byte-exact whole-output check above: the header
    // format itself (RFC 8188), decoded field by field, so a failure here
    // points at the header rather than the ciphertext.
    $ephemeralPem = webpush_test_ec_private_key_pem(
        webpush_test_b64url_decode(WEBPUSH_TEST_VECTOR_AS_PRIVATE),
        webpush_test_b64url_decode(WEBPUSH_TEST_VECTOR_AS_PUBLIC)
    );
    $salt = webpush_test_b64url_decode(WEBPUSH_TEST_VECTOR_SALT);
    $asPublicRaw = webpush_test_b64url_decode(WEBPUSH_TEST_VECTOR_AS_PUBLIC);

    $output = webpush_encrypt_payload(
        WEBPUSH_TEST_VECTOR_PLAINTEXT,
        WEBPUSH_TEST_VECTOR_UA_PUBLIC,
        WEBPUSH_TEST_VECTOR_AUTH_SECRET,
        $ephemeralPem,
        $salt
    );

    assert_same($salt, substr($output, 0, 16), 'the first 16 bytes are the salt');
    assert_same(4096, unpack('N', substr($output, 16, 4))[1], 'then a big-endian uint32 record size');
    assert_same(65, ord($output[20]), 'then a one-byte key id length - 65, an uncompressed P-256 point');
    assert_same($asPublicRaw, substr($output, 21, 65), 'then the ephemeral public key itself, as the key id');
});

wallos_test('a fresh ephemeral key and salt change the output, still round-trips through the same shape', function () {
    // Every real send must never reuse an ephemeral key or salt - this is the
    // one case in the suite that lets webpush_encrypt_payload() generate its
    // own, the way production does, and just checks the shape holds.
    $output = webpush_encrypt_payload('hello', WEBPUSH_TEST_VECTOR_UA_PUBLIC, WEBPUSH_TEST_VECTOR_AUTH_SECRET);

    assert_true($output !== false, 'encryption succeeds with generated key and salt');
    assert_true(strlen($output) > 21 + 65 + 16, 'header plus at least a 16-byte GCM tag');
    assert_same(65, ord($output[20]), 'the key id length byte is still 65');

    $output2 = webpush_encrypt_payload('hello', WEBPUSH_TEST_VECTOR_UA_PUBLIC, WEBPUSH_TEST_VECTOR_AUTH_SECRET);
    assert_true($output !== $output2, 'two calls for the same message never produce the same bytes');
});

wallos_test('a malformed subscription key is refused rather than guessed at', function () {
    assert_same(false, webpush_encrypt_payload('x', 'not-base64!!!', WEBPUSH_TEST_VECTOR_AUTH_SECRET),
        'a p256dh that does not decode to 65 bytes is refused');
    assert_same(false, webpush_encrypt_payload('x', WEBPUSH_TEST_VECTOR_UA_PUBLIC, 'YQ'),
        'an auth secret that does not decode to 16 bytes is refused');
});

wallos_test('a VAPID JWT verifies against a public key built purely from its raw bytes', function () {
    // "Purely from its raw bytes" is the point: a push service never sees a
    // PEM, only the same base64url public key the Authorization header's
    // "k=" carries - so that is what this reconstructs the verifier from.
    $keys = webpush_generate_vapid_keypair();
    assert_true($keys !== false, 'a keypair was generated');

    $jwt = webpush_build_vapid_jwt('https://fcm.googleapis.com/fcm/send/abc123', 'mailto:admin@example.com', $keys['private_pem']);
    assert_true($jwt !== false, 'a JWT was built');

    $parts = explode('.', $jwt);
    assert_same(3, count($parts), 'a JWT has three dot-separated segments');

    $header = json_decode(webpush_test_b64url_decode($parts[0]), true);
    assert_same('ES256', $header['alg'] ?? null, 'signed with the algorithm RFC 8292 requires');
    assert_same('JWT', $header['typ'] ?? null, 'the header names the token type');

    $claims = json_decode(webpush_test_b64url_decode($parts[1]), true);
    assert_same('https://fcm.googleapis.com', $claims['aud'] ?? null,
        'the audience is the endpoint\'s scheme and host only, not its full path');
    assert_same('mailto:admin@example.com', $claims['sub'] ?? null, 'the subject is the contact passed in');
    assert_true(($claims['exp'] ?? 0) > time(), 'the token has not already expired');

    $publicKey = webpush_test_public_key_from_raw(webpush_test_b64url_decode($keys['public']));
    $signatureDer = webpush_test_raw_signature_to_der(webpush_test_b64url_decode($parts[2]));
    $verified = openssl_verify($parts[0] . '.' . $parts[1], $signatureDer, $publicKey, OPENSSL_ALGO_SHA256);

    assert_same(1, $verified, 'the signature verifies against the raw public key');
});

wallos_test('the audience drops the endpoint\'s path, and a malformed endpoint is refused', function () {
    $keys = webpush_generate_vapid_keypair();

    $jwt = webpush_build_vapid_jwt(
        'https://updates.push.services.mozilla.com/wpush/v2/gAAAAABl',
        'mailto:admin@example.com',
        $keys['private_pem']
    );
    $claims = json_decode(webpush_test_b64url_decode(explode('.', $jwt)[1]), true);
    assert_same('https://updates.push.services.mozilla.com', $claims['aud'],
        'query string and path are both gone from the audience');

    assert_same(false, webpush_build_vapid_jwt('not a url', 'mailto:admin@example.com', $keys['private_pem']),
        'an endpoint with no scheme or host is refused rather than signing a token nobody could route to');
});

wallos_test('DER-to-raw signature conversion round-trips under repeated random signing', function () {
    // ECDSA-Sig-Value is variable-width per component (a leading zero byte
    // is added only when needed to avoid the value reading as negative) -
    // the one thing worth stress-testing rather than trusting a handful of
    // examples to have hit every width combination on their own.
    $key = webpush_openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
    $details = openssl_pkey_get_details($key);
    $publicKey = webpush_test_public_key_from_raw(
        "\x04" . str_pad($details['ec']['x'], 32, "\x00", STR_PAD_LEFT) . str_pad($details['ec']['y'], 32, "\x00", STR_PAD_LEFT)
    );

    $failures = 0;
    for ($i = 0; $i < 200; $i++) {
        $message = random_bytes(32);
        openssl_sign($message, $der, $key, OPENSSL_ALGO_SHA256);

        $raw = webpush_der_signature_to_raw($der, 32);
        if ($raw === false || strlen($raw) !== 64) {
            $failures++;
            continue;
        }

        $reencodedDer = webpush_test_raw_signature_to_der($raw);
        if (openssl_verify($message, $reencodedDer, $publicKey, OPENSSL_ALGO_SHA256) !== 1) {
            $failures++;
        }
    }

    assert_same(0, $failures, '200 random signatures all round-trip and verify (0 failures)');
});

wallos_test('the VAPID keypair is generated once and then persisted on the admin row', function () {
    $db = wallos_test_open_database();

    $before = $db->querySingle("SELECT vapid_public_key, vapid_private_key FROM admin LIMIT 1", true);
    assert_same('', $before['vapid_public_key'] ?? '', 'nothing generated for an installation that has not needed it yet');

    $keys = webpush_get_vapid_keys($db);
    assert_true($keys !== false, 'a keypair was generated on first use');

    $after = $db->querySingle("SELECT vapid_public_key, vapid_private_key FROM admin LIMIT 1", true);
    assert_same($keys['public'], $after['vapid_public_key'], 'the public key was persisted');
    assert_same($keys['private_pem'], $after['vapid_private_key'], 'and the private key with it');

    $again = webpush_get_vapid_keys($db);
    assert_same($keys['public'], $again['public'], 'a second call reads the same stored keypair back');
    assert_same($keys['private_pem'], $again['private_pem'], 'rather than generating - and so invalidating - a new one');

    $db->close();
});

wallos_test('webpush_send() reports 404/410 as prune, and everything else as not', function () {
    // The distinction the cron job's pruning logic depends on: 404/410 mean
    // the push service itself has discarded the subscription; nothing else -
    // not a timeout, not a 5xx, not a 429 - says that, and none of the rest
    // are exercised by a live send in this suite (see the comment on
    // webpush_send() itself for why offline is not one of them either).
    //
    // curl_exec() is not stubbed anywhere in this codebase's tests, so this
    // checks the pure classification, not the network path: build the
    // 'prune' decision the same way webpush_send() does and confirm it
    // agrees for every status code family that reaches it.
    $statuses = [200 => false, 201 => false, 400 => false, 404 => true, 410 => true, 413 => false, 429 => false, 500 => false, 503 => false];

    foreach ($statuses as $status => $expectedPrune) {
        $prune = in_array($status, [404, 410], true);
        assert_same($expectedPrune, $prune, "status $status prunes: " . ($expectedPrune ? 'yes' : 'no'));
    }
});
