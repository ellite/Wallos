let currentDetailsId = null;

function showSubscriptionDetails(event, id) {
  const card = document.querySelector(`.subscription[data-id="${id}"]`);
  if (card && card.classList.contains('flipped')) {
    card.classList.remove('flipped');
    return;
  }
  const url = `endpoints/subscription/get.php?id=${id}`;
  fetch(url)
    .then((response) => {
      if (response.ok) {
        return response.json();
      }
      showErrorMessage(translate('failed_to_load_subscription'));
    })
    .then((data) => {
      if (!data || data.error || data === "Error") {
        showErrorMessage(translate('failed_to_load_subscription'));
      } else {
        renderSubscriptionDetails(data);
      }
    })
    .catch((error) => {
      console.log(error);
      showErrorMessage(translate('failed_to_load_subscription'));
    });
}

function detailsFormatPrice(price, currencyId) {
  const lookups = window.subscriptionLookups;
  const currency = lookups.currencies[currencyId];
  const value = Number(price);
  if (currency && currency.code) {
    try {
      return new Intl.NumberFormat(window.lang || 'en', { style: 'currency', currency: currency.code }).format(value);
    } catch (e) { /* unknown currency code, fall through */ }
  }
  const symbol = currency ? (currency.symbol || currency.code || '') : '';
  return `${symbol}${value.toFixed(2)}`;
}

function detailsFormatDate(dateString) {
  if (!dateString) {
    return "";
  }
  const date = new Date(dateString + "T00:00:00");
  if (isNaN(date)) {
    return dateString;
  }
  try {
    return date.toLocaleDateString(window.lang || 'en', { day: 'numeric', month: 'short', year: 'numeric' });
  } catch (e) {
    return date.toLocaleDateString('en', { day: 'numeric', month: 'short', year: 'numeric' });
  }
}

function detailsBillingCycleText(cycle, frequency) {
  const units = window.subscriptionLookups.cycles[cycle];
  if (!units) {
    return "";
  }
  return Number(frequency) === 1 || Number(cycle) === 5 ? units.one : `${frequency} ${units.many}`;
}

function detailsProgressPercentage(subscription) {
  const cycleDays = { 1: 1, 2: 7, 3: 30, 4: 365 }[subscription.cycle];
  if (!cycleDays) {
    return null; // one-time purchase
  }
  const totalDays = cycleDays * subscription.frequency;
  const nextPayment = new Date(subscription.next_payment + "T00:00:00");
  if (isNaN(nextPayment)) {
    return null;
  }
  const daysUntil = (nextPayment - new Date()) / 86400000;
  const progress = ((totalDays - daysUntil) / totalDays) * 100;
  return Math.min(100, Math.max(0, Math.floor(progress)));
}

function detailsAddChip(container, text, style) {
  const chip = document.createElement('span');
  chip.className = 'details-chip' + (style ? ' ' + style : '');
  chip.textContent = text;
  container.appendChild(chip);
}

