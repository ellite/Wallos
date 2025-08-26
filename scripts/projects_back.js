// --- 全局变量和初始化 ---

let isSortOptionsOpen = false; // 标记排序选项菜单是否打开
let scrollTopBeforeOpening = 0; // 存储打开模态框前页面的滚动位置
const shouldScroll = window.innerWidth <= 768; // 判断是否为移动设备宽度，用于恢复滚动位置

/**
 * 切换订阅项的展开/收起状态。
 * @param {number} subId - 订阅项的ID。
 */
function toggleOpenSubscription(subId) {
  const subscriptionElement = document.querySelector('.subscription[data-id="' + subId + '"]');
  subscriptionElement.classList.toggle('is-open');
}

/**
 * 切换排序选项菜单的显示/隐藏。
 */
function toggleSortOptions() {
  const sortOptions = document.querySelector("#sort-options");
  sortOptions.classList.toggle("is-open");
  isSortOptionsOpen = !isSortOptionsOpen;
}

/**
 * 根据“启用通知”复选框的状态，切换“提前通知天数”下拉菜单的可用性。
 */
function toggleNotificationDays() {
  const notifyCheckbox = document.querySelector("#notifications");
  const notifyDaysBefore = document.querySelector("#notify_days_before");
  notifyDaysBefore.disabled = !notifyCheckbox.checked;
}
/**
 * 【最终确认版】专门用于“订阅”表单的重置函数
 */
function resetSubscriptionForm() {
  // 关键第一步：先通过 reset() 将所有字段恢复到HTML初始状态
  const form = document.querySelector("#subs-form");
  if (form) {
    form.reset();
  }

  // 关键第二步：在表单重置后，再用JS为特定字段设置新的默认值
  const id = document.querySelector("#id");
  if (id) id.value = "";

  const formTitle = document.querySelector("#form-title");
  if (formTitle) formTitle.textContent = translate('add_subscription');

  const logo = document.querySelector("#form-logo");
  if (logo) {
    logo.src = "";
    logo.style.display = 'none';
  }

  const logoUrl = document.querySelector("#logo-url");
  if (logoUrl) logoUrl.value = "";

  const logoSearchButton = document.querySelector("#logo-search-button");
  if (logoSearchButton) logoSearchButton.classList.add("disabled");

  const submitButton = document.querySelector("#save-button");
  if (submitButton) submitButton.disabled = false;

  const autoRenew = document.querySelector("#auto_renew");
  if (autoRenew) autoRenew.checked = true;

  // 【重要】确保这部分代码存在，用于自动加载当天的开始日期
  const startDate = document.querySelector("#start_date");
  if (startDate) {
    startDate.value = new Date().toISOString().split('T')[0];
  }

  const notifyDaysBefore = document.querySelector("#notify_days_before");
  if (notifyDaysBefore) notifyDaysBefore.disabled = true;

  const replacementSubscriptionIdSelect = document.querySelector("#replacement_subscription_id");
  if (replacementSubscriptionIdSelect) replacementSubscriptionIdSelect.value = "0";

  const replacementSubscription = document.querySelector("#replacement_subscritpion");
  if (replacementSubscription) replacementSubscription.classList.add("hide");

  closeLogoSearch();

  const deleteButton = document.querySelector("#deletesub");
  if (deleteButton) {
    deleteButton.style.display = 'none';
    deleteButton.removeAttribute("onClick");
  }
}

/**
 * 使用获取到的订阅数据填充编辑表单。
 * @param {object} subscription - 包含订阅详情的对象。
 */
function fillEditFormFields(subscription) {
  const formTitle = document.querySelector("#form-title");
  formTitle.textContent = translate('edit_subscription'); // 修改标题为“编辑订阅”
  const logo = document.querySelector("#form-logo");
  const logoFile = subscription.logo !== null ? "images/uploads/logos/" + subscription.logo : "";
  if (logoFile) {
    logo.src = logoFile; // 显示Logo
    logo.style = 'display: block';
  }
  const logoSearchButton = document.querySelector("#logo-search-button");
  logoSearchButton.classList.remove("disabled"); // 启用Logo搜索按钮


  const projectIdField = document.querySelector("#subscription-form #project_id");
  if (projectIdField) projectIdField.value = subscription.project_id;

  // 填充各个字段
  const id = document.querySelector("#id");
  id.value = subscription.id;
  const name = document.querySelector("#name");
  name.value = subscription.name;
  const price = document.querySelector("#price");
  price.value = subscription.price;

  // 填充下拉选择框
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

  // 填充日期字段
  const startDate = document.querySelector("#start_date");
  startDate.value = subscription.start_date;
  const nextPament = document.querySelector("#next_payment");
  nextPament.value = subscription.next_payment;
  const cancellationDate = document.querySelector("#cancellation_date");
  cancellationDate.value = subscription.cancellation_date;

  // 填充其他字段
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
    notifyDaysBefore.disabled = false; // 如果开启了通知，则启用天数选择
  }

  // 处理“替换为”选项
  const replacementSubscriptionIdSelect = document.querySelector("#replacement_subscription_id");
  replacementSubscriptionIdSelect.value = subscription.replacement_subscription_id ?? 0;

  const replacementSubscription = document.querySelector(`#replacement_subscritpion`);
  if (subscription.inactive) {
    replacementSubscription.classList.remove("hide"); // 如果订阅已停用，则显示“替换为”部分
  } else {
    replacementSubscription.classList.add("hide");
  }

  // 显示并设置删除按钮
  const deleteButton = document.querySelector("#deletesub");
  deleteButton.style = 'display: block';
  deleteButton.setAttribute("onClick", `deleteSubscription(event, ${subscription.id})`);

  // 打开表单模态框
  const modal = document.getElementById('subscription-form');
  modal.classList.add("is-open");
}

