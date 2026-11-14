<?php
/*
  Histogram buckets for the price distribution graph.

  The boundaries are derived from the prices themselves so the graph stays
  readable whether a subscription costs a few cents or hundreds of thousands of
  currency units. A "nice" 1/2/5 x 10^k step is chosen from the 95th
  percentile, which keeps outliers from flattening every other bucket.
*/

/**
 * Rounds a raw step up to the next 1, 2 or 5 times a power of ten.
 *
 * @param float $rawStep
 * @return float
 */
function wallos_histogram_nice_step($rawStep)
{
    $rawStep = (float) $rawStep;

    if (!is_finite($rawStep) || $rawStep <= 0) {
        return 1.0;
    }

    $exponent = (int) floor(log10($rawStep));
    $fraction = $rawStep / pow(10, $exponent);
    $niceFraction = 10.0;
    foreach ([1.0, 2.0, 5.0] as $candidate) {
        if ($fraction <= $candidate) {
            $niceFraction = $candidate;
            break;
        }
    }

    $step = $niceFraction * pow(10, $exponent);

    // Keep tiny and huge prices on a finite, non-zero step.
    return round(max(0.01, min(1000000000000000.0, $step)), 10);
}

/**
 * @param float $value
 * @param float $step
 * @return string
 */
function wallos_histogram_format_value($value, $step)
{
    $decimals = 0;
    if ($step < 1) {
        $decimals = min(8, (int) ceil(-log10($step)));
    }

    return number_format((float) $value, $decimals, '.', '');
}

/**
 * Builds price-distribution buckets from the data.
 *
 * @param array $monthlyPrices Monthly prices in the user's main currency.
 * @param int   $bucketCount   Number of buckets to return.
 * @return array<int, array{label: string, min: float, max: float}>
 */
function wallos_histogram_buckets(array $monthlyPrices, int $bucketCount = 5): array
{
    $bucketCount = max(1, $bucketCount);

    $prices = [];
    foreach ($monthlyPrices as $price) {
        if (!is_numeric($price)) {
            continue;
        }
        $price = (float) $price;
        if (is_finite($price)) {
            $prices[] = $price;
        }
    }
    sort($prices, SORT_NUMERIC);

    if ($prices === []) {
        // No data to scale against: keep the chart's axes readable.
        $referenceMax = 100.0;
    } else {
        $rank = (int) ceil(0.95 * count($prices)) - 1;
        $rank = max(0, min(count($prices) - 1, $rank));
        $referenceMax = max(0.0, $prices[$rank]);
        if ($referenceMax <= 0) {
            $referenceMax = 1.0;
        }
    }

    $step = wallos_histogram_nice_step($referenceMax / $bucketCount);
    $buckets = [];
    for ($index = 0; $index < $bucketCount; $index++) {
        $min = $index * $step;
        $max = ($index + 1) * $step;
        $label = $index === $bucketCount - 1
            ? wallos_histogram_format_value($min, $step) . '+'
            : wallos_histogram_format_value($min, $step) . '–' . wallos_histogram_format_value($max, $step);

        $buckets[] = ['label' => $label, 'min' => $min, 'max' => $max];
    }

    return $buckets;
}
