<?php

$i18n = [
    // Registration page
    "create_account"  => "You need to create an account before you're able to login",
    'username'        => "Username",
    'password'        => "Password",
    "email"           => "Email",
    "confirm_password" => "Confirm Password",
    "main_currency"   => "Main Currency",
    "language"        => "Language",
    "passwords_dont_match" => "Passwords do not match",
    "registration_failed" => "Registration failed, please try again.",
    "register"        => "Register",
    // Login Page
    'please_login'    => "Please login",
    'stay_logged_in'  => "Stay logged in (30 days)",
    'login'           => "Login",
    'login_failed'    => "Login details are incorrect",
    // Header
    'subscriptions'   => "Subscriptions",
    'stats'           => "Statistics",
    'settings'        => "Settings",
    'about'           => "About",
    'logout'          => "Logout",
    // Subscriptions page
    "subscription"    => "Subscription",
    "no_subscriptions_yet" => "You don't have any subscriptions yet",
    "add_first_subscription" => "Add first subscription",
    'new_subscription' => "New Subscription",
    'sort'            => "Sort",
    'name'            => "Name",
    'last_added'      => "Last Added",
    'price'           => "Price",
    'next_payment'    => "Next Payment",
    'activated'       => "Active Subscription",
    'member'          => "Member",
    'category'        => "Category",
    'payment_method'  => "Payment Method",
    "Daily"           => "Daily",
    "Weekly"          => "Weekly",
    "Monthly"         => "Monthly",
    "Yearly"          => "Yearly",
    "days"            => "days",
    "weeks"           => "weeks",
    "months"          => "months",
    "years"           => "years",
    "external_url"    => "Visit External URL",
    "empty_page"      => "Empty Page",
    // Subscription form
    "add_subscription" => "Add subscription",
    "edit_subscription" => "Edit subscription",
    "subscription_name" => "Subscription name",
    "logo_preview"    => "Logo Preview",
    "search_logo"     => "Search logo on the web",
    "web_search"      => "Web search",
    "currency"        => "Currency",
    "billing_cycle"   => "Billing Cycle",
    "frequency"       => "Frequency",
    "cycle"           => "Cycle",
    "next_payment"    => "Next Payment",
    "payment_method"  => "Payment Method",
    "no_category"     => "No category",
    "paid_by"         => "Paid by",
    "url"             => "URL",
    "notes"           => "Notes",
    "enable_notifications" => "Enable Notifications for this subscription",
    "delete"          => "Delete",
    "cancel"          => "Cancel",
    "upload_logo"     => "Upload Logo",
    // Statistics page
    'general_statistics' => "General Statistics",
    'active_subscriptions' => "Active Subscriptions",
    'inactive_subscriptions' => "Inactive Subscriptions",
    'monthly_cost'    => "Monthly Cost",
    'yearly_cost'     => "Yearly Cost",
    'average_monthly' => "Average Monthly Subscription Cost",
    'most_expensive'  => "Most Expensive Subscription Cost",
    'amount_due'      => "Amount due this month",
    'split_views'     => "Split Views",
    'category_split'  => "Category Split",
    'household_split' => "Household Split",
    // About page
    'about_and_credits' => "About and Credits",
    'license'         => "License",
    'issues_and_requests' => "Issues and Requests",
    'the_author'      => "The author",
    'icons'           => "Icons",
    'payment_icons'   => "Payment Icons",
    // Settings page
    'user_details'    => "User Details",
    "household"        => "Household",
    "save_member"     => "Save Member",
    "delete_member"   => "Delete Member",
    "cant_delete_member" => "Can't delete main member",
    "cant_delete_member_in_use" => "Can't delete member in use in subscription",
    "notifications"   => "Notifications",
    "enable_email_notifications" => "Enable email notifications",
    "notify_me"       => "Notify me",
    "day_before"      => "day before",
    "days_before"     => "days before",
    "smtp_address"    => "SMTP Address",
    "port"            => "Port",
    "smtp_username"   => "SMTP Username",
    "smtp_password"   => "SMTP Password",
    "from_email"      => "From email (Optional)",
    "smtp_info"       => "SMTP Password is transmitted and stored in plaintext. For security, please create an account just for this.",
    "categories"      => "Categories",
    "save_category"   => "Save Category",
    "delete_category" => "Delete Category",
    "cant_delete_category_in_use" => "Can't delete category in use in subscription",
    "currencies"      => "Currencies",
    "save_currency"   => "Save currency",
    "delete_currency" => "Delete currency",
    "cant_delete_main_currency" => "Can't delete main currency",
    "cant_delete_currency_in_use" => "Can't delete currency in use in subscription",
    "exchange_update" => "Exchange rates last updated on",
    "currency_info"   => "Find the supported currencies and correct currency codes on",
    "currency_performance" => "For improved performance keep only the currencies you use.",
    "fixer_api_key"   => "Fixer API Key",
    "api_key"         => "API Key",
    "fixer_info"      => "If you use multiple currencies, and want accurate statistics and sorting on the subscriptions, a FREE API Key from Fixer is necessary.",
    "get_key"         => "Get your key at",
    "display_settings" => "Display Settings",
    "switch_theme"    => "Switch Light / Dark Theme",
    "calculate_monthly_price" => "Calculate and show monthly price for all subscriptions",
    "convert_prices"  => "Always convert and show prices on my main currency (slower)",
    "experimental_settings" => "Experimental Settings",
    "remove_background" => "Attempt to remove background of logos from image search (experimental)",
    "experimental_info" => "Experimental settings will probably not work perfectly.",
    "payment_methods" => "Payment Methods",
    "payment_methods_info" => "Click a payment method to disable / enable it.",
    "cant_delete_payment_method_in_use" => "Can't disable used payment method",
    "disable"         => "Disable",
    "enable"          => "Enable",
    "test"            => "Test",
    "add"             => "Add",
    "save"            => "Save",
    // Toast
    "success"         => "Success",
    // Endpoint responses
    "session_expired" => "Your session expired. Please login again",
    "fields_missing"  => "Some fields are missing",
    "fill_all_fields" => "Please fill all fields",
    "fill_mandatory_fields" => "Please fill all mandatory fields",
    "error"           => "Error",
    // Category
    "failed_add_category" => "Failed to add category",
    "failed_edit_category" => "Failed to edit category",
    "category_in_use" => "Category is in use in subscriptions and can't be removed",
    "failed_remove_category" => "Failed to remove category",
    "category_saved"  => "Category saved",
    "category_removed" => "Category removed",
    // Currency
    "currency_saved"  => "was saved.",
    "error_adding_currency" => "Error adding currency entry.",
    "failed_to_store_currency" => "Failed to store Currency on the Database.",
    "currency_in_use" => "Currency is in use in subscriptions and can't be deleted.",
    "currency_is_main" => "Currency is set as main currency and can't be deleted.",
    "failed_to_remove_currency" => "Failed to remove currency from the Database.",
    "failed_to_store_api_key" => "Failed to store API Key on the Database.",
    "invalid_api_key"  => "Invalid API Key.",
    "api_key_saved"   => "API key saved successfully",
    "currency_removed" => "Currency removed",
    // Household
    "failed_add_household" => "Failed to add household member",
    "failed_edit_household" => "Failed to edit household member",
    "failed_remove_household" => "Failed to remove household member",
    "household_in_use" => "Household member is in use in subscriptions and can't be removed",
    "member_saved"     => "Member saved",
    "member_removed"   => "Member removed",
    // Notifications
    "error_saving_notifications" => "Error saving notifications data.",
    "wallos_notification" => "Wallos Notification",
    "test_notification" => "This is a test notification. If you're seeing this, the configuration is correct.",
    "email_error"      => "Error sending email",
    "notification_sent_successfuly" => "Notification sent successfully",
    "notifications_settings_saved" => "Notification settings saved successfully.",
    // Payments
    "payment_in_use"   => "Can't disable used payment method",
    "failed_update_payment" => "Failed to update payment method in the database",
    "enabled"          => "enabled",
    "disabled"         => "disabled",
    // Subscription
    "error_fetching_image" => "Error fetching image",
    "subscription_updated_successfuly" => "Subscription updated successfully",
    "subscription_added_successfuly" => "Subscription added successfully",
    "error_deleting_subscription" => "Error deleting subscription.",
    "invalid_request_method" => "Invalid request method.",
    // User
    "error_updating_user_data" => "Error updating user data.",
    "user_details_saved" => "User details saved",

];


?>