/**
 * 打开编辑订阅表单，并从后端获取数据。
 * @param {Event} event - 点击事件对象。
 * @param {number} id - 订阅ID。
 */
function openEditSubscription(event, id) {
  event.stopPropagation(); // 阻止事件冒泡
  scrollTopBeforeOpening = window.scrollY; // 记录当前滚动位置
  const body = document.querySelector('body');
  body.classList.add('no-scroll'); // 禁止页面滚动
  const url = `endpoints/subscription/get.php?id=${id}`;
  // 使用fetch API从后端获取订阅数据
  fetch(url)
      .then((response) => {
        if (response.ok) {
          return response.json(); // 解析JSON数据
        } else {
          showErrorMessage(translate('failed_to_load_subscription'));
        }
      })
      .then((data) => {
        if (data.error || data === "Error") {
          showErrorMessage(translate('failed_to_load_subscription'));
        } else {
          const subscription = data;
          fillEditFormFields(subscription); // 填充表单
        }
      })
      .catch((error) => {
        console.log(error);
        showErrorMessage(translate('failed_to_load_subscription'));
      });
}


/**
 * 【优化版】打开用于添加新订阅的空白表单。
 * @param {Event|null} event - 可选的点击事件对象。
 * @param {number|null} projectId - 可选的项目ID。如果提供了，会自动填充到表单中。
 */
function addSubscription(event = null, projectId = null) {
  // 如果是通过点击事件调用的，阻止其默认行为和冒泡
  if (event) {
    event.stopPropagation();
    event.preventDefault();
  }

  // 调用重置函数，确保表单是干净的
  resetSubscriptionForm();

  // 【关键】获取订阅表单中隐藏的 project_id 输入框
  // 使用更精确的选择器，确保只在订阅表单内查找
  const projectIdField = document.querySelector("#subscription-form #project_id");

  // 如果传入了 projectId，就设置隐藏输入框的值
  if (projectIdField && projectId !== null) {
    projectIdField.value = projectId;
  } else if (projectIdField) {
    // 如果没有传入projectId（例如点击全局“新建订阅”按钮），确保该值为空
    projectIdField.value = '';
  }

  // 打开表单的逻辑保持不变
  const modal = document.getElementById('subscription-form');
  if (modal) {
    modal.classList.add("is-open");
    document.body.classList.add('no-scroll');
  }
}
/**
 * 关闭添加/编辑订阅表单。
 */
function closeAddSubscription() {
  const modal = document.getElementById('subscription-form');
  if (modal) {
    modal.classList.remove("is-open");
    document.body.classList.remove('no-scroll');
    if (shouldScroll) {
      window.scrollTo(0, scrollTopBeforeOpening);
    }
    // 关键修正：确保这里调用的是订阅表单专属的重置函数
    resetSubscriptionForm();
  }
}

/**
 * 处理用户选择本地Logo文件的事件，并预览。
 * @param {Event} event - 文件选择事件对象。
 */
function handleFileSelect(event) {
  const fileInput = event.target;
  const logoPreview = document.querySelector('.logo-preview');
  const logoImg = logoPreview.querySelector('img');
  const logoUrl = document.querySelector("#logo-url");
  logoUrl.value = ""; // 清空通过URL选择的Logo

  // 使用FileReader来读取本地文件并显示预览
  if (fileInput.files && fileInput.files[0]) {
    const reader = new FileReader();
    reader.onload = function (e) {
      logoImg.src = e.target.result;
      logoImg.style.display = 'block';
    };
    reader.readAsDataURL(fileInput.files[0]);
  }
}

/**
 * 删除一个订阅项。
 * @param {Event} event - 点击事件对象。
 * @param {number} id - 订阅ID。
 */
function deleteSubscription(event, id) {
  event.stopPropagation();
  event.preventDefault();
  // 弹出确认框
  if (confirm(translate('confirm_delete_subscription'))) {
    // 发送DELETE请求到后端
    fetch(`endpoints/subscription/delete.php?id=${id}`, {
      method: 'DELETE',
    })
        .then(response => {
          if (response.ok) {
            showSuccessMessage(translate('subscription_deleted'));
            fetchSubscriptions(null, null, "delete"); // 重新加载订阅列表
            closeAddSubscription(); // 关闭表单
          } else {
            showErrorMessage(translate('error_deleting_subscription'));
          }
        })
        .catch(error => {
          console.error('Error:', error);
        });
  }
}

/**
 * 克隆一个订阅项。
 * @param {Event} event - 点击事件对象。
 * @param {number} id - 订阅ID。
 */
function cloneSubscription(event, id) {
  event.stopPropagation();
  event.preventDefault();
  const url = `endpoints/subscription/clone.php?id=${id}`;
  // 发送请求到后端克隆接口
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
          fetchSubscriptions(id, event, "clone"); // 重新加载列表，并在加载后打开编辑克隆项
          showSuccessMessage(decodeURI(data.message));
        } else {
          showErrorMessage(data.message || translate('error'));
        }
      })
      .catch(error => {
        showErrorMessage(error.message || translate('error'));
      });
}

/**
 * 手动续费一个订阅项。
 * @param {Event} event - 点击事件对象。
 * @param {number} id - 订阅ID。
 */
