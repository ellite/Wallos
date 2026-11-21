<?php
/*
  Resolve the "Payment every" selection into the cycle/frequency pair stored on
  a subscription.

  The form offers a "Custom" cycle so a user can type the number of days
  between payments directly (for example every 10, 15 or 45 days). Wallos
  already models an arbitrary day interval as cycle 1 (Daily) plus a frequency
  of N, so custom values are normalised to that pair here. This lives on the
  server as well as in the form so the option still works when the browser does
  not run the helper script.
*/

if (!function_exists('normalizeSubscriptionInterval')) {
    /**
     * @param mixed $cycle       Selected cycle id, or the literal "custom".
     * @param mixed $frequency   Selected frequency (ignored for custom).
     * @param mixed $customDays  Number of days typed into the custom input.
     *
     * @return array{cycle: int, frequency: int}|null Null when a custom
     *                                               interval is out of range.
     */
    function normalizeSubscriptionInterval($cycle, $frequency, $customDays = null)
    {
        if ((string) $cycle !== 'custom') {
            return ['cycle' => (int) $cycle, 'frequency' => (int) $frequency];
        }

        if (is_string($customDays)) {
            $customDays = trim($customDays);
        }

        if ($customDays === null || $customDays === '' || filter_var($customDays, FILTER_VALIDATE_INT) === false) {
            return null;
        }

        $days = (int) $customDays;

        // The existing "Days" frequency select offers 1 to 366, so a custom
        // interval stays inside the same supported range.
        if ($days < 1 || $days > 366) {
            return null;
        }

        return ['cycle' => 1, 'frequency' => $days];
    }
}
