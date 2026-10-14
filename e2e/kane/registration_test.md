---
mode: testing
url: http://localhost:8282/
max_steps: 25
tags: [smoke, auth]
---

# Register the first account and log in

## Register on a fresh instance
Open http://localhost:8282/.
Verify the page shows "You need to create an account before you're able to login".
Fill in the registration form: Username "demo", First name "Demo", Last name "User", Email "demo@example.com", Password "Demo1234!", Confirm Password "Demo1234!", and choose "US Dollar" as the Main Currency.
Click "Register".
Verify the login page shows "Registration successful".

## Log in with the new account
Type "demo" into the "Username" field and "Demo1234!" into the "Password" field, then click "Login".
Verify the URL contains "subscriptions.php" and the page shows "You don't have any subscriptions yet".