function renewSubscription(event, id) {
  event.stopPropagation();
  event.preventDefault();
  const url = `endpoints/subscription/renew.php?id=${id}`;
  // 发送请求到后端续费接口
  fetch(url)
      .then(response => {
        if (!response.ok) {
          throw new Error(translate('network_response_error'));
        }
        return response.json();
      })
      .then(data => {
        if (data.success) {
          fetchSubscriptions(null, null, "renew"); // 重新加载列表
          showSuccessMessage(decodeURI(data.message));
        } else {
          showErrorMessage(data.message || translate('error'));
        }
      })
      .catch(error => {
        showErrorMessage(error.message || translate('error'));
      });
}

/**
 * 根据订阅名称输入框是否有内容，来设置Logo搜索按钮的可用状态。
 */
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

/**
 * 在线搜索Logo。
 */
function searchLogo() {
  const nameInput = document.querySelector("#name");
  const searchTerm = nameInput.value.trim();
  if (searchTerm !== "") {
    const logoSearchPopup = document.querySelector("#logo-search-results");
    logoSearchPopup.classList.add("is-open");
    const imageSearchUrl = `endpoints/logos/search.php?search=${searchTerm}`;
    // 请求后端Logo搜索接口
    fetch(imageSearchUrl)
        .then(response => response.json())
        .then(data => {
          if (data.imageUrls) {
            displayImageResults(data.imageUrls); // 显示搜索到的图片
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

/**
 * 将搜索到的Logo图片显示在结果区域。
 * @param {string[]} imageSources - 图片URL数组。
 */
function displayImageResults(imageSources) {
  const logoResults = document.querySelector("#logo-search-images");
  logoResults.innerHTML = ""; // 清空旧结果

  imageSources.forEach(src => {
    const img = document.createElement("img");
    img.src = src;
    img.onclick = function () {
      selectWebLogo(src); // 点击图片即可选中
    };
    img.onerror = function () {
      this.parentNode.removeChild(this); // 图片加载失败则移除
    };
    logoResults.appendChild(img);
  });
}

/**
 * 选中一个在线搜索到的Logo。
 * @param {string} url - 选中的图片URL。
 */
function selectWebLogo(url) {
  closeLogoSearch();
  const logoPreview = document.querySelector("#form-logo");
  const logoUrl = document.querySelector("#logo-url");
  logoPreview.src = url; // 设置预览图
  logoPreview.style.display = 'block';
  logoUrl.value = url; // 将URL存入隐藏字段
}

/**
 * 关闭Logo搜索结果弹窗。
 */
function closeLogoSearch() {
  const logoSearchPopup = document.querySelector("#logo-search-results");
  logoSearchPopup.classList.remove("is-open");
  const logoResults = document.querySelector("#logo-search-images");
  logoResults.innerHTML = "";
}

/**
 * 【新增】根据当前过滤器重新从后端获取并刷新项目列表。
 * @param {string} initiator - 触发刷新的原因。
 */
function fetchProjects(initiator) {
  const container = document.querySelector("#subscriptions"); // 列表容器的ID保持不变
  if (!container) return;

  // 指向获取项目列表的后端接口
  let getProjectsUrl = "endpoints/projects/get.php";

  // 根据当前激活的过滤器拼接URL (与 get.php 逻辑保持一致)
  if (activeFilters['categories'] && activeFilters['categories'].length > 0) {
    getProjectsUrl += `?categories=${activeFilters['categories']}`;
  }
  // (可以根据需要添加其他过滤器的URL参数)

  fetch(getProjectsUrl)
      .then(response => response.text())
      .then(data => {
        if (data) {
          container.innerHTML = data; // 用返回的新HTML替换旧的列表内容

          const mainActions = document.querySelector("#main-actions");
          if (mainActions) {
            if (data.includes("no-matching-subscriptions")) {
              // 可以选择隐藏操作栏
            } else {
              mainActions.classList.remove("hidden");
            }
          }
        }
        setSwipeElements(); // 重新为新加载的元素设置滑动事件
      })
      .catch(error => {
        console.error(translate('error_reloading_project_list'), error);
      });
}

/**
 * 根据当前过滤器重新从后端获取并刷新订阅列表。
 * @param {number|null} id - 操作后需要关注的订阅ID。
 * @param {Event|null} event - 触发事件。
 * @param {string} initiator - 触发刷新的原因 (例如, "add", "delete", "filter")。
 */
function fetchSubscriptions(id, event, initiator) {
  const subscriptionsContainer = document.querySelector("#subscriptions");
  let getSubscriptions = "endpoints/projects/get.php";

  // 根据当前激活的过滤器拼接URL
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
  if (activeFilters['renewalType'] !== "") {
    getSubscriptions += getSubscriptions.includes("?") ? `&renewalType=${activeFilters['renewalType']}` : `?renewalType=${activeFilters['renewalType']}`;
  }

  // 发送请求获取HTML内容
  fetch(getSubscriptions)
      .then(response => response.text())
      .then(data => {
        if (data) {
          subscriptionsContainer.innerHTML = data; // 直接用返回的HTML替换列表内容
          const mainActions = document.querySelector("#main-actions");
          if (data.includes("no-matching-subscriptions")) {
            // 如果没有匹配结果，可以隐藏操作栏
          } else {
            mainActions.classList.remove("hidden");
          }
        }

        // 如果是克隆操作，完成后自动打开编辑表单
        if (initiator == "clone" && id && event) {
          openEditSubscription(event, id);
        }

        setSwipeElements(); // 重新设置移动端滑动事件

        // 如果是添加了第一个订阅，显示滑动提示动画
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

/**
 * 设置排序选项，并将其存入Cookie。
 * @param {string} sortOption - 排序标准。
 */
function setSortOption(sortOption) {
  const sortOptionsContainer = document.querySelector("#sort-options");
  const sortOptionsList = sortOptionsContainer.querySelectorAll("li");
  // 更新UI，高亮选中的排序项
  sortOptionsList.forEach((option) => {
    if (option.getAttribute("id") === "sort-" + sortOption) {
      option.classList.add("selected");
    } else {
      option.classList.remove("selected");
    }
  });
  // 将排序选项存入Cookie，有效期30天
  const daysToExpire = 30;
  const expirationDate = new Date();
  expirationDate.setDate(expirationDate.getDate() + daysToExpire);
  const cookieValue = encodeURIComponent(sortOption) + '; expires=' + expirationDate.toUTCString();
  document.cookie = 'sortOrder=' + cookieValue + '; SameSite=Strict';
  fetchSubscriptions(null, null, "sort"); // 重新加载列表
  toggleSortOptions(); // 关闭排序菜单
}

/**
 * 将SVG文件转换为PNG文件（在前端）。
 * @param {File} file - SVG文件对象。
 * @param {function} callback - 转换完成后的回调函数。
 */
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
      callback(pngFile); // 调用回调函数，并传入转换后的PNG文件
    };
  };

  reader.readAsDataURL(file);
}

/**
 * 将DataURL转换为File对象。
 * @param {string} dataurl - DataURL字符串。
 * @param {string} filename - 文件名。
 * @returns {File} - 文件对象。
 */
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

/**
 * 【关键修改】提交表单数据到后端。
 * @param {FormData} formData - 表单数据。
 * @param {HTMLButtonElement} submitButton - 提交按钮。
 * @param {string} endpoint - 后端接口URL。
 * @param {function} successCallback - 【重要】操作成功后要执行的回调函数。
 */
function submitFormData(formData, submitButton, endpoint, successCallback) {
  fetch(endpoint, {
    method: "POST",
    body: formData,
  })
      .then((response) => response.json())
      .then((data) => {
        if (data.status === "Success") {
          showSuccessMessage(data.message);
          // 关键：检查并执行传入的回调函数
          if (successCallback && typeof successCallback === 'function') {
            successCallback();
          }
        } else {
          showErrorMessage(data.message || 'An unknown error occurred.');
        }
      })
      .catch((error) => {
        showErrorMessage(error.toString());
      })
      .finally(() => {
        // 无论成功或失败，最后都恢复按钮
        if (submitButton) submitButton.disabled = false;
      });
}
// --- 事件监听器 ---


/**
 * 根据搜索框内容实时筛选订阅列表。
 */
function searchSubscriptions() {
  const searchInput = document.querySelector("#search");
  const searchContainer = searchInput.parentElement;
  const searchTerm = searchInput.value.trim().toLowerCase();

  // 控制清除按钮的显示/隐藏
  if (searchTerm.length > 0) {
    searchContainer.classList.add("has-text");
  } else {
    searchContainer.classList.remove("has-text");
  }

  // 遍历所有订阅项进行匹配
  const subscriptions = document.querySelectorAll(".subscription");
  subscriptions.forEach(subscription => {
    const name = subscription.getAttribute('data-name').toLowerCase();
    if (!name.includes(searchTerm)) {
      subscription.parentElement.classList.add("hide"); // 不匹配则隐藏
    } else {
      subscription.parentElement.classList.remove("hide"); // 匹配则显示
    }
  });
}

/**
 * 清空搜索框。
 */
function clearSearch() {
  const searchInput = document.querySelector("#search");
  searchInput.value = "";
  searchSubscriptions(); // 再次调用以恢复列表显示
}

/**
 * 关闭所有过滤器子菜单。
 */
function closeSubMenus() {
  var subMenus = document.querySelectorAll('.filtermenu-submenu-content');
  subMenus.forEach(subMenu => {
    subMenu.classList.remove('is-open');
  });
}

/**
 * 为所有订阅项设置移动端滑动事件。
 */
function setSwipeElements() {
  if (window.mobileNavigation) {
    const swipeElements = document.querySelectorAll('.subscription');

    swipeElements.forEach((element) => {
      let startX = 0, startY = 0, currentX = 0, currentY = 0, translateX = 0;
      // 根据是否有“续费”按钮决定最大滑动距离
      const maxTranslateX = element.classList.contains('manual') ? -240 : -180;

      // 触摸开始
      element.addEventListener('touchstart', (e) => {
        startX = e.touches[0].clientX;
        startY = e.touches[0].clientY;
        element.style.transition = ''; // 移除过渡效果以便平滑拖动
      });

      // 触摸移动
      element.addEventListener('touchmove', (e) => {
        currentX = e.touches[0].clientX;
        currentY = e.touches[0].clientY;
        const diffX = currentX - startX;
        const diffY = currentY - startY;

        // 判断是否为水平滑动
        if (Math.abs(diffX) > Math.abs(diffY)) {
          e.preventDefault(); // 阻止页面垂直滚动
          translateX = Math.min(0, Math.max(maxTranslateX, diffX)); // 计算并限制滑动距离
          element.style.transform = `translateX(${translateX}px)`;
        }
      });

      // 触摸结束
      element.addEventListener('touchend', () => {
        // 根据滑动距离决定是吸附到打开状态还是关闭状态
        if (translateX < maxTranslateX / 2) {
          translateX = maxTranslateX; // 完全打开
        } else {
          translateX = 0; // 关闭
        }
        element.style.transition = 'transform 0.2s ease'; // 添加平滑的吸附动画
        element.style.transform = `translateX(${translateX}px)`;
      });
    });
  }
}

// 全局变量，用于存储当前激活的过滤器
const activeFilters = [];
activeFilters['categories'] = [];
activeFilters['members'] = [];
activeFilters['payments'] = [];
activeFilters['state'] = "";
activeFilters['renewalType'] = "";

// 监听DOM加载完成，设置过滤器菜单的事件
document.addEventListener("DOMContentLoaded", function () {
  var filtermenu = document.querySelector('#filtermenu-button');
  filtermenu.addEventListener('click', function () {
    this.parentElement.querySelector('.filtermenu-content').classList.toggle('is-open');
    closeSubMenus();
  });

  // 监听全局点击，用于关闭过滤器菜单
  document.addEventListener('click', function (e) {
    var filtermenuContent = document.querySelector('.filtermenu-content');
    if (filtermenuContent.classList.contains('is-open')) {
      var subMenus = document.querySelectorAll('.filtermenu-submenu');
      var clickedInsideSubmenu = Array.from(subMenus).some(subMenu => subMenu.contains(e.target) || subMenu === e.target);
      // 如果点击位置不在过滤器菜单内部，则关闭它
      if (!filtermenu.contains(e.target) && !clickedInsideSubmenu) {
        closeSubMenus();
        filtermenuContent.classList.remove('is-open');
      }
    }
  });

  setSwipeElements(); // 页面加载时设置滑动事件
});

/**
 * 切换过滤器子菜单的显示/隐藏。
 */
function toggleSubMenu(subMenu) {
  var subMenu = document.getElementById("filter-" + subMenu);
  if (subMenu.classList.contains("is-open")) {
    closeSubMenus();
  } else {
    closeSubMenus();
    subMenu.classList.add("is-open");
  }
}

/**
 * 切换“替换为”下拉菜单的显示/隐藏。
 */
function toggleReplacementSub() {
  const checkbox = document.getElementById('inactive');
  const replacementSubscription = document.querySelector(`#replacement_subscritpion`);

  if (checkbox.checked) {
    replacementSubscription.classList.remove("hide");
  } else {
    replacementSubscription.classList.add("hide");
  }
}

// 为所有过滤器项目添加点击事件监听器

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
    } else if (this.hasAttribute('data-renewaltype')) {
      const renewalType = this.getAttribute('data-renewaltype');
      if (activeFilters['renewalType'] === renewalType) {
        activeFilters['renewalType'] = "";
        this.classList.remove('selected');
      } else {
        activeFilters['renewalType'] = renewalType;
        Array.from(this.parentNode.children).forEach(sibling => {
          sibling.classList.remove('selected');
        });
        this.classList.add('selected');
      }
    }

    if (activeFilters['categories'].length > 0 || activeFilters['members'].length > 0 ||
        activeFilters['payments'].length > 0 || activeFilters['state'] !== "" ||
        activeFilters['renewalType'] !== "") {
      document.querySelector('#clear-filters').classList.remove('hide');
    } else {
      document.querySelector('#clear-filters').classList.add('hide');
    }

    fetchSubscriptions(null, null, "filter");
  });
});

