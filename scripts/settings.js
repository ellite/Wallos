const saveIconContent = '<i class="fa-solid fa-check"></i>';
const deleteIconContent = '<i class="fa-solid fa-trash-can"></i>';

function saveMonthlyBudget() {
  const button = document.getElementById("saveMonthlyBudget");
  button.disabled = true;

  const budget = Number(document.getElementById("monthly_budget").value || 0);

  if (Number.isNaN(budget) || budget < 0) {
    showErrorMessage(translate("invalid_budget"));
    button.disabled = false;
    return;
  }

  fetch('endpoints/user/budget.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-Token': window.csrfToken,
    },
    body: JSON.stringify({ monthly_budget: budget }),
  })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        showSuccessMessage(data.message);
      } else {
        showErrorMessage(data.message);
      }
    })
    .catch(error => {
      console.error(error);
      showErrorMessage(translate('unknown_error'));
    })
    .finally(() => {
      button.disabled = false;
    });
}

function savePeriodBudget() {
  const button = document.getElementById("savePeriodBudget");
  button.disabled = true;

  const budget = Number(document.getElementById("period_budget").value || 0);
  const budgetPeriodType = document.getElementById("budget_period_type").value;
  const budgetPeriodAnchorDateInput = document.getElementById("budget_period_anchor_date");
  let budgetPeriodAnchorDate = budgetPeriodAnchorDateInput.value;
  const validPeriodTypes = ["weekly", "fortnightly", "monthly"];

  if (!budgetPeriodAnchorDate || budgetPeriodAnchorDate === "1970-01-01") {
    const today = new Date();
    const month = `${today.getMonth() + 1}`.padStart(2, "0");
    const day = `${today.getDate()}`.padStart(2, "0");
    budgetPeriodAnchorDate = `${today.getFullYear()}-${month}-${day}`;
    budgetPeriodAnchorDateInput.value = budgetPeriodAnchorDate;
  }

  if (Number.isNaN(budget) || budget < 0) {
    showErrorMessage(translate("invalid_budget"));
    button.disabled = false;
    return;
  }

  if (!validPeriodTypes.includes(budgetPeriodType)) {
    showErrorMessage(translate("invalid_budget_period"));
    button.disabled = false;
    return;
  }

  if (!/^\d{4}-\d{2}-\d{2}$/.test(budgetPeriodAnchorDate)) {
    showErrorMessage(translate("invalid_budget_anchor_date"));
    button.disabled = false;
    return;
  }

  fetch('endpoints/user/budget.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-Token': window.csrfToken,
    },
    body: JSON.stringify({
      period_budget: budget,
      budget_period_type: budgetPeriodType,
      budget_period_anchor_date: budgetPeriodAnchorDate,
    }),
  })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        showSuccessMessage(data.message);
      } else {
        showErrorMessage(data.message || translate('unknown_error'));
      }
    })
    .catch(error => {
      console.error(error);
      showErrorMessage(translate('unknown_error'));
    })
    .finally(() => {
      button.disabled = false;
    });
}


function addMemberButton(memberId) {
  const addButton = document.getElementById("addMember");
  addButton.disabled = true;

  fetch("endpoints/household/household.php", {
    method: "POST",
    headers: {
      "Content-Type": "application/x-www-form-urlencoded",
      'X-CSRF-Token': window.csrfToken,
    },
    body: new URLSearchParams({action: "add"}),
  })
    .then(response => {
      if (!response.ok) {
        showErrorMessage(translate("failed_add_member"));
        throw new Error(translate("network_response_error"));
      }
      return response.json();
    })
    .then(responseData => {
      if (responseData.success) {
        const newMemberId = responseData.householdId;
        const container = document.getElementById("householdMembers");

        const div = document.createElement("div");
        div.className = "form-group-inline";
        div.dataset.memberid = newMemberId;

        const input = document.createElement("input");
        input.type = "text";
        input.placeholder = translate("member");
        input.name = "member";
        input.value = translate("member");

        const emailInput = document.createElement("input");
        emailInput.type = "text";
        emailInput.placeholder = translate("email");
        emailInput.name = "email";
        emailInput.value = "";

        const editLink = document.createElement("button");
        editLink.className = "image-button medium";
        editLink.name = "save";
        editLink.onclick = () => editMember(newMemberId);
        editLink.innerHTML = saveIconContent;
        editLink.title = translate("save_member");

        const deleteLink = document.createElement("button");
        deleteLink.className = "image-button medium";
        deleteLink.name = "delete";
        deleteLink.onclick = () => removeMember(newMemberId);
        deleteLink.innerHTML = deleteIconContent;
        deleteLink.title = translate("delete_member");

        div.appendChild(input);
        div.appendChild(emailInput);
        div.appendChild(editLink);
        div.appendChild(deleteLink);

        container.appendChild(div);
      } else {
        showErrorMessage(responseData.message || translate("failed_add_member"));
      }
    })
    .catch(error => {
      console.error(error);
      showErrorMessage(translate("failed_add_member"));
    })
    .finally(() => {
      addButton.disabled = false;
    });
}

