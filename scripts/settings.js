const deleteSvgContent = `
<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 48 48" id="Recycle-Bin-2--Streamline-Plump.svg" height="48" width="48">
  <g id="recycle-bin-2--remove-delete-empty-bin-trash-garbage">
    <path id="Union" class="accent-color" d="M43.318 15.934a1.5 1.5 0 0 0 -1.618 -1.591c-3.016 0.246 -8.46 0.52 -17.721 0.52 -9.215 0 -14.65 -0.271 -17.675 -0.516a1.5 1.5 0 0 0 -1.618 1.59c0.888 13.84 1.74 21.07 2.253 24.547 0.332 2.252 1.85 4.217 4.226 4.788 2.445 0.588 6.55 1.227 12.837 1.227 6.286 0 10.392 -0.64 12.837 -1.227 2.375 -0.57 3.894 -2.536 4.226 -4.788 0.513 -3.477 1.365 -10.708 2.253 -24.55Z" stroke-width="1"/>
    <path id="Union_2" class="main-color" d="M23.37 1a8 8 0 0 0 -7.034 4.188c-3.411 0.072 -6 0.182 -7.814 0.282 -2.312 0.127 -4.692 1.242 -5.7 3.605 -0.244 0.57 -0.475 1.212 -0.663 1.919 -0.68 2.548 1.302 4.622 3.657 4.822 3.057 0.258 8.614 0.548 18.161 0.548 9.549 0 15.106 -0.29 18.162 -0.549 2.374 -0.2 4.291 -2.261 3.751 -4.785a16.68 16.68 0 0 0 -0.294 -1.167c-0.824 -2.831 -3.517 -4.277 -6.188 -4.411a260.66 260.66 0 0 0 -7.744 -0.264A8 8 0 0 0 24.631 1H23.37Z" stroke-width="1"/>
    <path id="Vector_831_Stroke" class="main-color" fill-rule="evenodd" d="M17.8 23.01a2 2 0 0 1 2.19 1.791l1 10a2 2 0 0 1 -3.98 0.398l-1 -10a2 2 0 0 1 1.79 -2.189Z" clip-rule="evenodd" stroke-width="1"/>
    <path id="Vector_832_Stroke" class="main-color" fill-rule="evenodd" d="M30.2 23.01a2 2 0 0 0 -2.19 1.791l-1 10a2 2 0 0 0 3.98 0.398l1 -10a2 2 0 0 0 -1.79 -2.189Z" clip-rule="evenodd" stroke-width="1"/>
  </g>
</svg>
`;

const editSvgContent = `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 48 48" id="File-Check-Alternate--Streamline-Plump.svg" height="48" width="48">
  <g id="file-check-alternate--file-common-check">
    <path id="Subtract" class="accent-color" d="M13.582 2.137C16.326 1.823 20.685 1.5 27 1.5a165 165 0 0 1 5.13 0.077 1.5 1.5 0 0 1 0.4 0.068c1.098 0.343 4.029 1.564 8.123 5.578 3.862 3.787 5.195 6.563 5.63 7.781a1.5 1.5 0 0 1 0.087 0.45c0.08 2.153 0.13 4.655 0.13 7.546 0 7.57 -0.343 12.478 -0.669 15.432 -0.32 2.9 -2.518 5.1 -5.413 5.431 -2.744 0.314 -7.103 0.637 -13.418 0.637 -1.044 0 -2.035 -0.009 -2.974 -0.025A14.458 14.458 0 0 0 28.5 34c0 -8.008 -6.492 -14.5 -14.5 -14.5a14.44 14.44 0 0 0 -6.492 1.531c0.053 -6.464 0.364 -10.773 0.66 -13.463 0.32 -2.9 2.519 -5.1 5.414 -5.431Z" stroke-width="1"></path>
    <path id="Intersect" class="main-color" d="M46.348 15.25c-2.42 -0.001 -6.57 -0.04 -8.948 -0.268 -2.598 -0.249 -4.641 -2.321 -4.896 -4.975 -0.214 -2.233 -0.253 -5.99 -0.254 -8.421 0.095 0.01 0.188 0.03 0.28 0.059 1.098 0.343 4.029 1.564 8.123 5.578 3.862 3.787 5.195 6.563 5.63 7.781 0.029 0.08 0.05 0.163 0.065 0.246Z" stroke-width="1"></path>
    <path id="Subtract_2" class="main-color" fill-rule="evenodd" d="M14 46c6.627 0 12 -5.373 12 -12s-5.373 -12 -12 -12S2 27.373 2 34s5.373 12 12 12Z" clip-rule="evenodd" stroke-width="1"></path>
    <path id="Subtract_3" class="accent-color" fill-rule="evenodd" d="M20.611 31.185a2 2 0 1 0 -3.222 -2.37l-4.413 6.002L10.5 32.01a2 2 0 0 0 -3 2.647l4.118 4.666a2 2 0 0 0 3.111 -0.138l5.882 -8Z" clip-rule="evenodd" stroke-width="1"></path>
  </g>
</svg>`;

