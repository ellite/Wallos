---
mode: testing
url: http://localhost:8282/
max_steps: 35
tags: [smoke, subscriptions]
---

# Add a subscription

## Register and log in on a fresh instance
Open http://localhost:8282/.
Fill in the registration form: Username "demo", First name "Demo", Last name "User", Email "demo@example.com", Password "Demo1234!", Confirm Password "Demo1234!", and choose "US Dollar" as the Main Currency.
Click "Register".
Type "demo" into the "Username" field and "Demo1234!" into the "Password" field, then click "Login".
Verify the page shows "You don't have any subscriptions yet".

## Add a monthly subscription
Click "Add first subscription".
In the "Add subscription" form, type "Netflix" into the "Subscription name" field and "15.99" into the "Price" field.
Click the button next to the "Next payment" date field to fill in the next payment date automatically.
Click "Save".
Verify the page shows "Subscription added successfully".

## Check the subscription list
Verify the subscription list shows "Netflix" with "Monthly" and "$15.99".
