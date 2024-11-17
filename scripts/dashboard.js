let isSortOptionsOpen = false;
let scrollTopBeforeOpening = 0;
const shouldScroll = window.innerWidth <= 768;

function toggleOpenSubscription(subId) {
  const subscriptionElement = document.querySelector('.subscription[data-id="' + subId + '"]');
  subscriptionElement.classList.toggle('is-open');
}

function toggleSortOptions() {
  const sortOptions = document.querySelector("#sort-options");
  sortOptions.classList.toggle("is-open");
  isSortOptionsOpen = !isSortOptionsOpen;
}

function toggleNotificationDays() {
  const notifyCheckbox = document.querySelector("#notifications");
  const notifyDaysBefore = document.querySelector("#notify_days_before");
  notifyDaysBefore.disabled = !notifyCheckbox.checked;
}

function resetForm() {
  const id = document.querySelector("#id");
  id.value = "";
  const formTitle = document.querySelector("#form-title");
  formTitle.textContent = translate('add_subscription');
  const logo = document.querySelector("#form-logo");
  logo.src = "";
  logo.style = 'display: none';
  const logoUrl = document.querySelector("#logo-url");
  logoUrl.value = "";
  const logoSearchButton = document.querySelector("#logo-search-button");
  logoSearchButton.classList.add("disabled");
  const submitButton = document.querySelector("#save-button");
  submitButton.disabled = false;
  const autoRenew = document.querySelector("#auto_renew");
  autoRenew.checked = true;
  const startDate = document.querySelector("#start_date");
  startDate.value = new Date().toISOString().split('T')[0];
  const notifyDaysBefore = document.querySelector("#notify_days_before");
  notifyDaysBefore.disabled = true;
  const replacementSubscriptionIdSelect = document.querySelector("#replacement_subscription_id");
  replacementSubscriptionIdSelect.value = "0";
  const replacementSubscription = document.querySelector(`#replacement_subscritpion`);
  replacementSubscription.classList.add("hide");
  const form = document.querySelector("#subs-form");
  form.reset();
  closeLogoSearch();
  const deleteButton = document.querySelector("#deletesub");
  deleteButton.style = 'display: none';
  deleteButton.removeAttribute("onClick");
}

function fillEditFormFields(subscription) {
  const formTitle = document.querySelector("#form-title");
  formTitle.textContent = translate('edit_subscription');
  const logo = document.querySelector("#form-logo");
  const logoFile = subscription.logo !== null ? "images/uploads/logos/" + subscription.logo : "";
  if (logoFile) {
    logo.src = logoFile;
    logo.style = 'display: block';
  }
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

  const startDate = document.querySelector("#start_date");
  startDate.value = subscription.start_date;
  const nextPament = document.querySelector("#next_payment");
  nextPament.value = subscription.next_payment;
  const cancellationDate = document.querySelector("#cancellation_date");
  cancellationDate.value = subscription.cancellation_date;

  const notes = document.querySelector("#notes");
  notes.value = subscription.notes;
  const inactive = document.querySelector("#inactive");
  inactive.checked = subscription.inactive;
  const url = document.querySelector("#url");
  url.value = subscription.url;

  const autoRenew = document.querySelector("#auto_renew");
  if (autoRenew) {
    autoRenew.checked = subscription.auto_renew;
  }

  const notifications = document.querySelector("#notifications");
  if (notifications) {
    notifications.checked = subscription.notify;
  }

  const notifyDaysBefore = document.querySelector("#notify_days_before");
  notifyDaysBefore.value = subscription.notify_days_before ?? 0;
  if (subscription.notify === 1) {
    notifyDaysBefore.disabled = false;
  }

  const replacementSubscriptionIdSelect = document.querySelector("#replacement_subscription_id");
  replacementSubscriptionIdSelect.value = subscription.replacement_subscription_id ?? 0;

  const replacementSubscription = document.querySelector(`#replacement_subscritpion`);
  if (subscription.inactive) {
    replacementSubscription.classList.remove("hide");
  } else {
    replacementSubscription.classList.add("hide");
  }

  const deleteButton = document.querySelector("#deletesub");
  deleteButton.style = 'display: block';
  deleteButton.setAttribute("onClick", `deleteSubscription(event, ${subscription.id})`);

  const modal = document.getElementById('subscription-form');
  modal.classList.add("is-open");
}

