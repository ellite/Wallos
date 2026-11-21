(function () {
  "use strict";

  function translate(key) {
    return typeof window.translate === "function" ? window.translate(key) : key;
  }

  function togglePaid(endpoint, action, event, id) {
    if (event) {
      event.stopPropagation();
      event.preventDefault();
    }

    return fetch(endpoint, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "X-CSRF-Token": window.csrfToken,
      },
      body: JSON.stringify({ id: id }),
    })
      .then((response) => {
        if (!response.ok) {
          throw new Error(translate("network_response_error"));
        }
        return response.json();
      })
      .then((data) => {
        if (data.success) {
          if (typeof fetchSubscriptions === "function") {
            fetchSubscriptions(data.id, event, action);
          } else {
            window.location.reload();
          }
          if (typeof showSuccessMessage === "function") {
            showSuccessMessage(decodeURI(data.message));
          }
        } else if (typeof showErrorMessage === "function") {
          showErrorMessage(data.message || translate("error"));
        }
      })
      .catch((error) => {
        if (typeof showErrorMessage === "function") {
          showErrorMessage(error.message || translate("error"));
        }
      });
  }

  window.markAsPaid = function (event, id) {
    return togglePaid("endpoints/subscription/mark_paid.php", "mark_paid", event, id);
  };

  window.unmarkPaid = function (event, id) {
    return togglePaid("endpoints/subscription/unmark_paid.php", "unmark_paid", event, id);
  };
})();