/**
 * 清除所有已应用的过滤器。
 */
function clearFilters() {
  const searchInput = document.querySelector("#search");
  searchInput.value = "";
  // 重置activeFilters数组
  activeFilters['categories'] = [];
  activeFilters['members'] = [];
  activeFilters['payments'] = [];
  activeFilters['state'] = "";
  activeFilters['renewalType'] = "";

  // 移除所有过滤器的选中状态
  document.querySelectorAll('.filter-item').forEach(function (item) {
    item.classList.remove('selected');
  });
  document.querySelector('#clear-filters').classList.add('hide');
  fetchSubscriptions(null, null, "clearfilters"); // 刷新列表
}

let currentActions = null; // 用于跟踪当前打开的操作菜单

// 全局点击事件，用于关闭打开的操作菜单
document.addEventListener('click', function (event) {
  if (currentActions && !currentActions.contains(event.target)) {
    currentActions.classList.remove('is-open');
    currentActions = null;
  }
});

/**
 * 展开或收起桌面版的操作菜单。
 * @param {Event} event - 点击事件对象。
 * @param {number} subscriptionId - 订阅ID。
 */
function expandActions(event, subscriptionId) {
  event.stopPropagation();
  event.preventDefault();
  const subscriptionDiv = document.querySelector(`.subscription[data-id="${subscriptionId}"]`);
  const actions = subscriptionDiv.querySelector('.actions');

  // 先关闭所有其他已打开的菜单
  const allActions = document.querySelectorAll('.actions.is-open');
  allActions.forEach((openAction) => {
    if (openAction !== actions) {
      openAction.classList.remove('is-open');
    }
  });

  // 切换当前点击的菜单
  actions.classList.toggle('is-open');

  // 更新当前打开的菜单引用
  if (actions.classList.contains('is-open')) {
    currentActions = actions;
  } else {
    currentActions = null;
  }
}

