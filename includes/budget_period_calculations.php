<?php

require_once __DIR__ . '/currency_rates.php';

if (!function_exists('sanitizeBudgetPeriodType')) {
    function sanitizeBudgetPeriodType($periodType)
    {
        $allowedTypes = ['weekly', 'fortnightly', 'semimonthly', 'monthly'];
        return in_array($periodType, $allowedTypes, true) ? $periodType : 'monthly';
    }
}

if (!function_exists('getDefaultBudgetAnchorDate')) {
    function getDefaultBudgetAnchorDate()
    {
        return (new DateTime('now'))->format('Y-m-d');
    }
}

if (!function_exists('sanitizeBudgetAnchorDate')) {
    function sanitizeBudgetAnchorDate($anchorDate)
    {
        if (!is_string($anchorDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $anchorDate)) {
            return getDefaultBudgetAnchorDate();
        }

        $parsed = DateTime::createFromFormat('Y-m-d', $anchorDate);
        if ($parsed === false || $parsed->format('Y-m-d') !== $anchorDate) {
            return getDefaultBudgetAnchorDate();
        }

        return $anchorDate;
    }
}

if (!function_exists('sanitizeBudgetSecondDay')) {
    function sanitizeBudgetSecondDay($secondDay)
    {
        $secondDay = (int) $secondDay;

        // 31 is how "the last day of the month" is expressed: getDateWithClampedDay
        // pulls it back to the 28th, 29th or 30th as the month requires.
        return ($secondDay >= 1 && $secondDay <= 31) ? $secondDay : 16;
    }
}

if (!function_exists('createDateAtMidnight')) {
    function createDateAtMidnight(DateTime $date)
    {
        return new DateTime($date->format('Y-m-d'));
    }
}

if (!function_exists('getDateWithClampedDay')) {
    function getDateWithClampedDay($year, $month, $day)
    {
        $base = DateTime::createFromFormat('!Y-n-j', $year . '-' . $month . '-1');
        if ($base === false) {
            $base = new DateTime('1970-01-01');
        }

        $lastDay = (int) $base->format('t');
        $clampedDay = min(max(1, (int) $day), $lastDay);

        return DateTime::createFromFormat('!Y-n-j', $year . '-' . $month . '-' . $clampedDay);
    }
}

if (!function_exists('getSemiMonthlyPeriodStarts')) {
    /**
     * The period starts falling in one month, in order and without duplicates.
     *
     * Both paydays are clamped to the month's length, so a user paid on the
     * 30th and the 31st has two distinct periods in March but only one in
     * February, where both clamp to the 28th. Returning one start for that
     * month is what keeps the period from collapsing to zero days.
     *
     * @return DateTime[]
     */
    function getSemiMonthlyPeriodStarts($year, $month, $firstDay, $secondDay)
    {
        $days = [min($firstDay, $secondDay), max($firstDay, $secondDay)];
        $starts = [];

        foreach ($days as $day) {
            $start = getDateWithClampedDay($year, $month, $day);
            $key = $start->format('Y-m-d');
            if (!isset($starts[$key])) {
                $starts[$key] = $start;
            }
        }

        return array_values($starts);
    }
}