function saveBudget() {
  const button = document.getElementById("saveBudget");
  button.disabled = true;

  const budget = document.getElementById("budget").value;

  fetch('endpoints/user/budget.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({ budget: budget })
  })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        showSuccessMessage(data.message);
      } else {
        showErrorMessage(data.message);
      }
      button.disabled = false;
    })
    .catch(error => {
      showErrorMessage(translate('unknown_error'));
      button.disabled = false;
    });

}

function addMemberButton(memberId) {
  document.getElementById("addMember").disabled = true;
  const url = 'endpoints/household/household.php?action=add';
  fetch(url)
    .then(response => {
      if (!response.ok) {
        throw new Error(translate('network_response_error'));
        showErrorMessage(translate('failed_add_member'));
      }
      return response.json();
    })
    .then(responseData => {
      if (responseData.success) {
        const newMemberId = responseData.householdId;;
        let container = document.getElementById("householdMembers");
        let div = document.createElement("div");
        div.className = "form-group-inline";
        div.dataset.memberid = newMemberId;

        let input = document.createElement("input");
        input.type = "text";
        input.placeholder = translate('member');
        input.name = "member";
        input.value = translate('member');

        let emailInput = document.createElement("input");
        emailInput.type = "text";
        emailInput.placeholder = translate('email');
        emailInput.name = "email";
        emailInput.value = "";

        let editLink = document.createElement("button");
        editLink.className = "image-button medium"
        editLink.name = "save";
        editLink.onclick = function () {
          editMember(newMemberId);
        };

        editLink.innerHTML = editSvgContent;
        editLink.title = translate('save_member');

        let deleteLink = document.createElement("button");
        deleteLink.className = "image-button medium"
        deleteLink.name = "delete";
        deleteLink.onclick = function () {
          removeMember(newMemberId);
        };

        deleteLink.innerHTML = deleteSvgContent;
        deleteLink.title = translate('delete_member');

        div.appendChild(input);
        div.appendChild(emailInput);
        div.appendChild(editLink);
        div.appendChild(deleteLink);

        container.appendChild(div);
      } else {
        showErrorMessage(responseData.errorMessage);
      }
      document.getElementById("addMember").disabled = false;
    })
    .catch(error => {
      showErrorMessage(translate('failed_add_member'));
      document.getElementById("addMember").disabled = false;
    });

}

function removeMember(memberId) {
  let url = `endpoints/household/household.php?action=delete&memberId=${memberId}`;
  fetch(url)
    .then(response => {
      if (!response.ok) {
        throw new Error(translate('network_response_error'));
      }
      return response.json();
    })
    .then(responseData => {
      if (responseData.success) {
        let divToRemove = document.querySelector(`[data-memberid="${memberId}"]`);
        if (divToRemove) {
          divToRemove.parentNode.removeChild(divToRemove);
        }
        showSuccessMessage(responseData.message);
      } else {
        showErrorMessage(responseData.errorMessage || translate('failed_remove_member'));
      }
    })
    .catch(error => {
      showErrorMessage(translate('failed_remove_member'));
    });
}

function editMember(memberId) {
  var saveButton = document.querySelector(`div[data-memberid="${memberId}"] button[name="save"]`);
  var memberNameElement = document.querySelector(`div[data-memberid="${memberId}"] input[name="member"]`);
  var memberEmailElement = document.querySelector(`div[data-memberid="${memberId}"] input[name="email"]`);
  saveButton.classList.add("disabled");
  saveButton.disabled = true;
  if (memberNameElement) {
    var memberName = encodeURIComponent(memberNameElement.value);
    var memberEmail = memberEmailElement ? encodeURIComponent(memberEmailElement.value) : '';
    var url = `endpoints/household/household.php?action=edit&memberId=${memberId}&name=${memberName}&email=${memberEmail}`;

    fetch(url)
      .then(response => {
        saveButton.classList.remove("disabled");
        if (!response.ok) {
          showErrorMessage(translate('failed_save_member'));
        }
        return response.json();
      })
      .then(responseData => {
        if (responseData.success) {
          showSuccessMessage(responseData.message);
        } else {
          showErrorMessage(responseData.errorMessage || translate('failed_save_member'));
        }
      })
      .catch(error => {
        showErrorMessage(translate('failed_save_member'));
      });
  }
}

