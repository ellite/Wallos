<?php
/*
  The price distribution graph used to split prices on a fixed set of
  boundaries (5, 10, 20, 50) that only made sense for USD-like amounts. For a
  currency with large nominal values (JPY) every subscription fell into the
  first bucket; for very small amounts everything fell into another. These
  cases pin the buckets to the data while keeping the chart shape stable.
*/

require_once WALLOS_ROOT . '/includes/histogram_buckets.php';

function histogram_test_assert_sane(array $buckets, $expectedCount, $context)
{
    assert_same($expectedCount, count($buckets), $context . ': bucket count');
    assert_equals(0.0, $buckets[0]['min'], $context . ': buckets start at zero');

    $previousMax = null;
    foreach ($buckets as $bucket) {
        assert_true($bucket['max'] > $bucket['min'], $context . ': no zero-width bucket');
        assert_true($bucket['label'] !== '', $context . ': every bucket has a label');

        if ($previousMax !== null) {
            assert_equals($previousMax, $bucket['min'], $context . ': buckets are contiguous');
        }
        $previousMax = $bucket['max'];
    }
}

function histogram_test_bucket_index($price, array $buckets)
{
    $lastIndex = count($buckets) - 1;
    foreach ($buckets as $index => $bucket) {
        if ($price < $bucket['max'] || $index === $lastIndex) {
            return $index;
        }
    }

    return null;
}

wallos_test('histogram buckets follow USD-like prices instead of fixed boundaries (#1242)', function () {
    $prices = [4.99, 9.99, 14.99, 19.99, 24.99, 49.99];

    $buckets = wallos_histogram_buckets($prices);
    histogram_test_assert_sane($buckets, 5, 'USD-like prices');
    assert_true($buckets[4]['max'] >= 49.99, 'USD-like prices: the top bucket covers the largest price');

    $used = [];
    foreach ($prices as $price) {
        $used[histogram_test_bucket_index($price, $buckets)] = true;
    }
    assert_true(count($used) >= 3, 'USD-like prices: the data is spread over several readable buckets');
});

wallos_test('histogram buckets scale up for JPY-like prices (#1242)', function () {
    $prices = [48000, 98000, 148000, 298000, 498000];

    $buckets = wallos_histogram_buckets($prices);
    histogram_test_assert_sane($buckets, 5, 'JPY-like prices');
    assert_true($buckets[4]['max'] >= 498000, 'JPY-like prices: the top bucket covers the largest price');
    assert_true($buckets[0]['max'] > 1000, 'JPY-like prices: the first bucket is not a hardcoded USD range');

    $used = [];
    foreach ($prices as $price) {
        $used[histogram_test_bucket_index($price, $buckets)] = true;
    }
    assert_true(count($used) >= 3, 'JPY-like prices: the data is spread over several readable buckets');
});

wallos_test('histogram buckets scale down for tiny prices (#1242)', function () {
    $prices = [0.05, 0.12, 0.25, 0.5, 0.9];

    $buckets = wallos_histogram_buckets($prices);
    histogram_test_assert_sane($buckets, 5, 'tiny prices');
    assert_true($buckets[4]['max'] >= 0.9, 'tiny prices: the top bucket covers the largest price');

    $used = [];
    foreach ($prices as $price) {
        $used[histogram_test_bucket_index($price, $buckets)] = true;
    }
    assert_true(count($used) >= 3, 'tiny prices: the data is spread over several readable buckets');
});

wallos_test('histogram buckets stay sane for an empty price list (#1242)', function () {
    $buckets = wallos_histogram_buckets([]);
    histogram_test_assert_sane($buckets, 5, 'empty input');

    $buckets = wallos_histogram_buckets([], 3);
    histogram_test_assert_sane($buckets, 3, 'empty input with a custom bucket count');
});
