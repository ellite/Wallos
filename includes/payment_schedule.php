<?php
require_once __DIR__ . '/inputvalidation.php';

/** Fail closed before constructing an interval, and bound all catch-up work. */
function wallos_advance_payment($date, $frequency, $cycle, DateTime $currentDate, $renew = false)
{
    $units = [1 => 'D', 2 => 'W', 3 => 'M', 4 => 'Y'];
    if (!validate_frequency($frequency) || !validate_frequency($cycle, 1, 4)
        || !wallos_valid_payment_date($date)) {
        throw new InvalidArgumentException('Invalid subscription payment schedule');
    }
    $next = new DateTime($date);
    $original = clone $next;
    $interval = new DateInterval('P' . (int)$frequency . $units[(int)$cycle]);
    $safety = 0;
    while ($next < $currentDate || ($renew && $next == $original)) {
        if (++$safety > 10000) {
            throw new RuntimeException('Payment schedule iteration limit exceeded');
        }
        $previous = clone $next;
        $next->add($interval);
        if ($next <= $previous) {
            throw new RuntimeException('Payment schedule did not advance');
        }
    }
    return $next;
}
