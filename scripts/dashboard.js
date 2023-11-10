let isSortOptionsOpen = false;

function toggleSortOptions() {
  const sortOptions = document.querySelector("#sort-options");
  sortOptions.classList.toggle("is-open");
  isSortOptionsOpen = !isSortOptionsOpen;
}

function resetForm() {
    const id = document.querySelector("#id");
    id.value = "";
    const formTitle = document.querySelector("#form-title");
    formTitle.textContent = "Add subscription";
    const logo = document.querySelector("#form-logo");
    logo.src = "";
    logo.style = 'display: none';
    const logoSearchButton = document.querySelector("#logo-search-button");
    logoSearchButton.classList.add("disabled");
    const submitButton = document.querySelector("#save-button");
    submitButton.disabled = false;
    const form = document.querySelector("#subs-form");
    form.reset();
    closeLogoSearch();
    const deleteButton = document.querySelector("#deletesub");
    deleteButton.style = 'display: none';
    deleteButton.removeAttribute("onClick");
}

function fillEditFormFields(subscription) {
  const formTitle = document.querySelector("#form-title");
  formTitle.textContent = "Edit subscription";
  const logo = document.querySelector("#form-logo");
  const defaultLogo = window.theme && window.theme == "light" ? "images/wallos.png" : "images/walloswhite.png";
  const logoFile = subscription.logo !== null ? "images/uploads/logos/" + subscription.logo : defaultLogo;
  logo.src = logoFile;
  logo.style = 'display: block';
  const logoSearchButton = document.querySelector("#logo-search-button");
  logoSearchButton.classList.remove("disabled");
  const id = document.querySelector("#id");
  id.value = subscription.id;
  const name = document.querySelector("#name");
  name.value = subscription.name;
  const price = document.querySelector("#price");
  price.value = subscription.price;

  const currencySelect = document.querySelector("#currency");
  currencySelect.value = subscription.currency_id.toString();
  const frequencySelect = document.querySelector("#frequency");
  frequencySelect.value = subscription.frequency;
  const cycleSelect = document.querySelector("#cycle");
  cycleSelect.value = subscription.cycle;
  const paymentSelect = document.querySelector("#payment_method");
  paymentSelect.value = subscription.payment_method_id;
  const categorySelect = document.querySelector("#category");
  categorySelect.value = subscription.category_id;
  const payerSelect = document.querySelector("#payer_user");
  payerSelect.value = subscription.payer_user_id;

  const nextPament = document.querySelector("#next_payment");
  nextPament.value = subscription.next_payment;
  const notes = document.querySelector("#notes");
  notes.value = subscription.notes;

  const notifications = document.querySelector("#notifications");
  if (notifications) {
    notifications.checked = subscription.notify;
  }

  const deleteButton = document.querySelector("#deletesub");
  deleteButton.style = 'display: block';
  deleteButton.setAttribute("onClick", `deleteSubscription(${subscription.id})`);

  const modal = document.getElementById('subscription-form');
  modal.classList.add("is-open");
}

function openEditSubscription(id) {
    const url = `endpoints/subscription/get.php?id=${id}`;
    fetch(url)
    .then((response) => {
      if (response.ok) {
        return response.json();
      } else {
        showErrorMessage("Failed to load subscription");
      }
    })
    .then((data) => {
      if (data.error || data === "Error") {
        showErrorMessage("Failed to load subscription");
      } else {
        const subscription = data;
        fillEditFormFields(subscription);
      }
    })
    .catch((error) => {
      showErrorMessage("Failed to load subscription");
    });
}

function addSubscription() {
    resetForm();
    const modal = document.getElementById('subscription-form');
    modal.classList.add("is-open"); 
}

function closeAddSubscription() {
    const modal = document.getElementById('subscription-form');
    modal.classList.remove("is-open"); 
    resetForm();
}

function handleFileSelect(event) {
    const fileInput = event.target;
    const logoPreview = document.querySelector('.logo-preview');
    const logoImg = logoPreview.querySelector('img');
    const logoUrl = document.querySelector("#logo-url");
    logoUrl.value = "";

    if (fileInput.files && fileInput.files[0]) {
        const reader = new FileReader();

        reader.onload = function (e) {
            logoImg.src = e.target.result;
            logoImg.style.display = 'block';
        };

        reader.readAsDataURL(fileInput.files[0]);
    }
}

function deleteSubscription(id) {
  if (confirm("Are you sure you want to delete this subscription?")) {
    fetch(`endpoints/subscription/delete.php?id=${id}`, {
      method: 'DELETE',
    })
    .then(response => {
        if (response.ok) {
          showSuccessMessage("Subscription deleted");
          fetchSubscriptions();
          closeAddSubscription();
        } else {
          alert("Error deleting the subscription");
        }
    })
    .catch(error => {
        console.error('Error:', error);
    });
  }
}