/**
 * 显示移动端滑动提示动画。
 */
function swipeHintAnimation() {
  if (window.mobileNavigation && window.matchMedia('(max-width: 768px)').matches) {
    const maxAnimations = 3; // 最多显示3次
    const cookieName = 'swipeHintCount';

    let count = parseInt(getCookie(cookieName)) || 0;
    if (count < maxAnimations) {
      const firstElement = document.querySelector('.subscription');
      if (firstElement) {
        // 动画效果：向左滑动一点然后滑回
        firstElement.style.transition = 'transform 0.3s ease';
        firstElement.style.transform = 'translateX(-80px)';

        setTimeout(() => {
          firstElement.style.transform = 'translateX(0px)';
        }, 600);
      }

      count++;
      // 将显示次数存入Cookie
      document.cookie = `${cookieName}=${count}; expires=Fri, 31 Dec 9999 23:59:59 GMT; path=/; SameSite=Strict`;
    }
  }
}

/**
 * 根据开始日期和计费周期，自动计算并填充下次付款日期。
 * @param {Event} e - 点击事件对象。
 */
function autoFillNextPaymentDate(e) {
  e.preventDefault();
  const frequencySelect = document.querySelector("#frequency");
  const cycleSelect = document.querySelector("#cycle");
  const startDate = document.querySelector("#start_date");
  const nextPayment = document.querySelector("#next_payment");

  if (!frequencySelect.value || !cycleSelect.value || !startDate.value || isNaN(Date.parse(startDate.value))) {
    return; // 如果缺少必要信息则不执行
  }

  const today = new Date();
  const cycle = cycleSelect.value;
  const frequency = Number(frequencySelect.value);

  // 从开始日期开始，循环增加计费周期，直到日期超过今天
  const nextDate = new Date(startDate.value);
  let safetyCounter = 0; // 防止无限循环
  const maxIterations = 1000;

  while (nextDate <= today && safetyCounter < maxIterations) {
    switch (cycle) {
      case '1': // 天
        nextDate.setDate(nextDate.getDate() + frequency);
        break;
      case '2': // 周
        nextDate.setDate(nextDate.getDate() + 7 * frequency);
        break;
      case '3': // 月
        nextDate.setMonth(nextDate.getMonth() + frequency);
        break;
      case '4': // 年
        nextDate.setFullYear(nextDate.getFullYear() + frequency);
        break;
    }
    safetyCounter++;
  }

  if (safetyCounter === maxIterations) {
    return; // 达到最大迭代次数，可能输入有误
  }

  // 将计算出的日期填充到输入框
  nextPayment.value = toISOStringWithTimezone(nextDate).substring(0, 10);
}

