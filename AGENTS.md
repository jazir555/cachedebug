# AGENTS.md: WordPress Plugin Development Guidelines

This document provides guidelines and best practices for developing WordPress plugins. Adhering to these guidelines will help ensure that plugins are secure, maintainable, performant, and compatible with the WordPress ecosystem. Its aim is to be a comprehensive guide for developers, including AI agents.

## Part I: General WordPress Plugin Development Best Practices

This section outlines best practices applicable to the development of *any* WordPress plugin.

### 1. Getting Started

-   **Understanding the WordPress Ecosystem**: Familiarize yourself with WordPress architecture, its template hierarchy, the role of themes vs. plugins, and common user expectations.
-   **Essential Tools**:
    -   **Code Editor**: VS Code, PhpStorm, Sublime Text, etc., with appropriate extensions for PHP, JS, CSS.
    -   **Local Development Environment**: Tools like Local, Docker, XAMPP, MAMP, or WP-ENV for running WordPress locally.
    -   **Browser Developer Tools**: Essential for debugging JS, CSS, and network requests.
    -   **Version Control**: Git is standard. Use a platform like GitHub, GitLab, or Bitbucket.
-   **WordPress Community & Official Resources**:
    -   [WordPress Developer Handbook](https://developer.wordpress.org/plugins/)
    -   [WordPress Code Reference](https://developer.wordpress.org/reference/)
    -   [WordPress Support Forums](https://wordpress.org/support/forums/)
    -   [Make WordPress Core Blog](https://make.wordpress.org/core/) for updates.

### 2. Plugin Architecture & Structure

-   **Standard Plugin Directory Structure**: A well-organized plugin structure makes development and maintenance easier.
    ```
    plugin-name/
    ├── plugin-name.php        # Main plugin file (with plugin header)
    ├── readme.txt             # Standard WordPress readme file for WordPress.org
    ├── uninstall.php          # Code to clean up on uninstallation
    ├── includes/              # PHP classes, functions, and core logic
    │   ├── class-plugin-main.php
    │   └── functions-helpers.php
    ├── admin/                 # Admin-specific files (PHP classes, CSS, JS, views/templates)
    │   ├── class-plugin-admin.php
    │   ├── css/
    │   ├── js/
    ├── public/                # Public-facing files (PHP classes, CSS, JS, views/templates)
    │   ├── class-plugin-public.php
    │   ├── css/
    │   ├── js/
    ├── languages/             # Translation files (.pot, .po, .mo)
    ├── assets/                # Static assets like images, fonts
    └── tests/                 # Unit, integration, and E2E tests
    ```
-   **Main Plugin File (`plugin-name.php`)**: Must contain the [plugin header comment](https://developer.wordpress.org/plugins/plugin-basics/header-requirements/). This file is the entry point.
-   **`readme.txt`**: Essential for the WordPress.org plugin directory. Follow the [WordPress plugin readme file standard](https://developer.wordpress.org/plugins/wordpress-org/how-your-readme-txt-works/).
-   **`uninstall.php`**: Used to clean up *all* plugin data (options, custom tables, files) when the plugin is *deleted* (not just deactivated). See "Plugin Lifecycle" for details.
-   **Organizing Code**:
    -   **PHP Namespacing (PSR-4)**: All plugin-specific PHP classes, interfaces, and traits should be within a unique vendor namespace (e.g., `VendorName\PluginName\`) to prevent conflicts. Follow PSR-4 autoloading standards, typically managed with Composer.
    -   **Prefixing**: Prefix all custom global functions, constants, action/filter hook names, database table names, and option names with a unique plugin prefix (e.g., `plugin_prefix_` or `MYPLUGIN_`) to avoid conflicts. Choose a prefix that is unlikely to be used by other plugins.

### 3. Coding Standards & Quality

-   **Adhering to Official WordPress Coding Standards**: This is crucial for readability, maintainability, and collaboration.
    -   [PHP Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/php/)
    -   [HTML Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/html/)
    -   [CSS Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/css/)
    -   [JavaScript Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/javascript/)
-   **Tooling**:
    -   **PHP_CodeSniffer (PHPCS)**: Use with the `WordPress-Coding-Standards` ruleset to automatically check and fix PHP code. Integrate into your development workflow.
    -   **Linters for JS/CSS**: ESLint (for JavaScript) and Stylelint (for CSS) are highly recommended.
-   **Inline Documentation & DocBlocks**: Write clear, concise comments for all functions, classes, methods, and complex code sections, following PHPDoc/JSDoc standards. This is vital for understanding and maintaining the code.

### 4. Core WordPress APIs & Functionality

Leverage WordPress's built-in APIs for common tasks:
-   **Hooks (Actions and Filters)**: The primary way to interact with WordPress core and other plugins. Understand their usage and how to create your own.
-   **Settings API**: For creating and managing plugin settings pages in the admin area.
-   **Options API**: For storing and retrieving persistent plugin options (`get_option`, `add_option`, `update_option`, `delete_option`).
-   **Transient API**: For temporary caching of data (`get_transient`, `set_transient`, `delete_transient`).
-   **Shortcode API**: For allowing users to embed plugin functionality into posts/pages (`add_shortcode`).
-   **`WP_Error`**: Use for robust error handling in functions that can fail.
-   **Filesystem API**: For safe and reliable file operations.
-   **REST API**: Understand how to register custom REST API endpoints or modify existing ones.
-   **Database API (`$wpdb`)**: For direct database interactions. *Always* use `$wpdb->prepare()` to prevent SQL injection. Use `$wpdb->insert`, `$wpdb->update`, `$wpdb->delete` where appropriate.

### 5. Internationalization (I18n) and Localization (L10n)

Design plugins to be translatable from the outset.
-   **Core I18n Functions**: Use WordPress I18n functions for all text strings (e.g., `__()`, `_e()`, `_x()`, `_n()`, `esc_html__()`, `sprintf()`).
    -   Example: `sprintf( __( 'Found %d items.', 'your-text-domain' ), $count );`
-   **Text Domain**: Define a unique `Text Domain:` in the main plugin file header and use it consistently in all I18n functions. Load the text domain using `load_plugin_textdomain()`, typically hooked into the `plugins_loaded` action.
-   **Context for Translators**: Use `_x()` or add translator comments (`/* translators: ... */`) to provide context for ambiguous strings.
-   **`.pot` File**: Include a Portable Object Template (`.pot`) file in the `languages/` directory. This file is the master template for translations. Use tools like WP-CLI (`wp i18n make-pot`) or build scripts to generate/update it.
-   **Localizing JavaScript Strings**: Use `wp_localize_script()` to pass translated strings and other necessary data from PHP to your JavaScript files.

### 6. Security: Building Secure Plugins

Security is paramount. Assume all external data (user input, API responses, etc.) is untrusted.
-   **Core Principles**:
    -   **Defense in Depth**: Implement multiple layers of security.
    -   **Least Privilege**: Users/processes should only have the permissions essential to perform their intended functions.
-   **Nonces (Numbers used once)**: Use nonces (e.g., `wp_create_nonce()`, `wp_verify_nonce()`, `check_admin_referer()`) to protect against Cross-Site Request Forgery (CSRF) attacks for any action, URL, or form submission that changes state.
-   **Data Sanitization**: Sanitize *all* incoming data (especially user input from `$_GET`, `$_POST`, `$_REQUEST`, REST API requests) before processing or saving it. Use appropriate WordPress sanitization functions (e.g., `sanitize_text_field()`, `sanitize_email()`, `sanitize_key()`, `wp_kses_post()`, `absint()`, `floatval()`).
-   **Data Validation**: Validate data against expected formats, types, ranges, or allowed values *after* sanitization and *before* use.
-   **Output Escaping**: Escape *all* data being outputted to the browser (HTML, attributes, JavaScript, URLs) to prevent Cross-Site Scripting (XSS) attacks. Use functions like `esc_html()`, `esc_attr()`, `esc_url()`, `esc_js()`, `wp_json_encode()` (for JSON in JS), `wp_kses_post()`.
-   **Database Security**:
    -   Always use `$wpdb->prepare()` for database queries involving variables to prevent SQL injection.
    -   Use specific `$wpdb` methods like `$wpdb->insert`, `$wpdb->update`, `$wpdb->delete` when possible, as they handle some escaping.
-   **Permissions Checks**: Use `current_user_can()` to check if the current user has the required capabilities before performing any privileged actions or accessing sensitive data.
-   **File Upload Security**: If your plugin handles file uploads:
    -   Validate file types (MIME types and extensions) and sizes server-side.
    -   Scan uploaded files for malware if possible.
    -   Store user-uploaded files outside the plugin's directory, preferably in the `wp-content/uploads` directory, and ensure they are not directly executable if they are not meant to be.
-   **Protecting Plugin Settings and Data**: Ensure sensitive settings or data stored by the plugin are adequately protected and not easily guessable or publicly accessible if not intended.
-   **Third-party Libraries**: Keep any included client-side or server-side libraries up-to-date and ensure they are from reputable sources and free of known vulnerabilities.
-   **Rate Limiting**: For actions that could be abused (e.g., API requests, form submissions), consider implementing rate limiting.
-   **Regular Security Audits & Staying Informed**: Periodically review your code for security issues. Stay updated on common WordPress vulnerabilities and security best practices.
-   **Error Handling**: Implement robust error handling. Return `WP_Error` objects for functions that can fail. Use `WP_DEBUG`, `WP_DEBUG_LOG`, and `WP_DEBUG_DISPLAY` for development, but ensure they are typically off on production sites. Provide clear, translatable, user-friendly error messages; avoid exposing raw PHP errors or sensitive system information.

### 7. Data Privacy & User Considerations

-   **Data Minimization**: Collect only the user data that is absolutely necessary for the plugin's functionality.
-   **GDPR and other Privacy Regulations**: Be aware of privacy regulations like GDPR. If you collect personal data, understand your responsibilities (e.g., consent, right to access/erasure).
-   **Transparency**: Clearly inform users what data your plugin collects, how it's used, and if it's shared with third parties. This can be part of your `readme.txt` or plugin settings page.
-   **Securely Handling Personally Identifiable Information (PII)**: If PII is stored, ensure it's done securely, encrypted if necessary, and access is strictly controlled.

### 8. Plugin Lifecycle Management

Understand and correctly implement logic for each stage:
-   **Activation (`register_activation_hook()`):**
    -   For one-time setup: PHP/WordPress version checks, setting default options (`add_option()`), creating custom database tables, flushing rewrite rules (if Custom Post Types or taxonomies are added).
    -   Do not perform lengthy operations or output anything directly during activation.
-   **Deactivation (`register_deactivation_hook()`):**
    -   For temporary cleanup: unscheduling cron jobs/Action Scheduler tasks, removing temporary data.
    -   **Crucially, do NOT delete user data (options, custom tables) on deactivation.** Users expect their settings to remain if they reactivate.
-   **Uninstallation (`uninstall.php`):**
    -   Executed *only* when a user explicitly *deletes* the plugin from the WordPress admin.
    -   Must clean up *all* plugin-specific data: options (`delete_option()`), custom database tables (`DROP TABLE`), post meta, user meta, files/directories created by the plugin.
    -   Crucially, check for the `WP_UNINSTALL_PLUGIN` constant definition at the beginning of `uninstall.php` to prevent direct access: `if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) { exit; }`

***

### **Enhanced Performance Guidelines for WordPress Plugin Development**

Performance is a critical feature. A slow plugin can negatively impact user experience, SEO, and conversion rates, leading to uninstalls and poor reviews. Adhere to these principles to build a fast, efficient, and scalable plugin.

## 1. Efficient PHP & Logic Execution

How and when your code runs is as important as what it does.

-   **Choose the Right Hook**: Execute your code on the latest possible action hook. Don't run code on `init` if it's only needed on the front-end (`template_redirect`) or for a specific shortcode.
    -   **Example**: A function that modifies a post's content should hook into `the_content`, not `wp_head`.
-   **Conditional Execution**: Wrap functionality in conditional checks. Don't load an entire class or file for a feature that's only active in the admin area or on a specific custom post type.
    ```php
    if ( is_admin() ) {
        // Load admin-only files and hooks here.
        require_once MY_PLUGIN_PATH . 'includes/admin/class-my-admin-settings.php';
    }
    ```
-   **Mind Your Loops**: Be extremely cautious with loops, especially nested loops that run on frequently loaded pages. A single inefficient query inside a loop can bring a site to a halt.

## 2. Database Performance

Database queries are often the single biggest performance bottleneck in WordPress.

-   **Be Specific and Efficient**:
    -   **Never `SELECT *`**: Only query for the specific columns you need.
    -   Use `WP_Query`'s `'fields' => 'ids'` or `'fields' => 'id=>parent'` when you only need post IDs. This is drastically faster than retrieving entire post objects.
-   **Minimize Queries**:
    -   Combine multiple queries into one where possible.
    -   Avoid running the same query multiple times on a single page load. Use static variables or a simple function cache.
-   **Leverage WordPress APIs**: Prefer WordPress functions (`get_posts`, `get_terms`, `get_post_meta`) over direct `$wpdb` calls. They have built-in caching, sanitization, and are more future-proof.
-   **Custom Table Indexes**: If you create custom database tables, ensure you place indexes on all columns used in `WHERE`, `JOIN`, `GROUP BY`, and `ORDER BY` clauses. This is non-negotiable for performance.
-   **Batch Processing**: For large data updates (e.g., migrating thousands of meta fields), use functions like `wp_suspend_cache_invalidation( true )` before your loop and `wp_suspend_cache_invalidation( false )` after to prevent cache churn on every single update.

## 3. Smart Asset (CSS & JS) Loading

Unnecessary assets are a primary cause of front-end slowdown.

-   **Conditional Loading is King**: Enqueue scripts (`wp_enqueue_script()`) and styles (`wp_enqueue_style()`) *only* on the exact pages where they are absolutely needed.
    -   Use `wp_enqueue_scripts` for the front-end and `admin_enqueue_scripts` for the admin.
    -   Use conditional tags (`is_singular('my_cpt')`, `is_page_template('template.php')`) or `get_current_screen()` in the admin to target specific pages.
-   **Optimize Your Assets**:
    -   **Minification**: Always serve minified CSS and JavaScript files in your production plugin.
    -   **Dependencies & Footer Loading**: Correctly declare script dependencies (`$deps` array). Load non-critical JavaScript in the footer by setting the `$in_footer` parameter to `true`. This improves perceived page load speed.
-   **Leverage Core Libraries**: If you need jQuery, Underscore.js, or Backbone.js, declare them as a dependency instead of bundling your own copy. WordPress already includes them.
    ```php
    wp_enqueue_script( 'my-script', 'path/to/script.js', array( 'jquery' ), '1.0.0', true );
    ```
-   **Concatenation with Caution**: With the prevalence of HTTP/2, concatenating all your assets into a single file is often an anti-pattern. Small, conditionally loaded files can be better for caching and parallelism. Only concatenate if you have many tiny files and a clear, measured benefit.

## 4. Intelligent Caching

Avoid re-calculating or re-fetching the same data repeatedly.

-   **Transients API**: The best choice for caching data that is expensive to generate (e.g., results from a remote API call, a complex database query, or a rendered component). Transients are stored in the database but will automatically use a persistent object cache (like Redis or Memcached) if one is available, making them incredibly fast and scalable.
    ```php
    $data = get_transient( 'my_plugin_remote_data' );
    if ( false === $data ) {
        $data = wp_remote_get( 'https://api.example.com/data' );
        // Don't forget error checking on the response!
        set_transient( 'my_plugin_remote_data', $data, 12 * HOUR_IN_SECONDS );
    }
    ```
-   **WordPress Object Cache (`WP_Object_Cache`)**: Ideal for non-persistent caching within a single page load. It helps avoid running the same expensive function or query multiple times during one request. It does not persist between page loads unless a persistent drop-in is installed.

## 5. Background & Asynchronous Processing

Don't make users wait for slow tasks. Offload them to the background.

-   **Use Case**: Sending emails, processing form submissions, calling slow third-party APIs, image resizing, or data imports/exports.
-   **Avoid `WP-Cron` for Critical Tasks**: Standard `WP-Cron` is not a real cron job; it is triggered by site visitors. On low-traffic sites, scheduled events may be severely delayed.
-   **Use a Robust Job Queue**: For anything important or resource-intensive, use a library like **[Action Scheduler](https://actionscheduler.org/)**. It's battle-tested (powers WooCommerce Subscriptions), provides a UI for managing jobs, and runs tasks in the background much more reliably. It can be easily included as a Composer dependency.

### 6. Mindful Content & Component Loading

Improve perceived performance by changing *how* content is delivered.

-   **Lazy Loading**: If your plugin generates content with images, iframes, or videos, implement or ensure compatibility with native (`loading="lazy"`) or JavaScript-based lazy loading to defer loading of below-the-fold media.
-   **AJAX for Components**: For complex or data-heavy UI components (like dashboards, charts, or detailed reports), consider loading a lightweight placeholder first. Then, fetch the full data and render the component via an AJAX call after the page has loaded. This dramatically speeds up the initial page render.

***

### 10. JavaScript in WordPress

-   **Enqueueing Scripts**: Always use `wp_enqueue_script()` to add JavaScript files. Specify dependencies (e.g., `jquery`), version numbers (for cache busting), and whether to load in the footer.
-   **Modern JavaScript (ES6+)**: You can write modern JavaScript (ES6+ features like arrow functions, classes, modules).
    -   **Transpilation**: Use tools like Babel (often via a build process with Webpack/Rollup) to convert modern JS into ES5 compatible code for wider browser support.
-   **Module Bundlers**: For complex JavaScript applications, use module bundlers like Webpack, Rollup, or Parcel to manage dependencies, bundle files, and optimize assets.
-   **Interacting with WordPress from JS**:
    -   **AJAX**: Use the WordPress AJAX API (`wp_ajax_{action}` and `wp_ajax_nopriv_{action}` hooks in PHP). Always include nonces.
    -   **REST API**: Leverage the WordPress REST API for more complex data interactions.
    -   **`wp_localize_script()`**: The standard method to pass data (settings, translated strings, URLs) from PHP to your enqueued JavaScript files.
-   **jQuery in WordPress**: WordPress bundles jQuery in noConflict mode. Use `jQuery` instead of `$` or wrap your code in a noConflict wrapper: `(function($) { /* your code here */ })(jQuery);`.

### 11. CSS & Styling

-   **Enqueueing Styles**: Always use `wp_enqueue_style()` to add CSS files. Specify dependencies, version numbers, and media types.
-   **CSS Naming Conventions**: Use specific, prefixed class names (e.g., `plugin-prefix-component-name__element--modifier` based on BEM) to avoid conflicts with themes and other plugins.
-   **Responsive Design**: Ensure your plugin's output and admin interfaces are responsive and work well on all screen sizes.
-   **Accessibility in Styling**:
    -   Ensure sufficient color contrast for text and UI elements.
    -   Provide clear visual focus indicators for interactive elements.
    -   Respect user preferences for reduced motion if applicable.

### 12. Database Management

-   **Custom Table Creation**:
    -   If your plugin requires custom tables, create them on activation using `dbDelta()` (found in `wp-admin/includes/upgrade.php`).
    -   Carefully design your table schema, including appropriate data types and indexes.
    -   Always prefix your table names: `$table_name = $wpdb->prefix . 'myplugin_custom_data';`.
-   **Database Migrations & Updates**:
    -   Store a plugin version number in the database (`get_option('myplugin_db_version')`).
    -   On plugin load (e.g., hooked to `plugins_loaded`), compare this stored version with the current plugin version. If different, run update routines.
    -   Update routines can alter table schemas (again, using `dbDelta()`), migrate data, or update option formats.
    -   Design update routines to be incremental (e.g., version 1 to 2, then 2 to 3) and idempotent (safe to run multiple times if needed).
    -   Update the stored DB version number after successful completion of updates.
    -   **Advise users to back up their database** before major plugin updates, especially if significant schema changes are involved.

### 13. Dependency Management

-   **PHP Dependencies (Composer)**:
    -   Use [Composer](https://getcomposer.org/) to manage external PHP libraries.
    -   Include `composer.json` (defines dependencies) and `composer.lock` (locks dependencies to specific versions) in your plugin.
    -   Include the Composer autoloader: `require_once __DIR__ . '/vendor/autoload.php';`.
    -   **Conflict Prevention**: If your plugin is intended for wide distribution, consider prefixing your Composer dependencies' namespaces using a tool like [PHP-Scoper](https://github.com/humbug/php-scoper) to prevent conflicts with the same libraries used by other plugins or themes.
-   **JavaScript Dependencies (npm or yarn)**:
    -   Use npm (`package.json`) or yarn (`yarn.lock`) to manage JavaScript dependencies for your build process or client-side code.
    -   These are typically development dependencies used for building assets, linting, testing, etc.
    -   Commit `package.json` and `package-lock.json` (or `yarn.lock`).

### 14. Testing Strategies

Thorough testing is non-negotiable for producing a quality plugin.
-   **Importance of Testing**: Catches bugs early, ensures reliability, facilitates refactoring, and verifies compatibility.
-   **Types of Tests**:
    -   **Unit Tests (PHPUnit)**: Test individual PHP functions, methods, and classes in isolation. WordPress provides a testing framework and tools for this.
        -   Use data providers for testing with multiple inputs.
        -   Use mocking/stubbing for dependencies.
    -   **Integration Tests**: Test interactions between different components of your plugin, or your plugin with WordPress core/other plugins.
    -   **End-to-End (E2E) Tests**: Simulate user interactions in a browser to test complete workflows (e.g., using tools like Playwright, Cypress, or Puppeteer).
    -   **JavaScript Tests**: Test your JavaScript code using frameworks like Jest, QUnit, Mocha, etc.
-   **Compatibility Testing**:
    -   Test with various WordPress versions (current, recent major versions).
    -   Test with various supported PHP versions.
    -   Test with popular themes (e.g., default WordPress themes like Twenty Twenty-Four, major commercial themes).
    -   Test with common plugins, especially caching, SEO, security, and page builders.
    -   Test in WordPress Multisite environments if your plugin is intended to support it.
    -   Test across different modern web browsers.
-   **Test Setup Automation**:
    -   Use the script `installdependencies.sh to automate the setup of the WordPress testing environment. 
-   **Test Coverage**: Aim for good test coverage, but prioritize testing critical functionality and complex logic.
-   **Visual Regression Testing**: For UI-heavy plugins, tools can capture screenshots and compare them to baseline versions to detect unintended visual changes.
-   **User Acceptance Testing (UAT)**: Before a major release, have real users test the plugin in beta to get feedback.

### 15. Accessibility (A11y)

Ensure your plugin is usable by as many people as possible, including those with disabilities.
-   **Core Principles (POUR)**:
    -   **Perceivable**: Information and user interface components must be presentable to users in ways they can perceive.
    -   **Operable**: User interface components and navigation must be operable.
    -   **Understandable**: Information and the operation of user interface must be understandable.
    -   **Robust**: Content must be robust enough that it can be interpreted reliably by a wide variety of user agents, including assistive technologies.
-   **Practical Steps**:
    -   **Semantic HTML**: Use HTML elements for their correct purpose (e.g., `<button>` for buttons, `<a>` for links, proper heading structure).
    -   **Keyboard Navigability**: All functionality should be accessible and operable using only a keyboard. Ensure logical focus order.
    -   **ARIA Attributes**: Use Accessible Rich Internet Applications (ARIA) attributes appropriately to enhance semantics for assistive technologies *when native HTML is insufficient*. Avoid overusing ARIA.
    -   **Sufficient Color Contrast**: Ensure text and important UI elements have adequate contrast against their background.
    -   **Forms**: Associate labels with form controls. Provide clear error messages and instructions.
    -   **Images**: Provide meaningful alternative text (`alt` attributes) for images.
    -   **Testing**: Test with accessibility tools (e.g., browser extensions like axe, WAVE) and manually with keyboard navigation and screen readers (e.g., NVDA, VoiceOver, JAWS).

### 16. User Experience (UX) for Admin Interfaces

A good user experience makes your plugin more enjoyable and easier to use.
-   **Clarity and Simplicity**: Strive for an intuitive admin interface. Avoid jargon where possible.
-   **Consistency with WordPress UI**: Follow WordPress admin design patterns and conventions to make your plugin feel familiar.
-   **Providing User Feedback**: Clearly indicate the results of user actions (e.g., success messages, error notifications, loading states).
-   **Minimizing Option Clutter**: Only present options that are necessary. Group related settings logically. Consider sensible defaults.
-   **Helpful Onboarding/Tooltips**: For complex features, provide guidance through onboarding flows, contextual help, or tooltips. Link to documentation.

### 17. Documentation

Good documentation is essential for both users and developers.
-   **Inline Comments & DocBlocks**:
    -   Use PHPDoc/JSDoc syntax for all classes, methods, functions, and properties.
    -   Explain *why* code is written a certain way, not just *what* it does, especially for complex logic.
-   **`readme.txt`**: This is the primary user-facing document, especially for plugins on WordPress.org.
    -   Must include: Plugin Name, Contributors, Tags, Requires at least, Tested up to, Stable tag, License, License URI.
    -   Should include: Short Description, Long Description, Installation steps, Frequently Asked Questions (FAQ), Screenshots, Changelog.
-   **User Manuals**: For complex plugins, provide more detailed user guides. These can be hosted on your website or included with the plugin.
-   **Developer Documentation**: If your plugin offers hooks (actions/filters), APIs, or functions for other developers to use:
    -   Document them thoroughly with examples of usage.
    -   Explain parameters, return values, and any important considerations.
    -   This can be in a separate `DEVELOPER_GUIDE.md` or on a website.

### 18. Release Management & Distribution

A structured release process helps ensure stability and clear communication.
-   **Semantic Versioning (SemVer)**: Use MAJOR.MINOR.PATCH (e.g., 1.0.0, 1.1.0, 1.1.1).
    -   MAJOR: Incompatible API changes.
    -   MINOR: Added functionality in a backward-compatible manner.
    -   PATCH: Backward-compatible bug fixes.
-   **Updating Version Numbers**: Consistently update the version number in:
    -   Main plugin file header (`Version:`).
    -   `readme.txt` (`Stable tag:`).
    -   Any internal plugin version constants or options used for DB migrations.
-   **Maintaining a Detailed Changelog**: Keep a chronological list of changes for each version (new features, improvements, bug fixes, security fixes). Often part of `readme.txt`.
-   **Git Tagging**: Create a Git tag for each release (e.g., `git tag 1.2.3`) and push tags to your repository (`git push --tags`).
-   **Pre-release Testing**:
    -   Conduct thorough internal testing.
    -   Consider alpha/beta testing phases for significant releases, involving a subset of users.
-   **Communication**: Inform users about new releases and significant changes (e.g., via blog posts, email newsletters, social media, or within the plugin admin interface if appropriate).
-   **WordPress.org Plugin Repository**: If distributing via WordPress.org:
    -   Familiarize yourself with the [Plugin Developer Guidelines](https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/).
    -   Use Subversion (SVN) to commit and deploy your plugin to the repository.
    -   Keep your `trunk/` up-to-date for development versions and use `tags/` for stable releases.

## Part II: Example Plugin Implementation & Conventions

This section provides practical examples and conventions, drawing inspiration from well-structured plugins like the LHA Animation Optimizer, to illustrate how the best practices from Part I can be applied.

### 1. Plugin Initialization and Orchestration

-   **Example Main Plugin Class (`Plugin_Name_Main`)**:
    -   Typically responsible for:
        -   Defining plugin constants (version, path, URL).
        -   Loading dependencies (like `vendor/autoload.php`).
        -   Instantiating core components (loader, admin manager, public manager, i18n).
        -   Registering activation, deactivation, and uninstall hooks.
        -   Initiating the loading of hooks.
    ```php
    // plugin-name/plugin-name.php
    if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

    final class Plugin_Name_Main {
        private static $_instance = null;
        public $loader;
        public $version;

        public static function instance() {
            if ( is_null( self::$_instance ) ) {
                self::$_instance = new self();
                self::$_instance->setup_constants();
                self::$_instance->includes();
                self::$_instance->init_hooks();
                // Instantiate other core classes like admin, public, i18n
                // $this->i18n = new Plugin_Name_I18n();
                // $this->admin = new Plugin_Name_Admin( $this->get_plugin_name(), $this->get_version() );
                // $this->public = new Plugin_Name_Public( $this->get_plugin_name(), $this->get_version() );
                // self::$_instance->loader = new Plugin_Name_Loader(); // Example
            }
            return self::$_instance;
        }

        private function setup_constants() { /* ... */ }
        private function includes() { /* require_once for classes */ }
        private function init_hooks() {
            register_activation_hook( __FILE__, array( 'Plugin_Name_Activator', 'activate' ) );
            register_deactivation_hook( __FILE__, array( 'Plugin_Name_Deactivator', 'deactivate' ) );
        }
        // ... other methods
    }

    function plugin_name_init() {
        return Plugin_Name_Main::instance();
    }
    add_action( 'plugins_loaded', 'plugin_name_init' );
    ```

-   **Hook Loader/Registry Class (`Plugin_Name_Loader`)**: For centralized hook registration.
    -   Maintains collections of actions and filters.
    -   Provides methods to add actions/filters.
    -   A `run()` method loops through collections and calls `add_action()` / `add_filter()`.
    *This promotes cleaner code by decoupling hook definitions from their execution points.*

### 2. Structuring Admin-Side Functionality

-   **Admin Orchestrator Class (`Plugin_Name_Admin` or `Settings_Manager_Example`)**:
    -   Manages admin-specific hooks (e.g., adding menu pages, enqueueing admin scripts/styles).
    -   Handles registration of settings via the Settings API (`register_setting`, `add_settings_section`, `add_settings_field`).
    -   Contains callbacks for rendering settings fields and sections.
    -   May house AJAX handlers for admin-specific actions.
-   **AJAX Handlers**:
    -   Define methods in the relevant admin class.
    -   Hook them using `add_action( 'wp_ajax_plugin_prefix_action_name', array( $this, 'ajax_handler_method' ) );`.
    -   **Security is CRITICAL**:
        1.  Verify nonce: `check_ajax_referer( 'your_nonce_action', 'nonce_field_name' );`
        2.  Check capabilities: `if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( 'Permission denied.' ); }`
        3.  Sanitize all incoming `$_POST` or `$_GET` data.
        4.  Perform the action.
        5.  Send response: `wp_send_json_success( $data );` or `wp_send_json_error( $message );`.

### 3. Structuring Public-Side Functionality

-   **Public-Facing Logic Class (`Plugin_Name_Public` or `Public_Script_Manager_Example`)**:
    -   Enqueues public-facing scripts and styles via `wp_enqueue_scripts` hook.
    -   Localizes data for JavaScript using `wp_localize_script()`:
        ```php
        // In your Public class, hooked to wp_enqueue_scripts
        wp_enqueue_script( 'plugin-prefix-public-js', PLUGIN_URL . 'public/js/script.js', array('jquery'), PLUGIN_VERSION, true );
        $localized_data = array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'public_action_nonce' ),
            'i18n'     => array(
                'processing' => __( 'Processing...', 'plugin-text-domain' ),
            ),
        );
        wp_localize_script( 'plugin-prefix-public-js', 'pluginPrefixSettings', $localized_data );
        ```
    -   Registers shortcodes or hooks into content filters if the plugin modifies front-end display.

### 4. Managing Custom Data

-   **Example: Custom Database Table**:
    -   **Schema Definition & Creation (in `Activator` class)**:
        ```php
        // In Plugin_Name_Activator::activate()
        global $wpdb;
        $table_name = $wpdb->prefix . 'plugin_prefix_custom_data';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            name tinytext NOT NULL,
            text text NOT NULL,
            url varchar(255) DEFAULT '' NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
        add_option( 'plugin_prefix_db_version', '1.0' );
        ```
    -   **Dedicated Class for CRUD Operations (`Custom_Data_Store_Example`)**:
        -   Methods like `get_item( $id )`, `add_item( $data )`, `update_item( $id, $data )`, `delete_item( $id )`.
        -   All methods using `$wpdb` must use `$wpdb->prepare()` for queries with variables.

### 5. Development Workflow Examples

-   **Adding a New Plugin Setting**:
    1.  **Define**: Add the setting to your `Plugin_Name_Admin` class, typically in a method that registers settings sections and fields (e.g., `add_settings_field()`).
    2.  **Render**: Create the callback function to output the HTML for the setting's input field.
    3.  **Sanitize**: Add a sanitization callback function and register it in `register_setting()`.
    4.  **Default**: Add a default value for the setting in your `Plugin_Name_Activator::activate()` method if it's a new option, or handle defaults in your retrieval logic.
    5.  **Usage**: Access the setting using `get_option('setting_name')`.
    6.  **JS Access**: If needed in JavaScript, add it to the data passed via `wp_localize_script()`.
-   **Adding a New AJAX Action (Admin Example)**:
    1.  **JS Call**: Client-side JavaScript makes a request (e.g., using `jQuery.post()`) to `admin-ajax.php`, including `action: 'plugin_prefix_my_action'`, your nonce, and other data.
    2.  **PHP Handler**: Create a method in `Plugin_Name_Admin` (e.g., `ajax_my_action_handler()`).
    3.  **Hook**: In `Plugin_Name_Admin` (or `Plugin_Name_Loader`), hook it: `add_action( 'wp_ajax_plugin_prefix_my_action', array( $this->admin_class_instance, 'ajax_my_action_handler' ) );`.
    4.  **Implement**: Inside the handler: verify nonce, check capabilities, sanitize inputs, perform logic, `wp_send_json_success()` or `wp_send_json_error()`.
-   **Internationalization Workflow**:
    1.  **Wrap Strings**: Wrap all user-facing strings in PHP with `__()`, `_e()`, etc., using your plugin's text domain. For JS, pass them via `wp_localize_script`.
    2.  **Update `.pot` File**: Regularly regenerate the `.pot` file.
        -   Using WP-CLI: `wp i18n make-pot . languages/your-plugin-text-domain.pot`
        -   Or via a build script (see below).

### 6. Build Processes (Example using npm and WP-CLI)

-   **`package.json`**: Defines JS dependencies (e.g., Babel, Webpack, ESLint, PostCSS) and scripts.
    ```json
    // package.json (simplified example)
    {
      "name": "my-wordpress-plugin",
      "version": "1.0.0",
      "scripts": {
        "build": "webpack --mode production",
        "dev": "webpack --mode development --watch",
        "lint:js": "eslint js/",
        "lint:css": "stylelint css/",
        "makepot": "wp i18n make-pot . languages/plugin-text-domain.pot --exclude=node_modules,vendor,build",
        "package": "npm run build && npm run makepot && node ./scripts/package.js"
      },
      "devDependencies": {
        "@babel/core": "^7.0.0",
        "eslint": "^8.0.0",
        "stylelint": "^15.0.0",
        "webpack": "^5.0.0",
        "webpack-cli": "^4.0.0"
        // ... other dependencies
      }
    }
    ```
-   **Example Build Steps / Scripts**:
    -   `npm install`: Installs JS dependencies listed in `package.json`.
    -   `npm run build`: Compiles assets (e.g., ES6+ to ES5 JS, SASS to CSS, minification).
    -   `npm run makepot`: Generates the `.pot` file for translations (often uses WP-CLI).
    -   `npm run package`: A custom script (e.g., `scripts/package.js` using Node.js) that might:
        1.  Run `build` and `makepot`.
        2.  Create a temporary directory.
        3.  Copy only necessary plugin files (excluding `node_modules`, `.git`, dev configs) to this directory.
        4.  Create a ZIP file (e.g., `plugin-name-1.0.0.zip`) from this temporary directory.
-   **Tools**:
    -   **WP-CLI**: Essential for many WordPress development tasks, including POT file generation (`wp i18n make-pot`).
    -   **Node.js/npm/yarn**: For managing JS dependencies and running build scripts.
    -   **Webpack/Rollup/Parcel**: JavaScript module bundlers.
    -   **Babel**: JavaScript transpiler.
    -   **PostCSS/SASS/LESS**: CSS preprocessors/processors.

### 7. Testing Setup (Example)

-   **Directory Structure**:
    ```
    tests/
    ├── php/                  # PHPUnit tests
    │   ├── bootstrap.php     # Test suite bootstrap
    │   ├──phpunit.xml.dist # PHPUnit configuration
    │   └── unit/             # Unit tests
    │       └── test-sample.php
    ├── js/                   # JavaScript tests (e.g., QUnit, Jest)
    │   └── qunit-tests.html  # Example QUnit runner
    │   └── test-example.js
    └── e2e/                  # End-to-end tests (e.g., Playwright, Cypress)
    ```
-   **Example Test Setup Script (`bin/setup-tests.sh` or similar)**:
    -   This script typically uses WP-CLI to:
        -   Download WordPress.
        -   Download the WordPress test suite.
        -   Configure `wp-tests-config.php`.
        -   Install the plugin and any dependencies needed for testing.
    ```bash
    #!/bin/bash
    # Example: bin/install-wp-tests.sh (from WordPress core)
    # Adapt for your plugin's needs.
    # This script would typically be called by a Composer script or a CI job.
    # (Refer to the LHA plugin's `install-wp-tests.sh` for a concrete example)
    ```
-   **Running Tests**:
    -   **PHPUnit**: `vendor/bin/phpunit -c tests/php/phpunit.xml.dist`
    -   **JavaScript Tests (QUnit)**:

        -   The `installdependencies.sh` script now fully automates the QUnit test environment setup in the `tests/js/` directory (or the directory specified by the `JS_TEST_DIR` variable within the script).
        -   This includes creating `tests/js/package.json` (if not present), installing QUnit via npm, and generating a `tests/js/qunit-tests.html` runner and a `tests/js/test-example.js`.
        -   To run the QUnit tests, open `tests/js/qunit-tests.html` (or the equivalent path if `JS_TEST_DIR` is customized) in your web browser.


        -   The `installdependencies.sh` script now fully automates the QUnit test environment setup in the `tests/js/` directory (or the directory specified by the `JS_TEST_DIR` variable within the script).
        -   This includes creating `tests/js/package.json` (if not present), installing QUnit via npm, and generating a `tests/js/qunit-tests.html` runner and a `tests/js/test-example.js`.
        -   To run the QUnit tests, open `tests/js/qunit-tests.html` (or the equivalent path if `JS_TEST_DIR` is customized) in your web browser.

        -   The `installdependencies.sh` script now sets up the QUnit testing environment by installing dependencies defined in `tests/js/package.json`.
        -   To run the QUnit tests, open `tests/js/qunit-tests.html` in your web browser.


    -   Integrate test runs into your CI/CD pipeline.

## II. Specific Guidelines for "WP Progressive HTML Loading" Plugin

This plugin has a unique architecture centered around modifying HTML output for progressive loading. Understanding these specifics is crucial for development and debugging. Adherence to the general guidelines above is assumed.

### 1. Core Concept: HTML Streaming
The primary goal is to improve perceived performance by streaming HTML content. Parts of the page (e.g., `<head>`) are sent and processed by the browser while the server still generates the rest. Development must always consider how changes impact this streaming.
- **Flush Markers**: `<!-- LHA_FLUSH_NOW -->` (default, configurable via `LHA_FLUSH_MARKER_COMMENT` constant) are inserted to signal the server to send buffered output.

### 2. Key Components & Architecture
- **`wp-progressive-html-loading.php` (Main Plugin File):**
    - Defines crucial constants (e.g., `LHA_FLUSH_MARKER_COMMENT`, `LHA_USER_TARGET_MARKER_TEXT`, various action hook names like `LHA_PROCESS_POST_ACTION`, `LHA_CLEANUP_POST_ACTION`, `LHA_ACTION_GROUP`). **Familiarize yourself with these.**
    - Initializes the plugin, service locator (`LHA\Core\ServiceLocator`), and feature registry (`LHA\Core\Registry`).
    - Handles activation (PHP version check, default options) and deactivation (unschedules all actions in `LHA_ACTION_GROUP`).
    - Includes a critical check for `vendor/autoload.php` and displays an admin notice if missing.

- **`src/Core/Scheduler.php` (`LHA\Core\Scheduler`):**
    - Abstraction layer for WordPress Action Scheduler. **All background tasks go through this.**
    - Key methods: `enqueue_async_action()`, `schedule_single_action()`, `schedule_recurring_action()`.
    - Manages batch processing via `schedule_master_batch_job()`.
    - All plugin-specific actions are grouped under `LHA_ACTION_GROUP` for easier management in Action Scheduler UI.

- **`src/Features/ContentProcessor.php` (`LHA\Features\ContentProcessor`):**
    - The heart of HTML modification logic; operates as a background task.
    - **Workflow:**
        1. Fetches raw HTML (via `LHA\Services\ContentFetcher`).
        2. Parses HTML into a `DOMDocument`.
        3. Applies strategies to insert flush markers (after `</head>`, user-defined comments, CSS selectors, Nth element). *CSS Selector & Nth Element strategies may be license-dependent via `\LHA\lha_is_pro_feature_active()`.*
        4. Filters markers by minimum chunk size (`_filter_flush_markers_by_chunk_size()`).
        5. Saves modified HTML and original source hash (via `LHA\Core\StorageManager`).
    - Hooks into `LHA_PROCESS_POST_ACTION` and batch actions.

- **`src/Core/StorageManager.php` (`LHA\Core\StorageManager`):**
    - Handles saving and retrieving processed HTML content for posts.

- **`src/Core/ServiceLocator.php` and `src/Core/Registry.php`:**
    - Implement service location and feature registration patterns for decoupled and manageable components. This architectural pattern is a good practice for complex plugins.

### 3. Background Processing Model (Action Scheduler)
- Most significant work (content analysis, modification, cleanup) is **asynchronous**.
- User actions (e.g., saving a post) typically schedule a background task, ensuring admin responsiveness.
- **Implication**: Changes might not be immediately visible; check Action Scheduler queue.

### 4. Potential Conflicts
This plugin modifies final HTML output, increasing potential for conflicts with:
- Other plugins filtering/altering content late in the rendering lifecycle.
- Advanced caching plugins (server-side or client-side).
- SEO plugins modifying `<head>` elements or content.
- Plugins injecting JS/CSS unconventionally.
- Page builders with complex output buffering.
- **Actionable**: Rigorous compatibility testing (see General Testing section) is essential.

### 5. Testing Streaming Behavior (Plugin-Specific)
- **`curl -N <URL>`:** Observe raw, incremental output in terminal. Look for flush markers.
- **Browser Developer Tools (Network Tab):** Analyze content download timing. Initial HTML (e.g., `<head>`) should arrive quickly.
- **View Source:** Verify flush markers (`<!-- LHA_FLUSH_NOW -->` or custom) appear as expected.
- **Content Variety:** Test with short/long posts, many images, various blocks/shortcodes.
- **Server Environments:** Test on different server setups, as server-level buffering can interact.

### 6. Debugging Tips (Plugin-Specific)
- **Action Scheduler UI:** Install Action Scheduler with composer (if not bundled). Filter by `LHA_ACTION_GROUP` (e.g., `lha-progressive-html-processing`) to check pending, failed, or completed tasks. **This is the first place to look for processing issues.**
- **Logging:**
    - Enable `WP_DEBUG` and `WP_DEBUG_LOG` in `wp-config.php`.
    - The plugin uses `LHA\Core\Logging`, outputting to `wp-content/debug.log` (or configured log). Look for messages prefixed "LHA Progressive HTML Loading".
- **Verify Processed Content:** Check where `StorageManager` stores data (e.g., post meta: `get_post_meta($post_id, '_lha_processed_html', true);`) to see if/how content was processed.
- **Plugin Settings:** Double-check admin settings for enabled strategies, selectors, markers.
- **License Status:** For premium features, ensure the license is active if checks like `\LHA\lha_is_pro_feature_active()` are used.
- **Caches:** Clear all caches (browser, plugin, server) when testing streaming changes.
- **Constants:** Be aware of constants defined in the main plugin file, as they control key behaviors and identifiers.

By following both the general WordPress guidelines and these plugin-specific considerations, development on "WP Progressive HTML Loading" can be more efficient, robust, and maintainable.