function renderSubscriptionDetails(subscription) {
  const lookups = window.subscriptionLookups;
  const strings = lookups.i18n;
  currentDetailsId = subscription.id;

  const logoContainer = document.querySelector('#details-logo');
  logoContainer.innerHTML = "";
  if (subscription.logo) {
    const img = document.createElement('img');
    img.src = "images/uploads/logos/" + subscription.logo;
    img.alt = "";
    logoContainer.appendChild(img);
  } else {
    const fallback = document.createElement('span');
    fallback.className = 'details-logo-fallback';
    fallback.textContent = (subscription.name || "?").charAt(0).toUpperCase();
    logoContainer.appendChild(fallback);
  }

  document.querySelector('#details-name').textContent = subscription.name;

  const chips = document.querySelector('#details-chips');
  chips.innerHTML = "";
  const isOneTime = Number(subscription.cycle) === 5;
  if (subscription.inactive) {
    detailsAddChip(chips, strings.inactive, 'warn');
  }
  if (isOneTime) {
    detailsAddChip(chips, strings.one_time, 'muted');
  } else if (Number(subscription.auto_renew) === 1) {
    detailsAddChip(chips, strings.automatic, 'ok');
  } else {
    detailsAddChip(chips, strings.manual_renewal, 'manual');
  }

  document.querySelector('#details-price').textContent = detailsFormatPrice(subscription.price, subscription.currency_id);
  document.querySelector('#details-billing-cycle').textContent = isOneTime ? "" : detailsBillingCycleText(subscription.cycle, subscription.frequency);

  const progressTrack = document.querySelector('#details-progress-track');
  const progress = detailsProgressPercentage(subscription);
  if (progress === null || subscription.inactive) {
    progressTrack.classList.add('hide');
  } else {
    progressTrack.classList.remove('hide');
    document.querySelector('#details-progress').style.width = progress + "%";
  }

  document.querySelector('#details-next-payment').textContent = detailsFormatDate(subscription.next_payment);
  document.querySelector('#details-start-date').textContent = detailsFormatDate(subscription.start_date) || strings.none;
  document.querySelector('#details-category').textContent = lookups.categories[subscription.category_id] || strings.none;
  document.querySelector('#details-payer').textContent = lookups.members[subscription.payer_user_id] || strings.none;

  const paymentMethod = lookups.paymentMethods[subscription.payment_method_id];
  const paymentIcon = document.querySelector('#details-payment-icon');
  if (paymentMethod) {
    paymentIcon.src = paymentMethod.icon;
    paymentIcon.classList.remove('hide');
    document.querySelector('#details-payment-name').textContent = paymentMethod.name;
  } else {
    paymentIcon.classList.add('hide');
    document.querySelector('#details-payment-name').textContent = strings.none;
  }

  let notificationsText = subscription.notify ? strings.enabled : strings.disabled;
  if (subscription.notify && subscription.notify_days_before >= 0) {
    const days = Number(subscription.notify_days_before);
    if (days === 0) {
      notificationsText += ` · ${strings.on_due_date}`;
    } else if (days === 1) {
      notificationsText += ` · 1 ${strings.day_before}`;
    } else {
      notificationsText += ` · ${days} ${strings.days_before}`;
    }
  }
  document.querySelector('#details-notifications').textContent = notificationsText;

  const cancellationItem = document.querySelector('#details-cancellation-item');
  if (subscription.cancellation_date) {
    cancellationItem.classList.remove('hide');
    document.querySelector('#details-cancellation').textContent = detailsFormatDate(subscription.cancellation_date);
  } else {
    cancellationItem.classList.add('hide');
  }

  const replacementItem = document.querySelector('#details-replacement-item');
  const replacementName = subscription.replacement_subscription_id ? lookups.subscriptionNames[subscription.replacement_subscription_id] : null;
  if (replacementName) {
    replacementItem.classList.remove('hide');
    document.querySelector('#details-replacement').textContent = replacementName;
  } else {
    replacementItem.classList.add('hide');
  }

  const notesItem = document.querySelector('#details-notes-item');
  if (subscription.notes) {
    notesItem.classList.remove('hide');
    document.querySelector('#details-notes').textContent = subscription.notes;
  } else {
    notesItem.classList.add('hide');
  }

  const urlButton = document.querySelector('#details-url-button');
  if (subscription.url) {
    urlButton.classList.remove('hide');
    urlButton.href = /^https?:\/\//.test(subscription.url) ? subscription.url : "https://" + subscription.url;
  } else {
    urlButton.classList.add('hide');
  }

  document.querySelector('#details-export-button').onclick = function () {
    exportCalendar(subscription.id);
  };

  document.querySelector('#subscription-details').classList.add('is-open');
  document.querySelector('#details-backdrop').classList.add('is-open');
  document.body.classList.add('details-open');
}

function closeSubscriptionDetails() {
  currentDetailsId = null;
  document.querySelector('#subscription-details').classList.remove('is-open');
  document.querySelector('#details-backdrop').classList.remove('is-open');
  document.body.classList.remove('details-open');
}

document.addEventListener('DOMContentLoaded', function () {
  const detailsModal = document.querySelector('#subscription-details');
  if (!detailsModal) {
    return;
  }

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape' && detailsModal.classList.contains('is-open')) {
      closeSubscriptionDetails();
    }
  });
});

function decodeHtmlEntities(str) {
  const txt = document.createElement('textarea');
  txt.innerHTML = str;
  return txt.value;
}

function exportCalendar(subscriptionId) {
  fetch('endpoints/subscription/exportcalendar.php', {
    method: 'POST',
    body: JSON.stringify({ id: subscriptionId }),
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-Token': window.csrfToken,
    }
  })
    .then(response => response.json())
    .then(data => {
      if (data.success && data.ics) {
        const blob = new Blob([data.ics], { type: 'text/calendar' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        // Use the subscription name for the file name, replacing any characters that are invalid in file names
        a.download = `${decodeHtmlEntities(data.name).replace(/[\/\\:*?"<>|]/g, '_').toLowerCase()}.ics`;
        document.body.appendChild(a);
        a.click();
        window.URL.revokeObjectURL(url);
      } else {
        showErrorMessage(data.message);
      }
    })
    .catch(error => console.error('Error:', error));
}
