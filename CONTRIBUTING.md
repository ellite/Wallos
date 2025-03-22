# Contributing to wallos

We welcome contributions from the community and look forward to working with you to improve this project!

## How to Contribute

1.  **Fork the repository:** Start by forking the wallos repository to your own GitHub account.
2.  **Clone your fork:** Clone the forked repository to your local machine (replace <YOUR_USERNAME> with your actual github username):

    ```bash
    git clone https://github.com/<YOUR_USERNAME>/wallos.git
    cd wallos
    ```

3.  **Create a branch:** Create a new branch for your changes:

    ```bash
    git checkout -b feature/your-feature-name
    ```

    or

    ```bash
    git checkout -b fix/your-bug-fix-name
    ```

4.  **Make your changes:** Implement your feature or bug fix.
5.  **Test your changes:** Ensure that your changes work as expected.
6.  **Commit your changes:** Commit your changes with a clear and concise message:

    ```bash
    git add .
    git commit -m "Add your feature or fix"
    ```

7.  **Push your changes:** Push your branch to your forked repository:

    ```bash
    git push origin feature/your-feature-name
    ```

8.  **Create a Pull Request:** Go to the wallos repository on GitHub (https://github.com/ellite/wallos) and create a pull request from your branch to the `main` branch.

## Pull Request Guidelines

* **One feature/fix per pull request:** Please keep pull requests focused on a single feature or bug fix.
* **Clear and descriptive title and description:** Provide a clear title and description of your changes.
* **Include relevant tests:** If possible, include tests for your changes.
* **Follow the project's coding style:** Adhere to the project's coding style and conventions.
* **Keep your pull request up to date:** If changes are requested, please update your pull request accordingly.

## Issues

* **Bug Reports:** If you find a bug, please open an issue with a clear description of the problem and steps to reproduce it.
* **Feature Requests:** If you have a feature request, please open an issue with a clear description of the feature and its benefits.
* **Priority:** Bug fixes will take priority over feature requests.

## Translations

If you want to contribute with a translation of wallos:

1.  **Add your language code:**
    * Open `includes/i18n/languages.php`.
    * Add your language code in the format: `"<language_code>" => ["name" => "<Language Name>", "dir" => "<ltr or rtl>"],`.
    * Please use the original language name and not the English translation.
    * Example: `"pt" => ["name" => "Português", "dir" => "ltr"],`.

2.  **Create language files:**
    * Copy `includes/i18n/en.php` and rename it to your language code (e.g., `pt.php`).
    * Translate all the values in the new language file.
    * Copy `scripts/i18n/en.js` and rename it to your language code (e.g., `pt.js`).
    * Translate all the values in the new javascript language file.
    * **Note:** Incomplete translations will not be accepted.

3.  **Create a Pull Request:** Follow the Pull Request Guidelines above.

## Contributors

<a href="https://github.com/ellite/wallos/graphs/contributors">
  <img src="https://contrib.rocks/image?repo=ellite/wallos" />
</a>


Thank you for your contributions!
