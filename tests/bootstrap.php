<?php
/*
  Minimal test harness for Wallos.

  Wallos vendors its libraries instead of using Composer, so the tests follow
  the same principle: no external dependency, just PHP and the SQLite
  extension the application already requires.

  A test file registers cases with wallos_test() and asserts with the assert_*
  helpers. tests/run.php discovers and runs them.
*/

define('WALLOS_ROOT', dirname(__DIR__));
define('WALLOS_TEST_TMP', sys_get_temp_dir() . '/wallos-tests');

$GLOBALS['wallos_tests'] = [];
$GLOBALS['wallos_test_failures'] = [];
$GLOBALS['wallos_test_assertions'] = 0;
$GLOBALS['wallos_test_current'] = null;

/**
 * Registers one test case.
 *
 * @param string   $name
 * @param callable $body
 */
function wallos_test($name, callable $body)
{
    $GLOBALS['wallos_tests'][] = ['name' => $name, 'body' => $body];
}

function wallos_test_fail($message)
{
    $GLOBALS['wallos_test_failures'][] = [
        'test' => $GLOBALS['wallos_test_current'],
        'message' => $message,
    ];
}

function assert_true($condition, $message)
{
    $GLOBALS['wallos_test_assertions']++;

    if (!$condition) {
        wallos_test_fail($message);
    }
}

function assert_same($expected, $actual, $message)
{
    $GLOBALS['wallos_test_assertions']++;

    if ($expected !== $actual) {
        wallos_test_fail(sprintf(
            '%s (expected %s, got %s)',
            $message,
            var_export($expected, true),
            var_export($actual, true)
        ));
    }
}

function assert_equals($expected, $actual, $message)
{
    $GLOBALS['wallos_test_assertions']++;

    if ($expected != $actual) {
        wallos_test_fail(sprintf(
            '%s (expected %s, got %s)',
            $message,
            var_export($expected, true),
            var_export($actual, true)
        ));
    }
}

function assert_contains($needle, $haystack, $message)
{
    $GLOBALS['wallos_test_assertions']++;

    if (strpos((string) $haystack, (string) $needle) === false) {
        wallos_test_fail($message . ' (missing: ' . $needle . ')');
    }
}

function assert_not_contains($needle, $haystack, $message)
{
    $GLOBALS['wallos_test_assertions']++;

    if (strpos((string) $haystack, (string) $needle) !== false) {
        wallos_test_fail($message . ' (unexpectedly present: ' . $needle . ')');
    }
}

/**
 * Builds the real application schema once per run by running the same
 * createdatabase.php and migration chain the container startup uses, then
 * hands out a fresh copy of it per test.
 *
 * The application resolves its database path relative to its own directory, so
 * the schema is built inside a throwaway copy of the source tree rather than in
 * the working copy.
 *
 * @return string Path to the freshly copied database.
 */
function wallos_test_database()
{
    static $template = null;

    if ($template === null) {
        $sandbox = WALLOS_TEST_TMP . '/sandbox';

        if (!is_dir($sandbox)) {
            mkdir($sandbox, 0700, true);
            foreach (['endpoints/cronjobs', 'includes', 'migrations', 'db'] as $directory) {
                mkdir($sandbox . '/' . $directory, 0700, true);
            }

            foreach (glob(WALLOS_ROOT . '/migrations/*.php') as $migration) {
                copy($migration, $sandbox . '/migrations/' . basename($migration));
            }
            copy(WALLOS_ROOT . '/endpoints/cronjobs/createdatabase.php', $sandbox . '/endpoints/cronjobs/createdatabase.php');
            copy(WALLOS_ROOT . '/includes/run_migrations.php', $sandbox . '/includes/run_migrations.php');
        }

        $databaseFile = $sandbox . '/db/wallos.db';

        if (!file_exists($databaseFile)) {
            // Both scripts print progress and resolve their paths from __DIR__,
            // which is why they run inside the sandbox copy.
            ob_start();
            require $sandbox . '/endpoints/cronjobs/createdatabase.php';
            $db = new SQLite3($databaseFile);
            $db->busyTimeout(5000);
            require $sandbox . '/includes/run_migrations.php';
            $db->close();
            ob_end_clean();
        }

        $template = $databaseFile;
    }

    $copy = WALLOS_TEST_TMP . '/case-' . uniqid('', true) . '.db';
    copy($template, $copy);

    return $copy;
}

