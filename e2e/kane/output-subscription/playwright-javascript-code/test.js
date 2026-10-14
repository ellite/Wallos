import testmu, { resolveVar, setVar } from '@testmuai/playwright-bindings';
import { expect } from '@playwright/test';

testmu.configure({
  "build": "f8d11c5e-f49d-4d36-9f60-0d046f31a294",
  "name": "Register and Add Subscription",
  "tcId": "TC-190",
  "network": (process.env.NETWORK ?? "false").toLowerCase() === "true",
  "autoHealVersion": "AH2",
  "defaultActionTimeoutMs": 10000,
  "defaultNavigationTimeoutMs": 60000,
  "kaneRunV4": true
});

testmu.test("Register and Add Subscription", async (page) => {
  let element_0, element_1, element_2, element_3, element_4, element_5, element_6, element_11, element_7, element_8, element_14, element_15, element_9, element_10, element_18, element_19;
  await testmu.step("Navigate to http://localhost:8282/", { instructionId: "aa293674-3312-4d13-9016-41518dbe0982" }, async () => {
    await page.goto("http://localhost:8282/");
  });
  
  await testmu.step("Waiting for the signup form to be ready", { instructionId: "141876fc-ae41-45c6-9d56-9f1208dc224d" }, async () => {
    await page.waitForTimeout(1000);
  });
  
  await testmu.step("Type \"demo\" into \"Username:\"", { instructionId: "f9b13c81-dea9-4f77-b2ba-c1bd95b3e604" }, async () => {
    element_0 = page.locator("internal:role=textbox[name=\"Username:\"i]");
    await element_0.click();
    await element_0.fill("demo");
  });
  
  await testmu.step("Type \"Demo\" into \"First name:\"", { instructionId: "dd42e269-257c-47ae-bb49-455247ef99fd" }, async () => {
    element_1 = page.locator("internal:role=textbox[name=\"First name:\"i]");
    await element_1.click();
    await element_1.fill("Demo");
  });
  
  await testmu.step("Type \"User\" into \"Last name:\"", { instructionId: "8a46d418-7b49-484f-a3b1-7d1f2f066673" }, async () => {
    element_2 = page.locator("internal:role=textbox[name=\"Last name:\"i]");
    await element_2.click();
    await element_2.fill("User");
  });
  
  await testmu.step("Type \"demo@example.com\" into \"Email:\"", { instructionId: "885ea531-b689-4d4c-986d-0743f4a8cecc" }, async () => {
    element_3 = page.locator("internal:role=textbox[name=\"Email:\"i]");
    await element_3.click();
    await element_3.fill("demo@example.com");
  });
  
  await testmu.step("Type \"Demo1234!\" into \"Password:\"", { instructionId: "f3ae848a-231a-43a9-8aac-d7a4eef86dcd" }, async () => {
    element_4 = page.locator("internal:role=textbox[name=\"Password:\"s]");
    await element_4.click();
    await element_4.fill("Demo1234!");
  });
  
  await testmu.step("Type \"Demo1234!\" into \"Confirm Password:\"", { instructionId: "fcfbc192-1f3a-44de-8dea-5cac267bc6b6" }, async () => {
    element_5 = page.locator("internal:role=textbox[name=\"Confirm Password:\"i]");
    await element_5.click();
    await element_5.fill("Demo1234!");
  });
  
  await testmu.step("Select \"US Dollar\" in \"Main Currency:\"", { instructionId: "aa532537-6934-4530-90d8-776f35cae2a5" }, async () => {
    element_6 = page.locator("internal:label=\"Main Currency:\"i");
    await element_6.selectOption();
  });
  
  await testmu.step("Clicking the Register submit button in the registration form", { instructionId: "8e6a6352-3195-4630-9535-6849abb310e5" }, async () => {
    element_11 = page.locator("internal:role=button[name=\"Register\"i]");
    await element_11.click();
  });
  
  await testmu.step("Waiting for the plan to start typing Username and Password", { instructionId: "b5429d08-fbef-4efa-a7b8-94e7f0966ef4" }, async () => {
    await page.waitForTimeout(1000);
  });
  
  await testmu.step("Type \"demo\" into \"Username:\"", { instructionId: "92c16d7d-b0ee-49b7-974e-969423b25f71" }, async () => {
    element_7 = page.locator("internal:role=textbox[name=\"Username:\"i]");
    await element_7.click();
    await element_7.fill("demo");
  });
  
  await testmu.step("Type \"Demo1234!\" into \"Password:\"", { instructionId: "a9e2df76-1486-4f34-8d6d-42c23e23e7a2" }, async () => {
    element_8 = page.locator("internal:role=textbox[name=\"Password:\"i]");
    await element_8.click();
    await element_8.fill("Demo1234!");
  });
  
  await testmu.step("Clicking the Login submit button", { instructionId: "1cac365b-be30-40e6-83ff-bb3dcdf4fb97" }, async () => {
    element_14 = page.locator("internal:role=button[name=\"Login\"i]");
    await element_14.click();
  });
  
  await testmu.step("Waiting for the subscriptions page to finish loading", { instructionId: "f71fc254-c485-4cbf-8b02-a2ddaa4253dd" }, async () => {
    await page.waitForTimeout(1000);
  });
  
  await testmu.step("Reading the empty subscriptions message", async () => {
    await page.waitForLoadState("domcontentloaded", { timeout: 5000 });
    setVar("subscriptions_empty_message", await testmu.textualAnalyzer(page, { wrappedJs: "(els) => {\n  const __m = {10: 0};\n  const el = (i) => els[__m[i]];\n  const __v = (el(10).textContent);\n  return (typeof __v === 'boolean' ? String(__v) : __v);\n}", locators: ["internal:text=\"You don't have any\"i"], query: "the empty subscriptions message", expectedValue: "You don't have any subscriptions yet", needsUnitConversion: false, operator: "equals", transforms: ["strip"], condition: "The page shows \"You don't have any subscriptions yet\"." }));
  });
  
  await testmu.step("Assertion check", { instructionId: "60304d07-ea11-4c37-a925-5974f63f95cb" }, async () => {
    await testmu.verifyAssertion(page, "Assertion check", {"operator": ["equals"], "assertion_operands": [], "left_operand": null, "right_operand": null, "operands": [], "sub_results": [{"description": "The page shows \"You don't have any subscriptions yet\".", "passed": true, "operator": "equals", "transforms": ["strip"], "json_path": null, "expected": "You don't have any subscriptions yet", "extracted_value": "{{subscriptions_empty_message}}", "store_key": "subscriptions_empty_message", "variable_refs": {"{{subscriptions_empty_message}}": "\n          You don't have any subscriptions yet        "}}], "sub_checks": [{"description": "The page shows \"You don't have any subscriptions yet\".", "store_key": "subscriptions_empty_message", "expected_value": "You don't have any subscriptions yet", "extracted_value": "{{subscriptions_empty_message}}", "operator": "equals", "transforms": ["strip"]}], "composite_operator": "and", "claim": "the page shows \"You don't have any subscriptions yet\""});
  });
  
  await testmu.step("Clicking Add first subscription button", { instructionId: "0d00c1be-a780-4318-921c-f0c345c38198" }, async () => {
    element_15 = page.locator("internal:role=button[name=\"\uf055 Add first subscription\"i]");
    await element_15.click();
  });
  
  await testmu.step("Waiting for queued plan steps to complete", { instructionId: "3cff261b-f912-4245-8e49-b97a9ebffecc" }, async () => {
    await page.waitForTimeout(1000);
  });
  
  await testmu.step("Type \"Netflix\" into \"Subscription name\"", { instructionId: "c7b79010-b37d-47ea-89aa-1126c3570bc6" }, async () => {
    element_9 = page.locator("internal:role=textbox[name=\"Subscription name\"i]");
    await element_9.click();
    await element_9.fill("Netflix");
  });
  
  await testmu.step("Type \"15.99\" into \"Price\"", { instructionId: "765f303d-7187-481c-8afd-a2f06b225ab4" }, async () => {
    element_10 = page.locator("internal:attr=[placeholder=\"Price\"i]");
    await element_10.click();
    await element_10.fill("15.99");
  });
  
  await testmu.step("Click \"\ue2ca\"", { instructionId: "97ecd1d6-d75c-4473-b2b2-ea1a8c33bcc0" }, async () => {
    element_18 = page.locator("internal:attr=[title=\"Calculate Next Payment Date\"i] >> nth=0");
    await element_18.click();
  });
  
  await testmu.step("Click \"Save\"", { instructionId: "a8ca5a72-2d86-4532-a989-ae63fdeb3d1b" }, async () => {
    element_19 = page.locator("internal:role=button[name=\"Save\"i]");
    await element_19.click();
  });
  
  await testmu.step("whether the subscription-added success message is visible on the page", { instructionId: "f5fb8d38-eb91-497a-a871-5e6b5d5af92e" }, async () => {
    setVar("subscription_added_success_message", await testmu.visionQuery(page, "whether the subscription-added success message is visible on the page", ""));
  });
  
  await testmu.step("Assertion check", { instructionId: "ddd11923-8fe2-4202-a3c2-a325fadc2d2b" }, async () => {
    await testmu.verifyAssertion(page, "Assertion check", {"operator": ["equals"], "assertion_operands": [], "left_operand": null, "right_operand": null, "operands": [], "sub_results": [{"description": "The page shows \"Subscription added successfully\".", "passed": true, "operator": "equals", "transforms": [], "json_path": null, "expected": "true", "extracted_value": "{{subscription_added_success_message}}", "store_key": "subscription_added_success_message", "variable_refs": {"{{subscription_added_success_message}}": "true"}}], "sub_checks": [{"description": "The page shows \"Subscription added successfully\".", "store_key": "subscription_added_success_message", "expected_value": "true", "extracted_value": "{{subscription_added_success_message}}", "operator": "equals", "transforms": []}], "composite_operator": "and", "claim": "the page shows \"Subscription added successfully\""});
  });
  
  await testmu.step("Checking the Netflix subscription billing period and price", async () => {
    await page.waitForLoadState("domcontentloaded", { timeout: 5000 });
    setVar("subscription_list_netflix_monthly_price_check", await testmu.textualAnalyzer(page, { wrappedJs: "(els) => {\n  const __m = {18: 0};\n  const el = (i) => els[__m[i]];\n  const __v = ((() => { const text = el(18).innerText; return text.includes('Netflix') && text.includes('Monthly') && text.includes('$15.99'); })());\n  return (typeof __v === 'boolean' ? String(__v) : __v);\n}", locators: ["internal:text=\"Netflix Monthly Nov 14 $15.99\"i"], query: "whether the Netflix subscription entry shows a Monthly billing period and a $15.99 price", expectedValue: "true", needsUnitConversion: false, operator: "equals", transforms: [], condition: "The subscription list shows \"Netflix\" with \"Monthly\" and \"$15.99\"." }));
  });
  
  await testmu.step("Assertion check", { instructionId: "665b4a82-f633-4ee7-8992-7dc2e83af6dd" }, async () => {
    await testmu.verifyAssertion(page, "Assertion check", {"operator": ["equals"], "assertion_operands": [], "left_operand": null, "right_operand": null, "operands": [], "sub_results": [{"description": "The subscription list shows \"Netflix\" with \"Monthly\" and \"$15.99\".", "passed": true, "operator": "equals", "transforms": [], "json_path": null, "expected": "true", "extracted_value": "{{subscription_list_netflix_monthly_price_check}}", "store_key": "subscription_list_netflix_monthly_price_check", "variable_refs": {"{{subscription_list_netflix_monthly_price_check}}": "true"}}], "sub_checks": [{"description": "The subscription list shows \"Netflix\" with \"Monthly\" and \"$15.99\".", "store_key": "subscription_list_netflix_monthly_price_check", "expected_value": "true", "extracted_value": "{{subscription_list_netflix_monthly_price_check}}", "operator": "equals", "transforms": []}], "composite_operator": "and", "claim": "the subscription list shows \"Netflix\" with \"Monthly\" and \"$15.99\""});
  });
});

testmu.run();