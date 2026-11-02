// Run with: node --test tests/theme_body_classes.test.js
const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');

const source = fs.readFileSync(path.join(__dirname, '../scripts/theme.js'), 'utf8');

for (const direction of ['ltr', 'rtl']) {
  for (const prefersDark of [false, true]) {
    test(`theme changes preserve ${direction} and mobile navigation (system dark: ${prefersDark})`, async () => {
      const classes = new Set([prefersDark ? 'automatic' : 'light', direction, 'mobile-navigation', 'custom-class']);
      const body = {
        get className() { return [...classes].join(' '); },
        set className(value) { classes.clear(); value.split(/\s+/).forEach(c => classes.add(c)); },
        classList: {
          remove: (...names) => names.forEach(name => classes.delete(name)),
          add: (...names) => names.forEach(name => classes.add(name)),
        },
      };
      const buttons = Object.fromEntries(['#theme-light', '#theme-dark', '#theme-automatic'].map(id => [
        id, { disabled: false, classList: { remove() {}, add() {} } },
      ]));
      const stylesheet = { disabled: true };
      const requests = [];
      const context = vm.createContext({
        document: {
          body,
          cookie: '',
          querySelector: id => id === '#dark-theme' ? stylesheet : buttons[id],
        },
        window: { csrfToken: 'synthetic-token', matchMedia: () => ({ matches: prefersDark }) },
        fetch: async (url, options) => {
          requests.push({ url, options });
          return { json: async () => ({ success: true, message: 'Success' }) };
        },
        showSuccessMessage() {},
        showErrorMessage() { assert.fail('unexpected error'); },
      });
      vm.runInContext(source, context);

      // Exercise repeated changes without reloading, including Automatic.
      for (const choice of ['1', '0', '2', '1', '2', '0']) {
        context.setDarkTheme(choice);
        await new Promise(resolve => setImmediate(resolve));
        const expected = choice === '2' ? (prefersDark ? 'dark' : 'light') : (choice === '1' ? 'dark' : 'light');
        assert.deepEqual([...classes].sort(), [direction, 'mobile-navigation', 'custom-class', expected].sort());
        assert.equal(stylesheet.disabled, expected === 'light');
        assert.match(context.document.cookie, choice === '2' ? /inUseTheme=/ : /theme=/);
        const request = requests.at(-1);
        assert.equal(request.url, 'endpoints/settings/theme.php');
        assert.equal(request.options.headers['X-CSRF-Token'], 'synthetic-token');
        assert.equal(JSON.parse(request.options.body).theme, choice);
        assert.ok(Object.values(buttons).every(button => !button.disabled));
      }
    });
  }
}
