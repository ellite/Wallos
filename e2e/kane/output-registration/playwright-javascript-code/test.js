import testmu, { resolveVar, setVar } from '@testmuai/playwright-bindings';
import { expect } from '@playwright/test';

testmu.configure({
  "build": "474dee07-ef7c-4bc1-afa3-6dfd08dc077c",
  "name": "User Registration and Login Verification",
  "tcId": "TC-187",
  "network": (process.env.NETWORK ?? "false").toLowerCase() === "true",
  "autoHealVersion": "AH2",
  "defaultActionTimeoutMs": 10000,
  "defaultNavigationTimeoutMs": 60000,
  "kaneRunV4": true
});

testmu.test("User Registration and Login Verification", async (page) => {
  let element_0, element_1, element_2, element_3, element_4, element_5, element_6, element_9, element_7, element_8, element_12;
  await testmu.step("Navigate to http://localhost:8282/", { instructionId: "a9482486-b665-4705-b212-6267f54d611a" }, async () => {
    await page.goto("http://localhost:8282/");
  });
  
  await testmu.step("Reading the account creation requirement message", async () => {
    await page.waitForLoadState("domcontentloaded", { timeout: 5000 });
    setVar("account_creation_required_message", await testmu.textualAnalyzer(page, { wrappedJs: "(els) => {\n  const __m = {4: 0};\n  const el = (i) => els[__m[i]];\n  const __v = (el(4).textContent);\n  return (typeof __v === 'boolean' ? String(__v) : __v);\n}", locators: ["internal:text=\"You need to create an account\"i"], query: "the account creation requirement message on the registration page", expectedValue: "You need to create an account before you're able to login", needsUnitConversion: false, operator: "equals", transforms: ["strip"], condition: "The page shows \"You need to create an account before you're able to login\"." }));
  });
  
  await testmu.step("Assertion check", { instructionId: "444d397f-fef7-42e3-8fba-a43cbd29775a" }, async () => {
    await testmu.verifyAssertion(page, "Assertion check", {"operator": ["equals"], "assertion_operands": [], "left_operand": null, "right_operand": null, "operands": [], "sub_results": [{"description": "The page shows \"You need to create an account before you're able to login\".", "passed": true, "operator": "equals", "transforms": ["strip"], "json_path": null, "expected": "You need to create an account before you're able to login", "extracted_value": "{{account_creation_required_message}}", "store_key": "account_creation_required_message", "variable_refs": {"{{account_creation_required_message}}": "\n                    You need to create an account before you're able to login                "}}], "sub_checks": [{"description": "The page shows \"You need to create an account before you're able to login\".", "store_key": "account_creation_required_message", "expected_value": "You need to create an account before you're able to login", "extracted_value": "{{account_creation_required_message}}", "operator": "equals", "transforms": ["strip"]}], "composite_operator": "and", "claim": "the page shows \"You need to create an account before you're able to login\""});
  });
  
  await testmu.step("Waiting for the form to finish loading and the cursor to focus", { instructionId: "955997ac-7420-441b-ad16-10928f12ecb5" }, async () => {
    await page.waitForTimeout(1000);
  });
  
  await testmu.step("Type \"demo\" into \"Username:\"", { instructionId: "bbac5b1d-d483-4693-88ff-497f95790607" }, async () => {
    element_0 = page.locator("internal:role=textbox[name=\"Username:\"i]");
    await element_0.click();
    await element_0.fill("demo");
  });
  
  await testmu.step("Type \"Demo\" into \"First name:\"", { instructionId: "89bc3752-eafe-4057-83c4-491deb437f6d" }, async () => {
    element_1 = page.locator("internal:role=textbox[name=\"First name:\"i]");
    await element_1.click();
    await element_1.fill("Demo");
  });
  
  await testmu.step("Type \"User\" into \"Last name:\"", { instructionId: "fe1ba11b-f54c-4b4a-957a-4bbbf76cb82a" }, async () => {
    element_2 = page.locator("internal:role=textbox[name=\"Last name:\"i]");
    await element_2.click();
    await element_2.fill("User");
  });
  
  await testmu.step("Type \"demo@example.com\" into \"Email:\"", { instructionId: "192b5fdd-0c1e-4291-bf04-2c8145929544" }, async () => {
    element_3 = page.locator("internal:role=textbox[name=\"Email:\"i]");
    await element_3.click();
    await element_3.fill("demo@example.com");
  });
  
  await testmu.step("Type \"Demo1234!\" into \"Password:\"", { instructionId: "bc001a81-05cb-4a27-b714-0f08000f0590" }, async () => {
    element_4 = page.locator("internal:role=textbox[name=\"Password:\"s]");
    await element_4.click();
    await element_4.fill("Demo1234!");
  });
  
  await testmu.step("Type \"Demo1234!\" into \"Confirm Password:\"", { instructionId: "9cca80cd-a933-4999-a819-097bdc145b38" }, async () => {
    element_5 = page.locator("internal:role=textbox[name=\"Confirm Password:\"i]");
    await element_5.click();
    await element_5.fill("Demo1234!");
  });
  
  await testmu.step("Select \"US Dollar\" in \"Main Currency:\"", { instructionId: "ba90c5e8-3e4f-4615-95ff-ca3f28155a90" }, async () => {
    element_6 = page.locator("internal:label=\"Main Currency:\"i");
    await element_6.selectOption();
  });
  
  await testmu.step("Waiting briefly after completing the registration form", { instructionId: "3043307c-e9c5-4232-9122-8bece345a6a3" }, async () => {
    await page.waitForTimeout(500);
  });
  
  await testmu.step("Clicking the Register submit button at the bottom of the registration form", { instructionId: "05329557-c144-41b5-b5bd-75f1c41ac8f6" }, async () => {
    element_9 = page.locator("internal:role=button[name=\"Register\"i]");
    await element_9.click();
  });
  
  await testmu.step("Waiting for the login page to finish loading after registration", { instructionId: "17322049-ce62-4a16-97f4-321317440da1" }, async () => {
    await page.waitForTimeout(1000);
  });
  
  await testmu.step("Reading the registration success message", async () => {
    await page.waitForLoadState("domcontentloaded", { timeout: 5000 });
    setVar("registration_successful_message", await testmu.textualAnalyzer(page, { wrappedJs: "(els) => {\n  const __m = {15: 0};\n  const el = (i) => els[__m[i]];\n  const __v = (el(15).textContent);\n  return (typeof __v === 'boolean' ? String(__v) : __v);\n}", locators: ["internal:text=\"Registration successful\"i"], query: "the registration success message shown on the login page", expectedValue: "Registration successful", needsUnitConversion: false, operator: "equals", transforms: ["strip"], condition: "The login page shows \"Registration successful\"." }));
  });
  
  await testmu.step("Assertion check", { instructionId: "9f7311a5-4a17-440d-9e04-da329d6187f5" }, async () => {
    await testmu.verifyAssertion(page, "Assertion check", {"operator": ["equals"], "assertion_operands": [], "left_operand": null, "right_operand": null, "operands": [], "sub_results": [{"description": "The login page shows \"Registration successful\".", "passed": true, "operator": "equals", "transforms": ["strip"], "json_path": null, "expected": "Registration successful", "extracted_value": "{{registration_successful_message}}", "store_key": "registration_successful_message", "variable_refs": {"{{registration_successful_message}}": "Registration successful"}}], "sub_checks": [{"description": "The login page shows \"Registration successful\".", "store_key": "registration_successful_message", "expected_value": "Registration successful", "extracted_value": "{{registration_successful_message}}", "operator": "equals", "transforms": ["strip"]}], "composite_operator": "and", "claim": "the login page shows \"Registration successful\""});
  });
  
  await testmu.step("Waiting for queued login steps to complete", { instructionId: "bbbc5bbc-dbd0-4b9b-a77c-44f6652197fe" }, async () => {
    await page.waitForTimeout(1000);
  });
  
  await testmu.step("Type \"demo\" into \"Username:\"", { instructionId: "852ae3c2-c0b3-4284-ae3d-21a1302293a3" }, async () => {
    element_7 = page.locator("internal:role=textbox[name=\"Username:\"i]");
    await element_7.click();
    await element_7.fill("demo");
  });
  
  await testmu.step("Type \"Demo1234!\" into \"Password:\"", { instructionId: "8b3ec662-7f6e-4965-b2b8-2428f4eb172c" }, async () => {
    element_8 = page.locator("internal:role=textbox[name=\"Password:\"i]");
    await element_8.click();
    await element_8.fill("Demo1234!");
  });
  
  await testmu.step("Clicking the Login submit button", { instructionId: "0df018b2-20db-47a1-8af4-1b7a1a54d133" }, async () => {
    element_12 = page.locator("internal:role=button[name=\"Login\"i]");
    await element_12.click();
  });
  
  await testmu.step("Waiting for the subscriptions page to finish loading", { instructionId: "8266d495-f5bf-4996-aaf9-94234ab585a8" }, async () => {
    await page.waitForTimeout(1000);
  });
  
  await testmu.step("Reading the current page URL", { instructionId: "b72a844e-1f3a-4fc1-b08b-01ea9a698e50" }, async () => {
    setVar("subscriptions_url_check", page.url());
  });
  
  await testmu.step("Reading the subscriptions empty-state message", async () => {
    await page.waitForLoadState("domcontentloaded", { timeout: 5000 });
    setVar("subscriptions_empty_message_check", await testmu.textualAnalyzer(page, { wrappedJs: "(els) => {\n  const __m = {10: 0};\n  const el = (i) => els[__m[i]];\n  const __v = (el(10).textContent);\n  return (typeof __v === 'boolean' ? String(__v) : __v);\n}", locators: ["internal:text=\"You don't have any\"i"], query: "the subscriptions empty-state message", expectedValue: "You don't have any subscriptions yet", needsUnitConversion: false, operator: "equals", transforms: ["strip"], condition: "The page shows \"You don't have any subscriptions yet\"." }));
  });
  
  await testmu.step("Assertion check", { instructionId: "03b8f9fc-1d9a-414c-8ba7-c48ef47903c9" }, async () => {
    await testmu.verifyAssertion(page, "Assertion check", {"operator": ["equals"], "assertion_operands": [], "left_operand": null, "right_operand": null, "operands": [], "sub_results": [{"description": "The URL contains \"subscriptions.php\".", "passed": true, "operator": "contains", "transforms": [], "json_path": null, "expected": "subscriptions.php", "extracted_value": "{{subscriptions_url_check}}", "store_key": "subscriptions_url_check", "variable_refs": {"{{subscriptions_url_check}}": "http://localhost:8282/subscriptions.php"}}, {"description": "The page shows \"You don't have any subscriptions yet\".", "passed": true, "operator": "equals", "transforms": ["strip"], "json_path": null, "expected": "You don't have any subscriptions yet", "extracted_value": "{{subscriptions_empty_message_check}}", "store_key": "subscriptions_empty_message_check", "variable_refs": {"{{subscriptions_empty_message_check}}": "\n          You don't have any subscriptions yet        "}}], "sub_checks": [{"description": "The URL contains \"subscriptions.php\".", "store_key": "subscriptions_url_check", "expected_value": "subscriptions.php", "extracted_value": "{{subscriptions_url_check}}", "operator": "contains", "transforms": []}, {"description": "The page shows \"You don't have any subscriptions yet\".", "store_key": "subscriptions_empty_message_check", "expected_value": "You don't have any subscriptions yet", "extracted_value": "{{subscriptions_empty_message_check}}", "operator": "equals", "transforms": ["strip"]}], "composite_operator": "and", "claim": "the URL contains \"subscriptions.php\" and the page shows \"You don't have any subscriptions yet\""});
  });
});

testmu.run();