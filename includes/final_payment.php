<?php
// The final date bounds scheduled charges; it does not record payment or cancellation.
function wallos_final_payment_error($date, $startDate, $nextPayment)
{
    if ($date === null || $date === '') {
        return null;
    }
    if (!is_string($date)) {
        return 'Final payment date must be a valid date in YYYY-MM-DD format.';
    }
    $parsed = DateTime::createFromFormat('!Y-m-d', $date);
    if (!$parsed || $parsed->format('Y-m-d') !== $date) {
        return 'Final payment date must be a valid date in YYYY-MM-DD format.';
    }
    if (($startDate && $date < $startDate) || ($nextPayment && $date < $nextPayment)) {
        return 'Final payment date cannot be before the start or next payment date.';
    }
    return null;
}

function wallos_payment_within_final_date(array $subscription, $date)
{
    $finalDate = $subscription['final_payment_date'] ?? null;
    return empty($finalDate) || $date <= $finalDate;
}

function wallos_subscription_ended(array $subscription, $today = null)
{
    $finalDate = $subscription['final_payment_date'] ?? null;
    return !empty($finalDate) && $finalDate < ($today ?? date('Y-m-d'));
}
?>