/**
 * 将Date对象转换为带时区信息的ISO格式字符串。
 * @param {Date} date - 日期对象。
 * @returns {string} - ISO格式字符串。
 */

function toISOStringWithTimezone(date) {
  const pad = n => String(Math.floor(Math.abs(n))).padStart(2, '0');
  const tzOffset = -date.getTimezoneOffset();
  const sign = tzOffset >= 0 ? '+' : '-';
  const hoursOffset = pad(tzOffset / 60);
  const minutesOffset = pad(tzOffset % 60);

  return date.getFullYear() +
      '-' + pad(date.getMonth() + 1) +
      '-' + pad(date.getDate()) +
      'T' + pad(date.getHours()) +
      ':' + pad(date.getMinutes()) +
      ':' + pad(date.getSeconds()) +
      sign + hoursOffset +
      ':' + minutesOffset;
}
// 页面加载完成后，如果列表存在，则尝试触发一次滑动提示动画
window.addEventListener('load', () => {
  if (document.querySelector('.subscription')) {
    swipeHintAnimation();
  }
});
//
//
//
//
//
//
// ===================================================================
// =================== 新建项目表单 (Project Form) 的JS函数 ===================
// ===================================================================

/**
 * 【专门用于“项目”表单的重置函数】
 */
function resetProjectForm() {
  const form = document.getElementById('project-subs-form');
  if (form) form.reset();

  const formTitle = document.getElementById('project-form-title');
  if (formTitle) formTitle.textContent = translate('new_project');

  const logo = document.getElementById('project-form-logo');
  if (logo) {
    logo.src = '';
    logo.style.display = 'none';
  }

  const logoUrl = document.getElementById('project-logo-url');
  if (logoUrl) logoUrl.value = '';

  const searchButton = document.getElementById('project-logo-search-button');
  if (searchButton) searchButton.classList.add('disabled');

  const submitButton = document.getElementById('save-project-btn');
  if (submitButton) submitButton.disabled = false;

  const deleteButton = document.getElementById('delete-project-btn');
  if (deleteButton) deleteButton.style.display = 'none';

  closeProjectLogoSearch();
}

/**
 * 打开项目表单。
 * @param {boolean} isNew - 如果是新建项目，则为 true，会重置表单。默认为 true。
 */
function openAddProject(isNew = true) {
  // 只有在新建项目时 (isNew 为 true) 才执行重置逻辑
  if (isNew) {
    // console.log("--- 正在重置表单以供新建 ---");
    // 这里放入所有清空和重置表单的代码
    const form = document.getElementById('project-subs-form');
    if (form) form.reset();

    const hiddenIdField = document.getElementById('project-id');
    if (hiddenIdField) hiddenIdField.value = '';

    const formTitle = document.getElementById('project-form-title');
    if (formTitle) formTitle.innerText = translate('new_project');

    const deleteButton = document.getElementById('delete-project-btn');
    if (deleteButton) deleteButton.style.display = 'none';

    const logoPreview = document.getElementById('project-form-logo');
    if (logoPreview) logoPreview.src = '';
  }

  // 这部分代码无论是新建还是编辑，都会执行，负责打开表单
  const formSection = document.getElementById('project-form');
  if (formSection) {
    formSection.classList.add('is-open');
    document.body.classList.add('no-scroll');
  }
}
/**
 * 关闭“新建项目”的表单。
 */
function closeAddProject() {
  const formSection = document.getElementById('project-form');
  if (formSection) {
    formSection.classList.remove('is-open');
    document.body.classList.remove('no-scroll');
    resetProjectForm();
  }
}
/**
 * (项目表单) 填充“编辑项目”表单的函数
 * @param {object} project - 包含项目详情的对象。
 */
