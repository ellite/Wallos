<?php
// Wallos v5 — additional statistics for stats.php.
// Requires stats_calculations.php to have run first ($subscriptions, $db, $userId, $userData, $i18n, $lang).
// All monetary values are converted to the main currency. Lifetime figures assume current prices.

$monthLabelFormatter = null;
try {
    $monthLabelFormatter = new IntlDateFormatter($lang, IntlDateFormatter::SHORT, IntlDateFormatter::NONE, null, null, 'MMM yy');
} catch (Throwable $e) {
    $monthLabelFormatter = new IntlDateFormatter('en', IntlDateFormatter::SHORT, IntlDateFormatter::NONE, null, null, 'MMM yy');
}

// Currency codes for the currency exposure split
$currencyCodesById = [];
$stmt = $db->prepare("SELECT id, code FROM currencies WHERE user_id = :userId");
$stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
$result = $stmt->execute();
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $currencyCodesById[$row['id']] = $row['code'];
}

// 12 projection buckets, starting next month
$projectionBuckets = [];
$projectionStart = new DateTime('first day of next month');
$projectionEnd = (clone $projectionStart)->modify('+12 months');
for ($i = 0; $i < 12; $i++) {
    $bucketDate = (clone $projectionStart)->modify("+$i months");
    $projectionBuckets[$bucketDate->format('Y-m')] = [
        'label' => $monthLabelFormatter->format($bucketDate),
        'total' => 0,
    ];
}

$cycleStepUnits = [1 => 'days', 2 => 'weeks', 3 => 'months', 4 => 'years'];

$activeMonthlyPrices = [];
$cheapestSubscription = null;
$manualRenewalsCount = 0;
$cycleSpend = [];
$currencySpend = [];
$paymentMethodCost = [];
$lifetimeSpends = [];
$totalLifetimeSpend = 0;
$oldestSubscription = null;
$subscriptionAges = [];
$newSubscriptionsPerYear = [];
$now = new DateTime('today');

if (isset($subscriptions)) {
    foreach ($subscriptions as $subscription) {
        $cycle = (int) $subscription['cycle'];
        $frequency = max(1, (int) $subscription['frequency']);
        $convertedPrice = getPriceConverted($subscription['price'], $subscription['currency_id'], $db, $userId);
        $monthlyPrice = getPricePerMonth($cycle, $frequency, $convertedPrice);

        // New subscriptions per year (all subscriptions, active or not)
        $startDate = null;
        if (!empty($subscription['start_date'])) {
            $startTimestamp = strtotime($subscription['start_date']);
            if ($startTimestamp !== false) {
                $startDate = (new DateTime())->setTimestamp($startTimestamp);
                $startYear = $startDate->format('Y');
                $newSubscriptionsPerYear[$startYear] = ($newSubscriptionsPerYear[$startYear] ?? 0) + 1;
            }
        }

        if ($subscription['inactive']) {
            continue;
        }

        // Split views over active spend
        if ($monthlyPrice > 0) {
            $cycleSpend[$cycle] = ($cycleSpend[$cycle] ?? 0) + $monthlyPrice;
            $currencyCode = $currencyCodesById[$subscription['currency_id']] ?? '?';
            $currencySpend[$currencyCode] = ($currencySpend[$currencyCode] ?? 0) + $monthlyPrice;
            $paymentMethodCost[$subscription['payment_method_id']] = ($paymentMethodCost[$subscription['payment_method_id']] ?? 0) + $monthlyPrice;
        }

        if ($cycle !== 5) {
            $activeMonthlyPrices[] = $monthlyPrice;

            if ($cheapestSubscription === null || $monthlyPrice < $cheapestSubscription['price']) {
                $cheapestSubscription = [
                    'price' => $monthlyPrice,
                    'name' => $subscription['name'],
                    'logo' => $subscription['logo'],
                    'logo_text_color' => $subscription['logo_text_color'] ?? null,
                    'logo_variant' => $subscription['logo_variant'] ?? null,
                ];
            }

            if ((int) $subscription['auto_renew'] !== 1) {
                $manualRenewalsCount++;
            }

            // Projection: walk renewals forward into the buckets
            $paymentTimestamp = strtotime($subscription['next_payment'] ?? '');
            if ($paymentTimestamp !== false && isset($cycleStepUnits[$cycle])) {
                $paymentDate = (new DateTime())->setTimestamp($paymentTimestamp);
                $safety = 0;
                while ($paymentDate < $projectionEnd && $safety < 1000) {
                    if ($paymentDate >= $projectionStart) {
                        $bucketKey = $paymentDate->format('Y-m');
                        if (isset($projectionBuckets[$bucketKey])) {
                            $projectionBuckets[$bucketKey]['total'] += $convertedPrice;
                        }
                    }
                    $paymentDate->modify("+{$frequency} {$cycleStepUnits[$cycle]}");
                    $safety++;
                }
            }

            // Lifetime spend and age (recurring subscriptions with a start date)
            if ($startDate !== null && $startDate <= $now) {
                $daysActive = $startDate->diff($now)->days;
                $ageYears = $daysActive / 365.25;
                $subscriptionAges[] = $ageYears;
                $lifetimeSpend = $monthlyPrice * ($daysActive / 30.44);
                $totalLifetimeSpend += $lifetimeSpend;
                $lifetimeSpends[] = [
                    'label' => html_entity_decode($subscription['name']),
                    'y' => round($lifetimeSpend, 2),
                ];

                if ($oldestSubscription === null || $ageYears > $oldestSubscription['years']) {
                    $oldestSubscription = [
                        'years' => $ageYears,
                        'name' => $subscription['name'],
                        'logo' => $subscription['logo'],
                        'logo_text_color' => $subscription['logo_text_color'] ?? null,
                        'logo_variant' => $subscription['logo_variant'] ?? null,
                    ];
                }
            }
        }
    }
}