function removeMember(memberId) {
  fetch("endpoints/household/household.php", {
    method: "POST",
    headers: {
      "Content-Type": "application/x-www-form-urlencoded",
      'X-CSRF-Token': window.csrfToken,
    },
    body: new URLSearchParams({
      action: "delete",
      memberId: memberId,
    }),
  })
    .then(response => {
      if (!response.ok) {
        throw new Error(translate("network_response_error"));
      }
      return response.json();
    })
    .then(responseData => {
      if (responseData.success) {
        const divToRemove = document.querySelector(`[data-memberid="${memberId}"]`);
        if (divToRemove) divToRemove.remove();
        showSuccessMessage(responseData.message);
      } else {
        showErrorMessage(responseData.message || translate("failed_remove_member"));
      }
    })
    .catch(error => {
      console.error(error);
      showErrorMessage(translate("failed_remove_member"));
    });
}


function editMember(memberId) {
  const saveButton = document.querySelector(`div[data-memberid="${memberId}"] button[name="save"]`);
  const memberNameElement = document.querySelector(`div[data-memberid="${memberId}"] input[name="member"]`);
  const memberEmailElement = document.querySelector(`div[data-memberid="${memberId}"] input[name="email"]`);

  if (!memberNameElement) return;

  saveButton.classList.add("disabled");
  saveButton.disabled = true;

  const memberName = memberNameElement.value;
  const memberEmail = memberEmailElement ? memberEmailElement.value : "";

  fetch("endpoints/household/household.php", {
    method: "POST",
    headers: {
      "Content-Type": "application/x-www-form-urlencoded",
      'X-CSRF-Token': window.csrfToken,
    },
    body: new URLSearchParams({
      action: "edit",
      memberId: memberId,
      name: memberName,
      email: memberEmail,
    }),
  })
    .then(response => {
      if (!response.ok) {
        showErrorMessage(translate("failed_save_member"));
        throw new Error(translate("network_response_error"));
      }
      return response.json();
    })
    .then(responseData => {
      if (responseData.success) {
        showSuccessMessage(responseData.message);
      } else {
        showErrorMessage(responseData.message || translate("failed_save_member"));
      }
    })
    .catch(error => {
      console.error(error);
      showErrorMessage(translate("failed_save_member"));
    })
    .finally(() => {
      saveButton.classList.remove("disabled");
      saveButton.disabled = false;
    });
}


function addCategoryButton(categoryId) {
  const addButton = document.getElementById("addCategory");
  addButton.disabled = true;

  fetch('endpoints/categories/category.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
      'X-CSRF-Token': window.csrfToken,
    },
    body: new URLSearchParams({action: 'add'}),
  })
    .then(response => {
      if (!response.ok) {
        showErrorMessage(translate('failed_add_category'));
        throw new Error(translate('network_response_error'));
      }
      return response.json();
    })
    .then(responseData => {
      if (responseData.success) {
        const newCategoryId = responseData.categoryId;
        const container = document.getElementById("categories");

        const row = document.createElement("div");
        row.className = "form-group-inline";
        row.dataset.categoryid = newCategoryId;

        const dragIcon = document.createElement("div");
        dragIcon.className = "drag-icon";
        dragIcon.innerHTML = '<i class="fa-solid fa-grip-vertical"></i>';

        const input = document.createElement("input");
        input.type = "text";
        input.placeholder = translate('category');
        input.name = "category";
        input.value = translate('category');

        const editLink = document.createElement("button");
        editLink.className = "image-button medium";
        editLink.name = "save";
        editLink.onclick = function () {
          editCategory(newCategoryId);
        };
        editLink.innerHTML = saveIconContent;
        editLink.title = translate('save_member');

        const deleteLink = document.createElement("button");
        deleteLink.className = "image-button medium";
        deleteLink.name = "delete";
        deleteLink.onclick = function () {
          removeCategory(newCategoryId);
        };
        deleteLink.innerHTML = deleteIconContent;
        deleteLink.title = translate('delete_member');

        row.appendChild(dragIcon);
        row.appendChild(input);
        row.appendChild(editLink);
        row.appendChild(deleteLink);
        container.appendChild(row);
      } else {
        showErrorMessage(responseData.message);
      }
    })
    .catch(error => {
      console.error(error);
      showErrorMessage(translate('failed_add_category'));
    })
    .finally(() => {
      addButton.disabled = false;
    });
}


