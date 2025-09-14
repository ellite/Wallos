document.addEventListener("DOMContentLoaded", function () {
  document.querySelectorAll(".ai-recommendation-item").forEach(function (item) {
    item.addEventListener("click", function () {
      item.classList.toggle("expanded");
    });
  });

  document.querySelectorAll(".delete-ai-recommendation").forEach(function (el) {
    el.addEventListener("click", function (e) {
      e.preventDefault();
      e.stopPropagation();
      const item = el.closest(".ai-recommendation-item");
      const id = item.getAttribute("data-id");
      fetch("endpoints/ai/delete_recommendation.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ id: id })
      })
        .then(res => res.json())
        .then(data => {
          if (data.success) {
            item.remove();
            showSuccessMessage(translate('success'));
          } else {
            showErrorMessage(data.message || "Delete failed.");
          }
        })
        .catch(() => showErrorMessage(translate('unknown_error')));
    });
  });
});