if (!function_exists('getActiveBudgetPeriod')) {
    function getActiveBudgetPeriod(DateTime $today, $periodType, $anchorDate, $secondDay = null)
    {
        $periodType = sanitizeBudgetPeriodType($periodType);
        $anchorDate = sanitizeBudgetAnchorDate($anchorDate ?: getDefaultBudgetAnchorDate());

        $todayDate = createDateAtMidnight($today);
        $anchor = DateTime::createFromFormat('!Y-m-d', $anchorDate);
        if ($anchor === false) {
            $anchor = new DateTime('1970-01-01');
        }

        if ($periodType === 'semimonthly') {
            $firstDay = (int) $anchor->format('j');
            $secondDay = sanitizeBudgetSecondDay($secondDay);

            // Walk the starts from the previous month through the next one, so
            // the period containing today is always among them whichever side
            // of a payday today falls on.
            $candidates = [];
            foreach ([-1, 0, 1] as $monthOffset) {
                $monthCursor = (clone $todayDate)->modify('first day of this month');
                if ($monthOffset !== 0) {
                    $monthCursor->modify($monthOffset . ' month');
                }

                foreach (getSemiMonthlyPeriodStarts(
                    (int) $monthCursor->format('Y'),
                    (int) $monthCursor->format('n'),
                    $firstDay,
                    $secondDay
                ) as $candidate) {
                    $candidates[$candidate->format('Y-m-d')] = $candidate;
                }
            }

            ksort($candidates);
            $candidates = array_values($candidates);

            $start = $candidates[0];
            $end = null;
            foreach ($candidates as $index => $candidate) {
                if ($candidate <= $todayDate) {
                    $start = $candidate;
                    $end = isset($candidates[$index + 1])
                        ? (clone $candidates[$index + 1])->modify('-1 day')
                        : null;
                }
            }

            if ($end === null) {
                // Today sits before every candidate, or after the last one:
                // fall back to a full month from the start it did match.
                $end = (clone $start)->modify('+1 month')->modify('-1 day');
            }

            return [
                'start' => $start,
                'end' => $end,
                'label' => formatBudgetPeriodLabel($start, $end),
                'type' => $periodType,
            ];
        }

        if ($periodType === 'weekly' || $periodType === 'fortnightly') {
            $periodLengthDays = $periodType === 'weekly' ? 7 : 14;
            $diffDays = (int) $anchor->diff($todayDate)->format('%r%a');
            $periodOffset = (int) floor($diffDays / $periodLengthDays);

            $start = clone $anchor;
            $start->modify(($periodOffset * $periodLengthDays) . ' day');

            if ($start > $todayDate) {
                $start->modify('-' . $periodLengthDays . ' day');
            }

            $end = clone $start;
            $end->modify('+' . ($periodLengthDays - 1) . ' day');
        } else {
            $anchorDay = (int) $anchor->format('j');
            $currentMonthStart = getDateWithClampedDay((int) $todayDate->format('Y'), (int) $todayDate->format('n'), $anchorDay);

            if ($todayDate < $currentMonthStart) {
                $currentMonthStart->modify('first day of previous month');
                $currentMonthStart = getDateWithClampedDay((int) $currentMonthStart->format('Y'), (int) $currentMonthStart->format('n'), $anchorDay);
            }

            $start = $currentMonthStart;
            $nextStartMonth = clone $start;
            $nextStartMonth->modify('first day of next month');
            $nextStart = getDateWithClampedDay((int) $nextStartMonth->format('Y'), (int) $nextStartMonth->format('n'), $anchorDay);
            $end = clone $nextStart;
            $end->modify('-1 day');
        }

        return [
            'start' => $start,
            'end' => $end,
            'label' => formatBudgetPeriodLabel($start, $end),
            'type' => $periodType,
        ];
    }
}

if (!function_exists('formatBudgetPeriodLabel')) {
    function formatBudgetPeriodLabel(DateTime $start, DateTime $end)
    {
        $startLabel = $start->format('M j');
        $endLabel = $end->format('M j');

        if ($start->format('Y') !== $end->format('Y')) {
            $startLabel .= ', ' . $start->format('Y');
            $endLabel .= ', ' . $end->format('Y');
        }

        return $startLabel . ' - ' . $endLabel;
    }
}

if (!function_exists('getSubscriptionIntervalSpec')) {
    function getSubscriptionIntervalSpec($cycle, $frequency)
    {
        $frequency = max(1, (int) $frequency);
        $cycle = (int) $cycle;

        $unit = match ($cycle) {
            1 => 'D',
            2 => 'W',
            3 => 'M',
            4 => 'Y',
            default => null,
        };

        return $unit !== null ? 'P' . $frequency . $unit : null;
    }
}

if (!function_exists('shiftSubscriptionOccurrence')) {
    function shiftSubscriptionOccurrence(DateTime $date, array $subscription, DateTime $anchorDate, $direction)
    {
        $frequency = max(1, (int) ($subscription['frequency'] ?? 1));
        $cycle = (int) ($subscription['cycle'] ?? 0);
        $direction = $direction < 0 ? -1 : 1;
        $step = $direction * $frequency;

        if ($cycle === 1) {
            $shifted = clone $date;
            $shifted->modify($step . ' day');
            return createDateAtMidnight($shifted);
        }

        if ($cycle === 2) {
            $shifted = clone $date;
            $shifted->modify(($step * 7) . ' day');
            return createDateAtMidnight($shifted);
        }

        if ($cycle === 3) {
            $totalMonths = ((int) $date->format('Y') * 12) + ((int) $date->format('n') - 1) + $step;
            $targetYear = (int) floor($totalMonths / 12);
            $targetMonth = ($totalMonths % 12) + 1;
            $anchorDay = (int) $anchorDate->format('j');

            return getDateWithClampedDay($targetYear, $targetMonth, $anchorDay);
        }

        if ($cycle === 4) {
            $targetYear = (int) $date->format('Y') + $step;
            $anchorMonth = (int) $anchorDate->format('n');
            $anchorDay = (int) $anchorDate->format('j');

            return getDateWithClampedDay($targetYear, $anchorMonth, $anchorDay);
        }

        return null;
    }
}