function addCategoryButton(categoryId) {
  document.getElementById("addCategory").disabled = true;
  const url = 'endpoints/categories/category.php?action=add';
  fetch(url)
    .then(response => {
      if (!response.ok) {
        showErrorMessage(translate('failed_add_category'));
        throw new Error(translate('network_response_error'));
      }
      return response.json();
    })
    .then(responseData => {
      if (responseData.success) {
        const newCategoryId = responseData.categoryId;;
        let container = document.getElementById("categories");
        let row = document.createElement("div");
        row.className = "form-group-inline";
        row.dataset.categoryid = newCategoryId;

        let dragIcon = document.createElement("div");
        dragIcon.className = "drag-icon";

        let input = document.createElement("input");
        input.type = "text";
        input.placeholder = translate('category');
        input.name = "category";
        input.value = translate('category');

        let editLink = document.createElement("button");
        editLink.className = "image-button medium"
        editLink.name = "save";
        editLink.onclick = function () {
          editCategory(newCategoryId);
        };

        editLink.innerHTML = editSvgContent;
        editLink.title = translate('save_member');

        let deleteLink = document.createElement("button");
        deleteLink.className = "image-button medium"
        deleteLink.name = "delete";
        deleteLink.onclick = function () {
          removeCategory(newCategoryId);
        };

        deleteLink.innerHTML = deleteSvgContent;
        deleteLink.title = translate('delete_member');

        row.appendChild(dragIcon);
        row.appendChild(input);
        row.appendChild(editLink);
        row.appendChild(deleteLink);

        container.appendChild(row);
      } else {
        showErrorMessage(responseData.errorMessage);
      }
      document.getElementById("addCategory").disabled = false;
    })
    .catch(error => {
      showErrorMessage(translate('failed_add_category'));
      document.getElementById("addCategory").disabled = false;
    });

}

function removeCategory(categoryId) {
  let url = `endpoints/categories/category.php?action=delete&categoryId=${categoryId}`;
  fetch(url)
    .then(response => {
      if (!response.ok) {
        throw new Error(translate('network_response_error'));
      }
      return response.json();
    })
    .then(responseData => {
      if (responseData.success) {
        let divToRemove = document.querySelector(`[data-categoryid="${categoryId}"]`);
        if (divToRemove) {
          divToRemove.parentNode.removeChild(divToRemove);
        }
        showSuccessMessage(responseData.message);
      } else {
        showErrorMessage(responseData.errorMessage || translate('failed_remove_category'));
      }
    })
    .catch(error => {
      showErrorMessage(translate('failed_remove_category'));
    });
}

function editCategory(categoryId) {
  var saveButton = document.querySelector(`div[data-categoryid="${categoryId}"] button[name="save"]`);
  var inputElement = document.querySelector(`div[data-categoryid="${categoryId}"] input[name="category"]`);

  saveButton.classList.add("disabled");
  saveButton.disabled = true;
  if (inputElement) {
    var categoryName = encodeURIComponent(inputElement.value);
    var url = `endpoints/categories/category.php?action=edit&categoryId=${categoryId}&name=${categoryName}`;

    fetch(url)
      .then(response => {
        saveButton.classList.remove("disabled");
        if (!response.ok) {
          showErrorMessage(translate('failed_save_category'));
        }
        return response.json();
      })
      .then(responseData => {
        if (responseData.success) {
          showSuccessMessage(responseData.message);
        } else {
          showErrorMessage(responseData.errorMessage || translate('failed_save_category'));
        }
      })
      .catch(error => {
        showErrorMessage(translate('failed_save_category'));
      });
  }
}