function removeCategory(categoryId) {
  fetch('endpoints/categories/category.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
      'X-CSRF-Token': window.csrfToken,
    },
    body: new URLSearchParams({
      action: 'delete',
      categoryId: categoryId,
    }),
  })
    .then(response => {
      if (!response.ok) {
        throw new Error(translate('network_response_error'));
      }
      return response.json();
    })
    .then(responseData => {
      if (responseData.success) {
        const divToRemove = document.querySelector(`[data-categoryid="${categoryId}"]`);
        if (divToRemove) divToRemove.remove();
        showSuccessMessage(responseData.message);
      } else {
        showErrorMessage(responseData.message || translate('failed_remove_category'));
      }
    })
    .catch(error => {
      console.error(error);
      showErrorMessage(translate('failed_remove_category'));
    });
}


function editCategory(categoryId) {
  const saveButton = document.querySelector(`div[data-categoryid="${categoryId}"] button[name="save"]`);
  const inputElement = document.querySelector(`div[data-categoryid="${categoryId}"] input[name="category"]`);

  if (!inputElement) return;

  saveButton.classList.add("disabled");
  saveButton.disabled = true;

  const categoryName = inputElement.value;

  fetch('endpoints/categories/category.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
      'X-CSRF-Token': window.csrfToken,
    },
    body: new URLSearchParams({
      action: 'edit',
      categoryId: categoryId,
      name: categoryName,
    }),
  })
    .then(response => {
      saveButton.classList.remove("disabled");
      saveButton.disabled = false;

      if (!response.ok) {
        showErrorMessage(translate('failed_save_category'));
        throw new Error(translate('network_response_error'));
      }
      return response.json();
    })
    .then(responseData => {
      if (responseData.success) {
        showSuccessMessage(responseData.message);
      } else {
        showErrorMessage(responseData.message || translate('failed_save_category'));
      }
    })
    .catch(error => {
      console.error(error);
      showErrorMessage(translate('failed_save_category'));
      saveButton.classList.remove("disabled");
      saveButton.disabled = false;
    });
}


function addCurrencyButton(currencyId) {
  const addButton = document.getElementById("addCurrency");
  addButton.disabled = true;

  fetch('endpoints/currency/currency.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
      'X-CSRF-Token': window.csrfToken,
    },
    body: new URLSearchParams({action: 'add'}),
  })
    .then(response => {
      if (!response.ok) {
        throw new Error(translate('network_response_error'));
      }
      return response.json();
    })
    .then(responseData => {
      if (responseData.success) {
        const newCurrencyId = responseData.currencyId;
        const container = document.getElementById("currencies");

        const div = document.createElement("div");
        div.className = "form-group-inline";
        div.dataset.currencyid = newCurrencyId;

        const inputSymbol = document.createElement("input");
        inputSymbol.type = "text";
        inputSymbol.placeholder = "$";
        inputSymbol.name = "symbol";
        inputSymbol.value = "$";
        inputSymbol.classList.add("short");

        const inputName = document.createElement("input");
        inputName.type = "text";
        inputName.placeholder = translate('currency');
        inputName.name = "currency";
        inputName.value = translate('currency');

        const inputCode = document.createElement("input");
        inputCode.type = "text";
        inputCode.placeholder = translate('currency_code');
        inputCode.name = "code";
        inputCode.value = "CODE";

        const editLink = document.createElement("button");
        editLink.className = "image-button medium";
        editLink.name = "save";
        editLink.onclick = function () {
          editCurrency(newCurrencyId);
        };
        editLink.innerHTML = saveIconContent;
        editLink.title = translate('save_member');

        const deleteLink = document.createElement("button");
        deleteLink.className = "image-button medium";
        deleteLink.name = "delete";
        deleteLink.onclick = function () {
          removeCurrency(newCurrencyId);
        };
        deleteLink.innerHTML = deleteIconContent;
        deleteLink.title = translate('delete_member');

        div.appendChild(inputSymbol);
        div.appendChild(inputName);
        div.appendChild(inputCode);
        div.appendChild(editLink);
        div.appendChild(deleteLink);

        container.appendChild(div);
      } else {
        showErrorMessage(responseData.message || translate('failed_add_currency'));
      }
    })
    .catch(error => {
      console.error(error);
      showErrorMessage(translate('failed_add_currency'));
    })
    .finally(() => {
      addButton.disabled = false;
    });
}

function removeCurrency(currencyId) {
  fetch('endpoints/currency/currency.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
      'X-CSRF-Token': window.csrfToken,
    },
    body: new URLSearchParams({
      action: 'delete',
      currencyId: currencyId,
    }),
  })
    .then(response => {
      if (!response.ok) {
        throw new Error(translate('network_response_error'));
      }
      return response.json();
    })
    .then(data => {
      if (data.success) {
        showSuccessMessage(data.message);
        const divToRemove = document.querySelector(`[data-currencyid="${currencyId}"]`);
        if (divToRemove) divToRemove.remove();
      } else {
        showErrorMessage(data.message || translate('failed_remove_currency'));
      }
    })
    .catch(error => {
      console.error(error);
      showErrorMessage(error.message || translate('failed_remove_currency'));
    });
}

