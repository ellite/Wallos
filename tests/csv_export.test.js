// Run with: node --test tests/csv_export.test.js
const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');

const source = fs.readFileSync(path.join(__dirname, '../scripts/profile.js'), 'utf8');
const match = source.match(/function csvDataUri\(csv\) \{[\s\S]*?\n\}/);
assert.ok(match, 'csvDataUri found in profile.js');
const context = vm.createContext({});
vm.runInContext(match[0], context);

test('CSV export is prefixed with a UTF-8 BOM so Excel does not show £ as Â£', () => {
  const uri = context.csvDataUri('name,price\nTest,£29.84');
  assert.ok(uri.startsWith('data:text/csv;charset=utf-8,%EF%BB%BF'), uri);
  const decoded = decodeURIComponent(uri.split(',').slice(1).join(','));
  assert.equal(decoded, '﻿name,price\nTest,£29.84');
});

test('exportAsCsv builds its link with csvDataUri', () => {
  assert.match(source, /setAttribute\('href', csvDataUri\(csvWithHeader\)\)/);
});
