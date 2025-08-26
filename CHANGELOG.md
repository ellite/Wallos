- - # Changelog
    
        All notable changes to this project will be documented in this file.
    
        ## [Unreleased] - 2025-08-25
    
        This is a major feature release that introduces the "Projects" module. This feature adds a new management layer, allowing users to group and organize their subscriptions under specific projects.
    
        ### Added
    
        -   **New Project Management Module**:
            -   Implemented full CRUD (Create, Read, Update, Delete) functionality for "Projects".
            -   Projects can now have a name, logo, category, URL, and notes.
    
        -   **New Projects Homepage (`projects.php`)**:
            -   Created a new main dashboard page to display all projects.
            -   The projects list displays aggregated data from its child subscriptions, including Total Amount, Next Payment Date, and an overall Active/Inactive status.
            -   Added server-side filtering for the projects list (by Category).
            -   Implemented comprehensive server-side sorting for the projects list by Name, Creation Date (ID), Price (Total Amount), Next Payment Date, Category, and Status.
    
        -   **Two-Level Project-Subscription View**:
            -   The main project list provides a summary view.
            -   Users can click to expand any project, revealing a detailed table of all associated subscriptions.
            -   Added a dedicated "Add Subscription" button within each project's expanded view to create a pre-linked subscription.
    
        -   **Subscription to Project Association**:
            -   The "Add/Edit Subscription" form now includes a "Project" dropdown menu, allowing any subscription to be associated with a project or left unassigned.
    
        -   **Full Internationalization (i18n) Support**:
            -   Adapted all user-facing text to support multiple languages, using AI-powered translations to ensure broad coverage.
    
        ### Changed
    
        -   **Database Schema**:
            -   **`projects` table (New)**: A new table was created to store project information (`id`, `user_id`, `name`, `logo`, `category_id`, `url`, `notes`).
            -   **`subscriptions` table (Altered)**: Added a new `project_id` column (INTEGER) to establish the relationship between subscriptions and projects.
    
        -   **Core Subscription Logic**:
            -   The subscription creation and editing process was updated to handle the new `project_id` field.
            -   The main subscription list (`subscriptions.php`) was updated to query and provide project data to its "Add/Edit Subscription" form.
    
        ### File Manifest
    
        The following files were created or modified to implement the Projects feature:
    
        -   #### **Core Pages**
            -   `projects.php` **(New)**: The primary dashboard for viewing, filtering, and sorting projects.
            -   `subscriptions.php` **(Modified)**: Updated to include a "Project" dropdown in the subscription form, populated with data from the `projects` table.
    
        -   #### **Backend API (`endpoints`)**
            -   `endpoints/project/add.php` **(New)**: Handles the creation and updating of projects in the database.
            -   `endpoints/project/get.php` **(New)**: Provides a single project's data for editing, and also serves the complete, filtered, and sorted project list HTML for AJAX requests.
            -   `endpoints/project/delete.php` **(New)**: Handles the deletion of a project and cascades the deletion to all of its associated subscriptions.
            -   `endpoints/subscription/add.php` **(Modified)**: Updated to process and save the optional `project_id`.
            -   `endpoints/subscription/get.php` **(Modified)**: Updated to include `project_id` in the data returned for a subscription.
    