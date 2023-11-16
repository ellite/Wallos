<?php

final class CurrencyFormatter
{
    private static $instance;

    private static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new NumberFormatter(Locale::acceptFromHttp($_SERVER['HTTP_ACCEPT_LANGUAGE']), NumberFormatter::CURRENCY);
        }

        return self::$instance;
    }

    public static function format($amount, $currency)
    {
        return self::getInstance()->formatCurrency($amount, $currency);
    }
}