function editCurrency(currencyId) {
  const saveButton = document.querySelector(`div[data-currencyid="${currencyId}"] button[name="save"]`);
  const inputSymbolElement = document.querySelector(`div[data-currencyid="${currencyId}"] input[name="symbol"]`);
  const inputNameElement = document.querySelector(`div[data-currencyid="${currencyId}"] input[name="currency"]`);
  const inputCodeElement = document.querySelector(`div[data-currencyid="${currencyId}"] input[name="code"]`);

  if (!inputNameElement) return;

  saveButton.classList.add("disabled");
  saveButton.disabled = true;

  const currencyName = inputNameElement.value;
  const currencySymbol = inputSymbolElement.value;
  const currencyCode = inputCodeElement.value;

  fetch('endpoints/currency/currency.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
      'X-CSRF-Token': window.csrfToken,
    },
    body: new URLSearchParams({
      action: 'edit',
      currencyId: currencyId,
      name: currencyName,
      symbol: currencySymbol,
      code: currencyCode,
    }),
  })
    .then(response => {
      if (!response.ok) {
        throw new Error(translate('network_response_error'));
      }
      return response.json();
    })
    .then(data => {
      saveButton.classList.remove("disabled");
      saveButton.disabled = false;

      if (data.success) {
        showSuccessMessage(data.message);
      } else {
        showErrorMessage(data.message || translate('failed_save_currency'));
      }
    })
    .catch(error => {
      console.error(error);
      showErrorMessage(error.message || translate('failed_save_currency'));
      saveButton.classList.remove("disabled");
      saveButton.disabled = false;
    });
}

function togglePayment(paymentId) {
  const element = document.querySelector(`div[data-paymentid="${paymentId}"]`);

  if (element.dataset.inUse === "yes") {
    return showErrorMessage(translate("cant_disable_payment_in_use"));
  }

  const newEnabledState = element.dataset.enabled === "1" ? "0" : "1";
  const paymentMethodName = element.querySelector(".payment-name").innerText;

  fetch("endpoints/payments/toggle.php", {
    method: "POST",
    headers: {
      "Content-Type": "application/x-www-form-urlencoded",
      "X-CSRF-Token": window.csrfToken,
    },
    body: new URLSearchParams({
      paymentId: paymentId,
      enabled: newEnabledState,
    }),
  })
    .then(response => {
      if (!response.ok) {
        throw new Error(translate("network_response_error"));
      }
      return response.json();
    })
    .then(data => {
      if (data.success) {
        element.dataset.enabled = newEnabledState;
        showSuccessMessage(`${paymentMethodName} ${data.message}`);
      } else {
        showErrorMessage(data.message || translate("failed_save_payment_method"));
      }
    })
    .catch(error => {
      console.error(error);
      showErrorMessage(error.message || translate("failed_save_payment_method"));
    });
}

document.body.addEventListener('click', function (e) {
  let targetElement = e.target;
  do {
    if (targetElement.classList && targetElement.classList.contains('payments-payment')) {
      let targetChild = e.target;
      do {
        if (targetChild.classList && (targetChild.classList.contains('payment-name') || targetChild.classList.contains('drag-icon'))) {
          return;
        }
        targetChild = targetChild.parentNode;
      } while (targetChild && targetChild !== targetElement);

      const paymentId = targetElement.dataset.paymentid;
      togglePayment(paymentId);
      return;
    }
    targetElement = targetElement.parentNode;
  } while (targetElement);
});

document.body.addEventListener('blur', function (e) {
  let targetElement = e.target;
  if (targetElement.classList && targetElement.classList.contains('payment-name')) {
    const paymentId = targetElement.closest('.payments-payment').dataset.paymentid;
    const newName = targetElement.textContent;
    renamePayment(paymentId, newName);
  }
}, true);

function renamePayment(paymentId, newName) {
  const name = newName.trim();
  if (!name) return;

  const formData = new FormData();
  formData.append("paymentId", paymentId);
  formData.append("name", name);

  fetch("endpoints/payments/rename.php", {
    method: "POST",
    headers: {
      "X-CSRF-Token": window.csrfToken,
    },
    body: formData,
  })
    .then(response => {
      if (!response.ok) {
        throw new Error(translate("network_response_error"));
      }
      return response.json();
    })
    .then(data => {
      if (data.success) {
        showSuccessMessage(`${newName} ${data.message}`);
      } else {
        showErrorMessage(data.message || translate("failed_save_payment_method"));
      }
    })
    .catch(error => {
      console.error(error);
      showErrorMessage(translate("unknown_error"));
    });
}


document.body.addEventListener('keypress', function (e) {
  let targetElement = e.target;
  if (targetElement.classList && targetElement.classList.contains('payment-name')) {
    if (e.key === 'Enter') {
      e.preventDefault();
      targetElement.blur();
    }
  }
});

