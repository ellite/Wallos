document.addEventListener("DOMContentLoaded", function () {
  function updateAiRecommendationNumbers() {
    document.querySelectorAll(".ai-recommendation-item").forEach(function (item, index) {
      const numberSpan = item.querySelector(".ai-recommendation-header h3 > span");
      if (numberSpan) {
        numberSpan.textContent = `${index + 1}. `;
      }
    });
  }

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
        headers: {
          "Content-Type": "application/json",
          "X-CSRF-Token": window.csrfToken,
        },
        body: JSON.stringify({ id: id }),
      })
        .then(res => res.json())
        .then(data => {
          if (data.success) {
            item.remove();
            updateAiRecommendationNumbers();
            showSuccessMessage(translate("success"));
          } else {
            showErrorMessage(data.message || translate("failed_delete_ai_recommendation"));
          }
        })
        .catch(error => {
          console.error(error);
          showErrorMessage(translate("unknown_error"));
        });
    });
  });

  initDashboardWidgetEditor();
});

function initDashboardWidgetEditor() {
  const dashboard = document.querySelector("section.contain.dashboard");
  const list = document.getElementById("dashboard-widgets-list");
  const editButton = document.getElementById("editDashboardWidgets");
  const doneButton = document.getElementById("doneDashboardWidgets");
  const addButton = document.getElementById("addPaymentMethodBudgetWidget");

  if (!dashboard || !list || !editButton || !doneButton || typeof Sortable === "undefined") {
    return;
  }

  let sortable = null;
  let editing = false;

  function parseJsonAttr(el, name, fallback) {
    try {
      const raw = el.getAttribute(name);
      if (!raw) {
        return fallback;
      }
      return JSON.parse(raw);
    } catch (e) {
      return fallback;
    }
  }

  function newInstanceId() {
    const bytes = new Uint8Array(4);
    if (window.crypto && window.crypto.getRandomValues) {
      window.crypto.getRandomValues(bytes);
    } else {
      for (let i = 0; i < bytes.length; i++) {
        bytes[i] = Math.floor(Math.random() * 256);
      }
    }
    return "pmb_" + Array.from(bytes).map(function (b) {
      return b.toString(16).padStart(2, "0");
    }).join("");
  }

  function collectLayout() {
    return Array.from(list.querySelectorAll(".dashboard-widget")).map(function (el) {
      const entry = {
        widget_id: el.getAttribute("data-widget-id"),
        enabled: el.getAttribute("data-enabled") === "1",
      };
      if (entry.widget_id === "payment_method_budget") {
        entry.instance_id = el.getAttribute("data-instance-id") || newInstanceId();
        entry.payment_method_ids = parseJsonAttr(el, "data-payment-method-ids", []);
        entry.title = el.getAttribute("data-title") || "";
        entry.display_mode = el.getAttribute("data-display-mode") === "combined" ? "combined" : "per_method";
      }
      return entry;
    });
  }

  // Saves are chained so an older request can never land after (and overwrite) a newer one.
  // The payload is collected when the request actually runs, so it always reflects the latest DOM.
  let saveQueue = Promise.resolve();
  let lastSavedLayout = null;

  function saveLayout() {
    const run = function () {
      const layout = JSON.stringify(collectLayout());
      return fetch("endpoints/settings/dashboard_widgets.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "X-CSRF-Token": window.csrfToken,
        },
        body: JSON.stringify({ widgets: JSON.parse(layout) }),
      })
        .then(function (response) { return response.json(); })
        .then(function (data) {
          if (data.success) {
            lastSavedLayout = layout;
            showSuccessMessage(data.message);
          } else {
            showErrorMessage(data.message || translate("unknown_error"));
          }
          return data;
        })
        .catch(function (error) {
          console.error(error);
          showErrorMessage(translate("unknown_error"));
        });
    };
    saveQueue = saveQueue.then(run, run);
    return saveQueue;
  }

  function setToggleVisual(widget, enabled) {
    const button = widget.querySelector(".dashboard-widget-toggle");
    if (!button) {
      return;
    }
    const icon = button.querySelector("i");
    button.setAttribute("aria-pressed", enabled ? "true" : "false");
    button.setAttribute("title", enabled
      ? (list.getAttribute("data-label-hide") || "")
      : (list.getAttribute("data-label-show") || ""));
    if (icon) {
      icon.classList.toggle("fa-eye", enabled);
      icon.classList.toggle("fa-eye-slash", !enabled);
    }
  }

  function syncChromeTitle(widget) {
    const custom = (widget.getAttribute("data-title") || "").trim();
    const fallback = list.getAttribute("data-default-pmb-title") || "";
    const titleEl = widget.querySelector(".dashboard-widget-chrome-title");
    if (titleEl) {
      titleEl.textContent = custom !== "" ? custom : fallback;
    }
  }

  function buildPaymentMethodBudgetWidget(instanceId) {
    const defaultTitle = list.getAttribute("data-default-pmb-title") || "";
    const methods = parseJsonAttr(list, "data-payment-methods", []).filter(function (method) {
      return method.enabled !== false;
    });
    const widget = document.createElement("div");
    widget.className = "dashboard-widget";
    widget.setAttribute("data-widget-id", "payment_method_budget");
    widget.setAttribute("data-enabled", "1");
    widget.setAttribute("data-has-content", "0");
    widget.setAttribute("data-instance-id", instanceId);
    widget.setAttribute("data-payment-method-ids", "[]");
    widget.setAttribute("data-title", "");
    widget.setAttribute("data-display-mode", "per_method");

    const methodChecks = methods.map(function (method) {
      const inputId = "pmb_" + instanceId + "_" + method.id;
      return '<div class="form-group-inline pmb-method-option">'
        + '<input type="checkbox" id="' + escapeAttr(inputId) + '" value="' + method.id + '">'
        + '<label for="' + escapeAttr(inputId) + '">' + escapeHtml(method.name) + '</label>'
        + '</div>';
    }).join("");

    const displayPerId = "pmb_display_per_" + instanceId;
    const displayCombinedId = "pmb_display_combined_" + instanceId;
    const displayName = "pmb_display_" + instanceId;

    widget.innerHTML = ''
      + '<div class="dashboard-widget-chrome">'
      +   '<div class="drag-icon" title="' + escapeAttr(list.getAttribute("data-label-reorder")) + '">'
      +     '<i class="fa-solid fa-grip-vertical" aria-hidden="true"></i>'
      +   '</div>'
      +   '<button type="button" class="dashboard-widget-toggle image-button medium"'
      +           ' title="' + escapeAttr(list.getAttribute("data-label-hide")) + '" aria-pressed="true">'
      +     '<i class="fa-solid fa-eye" aria-hidden="true"></i>'
      +   '</button>'
      +   '<button type="button" class="dashboard-widget-configure image-button medium"'
      +           ' title="' + escapeAttr(list.getAttribute("data-label-configure")) + '">'
      +     '<i class="fa-solid fa-gear" aria-hidden="true"></i>'
      +   '</button>'
      +   '<button type="button" class="dashboard-widget-remove image-button medium"'
      +           ' title="' + escapeAttr(list.getAttribute("data-label-remove")) + '">'
      +     '<i class="fa-solid fa-trash-can" aria-hidden="true"></i>'
      +   '</button>'
      +   '<span class="dashboard-widget-chrome-title">' + escapeHtml(defaultTitle) + '</span>'
      + '</div>'
      + '<div class="dashboard-widget-config" hidden>'
      +   '<div class="form-group">'
      +     '<label>' + escapeHtml(list.getAttribute("data-label-widget-title")) + '</label>'
      +     '<input type="text" class="pmb-title-input thin" maxlength="80" value=""'
      +            ' placeholder="' + escapeAttr(defaultTitle) + '">'
      +   '</div>'
      +   '<div class="form-group">'
      +     '<label>' + escapeHtml(list.getAttribute("data-label-display-mode")) + '</label>'
      +     '<div class="pmb-display-mode">'
      +       '<div class="form-group-inline">'
      +         '<input type="radio" class="pmb-display-mode-input" name="' + escapeAttr(displayName) + '"'
      +                ' id="' + escapeAttr(displayPerId) + '" value="per_method" checked>'
      +         '<label for="' + escapeAttr(displayPerId) + '">'
      +           escapeHtml(list.getAttribute("data-label-display-per-method")) + '</label>'
      +       '</div>'
      +       '<div class="form-group-inline">'
      +         '<input type="radio" class="pmb-display-mode-input" name="' + escapeAttr(displayName) + '"'
      +                ' id="' + escapeAttr(displayCombinedId) + '" value="combined">'
      +         '<label for="' + escapeAttr(displayCombinedId) + '">'
      +           escapeHtml(list.getAttribute("data-label-display-combined")) + '</label>'
      +       '</div>'
      +     '</div>'
      +     '<div class="settings-notes"><p><i class="fa-solid fa-circle-info"></i> '
      +       escapeHtml(list.getAttribute("data-label-display-info")) + '</p></div>'
      +   '</div>'
      +   '<div class="form-group">'
      +     '<label>' + escapeHtml(list.getAttribute("data-label-select-methods")) + '</label>'
      +     '<div class="pmb-method-checks">' + methodChecks + '</div>'
      +     '<div class="settings-notes"><p><i class="fa-solid fa-circle-info"></i> '
      +       escapeHtml(list.getAttribute("data-label-select-info")) + '</p></div>'
      +   '</div>'
      +   '<input type="button" class="button thin pmb-config-save" value="'
      +     escapeAttr(list.getAttribute("data-label-save")) + '">'
      + '</div>'
      + '<div class="dashboard-widget-body">'
      +   '<p class="dashboard-widget-empty">' + escapeHtml(list.getAttribute("data-label-empty")) + '</p>'
      + '</div>';

    return widget;
  }

  function escapeHtml(value) {
    return String(value == null ? "" : value)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;");
  }

  function escapeAttr(value) {
    return escapeHtml(value).replace(/'/g, "&#39;");
  }

  // Mirrors the server-side cap in wallos_normalize_dashboard_widget_layout_input().
  const MAX_PAYMENT_METHOD_BUDGETS = 10;

  function syncAddButton() {
    if (!addButton) {
      return;
    }
    const pmbCount = list.querySelectorAll('.dashboard-widget[data-widget-id="payment_method_budget"]').length;
    addButton.disabled = pmbCount >= MAX_PAYMENT_METHOD_BUDGETS;
  }

  function enterEditMode() {
    editing = true;
    lastSavedLayout = JSON.stringify(collectLayout());
    dashboard.classList.add("editing-widgets");
    if (addButton) {
      addButton.hidden = false;
      syncAddButton();
    }

    sortable = Sortable.create(list, {
      handle: ".drag-icon",
      ghostClass: "sortable-ghost",
      animation: 150,
      delay: 150,
      delayOnTouchOnly: true,
      touchStartThreshold: 5,
      // Scroll the page (and nested overflow containers) while dragging near edges.
      scroll: true,
      bubbleScroll: true,
      forceAutoScrollFallback: true,
      scrollSensitivity: 80,
      scrollSpeed: 25,
      onEnd: function () {
        // Skip the request when the drag didn't change the order.
        if (JSON.stringify(collectLayout()) !== lastSavedLayout) {
          saveLayout();
        }
      },
    });
  }

  function exitEditMode() {
    editing = false;
    dashboard.classList.remove("editing-widgets");
    if (addButton) {
      addButton.hidden = true;
    }
    list.querySelectorAll(".dashboard-widget-config").forEach(function (panel) {
      panel.hidden = true;
    });
    if (sortable) {
      sortable.destroy();
      sortable = null;
    }
  }

  editButton.addEventListener("click", function () {
    enterEditMode();
  });

  function readDisplayMode(widget) {
    const checked = widget.querySelector(".pmb-display-mode-input:checked");
    if (checked && checked.value === "combined") {
      return "combined";
    }
    return "per_method";
  }

  function applyOpenConfigPanels() {
    list.querySelectorAll(".dashboard-widget[data-widget-id='payment_method_budget']").forEach(function (widget) {
      const panel = widget.querySelector(".dashboard-widget-config");
      if (!panel || panel.hidden) {
        return;
      }
      const titleInput = widget.querySelector(".pmb-title-input");
      const title = titleInput ? titleInput.value.trim() : "";
      const ids = Array.from(widget.querySelectorAll(".pmb-method-checks input[type='checkbox']:checked"))
        .map(function (input) { return parseInt(input.value, 10); })
        .filter(function (id) { return id > 0; });
      widget.setAttribute("data-title", title);
      widget.setAttribute("data-payment-method-ids", JSON.stringify(ids));
      widget.setAttribute("data-display-mode", readDisplayMode(widget));
      syncChromeTitle(widget);
    });
  }

  doneButton.addEventListener("click", function () {
    applyOpenConfigPanels();
    saveLayout().then(function (data) {
      exitEditMode();
      if (data && data.success) {
        window.location.reload();
      }
    }).catch(function () {
      exitEditMode();
    });
  });

  if (addButton) {
    addButton.addEventListener("click", function () {
      if (!editing) {
        return;
      }
      const pmbCount = list.querySelectorAll('.dashboard-widget[data-widget-id="payment_method_budget"]').length;
      if (pmbCount >= MAX_PAYMENT_METHOD_BUDGETS) {
        return;
      }
      const widget = buildPaymentMethodBudgetWidget(newInstanceId());
      list.appendChild(widget);
      const config = widget.querySelector(".dashboard-widget-config");
      if (config) {
        config.hidden = false;
      }
      syncAddButton();
      // Not saved yet: the widget is persisted by its config "Save" button or by "Done".
    });
  }

  list.addEventListener("click", function (event) {
    const toggle = event.target.closest(".dashboard-widget-toggle");
    if (toggle && editing) {
      event.preventDefault();
      event.stopPropagation();

      const widget = toggle.closest(".dashboard-widget");
      if (!widget) {
        return;
      }

      const enabled = widget.getAttribute("data-enabled") !== "1";
      widget.setAttribute("data-enabled", enabled ? "1" : "0");
      setToggleVisual(widget, enabled);
      saveLayout().then(function (data) {
        if (!(data && data.success)) {
          widget.setAttribute("data-enabled", enabled ? "0" : "1");
          setToggleVisual(widget, !enabled);
        }
      });
      return;
    }

    const configure = event.target.closest(".dashboard-widget-configure");
    if (configure && editing) {
      event.preventDefault();
      event.stopPropagation();
      const widget = configure.closest(".dashboard-widget");
      const panel = widget && widget.querySelector(".dashboard-widget-config");
      if (panel) {
        panel.hidden = !panel.hidden;
      }
      return;
    }

    const remove = event.target.closest(".dashboard-widget-remove");
    if (remove && editing) {
      event.preventDefault();
      event.stopPropagation();
      const widget = remove.closest(".dashboard-widget");
      if (!widget || widget.getAttribute("data-widget-id") !== "payment_method_budget") {
        return;
      }
      const pmbCount = list.querySelectorAll('.dashboard-widget[data-widget-id="payment_method_budget"]').length;
      if (pmbCount <= 1) {
        showErrorMessage(list.getAttribute("data-cannot-remove-last") || translate("unknown_error"));
        return;
      }
      const confirmMsg = list.getAttribute("data-confirm-remove") || "";
      if (confirmMsg && !window.confirm(confirmMsg)) {
        return;
      }
      const nextSibling = widget.nextSibling;
      widget.remove();
      syncAddButton();
      saveLayout().then(function (data) {
        if (!(data && data.success)) {
          list.insertBefore(widget, nextSibling && nextSibling.parentNode === list ? nextSibling : null);
          syncAddButton();
        }
      });
      return;
    }

    const saveConfig = event.target.closest(".pmb-config-save");
    if (saveConfig && editing) {
      event.preventDefault();
      event.stopPropagation();
      const widget = saveConfig.closest(".dashboard-widget");
      if (!widget) {
        return;
      }
      const titleInput = widget.querySelector(".pmb-title-input");
      const title = titleInput ? titleInput.value.trim() : "";
      const ids = Array.from(widget.querySelectorAll(".pmb-method-checks input[type='checkbox']:checked"))
        .map(function (input) { return parseInt(input.value, 10); })
        .filter(function (id) { return id > 0; });

      widget.setAttribute("data-title", title);
      widget.setAttribute("data-payment-method-ids", JSON.stringify(ids));
      widget.setAttribute("data-display-mode", readDisplayMode(widget));
      syncChromeTitle(widget);

      const panel = widget.querySelector(".dashboard-widget-config");
      if (panel) {
        panel.hidden = true;
      }
      saveLayout();
    }
  });
}
