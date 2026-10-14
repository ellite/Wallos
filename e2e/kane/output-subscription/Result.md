---
test: ../subscription_test.md
status: passed
started: 2026-10-14T08:37:44.485Z
duration_s: 147
session_id: 6e8c4d3c-fd53-4fd3-8744-cf9da5d6a5fd
---

# Add a subscription — Result

## Register and log in on a fresh instance ✓ passed (63.7s)
md5: 94455a99cfa59ca417585f0eb2576722
Open http://localhost:8282/.
Fill in the registration form: Username "demo", First name "Demo", Last name "User", Email "demo@example.com", Password "Demo1234!", Confirm Password "Demo1234!", and choose "US Dollar" as the Main Currency.
Click "Register".
Type "demo" into the "Username" field and "Demo1234!" into the "Password" field, then click "Login".
Verify the page shows "You don't have any subscriptions yet".

## Add a monthly subscription ✓ passed (57.8s)
md5: 776ebef88e516887beacb430545e0ad9
Click "Add first subscription".
In the "Add subscription" form, type "Netflix" into the "Subscription name" field and "15.99" into the "Price" field.
Click the button next to the "Next payment" date field to fill in the next payment date automatically.
Click "Save".
Verify the page shows "Subscription added successfully".

## Check the subscription list ✓ passed (19.3s)
md5: 5297a13947f3958fa75dd3a604e293f6
Verify the subscription list shows "Netflix" with "Monthly" and "$15.99".