function fillProjectEditForm(project) {
  const form = document.getElementById('project-subs-form');
  const formTitle = document.getElementById('project-form-title');
  const projectIdField = document.getElementById('project-id');
  const projectNameField = document.getElementById('project-name');
  const projectCategoryField = document.getElementById('project-category');
  const projectUrlField = document.getElementById('project-url');
  const projectNotesField = document.getElementById('project-notes');
  const projectLogoImg = document.getElementById('project-form-logo');
  const deleteButton = document.getElementById('delete-project-btn');
  const searchButton = document.getElementById('project-logo-search-button');

  if (formTitle) formTitle.textContent = translate('edit_project');
  if (projectIdField) projectIdField.value = project.id;
  if (projectNameField) projectNameField.value = project.name;
  if (projectCategoryField) projectCategoryField.value = project.category_id;
  if (projectUrlField) projectUrlField.value = project.url;
  if (projectNotesField) projectNotesField.value = project.notes;

  if (projectLogoImg) {
    if (project.logo) {
      projectLogoImg.src = "images/uploads/logos/" + project.logo;
      projectLogoImg.style.display = 'block';
    } else {
      projectLogoImg.src = '';
      projectLogoImg.style.display = 'none';
    }
  }

  if (searchButton) searchButton.classList.remove('disabled');

  if (deleteButton) {
    deleteButton.style.display = 'block';
    deleteButton.setAttribute("onClick", `deleteProject(event, ${project.id})`);
  }

  if (form) form.action = "endpoints/project/add.php";
}

/**
 * 打开“编辑项目”的表单，并从后端获取数据。
 * @param {Event} event - 点击事件对象。
 * @param {number} projectId - 要编辑的项目的ID。
 */
function openEditProject(event, projectId) {
  event.stopPropagation();

  // 【修改一】: 删除或注释掉这一行！编辑时不能先重置。
  // resetProjectForm();

  const url = `endpoints/project/get.php?id=${projectId}`;
  fetch(url)
      .then(response => response.json())
      .then(data => {
        if (data && !data.error) {
          fillProjectEditForm(data); // 这行保留，用来填充数据

          // 【修改二】: 调用 openAddProject 并传入 false，
          //    告诉它“只负责打开，不要重置内容”。
          openAddProject(false);
        } else {
          showErrorMessage(translate('error_loading_project_info') + (data.error || translate('unknown_error')));
        }
      })
      .catch(error => console.error(translate('error_fetching_project_info'), error));
}
/**
 * 删除一个项目。
 * @param {Event} event - 点击事件对象。
 * @param {number} projectId - 要删除的项目的ID。
 */
function deleteProject(event, projectId) {
  event.stopPropagation();
  event.preventDefault();
  if (confirm(translate('delete_project_confirmation'))) {
    fetch(`endpoints/project/delete.php?id=${projectId}`, { method: 'DELETE' })
        .then(response => {
          if (response.ok) {
            showSuccessMessage(translate('project_deleted_successfully'));
            window.location.reload();
          } else {
            showErrorMessage(translate('error_deleting_project'));
          }
        })
        .catch(error => console.error(translate('error_deleting_project'), error));
  }
}
/**
 * (项目表单) 根据项目名称输入框是否有内容，来设置Logo搜索按钮的可用状态。
 */
function setProjectSearchButtonStatus() {
  const nameInput = document.querySelector("#project-name");
  const logoSearchButton = document.querySelector("#project-logo-search-button");
  if (nameInput && logoSearchButton) {
    const hasSearchTerm = nameInput.value.trim().length > 0;
    if (hasSearchTerm) {
      logoSearchButton.classList.remove("disabled");
    } else {
      logoSearchButton.classList.add("disabled");
    }
  }
}

/**
 * (项目表单) 处理用户选择本地Logo文件的事件，并生成预览。
 */