function handleFileSelect(event) {
  const fileInput = event.target;
  const iconPreview = document.querySelector('.icon-preview');
  const iconImg = iconPreview.querySelector('img');
  const iconUrl = document.querySelector("#icon-url");
  iconUrl.value = "";

  if (fileInput.files && fileInput.files[0]) {
    const reader = new FileReader();

    reader.onload = function (e) {
      iconImg.src = e.target.result;
      iconImg.style.display = 'block';
    };

    reader.readAsDataURL(fileInput.files[0]);
  }
}

function setSearchButtonStatus() {

  const nameInput = document.querySelector("#paymentname");
  const hasSearchTerm = nameInput.value.trim().length > 0;
  const iconSearchButton = document.querySelector("#icon-search-button");
  if (hasSearchTerm) {
    iconSearchButton.classList.remove("disabled");
  } else {
    iconSearchButton.classList.add("disabled");
  }

}

function searchPaymentIcon() {
  const nameInput = document.querySelector("#paymentname");
  const searchTerm = nameInput.value.trim();
  if (searchTerm === "") {
    nameInput.focus();
    return;
  }

  const iconSearchPopup = document.querySelector("#icon-search-results");
  const iconResults = document.querySelector("#icon-search-images");
  const iconSearchBackdrop = document.querySelector("#icon-search-backdrop");
  iconSearchPopup.classList.add("is-open");
  if (iconSearchBackdrop) {
    iconSearchBackdrop.classList.add("is-open");
  }
  const iconSearchTitle = document.querySelector("#icon-search-title");
  if (iconSearchTitle) {
    const baseTitle = iconSearchTitle.dataset.title;
    iconSearchTitle.textContent = `${baseTitle}: ${searchTerm}`;
  }
  showSearchState(iconResults, 'loading');

  const imageSearchUrl = `endpoints/payments/search.php?search=${searchTerm}`;
  fetch(imageSearchUrl)
    .then(response => response.json())
    .then(data => {
      if (data.imageUrls && data.imageUrls.length > 0) {
        displayImageResults(data.imageUrls);
      } else if (data.error) {
        console.error(data.error);
        showSearchState(iconResults, 'error');
      } else {
        showSearchState(iconResults, 'empty');
      }
    })
    .catch(error => {
      console.error(translate('error_fetching_image_results'), error);
      showSearchState(iconResults, 'error');
    });
}

function displayImageResults(imageSources) {
  const iconResults = document.querySelector("#icon-search-images");
  iconResults.innerHTML = "";

  imageSources.forEach(src => {
    const img = document.createElement("img");
    img.src = src;
    img.onclick = function () {
      selectWebIcon(src);
    };
    img.onerror = function () {
      this.parentNode.removeChild(this);
    };
    iconResults.appendChild(img);
  });
}

function selectWebIcon(url) {
  closeIconSearch();
  const iconPreview = document.querySelector("#form-icon");
  const iconUrl = document.querySelector("#icon-url");
  iconPreview.src = url;
  iconPreview.style.display = 'block';
  iconUrl.value = url;
}

function closeIconSearch() {
  const iconSearchPopup = document.querySelector("#icon-search-results");
  iconSearchPopup.classList.remove("is-open");
  const iconSearchBackdrop = document.querySelector("#icon-search-backdrop");
  if (iconSearchBackdrop) {
    iconSearchBackdrop.classList.remove("is-open");
  }
  const iconSearchTitle = document.querySelector("#icon-search-title");
  if (iconSearchTitle) {
    iconSearchTitle.textContent = iconSearchTitle.dataset.title;
  }
  const iconResults = document.querySelector("#icon-search-images");
  iconResults.innerHTML = "";
}

function resetFormIcon() {
  const iconPreview = document.querySelector("#form-icon");
  iconPreview.src = "";
  iconPreview.style.display = 'none';
}

function reloadPaymentMethods() {
  const paymentsContainer = document.querySelector("#payments-list");
  const paymentMethodsEndpoint = "endpoints/payments/get.php";

  fetch(paymentMethodsEndpoint)
    .then(response => response.text())
    .then(data => {
      paymentsContainer.innerHTML = data;
    });
}

function addPaymentMethod() {
  closeIconSearch();
  const addPaymentMethodEndpoint = "endpoints/payments/add.php";
  const paymentMethodForm = document.querySelector("#payments-form");
  const submitButton = document.querySelector("#add-payment-button");

  submitButton.disabled = true;
  const formData = new FormData(paymentMethodForm);
  formData.append("action", "add");

  fetch(addPaymentMethodEndpoint, {
    method: "POST",
    headers: {
      "X-CSRF-Token": window.csrfToken,
    },
    body: formData,
  })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        showSuccessMessage(data.message);
        paymentMethodForm.reset();
        resetFormIcon();
        reloadPaymentMethods();
      } else {
        showErrorMessage(data.message || translate("failed_add_payment_method"));
      }
    })
    .catch(error => {
      console.error(error);
      showErrorMessage(translate("unknown_error"));
    })
    .finally(() => {
      submitButton.disabled = false;
    });
}