function addCurrencyButton(currencyId) {
  document.getElementById("addCurrency").disabled = true;
  const url = 'endpoints/currency/add.php';
  fetch(url)
    .then(response => {
      if (!response.ok) {
        throw new Error(translate('network_response_error'));
        showErrorMessage(response.text());
      }
      return response.text();
    })
    .then(responseText => {
      if (responseText !== "Error") {
        const newCurrencyId = responseText;
        let container = document.getElementById("currencies");
        let div = document.createElement("div");
        div.className = "form-group-inline";
        div.dataset.currencyid = newCurrencyId;

        let inputSymbol = document.createElement("input");
        inputSymbol.type = "text";
        inputSymbol.placeholder = "$";
        inputSymbol.name = "symbol";
        inputSymbol.value = "$";
        inputSymbol.classList.add("short");

        let inputName = document.createElement("input");
        inputName.type = "text";
        inputName.placeholder = translate('currency');
        inputName.name = "currency";
        inputName.value = translate('currency');

        let inputCode = document.createElement("input");
        inputCode.type = "text";
        inputCode.placeholder = translate('currency_code');
        inputCode.name = "code";
        inputCode.value = "CODE";

        let editLink = document.createElement("button");
        editLink.className = "image-button medium"
        editLink.name = "save";
        editLink.onclick = function () {
          editCurrency(newCurrencyId);
        };

        editLink.innerHTML = editSvgContent;
        editLink.title = translate('save_member');

        let deleteLink = document.createElement("button");
        deleteLink.className = "image-button medium"
        deleteLink.name = "delete";
        deleteLink.onclick = function () {
          removeCurrency(newCurrencyId);
        };

        deleteLink.innerHTML = deleteSvgContent;
        deleteLink.title = translate('delete_member');

        div.appendChild(inputSymbol);
        div.appendChild(inputName);
        div.appendChild(inputCode);
        div.appendChild(editLink);
        div.appendChild(deleteLink);

        container.appendChild(div);
      } else {
        // TODO: Show error
      }
      document.getElementById("addCurrency").disabled = false;
    })
    .catch(error => {
      // TODO: Show error
      document.getElementById("addCurrency").disabled = false;
    });

}

function removeCurrency(currencyId) {
  let url = `endpoints/currency/remove.php?currencyId=${currencyId}`;
  fetch(url)
    .then(response => {
      if (!response.ok) {
        throw new Error(translate('network_response_error'));
      }
      return response.json();
    })
    .then(data => {
      if (data.success) {
        showSuccessMessage(data.message);
        let divToRemove = document.querySelector(`[data-currencyid="${currencyId}"]`);
        if (divToRemove) {
          divToRemove.parentNode.removeChild(divToRemove);
        }
      } else {
        showErrorMessage(data.message || translate('failed_remove_currency'));
      }
    })
    .catch(error => {
      showErrorMessage(error.message || translate('failed_remove_currency'));
    });
}

function editCurrency(currencyId) {
  var saveButton = document.querySelector(`div[data-currencyid="${currencyId}"] button[name="save"]`);
  var inputSymbolElement = document.querySelector(`div[data-currencyid="${currencyId}"] input[name="symbol"]`);
  var inputNameElement = document.querySelector(`div[data-currencyid="${currencyId}"] input[name="currency"]`);
  var inputCodeElement = document.querySelector(`div[data-currencyid="${currencyId}"] input[name="code"]`);
  saveButton.classList.add("disabled");
  saveButton.disabled = true;
  if (inputNameElement) {
    var currencyName = encodeURIComponent(inputNameElement.value);
    var currencySymbol = encodeURIComponent(inputSymbolElement.value);
    var currencyCode = encodeURIComponent(inputCodeElement.value);
    var url = `endpoints/currency/edit.php?currencyId=${currencyId}&name=${currencyName}&symbol=${currencySymbol}&code=${currencyCode}`;

    fetch(url)
      .then(response => {
        if (!response.ok) {
          throw new Error(translate('network_response_error'));
        }
        return response.json();
      })
      .then(data => {
        if (data.success) {
          saveButton.classList.remove("disabled");
          saveButton.disabled = false;
          showSuccessMessage(decodeURI(data.message));
        } else {
          saveButton.classList.remove("disabled");
          saveButton.disabled = false;
          showErrorMessage(data.message || translate('failed_save_currency'));
        }
      })
      .catch(error => {
        saveButton.classList.remove("disabled");
        saveButton.disabled = false;
        showErrorMessage(error.message || translate('failed_save_currency'));
      });
  }
}

