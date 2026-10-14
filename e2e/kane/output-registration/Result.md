---
test: ../registration_test.md
status: passed
started: 2026-10-14T08:32:09.527Z
duration_s: 99
session_id: 5b8d2229-7e61-4771-b649-219db14cd113
---

# Register the first account and log in — Result

## Register on a fresh instance ✓ passed (57.8s)
md5: dd41a678255875dad080f440ec11bd05
Open http://localhost:8282/.
Verify the page shows "You need to create an account before you're able to login".
Fill in the registration form: Username "demo", First name "Demo", Last name "User", Email "demo@example.com", Password "Demo1234!", Confirm Password "Demo1234!", and choose "US Dollar" as the Main Currency.
Click "Register".
Verify the login page shows "Registration successful".

## Log in with the new account ✓ passed (34.9s)
md5: 48067906acdf00ec326bc34c2b69785b
Type "demo" into the "Username" field and "Demo1234!" into the "Password" field, then click "Login".
Verify the URL contains "subscriptions.php" and the page shows "You don't have any subscriptions yet".
