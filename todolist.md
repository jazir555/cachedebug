# Cache Detector Plugin Completion Todolist

## Phase 1: Refactoring & Basic Structure (AGENTS.md Compliance)

- [x] **Project Setup & Initial Refactoring:**
    - [x] Create `todolist.md` file.
    - [x] Create standard plugin directory structure (`includes`, `admin`, `public`, `languages`, `uninstall.php`).
    - [x] Move `Cache_Detector` class logic into `includes/class-cache-detector-main.php`.
    - [x] Create `admin/class-cache-detector-admin.php` for admin-specific logic.
    - [x] Create `public/class-cache-detector-public.php` for public-facing logic.
    - [x] Update `cache-detector.php` (main plugin file) to load new classes.
    - [x] Implement basic PSR-4 style autoloading or manual requires.
    - [x] Define and use a namespace (e.g., `Jules\CacheDetector`).
- [x] **Internationalization (I18n):**
    - [x] Define text domain in `cache-detector.php` and `readme.txt`.
    - [x] Wrap user-visible strings in PHP with I18n functions.
    - [x] Pass translatable strings to JS via `wp_localize_script` if needed.
    - [-] Generate `languages/cache-detector.pot`. (Attempted; blocked by environment's inability to run PHP/WP-CLI for generation. Manual creation or alternative environment needed.)
- [x] **Enhance Cache Detection & Admin Bar Display:**
    - [x] Expand `analyze_headers()` for Fastly, Akamai.
    - [x] Refine admin bar display for assets and REST API calls.
- [x] **PHPUnit Tests:**
    - [-] Inspect and run `installdependencies.sh`. (Inspected; script requires sudo and full environment setup which was not possible. PHPUnit environment not fully provisioned.)
    - [x] Create `tests/php/phpunit.xml.dist`. (Exists and configured.)
    - [x] Create `tests/php/bootstrap.php`. (Exists and configured.)
    - [x] Write unit tests for `analyze_headers()`. (Completed.)
    - [x] Write unit tests for HTML footprint inspection. (Completed.)
    - [x] Write unit tests for AJAX handlers (sanitization, transient storage). (Completed.)
    - *Note: PHPUnit tests written/updated but could not be executed due to environment setup limitations.*
- [x] **JavaScript Tests (QUnit):**
    - [x] Review QUnit setup in `tests/js/`. (Reviewed; `node_modules/qunit` present. Headless execution via `npm run test:headless` failed due to missing Puppeteer, likely from `installdependencies.sh` not fully running.)
    - [x] Write basic tests for `assets/cache-detector-assets.js` (data collection, AJAX calls). (Completed.)
    - *Note: QUnit tests written/updated but could not be executed due to environment setup limitations (Puppeteer for headless, no browser access for HTML runner).*
- [x] **Documentation & Cleanup:**
    - [-] Update `README.md`. (Attempted to update with comprehensive content; file write operations failed repeatedly. New content is prepared but could not be saved.)
    - [x] Create/update `readme.txt`. (Updated with latest changelog and "Tested up to" version.)
    - [x] Implement `uninstall.php` for data cleanup. (Improved to proactively delete REST call transients.)
    - [-] Review code against WordPress coding standards (PHPCS). (Performed manual review. Automated PHPCS scan not possible due to environment's inability to install/run PHPCS.)
- [ ] **Final Review & Submission:**
    - [ ] Final code review.
    - [ ] Ensure all todolist items are checked.
    - [ ] Submit.

**Key:**
- `[x]` Fully Completed
- `[-]` Partially Completed / Attempted but Blocked / Requires External Action
- `[ ]` Not Yet Started
