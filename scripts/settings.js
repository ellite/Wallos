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

function changeAvatar(number) {
  document.getElementById("avatarImg").src = "images/avatars/" + number + ".svg";
  document.getElementById("avatarUser").value = number;
  closeAvatarSelect();
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
      editImage.src = "images/siteicons/save.png";
      editImage.title = translate('save_member');

      editLink.appendChild(editImage);

      let deleteLink = document.createElement("button");
      deleteLink.className = "image-button medium"
      deleteLink.name = "delete";
      deleteLink.onclick = function() {
        removeMember(newMemberId);
      };

      let deleteImage = document.createElement("img");
      deleteImage.src = "images/siteicons/delete.png";
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
      throw new Error(translate('network_response_error'));
      showErrorMessage(translate('failed_add_category'));
    }
    return response.json();
  })
  .then(responseData => {
    if(responseData.success) {
      const newCategoryId = responseData.categoryId;;
      let container = document.getElementById("categories");
      let row = document.createElement("li");
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
      editImage.src = "images/siteicons/save.png";
      editImage.title = translate('save_category');

      editLink.appendChild(editImage);

      let deleteLink = document.createElement("button");
      deleteLink.className = "image-button medium"
      deleteLink.name = "delete";
      deleteLink.onclick = function() {
        removeCategory(newCategoryId);
      };

      let deleteImage = document.createElement("img");
      deleteImage.src = "images/siteicons/delete.png";
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
  console.log(saveButton);
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
  const url = 'endpoints/currency/currency.php?action=add';
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
      editImage.src = "images/siteicons/save.png";
      editImage.title = translate('save_currency');

      editLink.appendChild(editImage);

      let deleteLink = document.createElement("button");
      deleteLink.className = "image-button medium"
      deleteLink.name = "delete";
      deleteLink.onclick = function() {
        removeCurrency(newCurrencyId);
      };

      let deleteImage = document.createElement("img");
      deleteImage.src = "images/siteicons/delete.png";
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
  let url = `endpoints/currency/currency.php?action=delete&currencyId=${currencyId}`;
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
    var url = `endpoints/currency/currency.php?action=edit&currencyId=${currencyId}&name=${currencyName}&symbol=${currencySymbol}&code=${currencyCode}`;

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
      return showErrorMessage(translate(cant_disable_payment_in_use));
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
            var newAvatar = document.getElementById("avatarUser").value;
            document.getElementById("avatar").src = "images/avatars/" + newAvatar + ".svg";
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

function saveNotificationsButton() {
  const button = document.getElementById("saveNotifications");
  button.disabled = true;

  const enabled = document.getElementById("notifications").checked ? 1 : 0;
  const days = document.getElementById("days").value;
  const smtpAddress = document.getElementById("smtpaddress").value;
  const smtpPort = document.getElementById("smtpport").value;
  const smtpUsername = document.getElementById("smtpusername").value;
  const smtpPassword = document.getElementById("smtppassword").value;
  const fromEmail = document.getElementById("fromemail").value;

  const data = {
    enabled: enabled,
    days: days,
    smtpaddress: smtpAddress,
    smtpport: smtpPort,
    smtpusername: smtpUsername,
    smtppassword: smtpPassword,
    fromemail: fromEmail
  };

  fetch('endpoints/notifications/save.php', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json'
    },
    body: JSON.stringify(data)
  })
  .then(response => response.json())
  .then(data => {
      if (data.success) {
          showSuccessMessage(data.message);
      } else {
          showErrorMessage(data.errorMessage);
      }
      button.disabled = false;
  })
  .catch(error => {
      showErrorMessage(translate('error_saving_notification_data'));
      button.disabled = false;
  });
}

function testNotificationButton()  {
  const button = document.getElementById("testNotifications");
  button.disabled = true;

  const smtpAddress = document.getElementById("smtpaddress").value;
  const smtpPort = document.getElementById("smtpport").value;
  const smtpUsername = document.getElementById("smtpusername").value;
  const smtpPassword = document.getElementById("smtppassword").value;
  const fromEmail = document.getElementById("fromemail").value;

  const data = {
    smtpaddress: smtpAddress,
    smtpport: smtpPort,
    smtpusername: smtpUsername,
    smtppassword: smtpPassword,
    fromemail: fromEmail
  };

  fetch('endpoints/notifications/sendtestmail.php', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json'
    },
    body: JSON.stringify(data)
  })
  .then(response => response.json())
  .then(data => {
      if (data.success) {
          showSuccessMessage(data.message);
      } else {
          showErrorMessage(data.errorMessage);
      }
      button.disabled = false;
  })
  .catch(error => {
      showErrorMessage(translate('error_sending_notification'));
      button.disabled = false;
  });
}

function switchTheme() {
  const darkThemeCss = document.querySelector("#dark-theme");
  darkThemeCss.disabled = !darkThemeCss.disabled;

  const themeChoice = darkThemeCss.disabled ? 'light' : 'dark';
  document.cookie = `theme=${themeChoice}; expires=Fri, 31 Dec 9999 23:59:59 GMT`;

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

function exportToJson() {
  window.location.href = "endpoints/subscriptions/export.php";
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
  delay: 500,
  delayOnTouchOnly: true,
  touchStartThreshold: 5,
  onEnd: function (evt) {
    saveCategorySorting();
  },
});