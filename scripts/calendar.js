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

function showExportPopup() {
  const host = window.location.href;
  const apiPath = "api/subscriptions/get_ical_feed.php";
  const apiKey = document.getElementById('apiKey').value;
  const queryParams = `?api_key=${apiKey}`;
  const fullUrl = host.replace('calendar.php', apiPath) + queryParams;
  document.getElementById('iCalendarUrl').value = fullUrl;
  document.getElementById('subscriptions_calendar').classList.add('is-open');
}

function closePopup() {
  document.getElementById('subscriptions_calendar').classList.remove('is-open');
}

function copyToClipboard() {
  const urlField = document.getElementById('iCalendarUrl');
  urlField.select();
  urlField.setSelectionRange(0, 99999); // For mobile devices
  navigator.clipboard.writeText(urlField.value)
      .then(() => {
          showSuccessMessage(translate('copied_to_clipboard'));
      })
      .catch(() => {
          showErrorMessage(translate('unknown_error'));
      });
}