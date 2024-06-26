<?php

$databaseFile = __DIR__ . '/../../db/wallos.db';

if (!file_exists($databaseFile)) {
    echo "Database does not exist. Creating it...\n";
    $db = new SQLite3($databaseFile, SQLITE3_OPEN_CREATE | SQLITE3_OPEN_READWRITE);
    $db->busyTimeout(5000);

    $db->exec('CREATE TABLE user (
        id INTEGER PRIMARY KEY,
        username TEXT NOT NULL,
        email TEXT NOT NULL,
        password TEXT NOT NULL,
        main_currency INTEGER NOT NULL,
        avatar TEXT,
        FOREIGN KEY(main_currency) REFERENCES currencies(id)
    )');

    $db->exec('CREATE TABLE payment_methods (
        id INTEGER PRIMARY KEY,
        name TEXT NOT NULL,
        icon TEXT
    )');

    $db->exec('CREATE TABLE subscriptions (
        id INTEGER PRIMARY KEY,
        name TEXT NOT NULL,
        logo TEXT,
        price REAL NOT NULL,
        currency_id INTEGER,
        next_payment DATE,
        cycle INTEGER,
        frequency INTEGER,
        notes TEXT,
        payment_method_id INTEGER,
        payer_user_id INTEGER,
        category_id INTEGER,
        notify BOOLEAN DEFAULT false,
        FOREIGN KEY(currency_id) REFERENCES currencies(id),
        FOREIGN KEY(cycle) REFERENCES cycles(id),
        FOREIGN KEY(frequency) REFERENCES frequencies(id),
        FOREIGN KEY(payment_method_id) REFERENCES payment_methods(id),
        FOREIGN KEY(payer_user_id) REFERENCES household(id)
        FOREIGN KEY(category_id) REFERENCES categories(id)
    )');

    $db->exec('CREATE TABLE categories (
        id INTEGER PRIMARY KEY,
        name TEXT NOT NULL
    )');

    $db->exec('CREATE TABLE currencies (
        id INTEGER PRIMARY KEY,
        name TEXT NOT NULL,
        symbol TEXT NOT NULL,
        code TEXT NOT NULL,
        rate TEXT NOT NULL
    )');

    $db->exec('CREATE TABLE household (
        id INTEGER PRIMARY KEY,
        name TEXT NOT NULL
    )');

    $db->exec('CREATE TABLE login_tokens (
        user_id INTEGER NOT NULL,
        token TEXT NOT NULL,
        timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES user(id) ON DELETE CASCADE ON UPDATE CASCADE
    )');

    $db->exec('CREATE TABLE cycles (
        id INTEGER PRIMARY KEY,
        days INTEGER NOT NULL,
        name TEXT NOT NULL
    )');

    $db->exec('CREATE TABLE frequencies (
        id INTEGER PRIMARY KEY,
        name INTEGER NOT NULL
    )');

    $db->exec('CREATE TABLE fixer (
        api_key TEXT NOT NULL
    )');

    $db->exec('CREATE TABLE last_exchange_update (
        date DATE NOT NULL
    )');

    $db->exec('CREATE TABLE last_update_next_payment_date (
        date DATE NOT NULL
    )');

    $db->exec('CREATE TABLE notifications (
        id INTEGER PRIMARY KEY,
        enabled BOOLEAN DEFAULT false,
        days INTEGER,
        smtp_address VARCHAR(255),
        smtp_port INTEGER,
        smtp_username VARCHAR(255),
        smtp_password VARCHAR(255)
    )');

    $db->exec("INSERT INTO categories (id, name) VALUES
    (1, 'No category'),
    (2, 'Entertainment'),
    (3, 'Music'),
    (4, 'Utilities'),
    (5, 'Food & Beverages'),
    (6, 'Health & Wellbeing'),
    (7, 'Productivity'),
    (8, 'Banking'),
    (9, 'Transport'),
    (10, 'Education'),
    (11, 'Insurance'),
    (12, 'Gaming'),
    (13, 'News & Magazines'),
    (14, 'Software'),
    (15, 'Technology'),
    (16, 'Cloud Services'),
    (17, 'Charity & Donations')");

    $db->exec("INSERT INTO cycles (id, days, name) VALUES
    (1, 1, 'Daily'),
    (2, 7, 'Weekly'),
    (3, 30, 'Monthly'),
    (4, 365, 'Yearly')");

    $db->exec("INSERT INTO frequencies (id, name) VALUES
    (1, 1),
    (2, 2),
    (3, 3),
    (4, 4),
    (5, 5),
    (6, 6),
    (7, 7),
    (8, 8),
    (9, 9),
    (10, 10),
    (11, 11),
    (12, 12),
    (13, 13),
    (14, 14),
    (15, 15),
    (16, 16),
    (17, 17),
    (18, 18),
    (19, 19),
    (20, 20),
    (21, 21),
    (22, 22),
    (23, 23),
    (24, 24),
    (25, 25),
    (26, 26),
    (27, 27),
    (28, 28),
    (29, 29),
    (30, 30),
    (31, 31)");

    $db->exec("INSERT INTO currencies (name, symbol, code, rate) VALUES
    ('Euro', '€', 'EUR', 1),
    ('US Dollar', '$', 'USD', 1),
    ('Japanese Yen', '¥', 'JPY', 1),
    ('Bulgarian Lev', 'лв', 'BGN', 1),
    ('Czech Republic Koruna', 'Kč', 'CZK', 1),
    ('Danish Krone', 'kr', 'DKK', 1),
    ('British Pound Sterling', '£', 'GBP', 1),
    ('Hungarian Forint', 'Ft', 'HUF', 1),
    ('Polish Zloty', 'zł', 'PLN', 1),
    ('Romanian Leu', 'lei', 'RON', 1),
    ('Swedish Krona', 'kr', 'SEK', 1),
    ('Swiss Franc', 'Fr', 'CHF', 1),
    ('Icelandic Króna', 'kr', 'ISK', 1),
    ('Norwegian Krone', 'kr', 'NOK', 1),
    ('Russian Ruble', '₽', 'RUB', 1),
    ('Turkish Lira', '₺', 'TRY', 1),
    ('Australian Dollar', '$', 'AUD', 1),
    ('Brazilian Real', 'R$', 'BRL', 1),
    ('Canadian Dollar', '$', 'CAD', 1),
    ('Chinese Yuan', '¥', 'CNY', 1),
    ('Hong Kong Dollar', 'HK$', 'HKD', 1),
    ('Indonesian Rupiah', 'Rp', 'IDR', 1),
    ('Israeli New Sheqel', '₪', 'ILS', 1),
    ('Indian Rupee', '₹', 'INR', 1),
    ('South Korean Won', '₩', 'KRW', 1),
    ('Mexican Peso', 'Mex$', 'MXN', 1),
    ('Malaysian Ringgit', 'RM', 'MYR', 1),
    ('New Zealand Dollar', 'NZ$', 'NZD', 1),
    ('Philippine Peso', '₱', 'PHP', 1),
    ('Singapore Dollar', 'S$', 'SGD', 1),
    ('Thai Baht', '฿', 'THB', 1),
    ('South African Rand', 'R', 'ZAR', 1)");

    $db->exec("INSERT INTO payment_methods (id, name, icon) VALUES
    (1, 'PayPal', 'paypal.png'),
    (2, 'Credit Card', 'creditcard.png'),
    (3, 'Bank Transfer', 'banktransfer.png'),
    (4, 'Direct Debit', 'directdebit.png'),
    (5, 'Money', 'money.png'),
    (6, 'Google Pay', 'googlepay.png'),
    (7, 'Samsung Pay', 'samsungpay.png'),
    (8, 'Apple Pay', 'applepay.png'),
    (9, 'Crypto', 'crypto.png'),
    (10, 'Klarna', 'klarna.png'),
    (11, 'Amazon Pay', 'amazonpay.png'),
    (12, 'SEPA', 'sepa.png'),
    (13, 'Skrill', 'skrill.png'),
    (14, 'Sofort', 'sofort.png'),
    (15, 'Stripe', 'stripe.png'),
    (16, 'Affirm', 'affirm.png'),
    (17, 'AliPay', 'alipay.png'),
    (18, 'Elo', 'elo.png'),
    (19, 'Facebook Pay', 'facebookpay.png'),
    (20, 'GiroPay', 'giropay.png'),
    (21, 'iDeal', 'ideal.png'),
    (22, 'Union Pay', 'unionpay.png'),
    (23, 'Interac', 'interac.png'),
    (24, 'WeChat', 'wechat.png'),
    (25, 'Paysafe', 'paysafe.png'),
    (26, 'Poli', 'poli.png'),
    (27, 'Qiwi', 'qiwi.png'),
    (28, 'ShopPay', 'shoppay.png'),
    (29, 'Venmo', 'venmo.png'),
    (30, 'VeriFone', 'verifone.png'),
    (31, 'WebMoney', 'webmoney.png')");

    echo "Database created.\n";
} else {
    echo "Database already exist. Checking for upgrades...\n";

    $db = new SQLite3($databaseFile);
    $db->busyTimeout(5000);

    if (!$db) {
        die('Connection to the database failed.');
    }

    # v0.9 to v1.0
    # Added new notifications table
    # Added notify column to subscriptions table

    $result = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='notifications'");
    if (!$result->fetchArray(SQLITE3_ASSOC)) {
        $db->exec('CREATE TABLE notifications (
            id INTEGER PRIMARY KEY,
            enabled BOOLEAN DEFAULT false,
            days INTEGER,
            smtp_address VARCHAR(255),
            smtp_port INTEGER,
            smtp_username VARCHAR(255),
            smtp_password VARCHAR(255)
        )');
        echo "Table 'notifications' created.\n";
    } else {
        echo "Table 'notifications' already exists.\n";
    }

    $result = $db->query("PRAGMA table_info(subscriptions)");
    $notifyColumnExists = false;
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        if ($row['name'] === 'notify') {
            $notifyColumnExists = true;
            break;
        }
    }
    if (!$notifyColumnExists) {
        $db->exec('ALTER TABLE subscriptions ADD COLUMN notify BOOLEAN DEFAULT false');
        echo "Column 'notify' added to table 'subscriptions'.\n";
    } else {
        echo "Column 'notify' already exists in table 'subscriptions'.\n";
    }

}

?>