function deletePaymentMethod(paymentId) {
  fetch(`endpoints/payments/delete.php?id=${paymentId}`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
       "X-CSRF-Token": window.csrfToken,
    },
    body: JSON.stringify({ id: paymentId }),
  })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        showSuccessMessage(data.message);
        var paymentToRemove = document.querySelector('.payments-payment[data-paymentid="' + paymentId + '"]');
        if (paymentToRemove) {
          paymentToRemove.remove();
        }
      } else {
        showErrorMessage(data.message);
      }
    })
    .catch((error) => {
      console.error('Error:', error);
    });
}

function savePaymentMethodsSorting() {
  const paymentMethods = document.getElementById("payments-list");
  const paymentMethodIds = Array.from(paymentMethods.children).map(
    paymentMethod => paymentMethod.dataset.paymentid
  );

  const formData = new FormData();
  paymentMethodIds.forEach(id => formData.append("paymentMethodIds[]", id));
  formData.append("action", "sort");

  fetch("endpoints/payments/sort.php", {
    method: "POST",
    headers: {
      "X-CSRF-Token": window.csrfToken,
    },
    body: formData,
  })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        showSuccessMessage(data.message);
      } else {
        showErrorMessage(data.message || translate("failed_sort_payment_methods"));
      }
    })
    .catch(error => {
      console.error(error);
      showErrorMessage(translate("unknown_error"));
    });
}


var el = document.getElementById('payments-list');
var sortable = Sortable.create(el, {
  handle: '.drag-icon',
  ghostClass: 'sortable-ghost',
  delay: 500,
  delayOnTouchOnly: true,
  touchStartThreshold: 5,
  onEnd: function (evt) {
    savePaymentMethodsSorting();
  },
});


document.addEventListener('DOMContentLoaded', function () {

  var removePaymentButtons = document.querySelectorAll(".delete-payment-method");
  removePaymentButtons.forEach(function (button) {
    button.addEventListener('click', function (event) {
      event.preventDefault();
      event.stopPropagation();
      let paymentId = event.target.getAttribute('data-paymentid');
      deletePaymentMethod(paymentId);
    });
  });

  loadGoogleSearchUsage();
  loadFixerUsage();

  if (document.getElementById("ai_type")) {
    toggleAiInputs();
  }

});

function addFixerKeyButton() {
  const addButton = document.getElementById("addFixerKey");
  addButton.disabled = true;

  const apiKeyInput = document.querySelector("#fixerKey");
  const apiKey = apiKeyInput.value.trim();
  const provider = document.querySelector("#fixerProvider").value;
  const convertCurrencyCheckbox = document.querySelector("#convertcurrency");

  fetch("endpoints/currency/fixer_api_key.php", {
    method: "POST",
    headers: {
      "Content-Type": "application/x-www-form-urlencoded",
      'X-CSRF-Token': window.csrfToken,
    },
    body: new URLSearchParams({
      api_key: apiKey,
      provider: provider,
    }),
  })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        showSuccessMessage(data.message);
        addButton.disabled = false;
        convertCurrencyCheckbox.disabled = false;

        fetch("endpoints/currency/update_exchange.php", {
          method: "POST",
          headers: {
            "Content-Type": "application/x-www-form-urlencoded",
            'X-CSRF-Token': window.csrfToken,
          },
          body: new URLSearchParams({force: "true"}),
        }).catch(console.error).finally(() => loadFixerUsage());
      } else {
        showErrorMessage(data.message);
        addButton.disabled = false;
      }
    })
    .catch(error => {
      console.error(error);
      showErrorMessage(translate("unknown_error"));
      addButton.disabled = false;
    });
}


function storeSettingsOnDB(endpoint, value) {
  fetch('endpoints/settings/' + endpoint + '.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-Token': window.csrfToken,
    },
    body: JSON.stringify({ "value": value })
  })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        showSuccessMessage(data.message);
      } else {
        showErrorMessage(data.message);
      }
    });
}

function setShowMonthlyPrice() {
  const showMonthlyPriceCheckbox = document.querySelector("#monthlyprice");
  const value = showMonthlyPriceCheckbox.checked;

  storeSettingsOnDB('monthly_price', value);
}

function setConvertCurrency() {
  const convertCurrencyCheckbox = document.querySelector("#convertcurrency");
  const value = convertCurrencyCheckbox.checked;

  storeSettingsOnDB('convert_currency', value);
}

function setRemoveBackground() {
  const removeBackgroundCheckbox = document.querySelector("#removebackground");
  const value = removeBackgroundCheckbox.checked;

  storeSettingsOnDB('remove_background', value);
}