/**
 * Opens a fresh database with the full application schema.
 *
 * @return SQLite3
 */
function wallos_test_open_database()
{
    $db = new SQLite3(wallos_test_database());
    $db->busyTimeout(5000);

    return $db;
}

/**
 * SQLite3 wrapper that counts statements, so tests can assert that a code path
 * does not issue one query per row.
 */
class WallosCountingDatabase extends SQLite3
{
    public $queryCount = 0;

    public function prepare($query): SQLite3Stmt|false
    {
        $this->queryCount++;

        return parent::prepare($query);
    }

    public function query($query): SQLite3Result|false
    {
        $this->queryCount++;

        return parent::query($query);
    }

    public function querySingle($query, $entireRow = false): mixed
    {
        $this->queryCount++;

        return parent::querySingle($query, $entireRow);
    }

    public function resetQueryCount()
    {
        $this->queryCount = 0;
    }
}

/**
 * @return WallosCountingDatabase
 */
function wallos_test_open_counting_database()
{
    $db = new WallosCountingDatabase(wallos_test_database());
    $db->busyTimeout(5000);

    return $db;
}

/**
 * Inserts a user together with the currency rows Wallos creates alongside it.
 *
 * @param SQLite3 $db
 * @param int     $id
 * @param string  $username
 */
function wallos_test_create_user($db, $id, $username)
{
    $stmt = $db->prepare("INSERT INTO user (id, username, email, password, main_currency) VALUES (:id, :username, :email, 'x', 1)");
    $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
    $stmt->bindValue(':username', $username, SQLITE3_TEXT);
    $stmt->bindValue(':email', $username . '@example.com', SQLITE3_TEXT);
    $stmt->execute();

    // createdatabase.php seeds a default currency list; the fixture replaces it
    // so a test can reason about exactly two rows per user.
    $stmt = $db->prepare('DELETE FROM currencies WHERE user_id = :userId');
    $stmt->bindValue(':userId', $id, SQLITE3_INTEGER);
    $stmt->execute();

    foreach ([['EUR', 'Euro', 1.0], ['USD', 'US Dollar', 1.1]] as $index => $currency) {
        $stmt = $db->prepare('INSERT INTO currencies (id, name, symbol, code, rate, user_id) VALUES (:id, :name, :symbol, :code, :rate, :userId)');
        $stmt->bindValue(':id', wallos_test_currency_id($id, $index), SQLITE3_INTEGER);
        $stmt->bindValue(':name', $currency[1], SQLITE3_TEXT);
        $stmt->bindValue(':symbol', $currency[0] === 'EUR' ? "\u{20AC}" : '$', SQLITE3_TEXT);
        $stmt->bindValue(':code', $currency[0], SQLITE3_TEXT);
        $stmt->bindValue(':rate', $currency[2], SQLITE3_FLOAT);
        $stmt->bindValue(':userId', $id, SQLITE3_INTEGER);
        $stmt->execute();
    }

    $stmt = $db->prepare('UPDATE user SET main_currency = :currencyId WHERE id = :id');
    $stmt->bindValue(':currencyId', wallos_test_currency_id($id, 0), SQLITE3_INTEGER);
    $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
    $stmt->execute();
}

/**
 * Fixture currency ids live above the seeded default list so they never clash.
 *
 * @param int $userId
 * @param int $index   0 = EUR (main currency), 1 = USD
 * @return int
 */
function wallos_test_currency_id($userId, $index)
{
    return 9000 + ($userId * 10) + $index;
}