function openEditSubscription(event, id) {
  event.stopPropagation();
  scrollTopBeforeOpening = window.scrollY;
  const body = document.querySelector('body');
  body.classList.add('no-scroll');
  const url = `endpoints/subscription/get.php?id=${id}`;
  fetch(url)
    .then((response) => {
      if (response.ok) {
        return response.json();
      } else {
        showErrorMessage(translate('failed_to_load_subscription'));
      }
    })
    .then((data) => {
      if (data.error || data === "Error") {
        showErrorMessage(translate('failed_to_load_subscription'));
      } else {
        const subscription = data;
        fillEditFormFields(subscription);
      }
    })
    .catch((error) => {
      console.log(error);
      showErrorMessage(translate('failed_to_load_subscription'));
    });
}

function addSubscription() {
  resetForm();
  const modal = document.getElementById('subscription-form');
  modal.classList.add("is-open");
  const body = document.querySelector('body');
  body.classList.add('no-scroll');
}

function closeAddSubscription() {
  const modal = document.getElementById('subscription-form');
  modal.classList.remove("is-open");
  const body = document.querySelector('body');
  body.classList.remove('no-scroll');
  if (shouldScroll) {
    window.scrollTo(0, scrollTopBeforeOpening);
  }
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

function deleteSubscription(event, id) {
  event.stopPropagation();
  event.preventDefault();
  if (confirm(translate('confirm_delete_subscription'))) {
    fetch(`endpoints/subscription/delete.php?id=${id}`, {
      method: 'DELETE',
    })
      .then(response => {
        if (response.ok) {
          showSuccessMessage(translate('subscription_deleted'));
          fetchSubscriptions(null, null, "delete");
          closeAddSubscription();
        } else {
          showErrorMessage(translate('error_deleting_subscription'));
        }
      })
      .catch(error => {
        console.error('Error:', error);
      });
  }
}

function cloneSubscription(event, id) {
  event.stopPropagation();
  event.preventDefault();

  const url = `endpoints/subscription/clone.php?id=${id}`;

  fetch(url)
    .then(response => {
      if (!response.ok) {
        throw new Error(translate('network_response_error'));
      }
      return response.json();
    })
    .then(data => {
      if (data.success) {
        const id = data.id;
        fetchSubscriptions(id, event, "clone");
        showSuccessMessage(decodeURI(data.message));
      } else {
        showErrorMessage(data.message || translate('error'));
      }
    })
    .catch(error => {
      showErrorMessage(error.message || translate('error'));
    });
}

function renewSubscription(event, id) {
  event.stopPropagation();
  event.preventDefault();

  const url = `endpoints/subscription/renew.php?id=${id}`;

  fetch(url)
    .then(response => {
      if (!response.ok) {
        throw new Error(translate('network_response_error'));
      }
      return response.json();
    })
    .then(data => {
      if (data.success) {
        const id = data.id;
        fetchSubscriptions(id, event, "renew");
        showSuccessMessage(decodeURI(data.message));
      } else {
        showErrorMessage(data.message || translate('error'));
      }
    })
    .catch(error => {
      showErrorMessage(error.message || translate('error'));
    });
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
        console.error(translate('error_fetching_image_results'), error);
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
    img.onclick = function () {
      selectWebLogo(src);
    };
    img.onerror = function () {
      this.parentNode.removeChild(this);
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

function fetchSubscriptions(id, event, initiator) {
  const subscriptionsContainer = document.querySelector("#subscriptions");
  let getSubscriptions = "endpoints/subscriptions/get.php";

  if (activeFilters['categories'].length > 0) {
    getSubscriptions += `?categories=${activeFilters['categories']}`;
  }
  if (activeFilters['members'].length > 0) {
    getSubscriptions += getSubscriptions.includes("?") ? `&members=${activeFilters['members']}` : `?members=${activeFilters['members']}`;
  }
  if (activeFilters['payments'].length > 0) {
    getSubscriptions += getSubscriptions.includes("?") ? `&payments=${activeFilters['payments']}` : `?payments=${activeFilters['payments']}`;
  }
  if (activeFilters['state'] !== "") {
    getSubscriptions += getSubscriptions.includes("?") ? `&state=${activeFilters['state']}` : `?state=${activeFilters['state']}`;
  }

  fetch(getSubscriptions)
    .then(response => response.text())
    .then(data => {
      if (data) {
        subscriptionsContainer.innerHTML = data;
        const mainActions = document.querySelector("#main-actions");
        if (data.includes("no-matching-subscriptions")) {
          // mainActions.classList.add("hidden");
        } else {
          mainActions.classList.remove("hidden");
        }
      }

      if (initiator == "clone" && id && event) {
        openEditSubscription(event, id);
      }

      setSwipeElements();
      if (initiator === "add") {
        if (document.getElementsByClassName('subscription').length === 1) {
          setTimeout(() => {
            swipeHintAnimation();
          }, 1000);
        }
      }
    })
    .catch(error => {
      console.error(translate('error_reloading_subscription'), error);
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
  const cookieValue = encodeURIComponent(sortOption) + '; expires=' + expirationDate.toUTCString();
  document.cookie = 'sortOrder=' + cookieValue + '; SameSite=Strict';
  fetchSubscriptions(null, null, "sort");
  toggleSortOptions();
}

function convertSvgToPng(file, callback) {
  const reader = new FileReader();

  reader.onload = function (e) {
    const img = new Image();
    img.src = e.target.result;
    img.onload = function () {
      const canvas = document.createElement('canvas');
      canvas.width = img.width;
      canvas.height = img.height;
      const ctx = canvas.getContext('2d');
      ctx.drawImage(img, 0, 0);
      const pngDataUrl = canvas.toDataURL('image/png');
      const pngFile = dataURLtoFile(pngDataUrl, file.name.replace(".svg", ".png"));
      callback(pngFile);
    };
  };

  reader.readAsDataURL(file);
}

function dataURLtoFile(dataurl, filename) {
  let arr = dataurl.split(','),
    mime = arr[0].match(/:(.*?);/)[1],
    bstr = atob(arr[1]),
    n = bstr.length,
    u8arr = new Uint8Array(n);

  while (n--) {
    u8arr[n] = bstr.charCodeAt(n);
  }

  return new File([u8arr], filename, { type: mime });
}

function submitFormData(formData, submitButton, endpoint) {
  fetch(endpoint, {
    method: "POST",
    body: formData,
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.status === "Success") {
        showSuccessMessage(data.message);
        fetchSubscriptions(null, null, "add");
        closeAddSubscription();

      }
    })
    .catch((error) => {
      showErrorMessage(error);
      submitButton.disabled = false;
    });
}

document.addEventListener('DOMContentLoaded', function () {
  const subscriptionForm = document.querySelector("#subs-form");
  const submitButton = document.querySelector("#save-button");
  const endpoint = "endpoints/subscription/add.php";

  subscriptionForm.addEventListener("submit", function (e) {
    e.preventDefault();

    submitButton.disabled = true;
    const formData = new FormData(subscriptionForm);

    const fileInput = document.querySelector("#logo");
    const file = fileInput.files[0];

    if (file && file.type === "image/svg+xml") {
      convertSvgToPng(file, function (pngFile) {
        formData.set("logo", pngFile);
        submitFormData(formData, submitButton, endpoint);
      });
    } else {
      submitFormData(formData, submitButton, endpoint);
    }
  });

  document.addEventListener('mousedown', function (event) {
    const sortOptions = document.querySelector('#sort-options');
    const sortButton = document.querySelector("#sort-button");

    if (!sortOptions.contains(event.target) && !sortButton.contains(event.target) && isSortOptionsOpen) {
      sortOptions.classList.remove('is-open');
      isSortOptionsOpen = false;
    }
  });

  document.querySelector('#sort-options').addEventListener('focus', function () {
    isSortOptionsOpen = true;
  });
});

function searchSubscriptions() {
  const searchInput = document.querySelector("#search");
  const searchContainer = searchInput.parentElement;
  const searchTerm = searchInput.value.trim().toLowerCase();

  if (searchTerm.length > 0) {
    searchContainer.classList.add("has-text");
  } else {
    searchContainer.classList.remove("has-text");
  }

  const subscriptions = document.querySelectorAll(".subscription");
  subscriptions.forEach(subscription => {
    const name = subscription.getAttribute('data-name').toLowerCase();
    if (!name.includes(searchTerm)) {
      subscription.parentElement.classList.add("hide");
    } else {
      subscription.parentElement.classList.remove("hide");
    }
  });
}

function clearSearch() {
  const searchInput = document.querySelector("#search");

  searchInput.value = "";
  searchSubscriptions();
}

function closeSubMenus() {
  var subMenus = document.querySelectorAll('.filtermenu-submenu-content');
  subMenus.forEach(subMenu => {
    subMenu.classList.remove('is-open');
  });

}

function setSwipeElements() {
  if (window.mobileNavigation) {
    const swipeElements = document.querySelectorAll('.subscription');

    swipeElements.forEach((element) => {
      let startX = 0;
      let startY = 0;
      let currentX = 0;
      let currentY = 0;
      let translateX = 0;
      const maxTranslateX = element.classList.contains('manual') ? -240 : -180;

      element.addEventListener('touchstart', (e) => {
        startX = e.touches[0].clientX;
        startY = e.touches[0].clientY;
        element.style.transition = ''; // Remove transition for smooth dragging
      });

      element.addEventListener('touchmove', (e) => {
        currentX = e.touches[0].clientX;
        currentY = e.touches[0].clientY;

        const diffX = currentX - startX;
        const diffY = currentY - startY;

        // Check if the swipe is more horizontal than vertical
        if (Math.abs(diffX) > Math.abs(diffY)) {
          e.preventDefault(); // Prevent vertical scrolling

          // Only update translateX if swiping within allowed range
          if (!(translateX === maxTranslateX && diffX < 0)) {
            translateX = Math.min(0, Math.max(maxTranslateX, diffX)); // Clamp translateX between -180 and 0
            element.style.transform = `translateX(${translateX}px)`;
          }
        }
      });

      element.addEventListener('touchend', () => {
        // Check the final swipe position to determine snap behavior
        if (translateX < maxTranslateX / 2) {
          // If more than halfway to the left, snap fully open
          translateX = maxTranslateX;
        } else {
          // If swiped less than halfway left or swiped right, snap back to closed
          translateX = 0;
        }
        element.style.transition = 'transform 0.2s ease'; // Smooth snap effect
        element.style.transform = `translateX(${translateX}px)`;
        element.style.zIndex = '1';
      });
    });

  }
}

const activeFilters = [];
activeFilters['categories'] = [];
activeFilters['members'] = [];
activeFilters['payments'] = [];
activeFilters['state'] = "";

document.addEventListener("DOMContentLoaded", function () {
  var filtermenu = document.querySelector('#filtermenu-button');
  filtermenu.addEventListener('click', function () {
    this.parentElement.querySelector('.filtermenu-content').classList.toggle('is-open');
    closeSubMenus();
  });

  document.addEventListener('click', function (e) {
    var filtermenuContent = document.querySelector('.filtermenu-content');
    if (filtermenuContent.classList.contains('is-open')) {
      var subMenus = document.querySelectorAll('.filtermenu-submenu');
      var clickedInsideSubmenu = Array.from(subMenus).some(subMenu => subMenu.contains(e.target) || subMenu === e.target);

      if (!filtermenu.contains(e.target) && !clickedInsideSubmenu) {
        closeSubMenus();
        filtermenuContent.classList.remove('is-open');
      }
    }
  });

  setSwipeElements();

});

function toggleSubMenu(subMenu) {
  var subMenu = document.getElementById("filter-" + subMenu);
  if (subMenu.classList.contains("is-open")) {
    closeSubMenus();
  } else {
    closeSubMenus();
    subMenu.classList.add("is-open");
  }
}

function toggleReplacementSub() {
  const checkbox = document.getElementById('inactive');
  const replacementSubscription = document.querySelector(`#replacement_subscritpion`);

  if (checkbox.checked) {
    replacementSubscription.classList.remove("hide");
  } else {
    replacementSubscription.classList.add("hide");
  }
}

document.querySelectorAll('.filter-item').forEach(function (item) {
  item.addEventListener('click', function (e) {
    const searchInput = document.querySelector("#search");
    searchInput.value = "";

    if (this.hasAttribute('data-categoryid')) {
      const categoryId = this.getAttribute('data-categoryid');
      if (activeFilters['categories'].includes(categoryId)) {
        const categoryIndex = activeFilters['categories'].indexOf(categoryId);
        activeFilters['categories'].splice(categoryIndex, 1);
        this.classList.remove('selected');
      } else {
        activeFilters['categories'].push(categoryId);
        this.classList.add('selected');
      }
    } else if (this.hasAttribute('data-memberid')) {
      const memberId = this.getAttribute('data-memberid');
      if (activeFilters['members'].includes(memberId)) {
        const memberIndex = activeFilters['members'].indexOf(memberId);
        activeFilters['members'].splice(memberIndex, 1);
        this.classList.remove('selected');
      } else {
        activeFilters['members'].push(memberId);
        this.classList.add('selected');
      }
    } else if (this.hasAttribute('data-paymentid')) {
      const paymentId = this.getAttribute('data-paymentid');
      if (activeFilters['payments'].includes(paymentId)) {
        const paymentIndex = activeFilters['payments'].indexOf(paymentId);
        activeFilters['payments'].splice(paymentIndex, 1);
        this.classList.remove('selected');
      } else {
        activeFilters['payments'].push(paymentId);
        this.classList.add('selected');
      }
    } else if (this.hasAttribute('data-state')) {
      const state = this.getAttribute('data-state');
      if (activeFilters['state'] === state) {
        activeFilters['state'] = "";
        this.classList.remove('selected');
      } else {
        activeFilters['state'] = state;
        Array.from(this.parentNode.children).forEach(sibling => {
          sibling.classList.remove('selected');
        });
        this.classList.add('selected');
      }
    }

    if (activeFilters['categories'].length > 0 || activeFilters['members'].length > 0 || activeFilters['payments'].length > 0) {
      document.querySelector('#clear-filters').classList.remove('hide');
    } else {
      document.querySelector('#clear-filters').classList.add('hide');
    }

    fetchSubscriptions(null, null, "filter");
  });
});

function clearFilters() {
  const searchInput = document.querySelector("#search");
  searchInput.value = "";
  activeFilters['categories'] = [];
  activeFilters['members'] = [];
  activeFilters['payments'] = [];
  document.querySelectorAll('.filter-item').forEach(function (item) {
    item.classList.remove('selected');
  });
  document.querySelector('#clear-filters').classList.add('hide');
  fetchSubscriptions(null, null, "clearfilters");
}

let currentActions = null;

document.addEventListener('click', function (event) {
  // Check if click was outside currentActions
  if (currentActions && !currentActions.contains(event.target)) {
    // Click was outside currentActions, close currentActions
    currentActions.classList.remove('is-open');
    currentActions = null;
  }
});

function expandActions(event, subscriptionId) {
  event.stopPropagation();
  event.preventDefault();
  const subscriptionDiv = document.querySelector(`.subscription[data-id="${subscriptionId}"]`);
  const actions = subscriptionDiv.querySelector('.actions');

  // Close all other open actions
  const allActions = document.querySelectorAll('.actions.is-open');
  allActions.forEach((openAction) => {
    if (openAction !== actions) {
      openAction.classList.remove('is-open');
    }
  });

  // Toggle the clicked actions
  actions.classList.toggle('is-open');

  // Update currentActions
  if (actions.classList.contains('is-open')) {
    currentActions = actions;
  } else {
    currentActions = null;
  }
}

function swipeHintAnimation() {
  if (window.mobileNavigation && window.matchMedia('(max-width: 768px)').matches) {
    const maxAnimations = 3;
    const cookieName = 'swipeHintCount';

    let count = parseInt(getCookie(cookieName)) || 0;
    if (count < maxAnimations) {
      const firstElement = document.querySelector('.subscription');
      if (firstElement) {
        firstElement.style.transition = 'transform 0.3s ease';
        firstElement.style.transform = 'translateX(-80px)';

        setTimeout(() => {
          firstElement.style.transform = 'translateX(0px)';
          firstElement.style.zIndex = '1';
        }, 600);
      }

      count++;
      document.cookie = `${cookieName}=${count}; expires=Fri, 31 Dec 9999 23:59:59 GMT; path=/; SameSite=Strict`;
    }
  }
}

window.addEventListener('load', () => {
  if (document.querySelector('.subscription')) {
    swipeHintAnimation();
  }
});