function togglePayment(paymentId) {
  const element = document.querySelector(`div[data-paymentid="${paymentId}"]`);

  if (element.dataset.inUse === 'yes') {
    return showErrorMessage(translate('cant_disable_payment_in_use'));
  }

  const newEnabledState = element.dataset.enabled === '1' ? '0' : '1';
  const paymentMethodName = element.querySelector('.payment-name').innerText;

  const url = `endpoints/payments/payment.php?action=toggle&paymentId=${paymentId}&enabled=${newEnabledState}`;

  fetch(url).then(response => {
    if (!response.ok) {
      throw new Error(translate('network_response_error'));
    }
    return response.json();
  }).then(data => {
    if (data.success) {
      element.dataset.enabled = newEnabledState;
      showSuccessMessage(`${paymentMethodName} ${data.message}`);
    } else {
      showErrorMessage(data.message || translate('failed_save_payment_method'));
    }
  }).catch(error => {
    showErrorMessage(error.message || translate('failed_save_payment_method'));
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
  const formData = new FormData();
  formData.append('paymentId', paymentId);
  formData.append('name', name);
  fetch('endpoints/payments/rename.php', {
    method: 'POST',
    body: formData
  }).then(response => {
    if (!response.ok) {
      throw new Error(translate('network_response_error'));
    }
    return response.json();
  }).then(data => {
    if (data.success) {
      showSuccessMessage(`${newName} ${data.message}`);
    } else {
      showErrorMessage(data.message);
    }
  }).catch(error => {
    showErrorMessage(translate('unknown_error'));
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
  if (searchTerm !== "") {
    const iconSearchPopup = document.querySelector("#icon-search-results");
    iconSearchPopup.classList.add("is-open");
    const imageSearchUrl = `endpoints/payments/search.php?search=${searchTerm}`;
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

  fetch(addPaymentMethodEndpoint, {
    method: "POST",
    body: formData
  })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        showSuccessMessage(data.message);
        paymentMethodForm.reset();
        resetFormIcon();
        reloadPaymentMethods();
      } else {
        showErrorMessage(data.errorMessage);
      }
      submitButton.disabled = false;
    })
    .catch(error => {
      showErrorMessage(translate('unknown_error'));
      submitButton.disabled = false;
    });

}

function deletePaymentMethod(paymentId) {
  fetch(`endpoints/payments/delete.php?id=${paymentId}`, {
    method: 'DELETE',
    headers: {
      'Content-Type': 'application/json',
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
        showErrorMessage(data.errorMessage);
      }
    })
    .catch((error) => {
      console.error('Error:', error);
    });
}

function savePaymentMethodsSorting() {
  const paymentMethods = document.getElementById('payments-list');
  const paymentMethodIds = Array.from(paymentMethods.children).map(paymentMethod => paymentMethod.dataset.paymentid);

  const formData = new FormData();
  paymentMethodIds.forEach(paymentMethodId => {
    formData.append('paymentMethodIds[]', paymentMethodId);
  });

  fetch('endpoints/payments/sort.php', {
    method: 'POST',
    body: formData
  })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        showSuccessMessage(data.message);
      } else {
        showErrorMessage(data.errorMessage);
      }
    })
    .catch(error => {
      showErrorMessage(translate('unknown_error'));
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

});

function addFixerKeyButton() {
  document.getElementById("addFixerKey").disabled = true;
  const apiKeyInput = document.querySelector("#fixerKey");
  apiKey = apiKeyInput.value.trim();
  const provider = document.querySelector("#fixerProvider").value;
  const convertCurrencyCheckbox = document.querySelector("#convertcurrency");
  fetch("endpoints/currency/fixer_api_key.php", {
    method: "POST",
    headers: {
      "Content-Type": "application/x-www-form-urlencoded",
    },
    body: `api_key=${encodeURIComponent(apiKey)}&provider=${encodeURIComponent(provider)}`,
  })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        showSuccessMessage(data.message);
        document.getElementById("addFixerKey").disabled = false;
        convertCurrencyCheckbox.disabled = false;
        // update currency exchange rates
        fetch("endpoints/currency/update_exchange.php?force=true");
      } else {
        showErrorMessage(data.message);
        document.getElementById("addFixerKey").disabled = false;
      }
    })
    .catch(error => {
      showErrorMessage(error);
      document.getElementById("addFixerKey").disabled = false;
    });
}

function storeSettingsOnDB(endpoint, value) {
  fetch('endpoints/settings/' + endpoint + '.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({ "value": value })
  })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        showSuccessMessage(data.message);
      } else {
        showErrorMessage(data.errorMessage);
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

function saveCategorySorting() {
  const categories = document.getElementById('categories');
  const categoryIds = Array.from(categories.children).map(category => category.dataset.categoryid);

  const formData = new FormData();
  categoryIds.forEach(categoryId => {
    formData.append('categoryIds[]', categoryId);
  });

  fetch('endpoints/categories/sort.php', {
    method: 'POST',
    body: formData
  })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        showSuccessMessage(data.message);
      } else {
        showErrorMessage(data.errorMessage);
      }
    })
    .catch(error => {
      showErrorMessage(translate('unknown_error'));
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