function setHideDisabled() {
  const hideDisabledCheckbox = document.querySelector("#hidedisabled");
  const value = hideDisabledCheckbox.checked;

  storeSettingsOnDB('hide_disabled', value);
}

function setDisabledToBottom() {
  const disabledToBottomCheckbox = document.querySelector("#disabledtobottom");
  const value = disabledToBottomCheckbox.checked;

  storeSettingsOnDB('disabled_to_bottom', value);
}

function setShowOriginalPrice() {
  const showOriginalPriceCheckbox = document.querySelector("#showoriginalprice");
  const value = showOriginalPriceCheckbox.checked;

  storeSettingsOnDB('show_original_price', value);
}

function setMobileNavigation() {
  const mobileNavigationCheckbox = document.querySelector("#mobilenavigation");
  const value = mobileNavigationCheckbox.checked;

  storeSettingsOnDB('mobile_navigation', value);
}

function setShowSubscriptionProgress() {
  const showSubscriptionProgressCheckbox = document.querySelector("#showsubscriptionprogress");
  const value = showSubscriptionProgressCheckbox.checked;

  storeSettingsOnDB('subscription_progress', value);
}

function loadApiUsage(endpoint, containerId, countId, fillId) {
  const usageContainer = document.getElementById(containerId);
  if (!usageContainer) {
    return;
  }

  fetch(endpoint, {
    headers: {
      'X-CSRF-Token': window.csrfToken,
    }
  })
    .then(response => response.json())
    .then(data => {
      if (!data.success || !data.total) {
        usageContainer.style.display = "none";
        return;
      }

      const percent = Math.min(100, Math.round((data.used / data.total) * 100));
      document.getElementById(countId).textContent = `${data.used} / ${data.total}`;

      const fill = document.getElementById(fillId);
      fill.style.width = percent + "%";
      fill.classList.toggle("warn", percent >= 80 && percent < 95);
      fill.classList.toggle("danger", percent >= 95);

      usageContainer.style.display = "";
    })
    .catch(() => {
      usageContainer.style.display = "none";
    });
}

function loadFixerUsage() {
  loadApiUsage("endpoints/settings/fixer_usage.php", "fixerUsage", "fixerUsageCount", "fixerUsageFill");
}

function loadGoogleSearchUsage() {
  loadApiUsage("endpoints/settings/google_search_usage.php", "googleSearchUsage", "googleSearchUsageCount", "googleSearchUsageFill");
}

function saveGoogleSearchButton() {
  const saveButton = document.getElementById("saveGoogleSearch");
  saveButton.disabled = true;

  const apiKey = document.querySelector("#googleSearchKey").value.trim();

  fetch("endpoints/settings/google_search.php", {
    method: "POST",
    headers: {
      "Content-Type": "application/x-www-form-urlencoded",
      'X-CSRF-Token': window.csrfToken,
    },
    body: new URLSearchParams({
      api_key: apiKey,
    }),
  })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        showSuccessMessage(data.message);
        loadGoogleSearchUsage();
      } else {
        showErrorMessage(data.message);
      }
    })
    .catch(() => {
      showErrorMessage(translate('unknown_error'));
    })
    .finally(() => {
      saveButton.disabled = false;
    });
}

function setWeekStartsSunday() {
  const weekStartsSundayCheckbox = document.querySelector("#weekstartssunday");
  const value = weekStartsSundayCheckbox.checked;

  storeSettingsOnDB('week_starts_sunday', value);
}

function saveCategorySorting() {
  const categories = document.getElementById("categories");
  const categoryIds = Array.from(categories.children).map(c => c.dataset.categoryid);

  const formData = new FormData();
  categoryIds.forEach(categoryId => formData.append("categoryIds[]", categoryId));
  formData.append("action", "sort");

  fetch("endpoints/categories/category.php", {
    method: "POST",
    headers: {"X-CSRF-Token": window.csrfToken},
    body: formData,
  })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        showSuccessMessage(data.message);
      } else {
        showErrorMessage(data.message);
      }
    })
    .catch(error => {
      console.error(error);
      showErrorMessage(translate("unknown_error"));
    });
}


var el = document.getElementById('categories');
var sortable = Sortable.create(el, {
  handle: '.drag-icon',
  ghostClass: 'sortable-ghost',
  delay: 500,
  delayOnTouchOnly: true,
  touchStartThreshold: 5,
  onEnd: function (evt) {
    saveCategorySorting();
  },
});

function fetch_ai_models() {
  const endpoint = 'endpoints/ai/fetch_models.php';
  const type = document.querySelector("#ai_type").value;
  const api_key = document.querySelector("#ai_api_key").value.trim();
  const ollama_host = document.querySelector("#ai_ollama_host").value.trim();
  const modelSelect = document.querySelector("#ai_model");

  fetch(endpoint, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-Token': window.csrfToken,
    },
    body: JSON.stringify({ type, api_key, ollama_host })
  })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        modelSelect.innerHTML = '';
        data.models.forEach(model => {
          const option = document.createElement('option');
          option.value = model.id;
          option.textContent = model.name;
          modelSelect.appendChild(option);
        });
      } else {
        showErrorMessage(data.message);
      }
    })
    .catch(error => {
      showErrorMessage(translate('unknown_error'));
    });
}

