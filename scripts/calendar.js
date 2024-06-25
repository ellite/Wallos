function nextMonth(currentMonth, currentYear) {
  let nextMonth = currentMonth + 1;
  let nextYear = currentYear;
  if (nextMonth > 12) {
    nextMonth = 1;
    nextYear += 1;
  }
  window.location.href = `calendar.php?month=${nextMonth}&year=${nextYear}`;
}

function prevMonth(currentMonth, currentYear) {
  let prevMonth = currentMonth - 1;
  let prevYear = currentYear;
  if (prevMonth < 1) {
    prevMonth = 12;
    prevYear -= 1;
  }
  window.location.href = `calendar.php?month=${prevMonth}&year=${prevYear}`;
}

function currentMoth() {
    window.location.href = `calendar.php`;
}

function closeSubscriptionModal() {
    const modal = document.getElementById('subscriptionModal');
    modal.classList.remove('is-open');
}

function openSubscriptionModal(subscriptionId) {
    const modal = document.getElementById('subscriptionModal');
    const modalContent = document.getElementById('subscriptionModalContent');

    modalContent.innerHTML = '';

    fetch('endpoints/subscription/getcalendar.php', {
        method: 'POST',
        body: JSON.stringify({id: subscriptionId}),
        headers: {
          'Content-Type': 'application/json'
        }
      })
      .then(response => response.json())
      .then(data => {
        if (data.success && data.data) {
          const subscription = data.data;
          const html = `
            <div class="modal-header">
                <h3>${subscription.name}</h3>
                <span class="fa-solid fa-xmark close-modal" onclick="closeSubscriptionModal()"></span>
            </div>
            <div class="modal-body">
                ${subscription.logo ? `<div class="subscription-logo">
                <img src="images/uploads/logos/${subscription.logo}" alt="${subscription.name}">
                </div>` : ''}
                <div class="subscription-info">
                ${subscription.price ? `<p><strong>${translate('price')}:</strong> ${subscription.currency}${subscription.price}</p>` : ''}
                ${subscription.category ? `<p><strong>${translate('category')}:</strong> ${subscription.category}</p>` : ''}
                ${subscription.payer_user ? `<p><strong>${translate('paid_by')}:</strong> ${subscription.payer_user}</p>` : ''}
                ${subscription.payment_method ? `<p><strong>${translate('payment_method')}:</strong> ${subscription.payment_method}</p>` : ''}
                ${subscription.notes ? `<p><strong>${translate('notes')}:</strong> ${subscription.notes}</p>` : ''}
                </div>
            </div>`;
          modalContent.innerHTML = html;
          modal.classList.add('is-open');
        } else {
          console.error(data.message);
        }
      })
      .catch(error => console.error('Error:', error));
}