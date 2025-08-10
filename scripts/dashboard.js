document.addEventListener("DOMContentLoaded", function () {
  document.querySelectorAll(".ai-recommendation-item").forEach(function (item) {
    item.addEventListener("click", function () {
      item.classList.toggle("expanded");
    });
  });
});