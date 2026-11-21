<?php

final class CurrencyFormatter
{
    private static $formatters = [];

    private static function getInstance($decimals = 2)
    {
        if (!isset(self::$formatters[$decimals])) {
            if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
                $locale = Locale::acceptFromHttp($_SERVER['HTTP_ACCEPT_LANGUAGE']);
            } else {
                $locale = 'en_US';
            }

            $formatter = new NumberFormatter($locale ?: 'en_US', NumberFormatter::CURRENCY);
            $formatter->setAttribute(NumberFormatter::FRACTION_DIGITS, $decimals);
            self::$formatters[$decimals] = $formatter;
        }

        return self::$formatters[$decimals];
    }

    /**
     * How many decimals a price needs: cents for normal amounts, and just
     * enough precision (up to satoshi, 8 decimals) for amounts below 0.01.
     */
    public static function decimalPlaces($amount)
    {
        $amount = abs((float) $amount);

        if (!is_finite($amount) || $amount == 0.0 || $amount >= 0.01) {
            return 2;
        }

        for ($decimals = 3; $decimals <= 8; $decimals++) {
            if (abs($amount - round($amount, $decimals)) < 1e-12) {
                return $decimals;
            }
        }

        return 8;
    }

    public static function format($amount, $currency)
    {
        return self::getInstance(self::decimalPlaces($amount))->formatCurrency((float) $amount, $currency);
    }
}
