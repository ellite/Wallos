function setCookie(name, value, days) {
    var expires = "";
    if (days) {
        var date = new Date();
        date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
        expires = "; expires=" + date.toUTCString();
    }
    document.cookie = name + "=" + value + expires;
}

function storeFormFieldValue(fieldId) {
    var fieldElement = document.getElementById(fieldId);
    if (fieldElement) {
        localStorage.setItem(fieldId, fieldElement.value);
    }
}

function storeFormFields() {
    storeFormFieldValue('username');
    storeFormFieldValue('email');
    storeFormFieldValue('password');
    storeFormFieldValue('confirm_password');
    storeFormFieldValue('currency');
}

function restoreFormFieldValue(fieldId) {
    var fieldElement = document.getElementById(fieldId);
    if (localStorage.getItem(fieldId)) {
        fieldElement.value = localStorage.getItem(fieldId) || '';
    }
}

function restoreFormFields() {
    restoreFormFieldValue('username');
    restoreFormFieldValue('email');
    restoreFormFieldValue('password');
    restoreFormFieldValue('confirm_password');
    restoreFormFieldValue('currency');
}

function removeFromStorage() {
    localStorage.removeItem('username');
    localStorage.removeItem('email');
    localStorage.removeItem('password');
    localStorage.removeItem('confirm_password');
    localStorage.removeItem('currency');
}

function changeLanguage(selectedLanguage) {
    storeFormFields();
    setCookie("language", selectedLanguage, 365);
    location.reload();
}

function runDatabaseMigration() {
    let url = "endpoints/db/migrate.php";
    fetch(url)
    .then(response => {
        if (!response.ok) {
            throw new Error(translate('network_response_error'));
        }
    });
}

window.onload = function () {
    restoreFormFields();
    removeFromStorage();
    runDatabaseMigration();
};
