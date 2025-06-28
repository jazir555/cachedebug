#!/usr/bin/env bash
set -e

# --- Configuration ---
PHP_VERSION="7.4"

# Main WordPress Site
DB_NAME="wordpress_main"
DB_USER="wp_main_user"
DB_PASS="a_very_strong_password" # <-- CHANGE THIS
WP_DIR="/var/www/html/wordpress_main"
APACHE_SITE_CONF_NAME="wordpress_main.conf"

# WordPress Test Environment
TEST_DB_NAME="wordpress_tests"
TEST_DB_USER="wp_test_user"
TEST_DB_PASS="another_strong_password" # <-- CHANGE THIS
WP_TESTS_DIR="/tmp/wordpress-develop"
WP_VERSION_TAG="6.5" # Use a specific WP version tag for tests

# --- System Paths (Do not change) ---
APACHE_SITES_AVAILABLE_DIR="/etc/apache2/sites-available"
APACHE_SITE_CONF_PATH="${APACHE_SITES_AVAILABLE_DIR}/${APACHE_SITE_CONF_NAME}"

# --- Script ---

# Set DEBIAN_FRONTEND to noninteractive to prevent dialogs
export DEBIAN_FRONTEND=noninteractive

# 1. Ensure running as root
if [ "$EUID" -ne 0 ]; then
  echo "This script must be run as root. Use: sudo $0"
  exit 1
fi

echo ">>> Updating system and installing prerequisites..."
apt-get update -y
apt-get install -y \
    software-properties-common curl wget gnupg2 lsb-release \
    apache2 mysql-server \
    unzip git nodejs npm # For WP test environment build steps

echo ">>> Installing PHP ${PHP_VERSION} and required extensions..."
add-apt-repository -y ppa:ondrej/php
apt-get update -y
apt-get install -y \
    php${PHP_VERSION} php${PHP_VERSION}-cli php${PHP_VERSION}-fpm \
    php${PHP_VERSION}-common php${PHP_VERSION}-mysql php${PHP_VERSION}-xml \
    php${PHP_VERSION}-curl php${PHP_VERSION}-gd php${PHP_VERSION}-imagick \
    php${PHP_VERSION}-intl php${PHP_VERSION}-mbstring php${PHP_VERSION}-soap \
    php${PHP_VERSION}-zip php${PHP_VERSION}-bcmath


# Output PHP version and path
echo "PHP ${PHP_VERSION} installed."
PHP_EXECUTABLE_PATH=$(which php${PHP_VERSION} || echo "/usr/bin/php${PHP_VERSION}")
echo "PHP executable likely at: ${PHP_EXECUTABLE_PATH}"
echo ">>> Configuring and enabling services..."
a2dismod php* || true # Disable old mod_php if it exists
a2enmod proxy_fcgi setenvif rewrite
a2enconf php${PHP_VERSION}-fpm
systemctl enable --now apache2
systemctl enable --now mysql
systemctl enable --now php${PHP_VERSION}-fpm

echo ">>> Creating databases and users..."
# A single, consolidated block for MySQL setup
mysql --execute="DROP DATABASE IF EXISTS test;"
mysql --execute="DELETE FROM mysql.db WHERE Db='test' OR Db='test\\_%';"
# Main site database
mysql --execute="CREATE DATABASE IF NOT EXISTS ${DB_NAME} DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql --execute="CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';"
mysql --execute="GRANT ALL ON ${DB_NAME}.* TO '${DB_USER}'@'localhost';"
# Test environment database
mysql --execute="CREATE DATABASE IF NOT EXISTS ${TEST_DB_NAME} DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql --execute="CREATE USER IF NOT EXISTS '${TEST_DB_USER}'@'localhost' IDENTIFIED BY '${TEST_DB_PASS}';"
mysql --execute="GRANT ALL ON ${TEST_DB_NAME}.* TO '${TEST_DB_USER}'@'localhost';"
mysql --execute="FLUSH PRIVILEGES;"

echo ">>> Installing WP-CLI..."
if ! command -v wp &> /dev/null; then
    curl -sSL -o /usr/local/bin/wp https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
    chmod +x /usr/local/bin/wp
    wp --info --allow-root
else
    echo "WP-CLI is already installed."
fi

echo ">>> Installing WordPress (Main Site)..."
if [ ! -f "${WP_DIR}/wp-settings.php" ]; then
  mkdir -p ${WP_DIR}
  wget -q https://wordpress.org/latest.tar.gz -O /tmp/wordpress.tar.gz
  tar -xzf /tmp/wordpress.tar.gz -C ${WP_DIR} --strip-components=1
  chown -R www-data:www-data ${WP_DIR}
  find ${WP_DIR} -type d -exec chmod 755 {} \;
  find ${WP_DIR} -type f -exec chmod 644 {} \;
  rm /tmp/wordpress.tar.gz
else
  echo "WordPress files already exist in ${WP_DIR}. Skipping install."
fi

echo ">>> Creating wp-config.php (Main Site)..."
if [ ! -f "${WP_DIR}/wp-config.php" ]; then
  wp config create --path="${WP_DIR}" --dbname="${DB_NAME}" --dbuser="${DB_USER}" --dbpass="${DB_PASS}" --allow-root
  # Add debug constants using WP-CLI for robustness
  wp config set WP_DEBUG true --raw --path="${WP_DIR}" --allow-root
  wp config set WP_DEBUG_LOG true --raw --path="${WP_DIR}" --allow-root
  wp config set WP_DEBUG_DISPLAY false --raw --path="${WP_DIR}" --allow-root