// --- Tiles ---

$costPerDay = $totalCostPerMonth > 0 ? ($totalCostPerMonth * 12) / 365.25 : 0;

$averageSubscriptionAge = count($subscriptionAges) > 0 ? array_sum($subscriptionAges) / count($subscriptionAges) : null;

// Month-over-month delta from the recorded cost trend
$monthOverMonthDelta = null;
$monthOverMonthPercentage = null;
if (isset($totalMonthlyCostDataPoints) && count($totalMonthlyCostDataPoints) >= 2) {
    $lastPoint = $totalMonthlyCostDataPoints[count($totalMonthlyCostDataPoints) - 1]['y'];
    $previousPoint = $totalMonthlyCostDataPoints[count($totalMonthlyCostDataPoints) - 2]['y'];
    $monthOverMonthDelta = $lastPoint - $previousPoint;
    if ($previousPoint > 0) {
        $monthOverMonthPercentage = ($monthOverMonthDelta / $previousPoint) * 100;
    }
}

// Projection-derived tiles
$projectionDataPoints = [];
$heaviestMonth = null;
$monthsOverBudget = null;
foreach ($projectionBuckets as $bucket) {
    $bucketTotal = round($bucket['total'], 2);
    $projectionDataPoints[] = ['label' => $bucket['label'], 'y' => $bucketTotal];
    if ($heaviestMonth === null || $bucketTotal > $heaviestMonth['total']) {
        $heaviestMonth = ['label' => $bucket['label'], 'total' => $bucketTotal];
    }
}
$showProjectionGraph = $activeSubscriptions > 0 && $heaviestMonth !== null && $heaviestMonth['total'] > 0;
if (isset($budget) && $budget > 0 && $showProjectionGraph) {
    $monthsOverBudget = count(array_filter($projectionDataPoints, fn($point) => $point['y'] > $budget));
}

// --- Graph datasets ---

usort($lifetimeSpends, fn($a, $b) => $b['y'] <=> $a['y']);
$lifetimeDataPoints = array_slice($lifetimeSpends, 0, 10);
$showLifetimeGraph = count($lifetimeDataPoints) >= 2;

$cycleDataPoints = [];
foreach ($cycleSpend as $cycleId => $spend) {
    if ($cycleId === 5 || $spend <= 0) {
        continue;
    }
    $cycleNames = [1 => 'Daily', 2 => 'Weekly', 3 => 'Monthly', 4 => 'Yearly'];
    $cycleDataPoints[] = [
        'label' => translate($cycleNames[$cycleId], $i18n),
        'y' => round($spend, 2),
    ];
}
usort($cycleDataPoints, fn($a, $b) => $b['y'] <=> $a['y']);
$showCycleGraph = count($cycleDataPoints) > 1;

$currencyDataPoints = [];
foreach ($currencySpend as $currencyCode => $spend) {
    if ($spend > 0) {
        $currencyDataPoints[] = ['label' => $currencyCode, 'y' => round($spend, 2)];
    }
}
usort($currencyDataPoints, fn($a, $b) => $b['y'] <=> $a['y']);
$showCurrencyGraph = count($currencyDataPoints) > 1;

$histogramBoundaries = [5, 10, 20, 50];
$histogramCounts = array_fill(0, count($histogramBoundaries) + 1, 0);
foreach ($activeMonthlyPrices as $monthlyPrice) {
    $bucketIndex = count($histogramBoundaries);
    foreach ($histogramBoundaries as $index => $boundary) {
        if ($monthlyPrice < $boundary) {
            $bucketIndex = $index;
            break;
        }
    }
    $histogramCounts[$bucketIndex]++;
}
$histogramDataPoints = [];
$previousBoundary = 0;
foreach ($histogramBoundaries as $index => $boundary) {
    $histogramDataPoints[] = ['label' => "{$previousBoundary}–{$boundary}", 'y' => $histogramCounts[$index]];
    $previousBoundary = $boundary;
}
$histogramDataPoints[] = ['label' => "{$previousBoundary}+", 'y' => $histogramCounts[count($histogramBoundaries)]];
$showHistogramGraph = count($activeMonthlyPrices) >= 3;

ksort($newSubscriptionsPerYear);
$newPerYearDataPoints = [];
foreach ($newSubscriptionsPerYear as $year => $yearCount) {
    $newPerYearDataPoints[] = ['label' => (string) $year, 'y' => $yearCount];
}
$showYearlyNewGraph = count($newPerYearDataPoints) >= 2;

?>
