<?php
/*
  Shared subscription details popup (backdrop + dialog + lookups).
  Include once near the end of the page, before the page's own scripts.
  Requires: $i18n, $version, $subscriptions, and the getdbkeys.php arrays.
  Runs no queries of its own — on some pages $db is already closed here.
*/
require_once __DIR__ . '/getdbkeys.php';
?>

<div class="details-backdrop" id="details-backdrop" onClick="closeSubscriptionDetails()"></div>
<section class="subscription-details" id="subscription-details" role="dialog" aria-modal="true"
  aria-labelledby="details-name">
  <button type="button" class="details-close" onClick="closeSubscriptionDetails()"
    title="<?= translate('cancel', $i18n) ?>">
    <i class="fa-solid fa-xmark"></i>
  </button>
  <header class="details-hero">
    <div class="details-heading">
      <span class="details-logo" id="details-logo"></span>
      <div class="details-chips" id="details-chips"></div>
    </div>
    <h3 id="details-name"></h3>
  </header>
  <div class="details-price-row">
    <span class="details-price" id="details-price"></span>
    <span class="details-cycle" id="details-billing-cycle"></span>
    <button type="button" class="button secondary-button details-action-button details-export-button"
      id="details-export-button" title="<?= translate('export_icalendar', $i18n) ?>"
      aria-label="<?= translate('export_icalendar', $i18n) ?>">
      <i class="fa-solid fa-calendar-plus"></i>
    </button>
    <a class="button secondary-button details-action-button hide" id="details-url-button" href="#" target="_blank"
      rel="noreferrer" title="<?= translate('external_url', $i18n) ?>"
      aria-label="<?= translate('external_url', $i18n) ?>">
      <i class="fa-solid fa-globe"></i>
    </a>
  </div>
  <div class="details-progress-track" id="details-progress-track">
    <span class="details-progress" id="details-progress"></span>
  </div>
  <dl class="details-grid">
    <div class="details-item">
      <dt><?= translate('next_payment', $i18n) ?></dt>
      <dd id="details-next-payment"></dd>
    </div>
    <div class="details-item">
      <dt><?= translate('start_date', $i18n) ?></dt>
      <dd id="details-start-date"></dd>
    </div>
    <div class="details-item">
      <dt><?= translate('category', $i18n) ?></dt>
      <dd id="details-category"></dd>
    </div>
    <div class="details-item">
      <dt><?= translate('paid_by', $i18n) ?></dt>
      <dd id="details-payer"></dd>
    </div>
    <div class="details-item">
      <dt><?= translate('payment_method', $i18n) ?></dt>
      <dd id="details-payment-method">
        <img id="details-payment-icon" src="" alt="">
        <span id="details-payment-name"></span>
      </dd>
    </div>
    <div class="details-item">
      <dt><?= translate('notifications', $i18n) ?></dt>
      <dd id="details-notifications"></dd>
    </div>
    <div class="details-item hide" id="details-cancellation-item">
      <dt><?= translate('cancellation_notification', $i18n) ?></dt>
      <dd id="details-cancellation"></dd>
    </div>
    <div class="details-item hide" id="details-replacement-item">
      <dt><?= translate('replaced_with', $i18n) ?></dt>
      <dd id="details-replacement"></dd>
    </div>
  </dl>
  <div class="details-notes hide" id="details-notes-item">
    <i class="fa-solid fa-note-sticky"></i>
    <span id="details-notes"></span>
  </div>
</section>

<?php
$detailsLookups = [
  'categories' => new stdClass(),
  'members' => new stdClass(),
  'paymentMethods' => new stdClass(),
  'currencies' => new stdClass(),
  'subscriptionNames' => new stdClass(),
  'cycles' => [
    1 => ['one' => translate('Daily', $i18n), 'many' => translate('days', $i18n)],
    2 => ['one' => translate('Weekly', $i18n), 'many' => translate('weeks', $i18n)],
    3 => ['one' => translate('Monthly', $i18n), 'many' => translate('months', $i18n)],
    4 => ['one' => translate('Yearly', $i18n), 'many' => translate('years', $i18n)],
    5 => ['one' => translate('One-time', $i18n), 'many' => translate('One-time', $i18n)],
  ],
  'i18n' => [
    'automatic' => translate('automatic', $i18n),
    'manual_renewal' => translate('manual_renewal', $i18n),
    'inactive' => translate('disabled', $i18n),
    'one_time' => translate('One-time', $i18n),
    'enabled' => translate('enabled', $i18n),
    'disabled' => translate('disabled', $i18n),
    'on_due_date' => translate('on_due_date', $i18n),
    'day_before' => translate('day_before', $i18n),
    'days_before' => translate('days_before', $i18n),
    'none' => translate('none', $i18n),
  ],
];
foreach ($categories as $categoryId => $category) {
  $detailsLookups['categories']->{$categoryId} = $category['name'];
}
foreach ($members as $memberId => $member) {
  $detailsLookups['members']->{$memberId} = $member['name'];
}
foreach ($payment_methods as $paymentMethodId => $paymentMethod) {
  $paymentIconFolder = (strpos($paymentMethod['icon'], 'images/uploads/icons/') !== false) ? "" : "images/uploads/logos/";
  $detailsLookups['paymentMethods']->{$paymentMethodId} = [
    'name' => $paymentMethod['name'],
    'icon' => $paymentIconFolder . $paymentMethod['icon'],
  ];
}
foreach ($currencies as $currencyId => $currency) {
  $detailsLookups['currencies']->{$currencyId} = [
    'code' => $currency['code'],
    'symbol' => $currency['symbol'],
  ];
}
foreach ($subscriptions as $detailsSubscription) {
  $detailsLookups['subscriptionNames']->{$detailsSubscription['id']} = $detailsSubscription['name'];
}
?>
<script>
  window.subscriptionLookups = <?= json_encode($detailsLookups, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
</script>
<script src="scripts/subscription-details.js?<?= $version ?>"></script>