if (!function_exists('getSubscriptionOccurrencesInRange')) {
    function getSubscriptionOccurrencesInRange(array $subscription, DateTime $rangeStart, DateTime $rangeEnd)
    {
        if (empty($subscription['next_payment'])) {
            return [];
        }

        $nextPayment = DateTime::createFromFormat('!Y-m-d', trim($subscription['next_payment']));
        if ($nextPayment === false) {
            return [];
        }

        $rangeStartDate = createDateAtMidnight($rangeStart);
        $rangeEndDate = createDateAtMidnight($rangeEnd);
        $occurrences = [];

        $autoRenew = isset($subscription['auto_renew']) ? (int) $subscription['auto_renew'] === 1 : true;

        // A one-time purchase never repeats, so it has no interval to walk: it is
        // due once, on its payment date. It is still money the period has to
        // cover, which is why it is counted rather than skipped.
        if ((int) ($subscription['cycle'] ?? 0) === 5) {
            return ($nextPayment >= $rangeStartDate && $nextPayment <= $rangeEndDate)
                ? [clone $nextPayment]
                : [];
        }

        $intervalSpec = getSubscriptionIntervalSpec($subscription['cycle'] ?? 0, $subscription['frequency'] ?? 1);

        if ($intervalSpec === null) {
            return [];
        }

        if (!$autoRenew) {
            return ($nextPayment >= $rangeStartDate && $nextPayment <= $rangeEndDate)
                ? [clone $nextPayment]
                : [];
        }

        $current = clone $nextPayment;
        $safetyCounter = 0;

        while ($current > $rangeStartDate) {
            $current = shiftSubscriptionOccurrence($current, $subscription, $nextPayment, -1);
            if ($current === null) {
                return [];
            }
            $safetyCounter++;
            if ($safetyCounter > 10000) {
                return [];
            }
        }

        while ($current < $rangeStartDate) {
            $current = shiftSubscriptionOccurrence($current, $subscription, $nextPayment, 1);
            if ($current === null) {
                return [];
            }
            $safetyCounter++;
            if ($safetyCounter > 10000) {
                return [];
            }
        }

        while ($current <= $rangeEndDate) {
            if ($current >= $rangeStartDate) {
                $occurrences[] = clone $current;
            }

            $nextOccurrence = shiftSubscriptionOccurrence($current, $subscription, $nextPayment, 1);
            if ($nextOccurrence === null || $nextOccurrence <= $current) {
                break;
            }
            $current = $nextOccurrence;
            $safetyCounter++;
            if ($safetyCounter > 10000) {
                break;
            }
        }

        return $occurrences;
    }
}

if (!function_exists('convertPriceToMainCurrency')) {
    function convertPriceToMainCurrency($price, $currencyId, SQLite3 $database, $userId)
    {
        return wallos_convert_price($price, $currencyId, $database, $userId);
    }
}

if (!function_exists('computeAmountNeededInPeriod')) {
    function computeAmountNeededInPeriod(array $subscriptions, DateTime $today, DateTime $periodEnd, SQLite3 $database, $userId)
    {
        $rangeStart = createDateAtMidnight($today);
        $amountNeeded = 0.0;

        foreach ($subscriptions as $subscription) {
            $isActive = isset($subscription['inactive']) && (int) $subscription['inactive'] === 0;
            if (!$isActive) {
                continue;
            }

            $occurrences = getSubscriptionOccurrencesInRange($subscription, $rangeStart, $periodEnd);
            if (empty($occurrences)) {
                continue;
            }

            $price = convertPriceToMainCurrency(
                $subscription['price'],
                $subscription['currency_id'],
                $database,
                $userId
            );

            $amountNeeded += $price * count($occurrences);
        }

        return $amountNeeded;
    }
}

?>