function handleProjectFileSelect(event) {
  const fileInput = event.target;
  const logoPreview = document.querySelector('#project-form .logo-preview');
  const logoUrl = document.querySelector("#project-logo-url");
  if (logoPreview && logoUrl) {
    const logoImg = logoPreview.querySelector('img');
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
}

/**
 * (项目表单) 根据项目名称在线搜索Logo。
 */
function searchProjectLogo() {
  const nameInput = document.querySelector("#project-name");
  const searchTerm = nameInput.value.trim();
  if (searchTerm !== "") {
    const logoSearchPopup = document.querySelector("#project-logo-search-results");
    logoSearchPopup.classList.add("is-open");
    const imageSearchUrl = `endpoints/logos/search.php?search=${searchTerm}`;
    fetch(imageSearchUrl)
        .then(response => response.json())
        .then(data => {
          if (data.imageUrls) {
            displayProjectImageResults(data.imageUrls);
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

/**
 * (项目表单) 将搜索到的Logo图片显示在结果区域。
 */
function displayProjectImageResults(imageSources) {
  const logoResults = document.querySelector("#project-logo-search-images");
  logoResults.innerHTML = "";
  imageSources.forEach(src => {
    const img = document.createElement("img");
    img.src = src;
    img.onclick = () => selectProjectWebLogo(src);
    img.onerror = () => this.parentNode.removeChild(this);
    logoResults.appendChild(img);
  });
}

/**
 * (项目表单) 选中一个在线搜索到的Logo，并更新表单。
 */
function selectProjectWebLogo(url) {
  closeProjectLogoSearch();
  const logoPreview = document.querySelector("#project-form-logo");
  const logoUrl = document.querySelector("#project-logo-url");
  logoPreview.src = url;
  logoPreview.style.display = 'block';
  logoUrl.value = url;
}

/**
 * (项目表单) 关闭Logo搜索结果弹窗。
 */
function closeProjectLogoSearch() {
  const logoSearchPopup = document.querySelector("#project-logo-search-results");
  if (logoSearchPopup) logoSearchPopup.classList.remove("is-open");
  const logoResults = document.querySelector("#project-logo-search-images");
  if (logoResults) logoResults.innerHTML = "";
}

//
//
//
//
//
//
/// ===================================================================
// ========================== 唯一的事件监听器 ==========================
// ===================================================================

// 使用一个统一的 DOMContentLoaded 事件监听器来初始化所有功能
document.addEventListener('DOMContentLoaded', function () {

  // --- 监听“订阅”表单的提交事件 (如果存在) ---
  const subscriptionForm = document.querySelector("#subs-form");
  if (subscriptionForm) {
    subscriptionForm.addEventListener("submit", function (e) {
      e.preventDefault();
      const submitButton = document.querySelector("#save-button");
      submitButton.disabled = true;
      const formData = new FormData(subscriptionForm);
      const fileInput = document.querySelector("#logo");
      const file = fileInput.files[0];
      const endpoint = "endpoints/subscription/add.php";

      const successCallback = () => {
        if (typeof fetchSubscriptions === "function") {
          fetchSubscriptions(null, null, "add");
        }
        closeAddSubscription();
      };

      if (file && file.type === "image/svg+xml") {
        convertSvgToPng(file, (pngFile) => {
          formData.set("logo", pngFile);
          submitFormData(formData, submitButton, endpoint, successCallback);
        });
      } else {
        submitFormData(formData, submitButton, endpoint, successCallback);
      }
    });
  }

  // --- 监听“项目”表单的提交事件 (如果存在) ---
  const projectForm = document.querySelector("#project-subs-form");
  if (projectForm) {
    projectForm.addEventListener("submit", function (e) {
      e.preventDefault();
      const submitButton = document.querySelector("#save-project-btn");
      submitButton.disabled = true;
      const formData = new FormData(projectForm);
      const fileInput = document.querySelector("#project-logo");
      const file = fileInput.files[0];
      const endpoint = projectForm.action;

      const successCallback = () => {
        closeAddProject();
        fetchProjects("add");
      };

      if (file && file.type === "image/svg+xml") {
        convertSvgToPng(file, (pngFile) => {
          formData.set("logo", pngFile);
          submitFormData(formData, submitButton, endpoint, successCallback);
        });
      } else {
        submitFormData(formData, submitButton, endpoint, successCallback);
      }
    });
  }

  // --- 监听所有过滤器的点击事件 ---
  document.querySelectorAll('.filter-item').forEach(function (item) {
    item.addEventListener('click', function (e) {
      const searchInput = document.querySelector("#search");
      if (searchInput) searchInput.value = "";

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
      }

      const clearButton = document.querySelector('#clear-filters');
      if (clearButton) {
        if (activeFilters['categories'].length > 0) {
          clearButton.classList.remove('hide');
        } else {
          clearButton.classList.add('hide');
        }
      }

      fetchProjects("filter");
    });
  });

  // --- 监听主过滤器菜单按钮的点击 ---
  console.log("正在尝试为过滤器按钮绑定事件..."); // <--- 新增日志1

  const filtermenu = document.querySelector('#filtermenu-button');

// 新增日志2：检查是否找到了按钮
  console.log("找到 filtermenu-button 了吗?", filtermenu);

  if (filtermenu) {
    filtermenu.addEventListener('click', function () {
      console.log("过滤器按钮被点击了！"); // <--- 新增日志3

      const filterMenuContent = this.parentElement.querySelector('.filtermenu-content');

      // 新增日志4：检查是否找到了菜单内容
      console.log("找到 filtermenu-content 了吗?", filterMenuContent);

      if (filterMenuContent) {
        filterMenuContent.classList.toggle('is-open');
      }
      closeSubMenus();
    });
    console.log("过滤器按钮的点击事件已成功绑定！"); // <--- 新增日志5
  } else {
    console.error("严重错误：在DOM加载后，未能找到 #filtermenu-button 元素！");
  }


  
  // --- 监听全局点击，用于关闭各种弹出菜单 ---
  document.addEventListener('click', function (event) {
    // 关闭排序菜单
    const sortOptions = document.querySelector('#sort-options');
    const sortButton = document.querySelector("#sort-button");
    if (sortOptions && sortButton && !sortOptions.contains(event.target) && !sortButton.contains(event.target) && isSortOptionsOpen) {
      sortOptions.classList.remove('is-open');
      isSortOptionsOpen = false;
    }

    // 关闭过滤器菜单
    const filtermenuContent = document.querySelector('.filtermenu-content');
    if (filtermenuContent && filtermenuContent.classList.contains('is-open')) {
      const subMenus = document.querySelectorAll('.filtermenu-submenu');
      let clickedInsideSubmenu = Array.from(subMenus).some(subMenu => subMenu.contains(event.target));
      if (filtermenu && !filtermenu.contains(event.target) && !filtermenuContent.contains(event.target) && !clickedInsideSubmenu) {
        closeSubMenus();
        filtermenuContent.classList.remove('is-open');
      }
    }

    // 关闭项目/订阅右侧的操作菜单
    if (currentActions && !currentActions.contains(event.target)) {
      currentActions.classList.remove('is-open');
      currentActions = null;
    }
  });

  // --- 初始化页面功能 ---
  setSwipeElements();

  // 页面加载完成后，如果列表存在，则尝试触发一次滑动提示动画
  if (document.querySelector('.subscription')) {
    swipeHintAnimation();
  }
});