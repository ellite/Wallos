<?php

if (!function_exists('sanitizeBudgetPeriodType')) {
    function sanitizeBudgetPeriodType($periodType)
    {
        $allowedTypes = ['weekly', 'fortnightly', 'monthly'];
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

if (!function_exists('getActiveBudgetPeriod')) {
    function getActiveBudgetPeriod(DateTime $today, $periodType, $anchorDate)
    {
        $periodType = sanitizeBudgetPeriodType($periodType);
        $anchorDate = sanitizeBudgetAnchorDate($anchorDate ?: getDefaultBudgetAnchorDate());

        $todayDate = createDateAtMidnight($today);
        $anchor = DateTime::createFromFormat('!Y-m-d', $anchorDate);
        if ($anchor === false) {
            $anchor = new DateTime('1970-01-01');
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
        $query = "SELECT rate FROM currencies WHERE id = :currencyId AND user_id = :userId";
        $stmt = $database->prepare($query);
        $stmt->bindValue(':currencyId', $currencyId, SQLITE3_INTEGER);
        $stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
        $result = $stmt->execute();
        $exchangeRate = $result ? $result->fetchArray(SQLITE3_ASSOC) : false;

        if ($exchangeRate === false || empty($exchangeRate['rate'])) {
            return (float) $price;
        }

        return (float) $price / (float) $exchangeRate['rate'];
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