function toggleAiInputs() {
  const type = document.getElementById("ai_type").value;
  const apiKeyInput = document.getElementById("ai_api_key");
  const apiKeyToggleIcon = apiKeyInput.closest(".password-field")?.querySelector(".password-toggle i");
  const urlGroup = document.getElementById("ai_url_group");
  const urlInput = document.getElementById("ai_ollama_host");
  const testButtonUrl = document.getElementById("fetchModelsButton2");
  const testButtonKey = document.getElementById("fetchModelsButton");

  // Reset key visibility
  apiKeyInput.type = "password";
  apiKeyToggleIcon?.classList.replace("fa-eye-slash", "fa-eye");

  if (type === "ollama") {
    apiKeyInput.classList.add("hidden");
    testButtonKey.classList.add("hidden");      // hide key-row Test
    urlGroup.style.display = "";
    urlInput.placeholder = "http://localhost:11434";
    testButtonUrl.classList.remove("hidden");   // show url-row Test
  } else if (type === "openai-compatible") {
    apiKeyInput.classList.remove("hidden");
    testButtonKey.classList.remove("hidden");   // show key-row Test
    urlGroup.style.display = "";
    urlInput.placeholder = "http://localhost:11434/v1";
    testButtonUrl.classList.add("hidden");      // hide url-row Test
  } else {
    apiKeyInput.classList.remove("hidden");
    testButtonKey.classList.remove("hidden");   // show key-row Test
    urlGroup.style.display = "none";
    testButtonUrl.classList.add("hidden");      // hidden anyway (group hidden)
  }
}

function saveAiSettingsButton() {
  const aiEnabled = document.querySelector("#ai_enabled").checked;
  const aiType = document.querySelector("#ai_type").value;
  const aiApiKey = document.querySelector("#ai_api_key").value.trim();
  const aiOllamaHost = document.querySelector("#ai_ollama_host").value.trim();
  const aiModel = document.querySelector("#ai_model").value;
  const aiRunSchedule = document.querySelector("#ai_run_schedule").value;

  fetch('endpoints/ai/save_settings.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-Token': window.csrfToken,
    },
    body: JSON.stringify({ ai_enabled: aiEnabled, ai_type: aiType, api_key: aiApiKey, ollama_host: aiOllamaHost, model: aiModel, ai_run_schedule: aiRunSchedule })
  })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        showSuccessMessage(data.message);
        const runAiActionButton = document.querySelector("#runAiRecommendations");
        if (data.enabled) {
          runAiActionButton.classList.remove("hidden");
        } else {
          runAiActionButton.classList.add("hidden");
        }
      } else {
        showErrorMessage(data.message);
      }
    })
    .catch(error => {
      showErrorMessage(translate('unknown_error'));
    });
}

function translateCategories() {
  const button = document.getElementById("translateCategories");
  const originalContent = button.innerHTML;

  button.disabled = true;
  button.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';

  fetch('endpoints/ai/translate_categories.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-Token': window.csrfToken,
    }
  })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        Object.entries(data.translations).forEach(([categoryId, categoryName]) => {
          const input = document.querySelector(`#categories div[data-categoryid="${categoryId}"] input[name="category"]`);
          if (input) {
            input.value = categoryName;
          }
        });
        showSuccessMessage(data.message);
      } else {
        showErrorMessage(data.message);
      }
    })
    .catch(error => {
      showErrorMessage(translate('unknown_error'));
    })
    .finally(() => {
      button.disabled = false;
      button.innerHTML = originalContent;
    });
}

function runAiRecommendations() {
  const endpoint = 'endpoints/ai/generate_recommendations.php';
  const button = document.querySelector("#runAiRecommendations");
  const spinner = document.querySelector("#aiSpinner");

  button.classList.add("hidden");
  spinner.classList.remove("hidden");

  fetch(endpoint, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-Token': window.csrfToken,
    }
  })
    .then(async response => {
      const responseText = await response.text();
      let data;

      try {
        data = JSON.parse(responseText);
      } catch (error) {
        throw new Error(`${translate('network_response_error')} (HTTP ${response.status})`);
      }

      if (!response.ok && !data.message) {
        throw new Error(`${translate('network_response_error')} (HTTP ${response.status})`);
      }

      return data;
    })
    .then(data => {
      if (data.success) {
        showSuccessMessage(data.message);
      } else {
        showErrorMessage(data.message);
      }
    })
    .catch(error => {
      showErrorMessage(error.message || translate('unknown_error'));
    })
    .finally(() => {
      button.classList.remove("hidden");
      spinner.classList.add("hidden");
    });

}
