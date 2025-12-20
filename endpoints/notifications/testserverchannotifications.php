<?php
require_once '../../includes/connect_endpoint.php';
require_once '../../includes/validate_endpoint.php';

$postData = file_get_contents("php://input");
$data = json_decode($postData, true);

$enabled = $data["enabled"] ?? 0;
$sendkey = $data["sendkey"] ?? "";

if (!$enabled || $sendkey === "") {
    echo json_encode([
        "success" => false,
        "message" => translate('fill_mandatory_fields', $i18n)
    ]);
    exit;
}

function sc_send($text, $desp = '', $key = '') {
    $postdata = http_build_query(array('text' => $text, 'desp' => $desp));

    if (strpos($key, 'sctp') === 0) {
        preg_match('/^sctp(\d+)t/', $key, $matches);
        $num = $matches[1] ?? '';
        $url = "https://{$num}.push.ft07.com/send/{$key}.send";
    } else {
        $url = "https://sctapi.ftqq.com/{$key}.send";
    }

    $opts = array('http' => array(
        'method'  => 'POST',
        'header'  => 'Content-type: application/x-www-form-urlencoded',
        'content' => $postdata
    ));

    $context = stream_context_create($opts);
    $result = @file_get_contents($url, false, $context);
    return $result !== false ? $result : '';
}

$title = 'Wallos Notification Test';
$body = 'This is a test notification from Wallos via Serverchan.';

$result = sc_send($title, $body, $sendkey);
$info = json_decode($result, true);
$code = (is_array($info) && array_key_exists('code', $info)) ? $info['code'] : null;
if ($code === 0) {
    echo json_encode([
        "success" => true,
        "message" => translate('notification_sent_successfuly', $i18n)
    ]);
} else {
    echo json_encode([
        "success" => false,
        "message" => translate('notification_failed', $i18n)
    ]);
}