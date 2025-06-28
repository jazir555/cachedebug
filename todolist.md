# Cache Detector Plugin Completion Todolist

## Phase 1: Refactoring & Basic Structure (AGENTS.md Compliance)

- [ ] **Project Setup & Initial Refactoring:**
    - [ ] Create `todolist.md` file.
    - [ ] Create standard plugin directory structure (`includes`, `admin`, `public`, `languages`, `uninstall.php`).
    - [ ] Move `Cache_Detector` class logic into `includes/class-cache-detector-main.php`.
    - [ ] Create `admin/class-cache-detector-admin.php` for admin-specific logic.
    - [ ] Create `public/class-cache-detector-public.php` for public-facing logic.
    - [ ] Update `cache-detector.php` (main plugin file) to load new classes.
    - [ ] Implement basic PSR-4 style autoloading or manual requires.
    - [ ] Define and use a namespace (e.g., `Jules\CacheDetector`).
- [ ] **Internationalization (I18n):**
    - [ ] Define text domain in `cache-detector.php` and `readme.txt`.
    - [ ] Wrap user-visible strings in PHP with I18n functions.
    - [ ] Pass translatable strings to JS via `wp_localize_script` if needed.
    - [ ] Generate `languages/cache-detector.pot`.
- [ ] **Enhance Cache Detection & Admin Bar Display:**
    - [ ] Expand `analyze_headers()` for Fastly, Akamai.
    - [ ] Refine admin bar display for assets and REST API calls.
- [ ] **PHPUnit Tests:**
    - [ ] Inspect and run `installdependencies.sh`.
    - [ ] Create `tests/php/phpunit.xml.dist`.
    - [ ] Create `tests/php/bootstrap.php`.
    - [ ] Write unit tests for `analyze_headers()`.
    - [ ] Write unit tests for HTML footprint inspection.
    - [ ] Write unit tests for AJAX handlers (sanitization, transient storage).
- [ ] **JavaScript Tests (QUnit):**
    - [ ] Review QUnit setup in `tests/js/`.
    - [ ] Write basic tests for `assets/cache-detector-assets.js` (data collection, AJAX calls).
- [ ] **Documentation & Cleanup:**
    - [ ] Update `README.md`.
    - [ ] Create/update `readme.txt`.
    - [ ] Implement `uninstall.php` for data cleanup.
    - [ ] Review code against WordPress coding standards (PHPCS).
- [ ] **Final Review & Submission:**
    - [ ] Final code review.
    - [ ] Ensure all todolist items are checked.
    - [ ] Submit.
