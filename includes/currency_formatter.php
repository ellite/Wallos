<?php

final class CurrencyFormatter
{
    private static $instance;

    private static function getInstance()
    {
        if (self::$instance === null) {
            if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
                self::$instance = new NumberFormatter(Locale::acceptFromHttp($_SERVER['HTTP_ACCEPT_LANGUAGE']), NumberFormatter::CURRENCY);
            } else {
                self::$instance = new NumberFormatter('en_US', NumberFormatter::CURRENCY);
            }
        }

        return self::$instance;
    }

    public static function format($amount, $currency)
    {
        return self::getInstance()->formatCurrency($amount, $currency);
    }
}
