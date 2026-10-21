<?php

if (!function_exists('getPaymentsDueInRemainingDays')) {
    /**
     * Number of payments that fall between a known next payment date and the end
     * of the period, both inclusive. The next payment itself always counts, so
     * the result is a whole number of at least 1. Monthly and longer cycles only
     * pay once in the remaining days of a month.
     *
     * @param int $cycle         1 = days, 2 = weeks, 3 = months, 4 = years
     * @param int $frequency     every N cycles
     * @param int $daysRemaining days from the next payment to the end of the period, inclusive
     */
    function getPaymentsDueInRemainingDays($cycle, $frequency, $daysRemaining)
    {
        $frequency = max(1, (int) $frequency);
        $daysRemaining = max(1, (int) $daysRemaining);

        if ($cycle == 1) {
            $interval = $frequency;
        } elseif ($cycle == 2) {
            $interval = 7 * $frequency;
        } else {
            return 1;
        }

        return intdiv($daysRemaining - 1, $interval) + 1;
    }
}
