#!/usr/bin/env bash

# Exit immediately if a command exits with a non-zero status
set -e

echo "🚀 Starting Laravel cPanel Deployment Auto-Fix..."

# ----------------------------------------------------------------------
# 1. ENVIRONMENT & USER DETECTION
# ----------------------------------------------------------------------
CURRENT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
CPANEL_USER=$(stat -c '%U' "$CURRENT_DIR")

if [ -z "$CPANEL_USER" ] || [ "$CPANEL_USER" = "root" ]; then
    # Fallback to path extraction if executed by root directly
    CPANEL_USER=$(echo "$CURRENT_DIR" | awk -F'/' '{print $3}')
fi

echo "👤 Detected cPanel User: ${CPANEL_USER}"
echo "📁 Project Directory: ${CURRENT_DIR}"

# ----------------------------------------------------------------------
# 2. AUTO-DETECT ACTIVE PHP VERSION
# ----------------------------------------------------------------------
PHP_BIN=$(which php 2>/dev/null || echo "/usr/bin/php")
PHP_VER_MAJOR_MINOR=$($PHP_BIN -r 'echo PHP_MAJOR_VERSION.PHP_MINOR_VERSION;' 2>/dev/null || echo "83")
PHP_VER_DOT=$($PHP_BIN -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;' 2>/dev/null || echo "8.3")

echo "🐘 Detected PHP Version: ${PHP_VER_DOT} (cPanel Handler: ea-php${PHP_VER_MAJOR_MINOR})"

# ----------------------------------------------------------------------
# 3. FIX PERMISSIONS & TRAVERSAL PATH
# ----------------------------------------------------------------------
echo "🔧 Setting folder and file permissions..."

# Fix parent path execution (+x) so Apache can traverse to .htaccess
IFS='/' read -ra PATH_PARTS <<< "$CURRENT_DIR"
BUILD_PATH=""
for part in "${PATH_PARTS[@]}"; do
    if [ -n "$part" ]; then
        BUILD_PATH="${BUILD_PATH}/${part}"
        if [ -d "$BUILD_PATH" ]; then
            chmod 755 "$BUILD_PATH" 2>/dev/null || true
        fi
    fi
done

# Standardize permissions inside the project
find "$CURRENT_DIR" -type d -exec chmod 755 {} \;
find "$CURRENT_DIR" -type f -exec chmod 644 {} \;

# Grant write access to Laravel storage and cache directories
chmod -R 775 "$CURRENT_DIR/storage" "$CURRENT_DIR/bootstrap/cache" 2>/dev/null || true

# If running as root, enforce ownership reset
if [ "$(id -u)" -eq 0 ]; then
    echo "👑 Enforcing cPanel ownership to ${CPANEL_USER}:${CPANEL_USER}..."
    chown -R "${CPANEL_USER}:${CPANEL_USER}" "$CURRENT_DIR"
fi

# ----------------------------------------------------------------------
# 4. FIX PUBLIC/.HTACCESS HANDLER MATCHING PHP VERSION
# ----------------------------------------------------------------------
PUBLIC_HTACCESS="$CURRENT_DIR/public/.htaccess"

if [ -f "$PUBLIC_HTACCESS" ]; then
    echo "📄 Normalizing public/.htaccess for ea-php${PHP_VER_MAJOR_MINOR}..."

    # Remove existing cPanel PHP handlers from .htaccess to avoid stale handler errors
    sed -i '/# php -- BEGIN cPanel-generated handler/,/# php -- END cPanel-generated handler/d' "$PUBLIC_HTACCESS"

    # Prepend the dynamically detected PHP handler block
    TMP_FILE=$(mktemp)
    cat <<EOF > "$TMP_FILE"
# php -- BEGIN cPanel-generated handler, do not edit
<IfModule mime_module>
  AddHandler application/x-httpd-ea-php${PHP_VER_MAJOR_MINOR} .php .php${PHP_VER_MAJOR_MINOR:0:1} .phtml
</IfModule>
# php -- END cPanel-generated handler, do not edit

EOF
    cat "$PUBLIC_HTACCESS" >> "$TMP_FILE"
    mv "$TMP_FILE" "$PUBLIC_HTACCESS"
    chmod 644 "$PUBLIC_HTACCESS"
fi

# ----------------------------------------------------------------------
# 5. LARAVEL PRODUCTION OPTIMIZATION
# ----------------------------------------------------------------------
echo "⚡ Running Laravel optimization tasks..."

cd "$CURRENT_DIR"

$PHP_BIN artisan storage:link --quiet || true
$PHP_BIN artisan config:cache
$PHP_BIN artisan route:cache
$PHP_BIN artisan view:cache
$PHP_BIN artisan event:cache

echo "✅ Deployment finished successfully! All permissions and PHP handlers synced."
