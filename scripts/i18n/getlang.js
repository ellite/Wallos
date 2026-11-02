function translate(key) {
    if (i18n[key]) {
        return i18n[key];
    } else {
        return "[Translation Missing]";
    }
}