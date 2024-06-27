function toggleAvatarSelect() {
  var avatarSelect = document.getElementById("avatarSelect");
  if (avatarSelect.classList.contains("is-open")) {
    avatarSelect.classList.remove("is-open");
  } else {
    avatarSelect.classList.add("is-open");
  }
}

function closeAvatarSelect() {
  var avatarSelect = document.getElementById("avatarSelect");
  avatarSelect.classList.remove("is-open");
}

document.querySelectorAll('.avatar-option').forEach((avatar) => {
    avatar.addEventListener("click", () => {
        changeAvatar(avatar.src);
        document.getElementById('avatarUser').value = avatar.getAttribute('data-src');
        closeAvatarSelect();
    })
});

function changeAvatar(src) {
    document.getElementById("avatarImg").src = src;
}

function successfulUpload(field, msg) {
    var reader = new FileReader();

    if (field.files.length === 0) {
      return;
    }
  
    if (! ['image/jpeg', 'image/png', 'image/gif', 'image/jtif', 'image/webp'].includes(field.files[0]['type'])) {
      showErrorMessage(msg);
      return;
    }

    reader.onload = function() {
        changeAvatar(reader.result);
    };

    reader.readAsDataURL(field.files[0]);
    closeAvatarSelect();
}

function deleteAvatar(path) {
  fetch('/endpoints/user/delete_avatar.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({ avatar: path }),
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      var avatarContainer = document.querySelector(`.avatar-container[data-src="${path}"]`);
      if (avatarContainer) {
        avatarContainer.remove();
      }
      showSuccessMessage();
    } else {
      showErrorMessage();
    }
  })
  .catch((error) => {
    console.error('Error:', error);
  });
}

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
    if(responseData.success) {
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
      editLink.onclick = function() {
        editMember(newMemberId);
      };

      let editImage = document.createElement("img");
      editImage.src = "images/siteicons/" + colorTheme + "/save.png";
      editImage.title = translate('save_member');

      editLink.appendChild(editImage);

      let deleteLink = document.createElement("button");
      deleteLink.className = "image-button medium"
      deleteLink.name = "delete";
      deleteLink.onclick = function() {
        removeMember(newMemberId);
      };

      let deleteImage = document.createElement("img");
      deleteImage.src = "images/siteicons/" + colorTheme + "/delete.png";
      deleteImage.title = translate('delete_member');

      deleteLink.appendChild(deleteImage);

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
    if(responseData.success) {
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
      editLink.onclick = function() {
        editCategory(newCategoryId);
      };

      let editImage = document.createElement("img");
      editImage.src = "images/siteicons/" + colorTheme + "/save.png";
      editImage.title = translate('save_category');

      editLink.appendChild(editImage);

      let deleteLink = document.createElement("button");
      deleteLink.className = "image-button medium"
      deleteLink.name = "delete";
      deleteLink.onclick = function() {
        removeCategory(newCategoryId);
      };

      let deleteImage = document.createElement("img");
      deleteImage.src = "images/siteicons/"  + colorTheme + "/delete.png";
      deleteImage.title = translate('delete_category');

      deleteLink.appendChild(deleteImage);

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
    if(responseText !== "Error") {
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
      editLink.onclick = function() {
        editCurrency(newCurrencyId);
      };

      let editImage = document.createElement("img");
      editImage.src = "images/siteicons/" + colorTheme + "/save.png";
      editImage.title = translate('save_currency');

      editLink.appendChild(editImage);

      let deleteLink = document.createElement("button");
      deleteLink.className = "image-button medium"
      deleteLink.name = "delete";
      deleteLink.onclick = function() {
        removeCurrency(newCurrencyId);
      };

      let deleteImage = document.createElement("img");
      deleteImage.src = "images/siteicons/" + colorTheme + "/delete.png";
      deleteImage.title = translate('delete_currency');

      deleteLink.appendChild(deleteImage);

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

document.body.addEventListener('click', function(e) {
  let targetElement = e.target;
  do {
    if (targetElement.classList && targetElement.classList.contains('payments-payment')) {
      let targetChild = e.target;
      do {
        if (targetChild.classList && (targetChild.classList.contains('payment-name') || targetChild.classList.contains('drag-icon') )) {
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

document.body.addEventListener('blur', function(e) {
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

document.body.addEventListener('keypress', function(e) {
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
      img.onclick = function() {
        selectWebIcon(src);
      };
      img.onerror = function() {
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


document.addEventListener('DOMContentLoaded', function() {
    
    document.getElementById("userForm").addEventListener("submit", function(event) {
        event.preventDefault();
        document.getElementById("userSubmit").disabled = true;
        const formData = new FormData(event.target);
        fetch("endpoints/user/save_user.php", {
          method: "POST",
          body: formData
        })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            document.getElementById("avatar").src = document.getElementById("avatarImg").src;
            var newUsername = document.getElementById("username").value;
            document.getElementById("user").textContent = newUsername;
            showSuccessMessage(data.message);
            if (data.reload) {
              location.reload();
            }
          } else {
            showErrorMessage(data.errorMessage);
          }
          document.getElementById("userSubmit").disabled = false;
        })
        .catch(error => {
          showErrorMessage(translate('unknown_error'));
        });
      });

      var removePaymentButtons = document.querySelectorAll(".delete-payment-method");
      removePaymentButtons.forEach(function(button) {
        button.addEventListener('click', function(event) {
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

function switchTheme() {
  const darkThemeCss = document.querySelector("#dark-theme");
  darkThemeCss.disabled = !darkThemeCss.disabled;

  const themeChoice = darkThemeCss.disabled ? 'light' : 'dark';
  document.cookie = `theme=${themeChoice}; expires=Fri, 31 Dec 9999 23:59:59 GMT`;

  document.body.className = themeChoice;

  const button = document.getElementById("switchTheme");
  button.disabled = true;

  fetch('endpoints/settings/theme.php', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json'
    },
    body: JSON.stringify({theme: themeChoice === 'dark'})
  })
  .then(response => response.json())
  .then(data => {
      if (data.success) {
          showSuccessMessage(data.message);
      } else {
          showErrorMessage(data.errorMessage);
      }
      button.disabled = false;
  }).catch(error => {
      button.disabled = false;
  });
}

function setDarkTheme(theme) {
  const darkThemeRadio = document.querySelector("#theme-dark");
  const lightThemeRadio = document.querySelector("#theme-light");
  const automaticThemeRadio = document.querySelector("#theme-automatic");
  const darkThemeCss = document.querySelector("#dark-theme");
  const themes = {0: 'light', 1: 'dark', 2: 'automatic'};
  const themeValue = themes[theme];
  const prefersDarkMode = window.matchMedia('(prefers-color-scheme: dark)').matches;
  
  darkThemeRadio.disabled = true;
  lightThemeRadio.disabled = true;
  automaticThemeRadio.disabled = true;
  
  fetch('endpoints/settings/theme.php', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json'
    },
    body: JSON.stringify({theme: theme})
  })
  .then(response => response.json())
  .then(data => {
      if (data.success) {
          darkThemeRadio.disabled = false;
          lightThemeRadio.disabled = false;
          automaticThemeRadio.disabled = false;

          document.cookie = `theme=${themeValue}; expires=Fri, 31 Dec 9999 23:59:59 GMT`;

          if (theme == 0) {
            darkThemeCss.disabled = true;
            document.body.className = 'light';
          }

          if (theme == 1)  {
            darkThemeCss.disabled = false;
            document.body.className = 'dark';
          }

          if (theme == 2) {
            darkThemeCss.disabled = !prefersDarkMode;
            document.body.className = prefersDarkMode ? 'dark' : 'light';
            document.cookie = `inUseTheme=${prefersDarkMode ? 'dark' : 'light'}; expires=Fri, 31 Dec 9999 23:59:59 GMT`;
          }

          showSuccessMessage(data.message);
      } else {
          showErrorMessage(data.errorMessage);
          darkThemeRadio.disabled = false;
          lightThemeRadio.disabled = false;
          automaticThemeRadio.disabled = false;
      }
  }).catch(error => {
      darkThemeRadio.disabled = false;
      lightThemeRadio.disabled = false;
      automaticThemeRadio.disabled = false;
  });
}

function storeSettingsOnDB(endpoint, value) {
  fetch('endpoints/settings/' + endpoint + '.php', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json'
    },
    body: JSON.stringify({"value": value})
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


function setTheme(themeColor) {
  var currentTheme = 'blue';
  var themeIds = ['red-theme', 'green-theme', 'yellow-theme', 'purple-theme'];

  themeIds.forEach(function(id) {
    var themeStylesheet = document.getElementById(id);
    if (themeStylesheet && !themeStylesheet.disabled) {
        currentTheme = id.replace('-theme', '');
        themeStylesheet.disabled = true;
    }
  });

  if (themeColor !== "blue") {
    var enableTheme = document.getElementById(themeColor + '-theme');
    enableTheme.disabled = false;
  }

  var images = document.querySelectorAll('img');
  images.forEach(function(img) {
    if (img.src.includes('siteicons/' + currentTheme)) {
        img.src = img.src.replace(currentTheme, themeColor);
    }
  });

  var labels = document.querySelectorAll('.theme-preview');
  labels.forEach(function(label) {
    label.classList.remove('is-selected');
  });

  var targetLabel = document.querySelector(`.theme-preview.${themeColor}`);
  if (targetLabel) {
    targetLabel.classList.add('is-selected');
  }

  document.cookie = `colorTheme=${themeColor}; expires=Fri, 31 Dec 9999 23:59:59 GMT`;

  fetch('endpoints/settings/colortheme.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({ color: themeColor })
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
    showErrorMessage(translate('unknown_error'));
  });

}

function resetCustomColors() {
  const button = document.getElementById("reset-colors");
  button.disabled = true;

  fetch('endpoints/settings/resettheme.php', {
      method: 'DELETE',
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      showSuccessMessage(data.message);
      const custom_theme_colors = document.getElementById('custom_theme_colors');
      if (custom_theme_colors) {
        custom_theme_colors.remove();
      }
      document.documentElement.style.removeProperty('--main-color');
      document.documentElement.style.removeProperty('--accent-color');
      document.documentElement.style.removeProperty('--hover-color');
      document.getElementById("mainColor").value = "#FFFFFF";
      document.getElementById("accentColor").value = "#FFFFFF";
      document.getElementById("hoverColor").value = "#FFFFFF";
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

function saveCustomColors() {
  const button = document.getElementById("save-colors");
  button.disabled = true;

  const mainColor = document.getElementById("mainColor").value;
  const accentColor = document.getElementById("accentColor").value;
  const hoverColor = document.getElementById("hoverColor").value;

  fetch('endpoints/settings/customtheme.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({ mainColor: mainColor, accentColor: accentColor, hoverColor: hoverColor })
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      showSuccessMessage(data.message);
      document.documentElement.style.setProperty('--main-color', mainColor);
      document.documentElement.style.setProperty('--accent-color', accentColor);
      document.documentElement.style.setProperty('--hover-color', hoverColor);
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