function setSearchButtonStatus() {

  const nameInput = document.querySelector("#name");
  const hasSearchTerm = nameInput.value.trim().length > 0;
  const logoSearchButton = document.querySelector("#logo-search-button");
  if (hasSearchTerm) {
    logoSearchButton.classList.remove("disabled");
  } else {
    logoSearchButton.classList.add("disabled");
  }

}

function searchLogo() {
  const nameInput = document.querySelector("#name");
  const searchTerm = nameInput.value.trim();
  if (searchTerm !== "") {
    const logoSearchPopup = document.querySelector("#logo-search-results");
    logoSearchPopup.classList.add("is-open");
    const imageSearchUrl = `endpoints/logos/search.php?search=${searchTerm}`;
    fetch(imageSearchUrl)
            .then(response => response.json())
            .then(data => {
                if (data.imageUrls) {
                    displayImageResults(data.imageUrls);
                } else if (data.error) {
                    console.error(data.error);
                }
            })
            .catch(error => {
                console.error("Error fetching image results:", error);
            });
  } else {
    nameInput.focus();
  }
}

function displayImageResults(imageSources) {
  const logoResults = document.querySelector("#logo-search-images");
  logoResults.innerHTML = "";

  imageSources.forEach(src => {
      const img = document.createElement("img");
      img.src = src;
      img.onclick = function() {
        selectWebLogo(src);
      };
      logoResults.appendChild(img);
  });
}

function selectWebLogo(url) {
  closeLogoSearch();
  const logoPreview = document.querySelector("#form-logo");
  const logoUrl = document.querySelector("#logo-url");
  logoPreview.src = url;
  logoPreview.style.display = 'block';
  logoUrl.value = url;
}

function closeLogoSearch() {
  const logoSearchPopup = document.querySelector("#logo-search-results");
  logoSearchPopup.classList.remove("is-open");
  const logoResults = document.querySelector("#logo-search-images");
  logoResults.innerHTML = "";
}

function fetchSubscriptions() {
  const subscriptionsContainer = document.querySelector("#subscriptions");
  const getSubscriptions = "endpoints/subscriptions/get.php";

  fetch(getSubscriptions)
    .then(response => response.text())
    .then(data => {
      if (data) {
        subscriptionsContainer.innerHTML = data;
        const mainActions = document.querySelector("#main-actions");
        if (data.includes("empty-page")) {
          mainActions.classList.add("hidden");
        } else {
          mainActions.classList.remove("hidden");
        }
      }
    })
    .catch(error => {
      console.error("Error reloading subscriptions:", error);
    });
}

function setSortOption(sortOption) {
  const sortOptionsContainer = document.querySelector("#sort-options");
  const sortOptionsList = sortOptionsContainer.querySelectorAll("li");
  sortOptionsList.forEach((option) => { 
    if (option.getAttribute("id") === "sort-" + sortOption) {
      option.classList.add("selected");
    } else {
      option.classList.remove("selected");
    }
  });
  const daysToExpire = 30;
  const expirationDate = new Date();
  expirationDate.setDate(expirationDate.getDate() + daysToExpire);
  const cookieValue = encodeURIComponent(sortOption) + '; expires=' + expirationDate.toUTCString() + '; path=/';
  document.cookie = 'sortOrder=' + cookieValue;
  fetchSubscriptions();
  toggleSortOptions();
}

document.addEventListener('DOMContentLoaded', function() {
    const subscriptionForm = document.querySelector("#subs-form");
    const submitButton = document.querySelector("#save-button");
    const endpoint = "endpoints/subscription/add.php";

    subscriptionForm.addEventListener("submit", function (e) {
    e.preventDefault();
    
    submitButton.disabled = true;
    const formData = new FormData(subscriptionForm);

    fetch(endpoint, {
        method: "POST",
        body: formData,
    })
    .then((response) => response.json())
    .then((data) => {
      if (data.status === "Success") {
        showSuccessMessage(data.message);
        fetchSubscriptions();
        closeAddSubscription();
      }
    })
    .catch((error) => {
        showErrorMessage(error);
        submitButton.disabled = false;
      });
    });

    document.addEventListener('mousedown', function(event) {
      const sortOptions = document.querySelector('#sort-options');
      const sortButton = document.querySelector("#sort-button");

      if (!sortOptions.contains(event.target) && !sortButton.contains(event.target) && isSortOptionsOpen) {
        sortOptions.classList.remove('is-open');
        isSortOptionsOpen = false;
      }
    });

    document.querySelector('#sort-options').addEventListener('focus', function() {
        isSortOptionsOpen = true;
    });
});