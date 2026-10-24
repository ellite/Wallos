<?php
namespace WallosSmtpTest;

require_once WALLOS_ROOT . '/libs/PHPMailer/Exception.php';
require_once WALLOS_ROOT . '/libs/PHPMailer/PHPMailer.php';
require_once WALLOS_ROOT . '/libs/PHPMailer/SMTP.php';

class UnavailableSmtp extends \PHPMailer\PHPMailer\SMTP
{
    public function connect($host, $port = null, $timeout = 30, $options = [])
    {
        $this->setError('Connection refused');
        return false;
    }
}

class Mail extends \PHPMailer\PHPMailer\PHPMailer
{
    public static $mode;
    public static $attempts;

    public function send()
    {
        self::$attempts++;
        if (self::$mode === 'unavailable SMTP') {
            $this->setSMTPInstance(new UnavailableSmtp());
            return parent::send();
        }
        if (self::$mode === 'throw') {
            throw new \PHPMailer\PHPMailer\Exception('SMTP password=secret token=secret');
        }
        $this->ErrorInfo = 'SMTP password=secret token=secret';
        return self::$mode !== 'false';
    }
}

// Only the database and transports are replaced; execute the cron's actual
// dispatch blocks, including the channels following email, without network IO.
class Database
{
    public function prepare($query) { return $this; }
    public function bindValue($key, $value, $type) {}
    public function execute() { return $this; }
    public function fetchArray($mode)
    {
        return ['email' => 'payer@example.com', 'name' => 'Payer', 'username' => 'Owner'];
    }
}

function validate_smtp_host(...$args) { return true; }
function buildNotificationMessage(...$args) { return 'Renewal reminder'; }
function mb_convert_encoding($value, ...$args) { return $value; }
function error_log($message) { $GLOBALS['wallos_smtp_logs'][] = $message; }
function curl_init($url = null) { return $url; }
function curl_setopt(...$args) { return true; }
function curl_getinfo(...$args) { return 200; }
function curl_exec($url)
{
    $GLOBALS['wallos_smtp_requests'][] = $url;
    return '{"code":200}';
}

function dispatch($mode, $invalidSender = false)
{
    $source = file_get_contents(WALLOS_ROOT . '/endpoints/cronjobs/sendnotifications.php');
    $start = strpos($source, '// Email notifications if enabled');
    // Use the outer "nothing to notify" branch as the dispatch boundary.
    $end = strrpos($source, '        } else {');
    assert_true($start !== false && $end > $start, 'cron dispatch block found');
    $dispatch = substr($source, $start, $end - $start);
    // Constants are needed even when the optional curl/SQLite extensions are absent.
    preg_match_all('/\b(?:CURLOPT_[A-Z_]+|CURLINFO_[A-Z_]+|SQLITE3_[A-Z_]+)\b/', $dispatch, $matches);
    foreach (array_unique($matches[0]) as $index => $constant) {
        if (!defined($constant)) {
            define($constant, $index);
        }
    }

    $db = new Database();
    $userId = 42;
    $notify = $notifyEmail = [1 => [['name' => 'Subscription']], 2 => [['name' => 'Other']]];
    $notifyOtherPayers = [];
    $periodSummaryLine = '';
    $sendPeriodStartSummaryOnly = false;
    $email = ['smtpAddress' => 'smtp.example.com', 'smtpPort' => 587,
        'smtpUsername' => 'user', 'smtpPassword' => 'secret', 'encryption' => 'none',
        'fromEmail' => $invalidSender ? 'invalid' : 'wallos@example.com', 'otherEmails' => 'cc@example.com'];
    $telegram = ['botToken' => 'secret', 'chatId' => '123'];
    $pushplus = ['token' => 'secret'];
    $emailNotificationsEnabled = $telegramNotificationsEnabled = $pushplusNotificationsEnabled = true;
    $discordNotificationsEnabled = $gotifyNotificationsEnabled = $mattermostNotificationsEnabled = false;
    $pushoverNotificationsEnabled = $ntfyNotificationsEnabled = $webhookNotificationsEnabled = false;
    $serverchanNotificationsEnabled = $pushNotificationsEnabled = false;
    Mail::$mode = $mode;
    Mail::$attempts = 0;
    $GLOBALS['wallos_smtp_logs'] = $GLOBALS['wallos_smtp_requests'] = [];
    ob_start();
    try {
        eval('namespace WallosSmtpTest; use WallosSmtpTest\Mail as PHPMailer; use PHPMailer\PHPMailer\Exception; ' . $dispatch);
        return ob_get_contents();
    } finally {
        ob_end_clean();
    }
}

foreach (['throw', 'unavailable SMTP', 'false', 'success', 'invalid sender'] as $mode) {
    wallos_test('SMTP isolation: ' . $mode, function () use ($mode) {
        $output = dispatch($mode, $mode === 'invalid sender');
        assert_same($mode === 'invalid sender' ? 0 : 2, Mail::$attempts, 'each payer is processed');
        assert_same(4, count($GLOBALS['wallos_smtp_requests']), 'Telegram and PushPlus each send for both payers');
        assert_contains('Telegram Notifications sent', $output, 'Telegram runs after email');
        assert_contains('PushPlus Notifications sent successfully', $output, 'later channels run after email');
        assert_same($mode === 'success' ? [] : array_fill(0, 2, 'Email notification failed for user 42.'),
            $GLOBALS['wallos_smtp_logs'], 'failures are logged without transport details');
        assert_same(false, strpos($output, 'secret'), 'output does not expose secrets');
        assert_same($mode === 'success' ? 2 : 0, substr_count($output, 'Email Notifications sent<br />'),
            'successful email output is preserved');
    });
}