else
  echo "wp-config.php already exists. Skipping."
fi

echo ">>> Creating Apache VirtualHost..."
cat > ${APACHE_SITE_CONF_PATH} <<EOF
<VirtualHost *:80>
    ServerAdmin webmaster@localhost
    DocumentRoot ${WP_DIR}
    <Directory ${WP_DIR}>
        AllowOverride All
        Require all granted
    </Directory>
    <FilesMatch \\.php$>
        SetHandler "proxy:unix:/run/php/php${PHP_VERSION}-fpm.sock|fcgi://localhost/"
    </FilesMatch>
    ErrorLog \${APACHE_LOG_DIR}/${DB_NAME}_error.log
    CustomLog \${APACHE_LOG_DIR}/${DB_NAME}_access.log combined
</VirtualHost>
EOF
a2ensite ${APACHE_SITE_CONF_NAME} > /dev/null
a2dissite 000-default.conf > /dev/null || true
echo ">>> Testing and restarting Apache..."
apachectl configtest
systemctl restart apache2

# --- Setup WordPress Test Environment ---
# This is now one clean, logical block.
echo ">>> Setting up WordPress PHPUnit test environment..."
if [ ! -d "${WP_TESTS_DIR}" ]; then
    echo "Cloning WordPress-Develop repository (branch ${WP_VERSION_TAG})..."
    git clone --depth 1 --branch ${WP_VERSION_TAG} https://github.com/WordPress/wordpress-develop.git "${WP_TESTS_DIR}"

    # Install Composer dependencies (PHPUnit, etc.) inside the cloned repo
    echo "Installing Composer dependencies in ${WP_TESTS_DIR}..."
    (
      cd "${WP_TESTS_DIR}"
      if ! command -v composer &> /dev/null; then
        echo "Composer not found globally, downloading locally..."
        curl -sS https://getcomposer.org/installer | php

        PHP_EXECUTABLE_PATH_FOR_COMPOSER=$(which php${PHP_VERSION} || echo "php") # Use specific PHP if possible
        ${PHP_EXECUTABLE_PATH_FOR_COMPOSER} composer.phar install
        echo "Locally downloaded composer.phar is at: $(pwd)/composer.phar"
      else
        echo "Using globally installed Composer: $(which composer)"
        composer install
      fi
    )
fi

# --- Setup QUnit Test Environment ---
echo ">>> Setting up QUnit JavaScript test environment..."
JS_TEST_DIR="tests/js" # Define the JS test directory

# Create the JS test directory if it doesn't exist
if [ ! -d "$JS_TEST_DIR" ]; then
  echo "Creating JavaScript test directory: ${JS_TEST_DIR}"
  mkdir -p "$JS_TEST_DIR"
fi

# Ensure npm is available
if ! command -v npm &> /dev/null; then
  echo "npm is not installed. Skipping QUnit setup."
else
  # Create a basic package.json if it doesn't exist
  if [ ! -f "${JS_TEST_DIR}/package.json" ]; then
    echo "Creating basic package.json in ${JS_TEST_DIR}..."
    (
      cd "$JS_TEST_DIR"
      npm init -y
    )
  fi

  echo "Installing QUnit and saving as a dev dependency in ${JS_TEST_DIR}..."
  (
    cd "$JS_TEST_DIR"
    # Check if QUnit is already a dependency to avoid unnecessary reinstall or version change
    if ! grep -q '"qunit"' package.json; then
      npm install --save-dev qunit
    else
      echo "QUnit already listed in package.json, running npm install to ensure it's installed."
      npm install
    fi
  )

  echo "Creating QUnit HTML runner: ${JS_TEST_DIR}/qunit-tests.html..."
  cat > "${JS_TEST_DIR}/qunit-tests.html" <<EOF
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width">
  <title>QUnit Tests</title>
  <link rel="stylesheet" href="node_modules/qunit/qunit/qunit.css">
</head>
<body>
  <div id="qunit"></div>
  <div id="qunit-fixture"></div>
  <script src="node_modules/qunit/qunit/qunit.js"></script>
  <!-- Add your plugin's JS files here if they can be tested in isolation -->
  <!-- e.g., <script src="../../wp-progressive-html-loading/assets/js/admin.js"></script> -->

  <!-- Add your test files here -->
  <script src="test-example.js"></script>
</body>
</html>
EOF

  echo "Creating example QUnit test file: ${JS_TEST_DIR}/test-example.js..."
  cat > "${JS_TEST_DIR}/test-example.js" <<EOF
QUnit.module('Example Tests', function() {
  QUnit.test('A basic true is true test', function(assert) {
    assert.strictEqual(true, true, 'True should be true');
  });

  QUnit.test('Another basic test: addition', function(assert) {
    assert.equal(2 + 2, 4, '2 + 2 should equal 4');
  });
});
EOF
  echo "QUnit setup complete. Open ${JS_TEST_DIR}/qunit-tests.html in a browser to run tests."
fi # Added missing fi for the npm check
# Assuming the script is run from the repository root
# and tests/js/package.json exists
if [ -f "tests/js/package.json" ]; then
  if command -v npm &> /dev/null; then
    echo "Installing QUnit dependencies via npm in tests/js/..."
    (
      cd tests/js
      npm install
    )
  else
    echo "npm is not installed. Skipping QUnit setup."
  fi
else
  echo "tests/js/package.json not found. Skipping QUnit setup."
fi

echo "✅✅✅ All-in-one WordPress setup is complete! ✅✅✅"
