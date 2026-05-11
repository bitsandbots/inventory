#!/bin/bash
#
# install.sh
#
# One-command setup for the Inventory Management System.
# Usage: bash install.sh
#
# Detects dependencies, creates database, imports schema,
# generates .env from .env.example, sets permissions.

set -e

# ---------------------------------------------------------------------------
# Resolve project root (directory containing this script)
# ---------------------------------------------------------------------------
PROJECT_ROOT="$(cd "$(dirname "$0")" && pwd)"
cd "$PROJECT_ROOT"

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

echo ""
echo "========================================="
echo " Inventory Management System — Installer"
echo "========================================="
echo ""

# ---------------------------------------------------------------------------
# Step 1: Dependency checks
# ---------------------------------------------------------------------------
echo -e "${CYAN}[1/6]${NC} Checking dependencies..."

MISSING=()

if ! command -v php &>/dev/null; then
    MISSING+=("php (PHP 8.x CLI)")
fi

if ! command -v mysql &>/dev/null; then
    MISSING+=("mysql (MySQL/MariaDB client)")
fi

if ! command -v apache2 &>/dev/null && ! command -v httpd &>/dev/null; then
    MISSING+=("apache2 or httpd (Apache web server)")
fi

if [ ${#MISSING[@]} -gt 0 ]; then
    echo -e "${RED}Missing dependencies:${NC}"
    for dep in "${MISSING[@]}"; do
        echo "  - $dep"
    done
    echo ""
    echo "Install them first, then re-run this script."
    echo "  Debian/Ubuntu: sudo apt install php php-mysql mariadb-server apache2"
    exit 1
fi

echo "  PHP:       $(php -v 2>&1 | head -1 | cut -d' ' -f2)"
echo "  MySQL:     $(mysql --version 2>&1 | head -1)"
echo "  Apache:    $(apache2 -v 2>&1 | head -1 || httpd -v 2>&1 | head -1)"
echo ""

# ---------------------------------------------------------------------------
# Step 2: Gather database credentials
# ---------------------------------------------------------------------------
echo -e "${CYAN}[2/6]${NC} Database configuration..."

# Accept from environment variables or prompt
DB_USER="${DB_USER:-}"
DB_PASS="${DB_PASS:-}"
DB_HOST="${DB_HOST:-localhost}"
DB_NAME="${DB_NAME:-inventory}"

if [ -z "$DB_USER" ]; then
    read -r -p "  MySQL username [root]: " DB_USER
    DB_USER="${DB_USER:-root}"
fi

if [ -z "$DB_PASS" ]; then
    read -r -s -p "  MySQL password (input hidden): " DB_PASS
    echo ""
fi

echo "  Host: $DB_HOST"
echo "  User: $DB_USER"
echo "  Database: $DB_NAME"
echo ""

# ---------------------------------------------------------------------------
# Step 3: Test database connection and create database
# ---------------------------------------------------------------------------
echo -e "${CYAN}[3/6]${NC} Testing database connection..."

MYSQL_CMD="mysql -h $DB_HOST -u $DB_USER"
if [ -n "$DB_PASS" ]; then
    MYSQL_CMD="$MYSQL_CMD -p'$DB_PASS'"
fi

if ! $MYSQL_CMD -e "SELECT 1;" &>/dev/null; then
    echo -e "${RED}  Cannot connect to MySQL. Verify credentials and that the server is running.${NC}"
    exit 1
fi

echo "  Connection successful."

# Check if database exists
DB_EXISTS=$($MYSQL_CMD -sN -e "SELECT COUNT(*) FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME='$DB_NAME';" 2>/dev/null || echo "0")

if [ "$DB_EXISTS" -eq 0 ]; then
    echo "  Creating database '$DB_NAME'..."
    $MYSQL_CMD -e "CREATE DATABASE IF NOT EXISTS \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
    echo "  Created."
else
    echo -e "${YELLOW}  Warning: Database '$DB_NAME' already exists.${NC}"
    read -r -p "  Drop and recreate? Tables will be lost. [y/N]: " RECREATE
    if [[ "$RECREATE" =~ ^[Yy]$ ]]; then
        $MYSQL_CMD -e "DROP DATABASE IF EXISTS \`$DB_NAME\`; CREATE DATABASE \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
        echo "  Recreated."
    else
        echo "  Using existing database."
    fi
fi

echo ""

# ---------------------------------------------------------------------------
# Step 4: Import schema
# ---------------------------------------------------------------------------
echo -e "${CYAN}[4/6]${NC} Importing schema.sql..."

if [ ! -f "$PROJECT_ROOT/schema.sql" ]; then
    echo -e "${RED}  Error: schema.sql not found in project root.${NC}"
    exit 1
fi

$MYSQL_CMD "$DB_NAME" < "$PROJECT_ROOT/schema.sql"
echo "  Schema imported (9 tables with indexes, constraints, and seed data)."
echo ""

# ---------------------------------------------------------------------------
# Step 5: Generate .env
# ---------------------------------------------------------------------------
echo -e "${CYAN}[5/6]${NC} Generating .env configuration..."

if [ -f "$PROJECT_ROOT/.env" ]; then
    echo -e "${YELLOW}  Warning: .env already exists.${NC}"
    read -r -p "  Overwrite? [y/N]: " OVERWRITE
    if [[ ! "$OVERWRITE" =~ ^[Yy]$ ]]; then
        echo "  Keeping existing .env."
        echo ""
        # Skip to permissions
    else
        GENERATE=1
    fi
else
    GENERATE=1
fi

if [ "${GENERATE:-0}" -eq 1 ]; then
    # Generate APP_SECRET using PHP (secure random) or fall back to openssl
    if command -v php &>/dev/null; then
        APP_SECRET=$(php -r 'echo bin2hex(random_bytes(32));')
    elif command -v openssl &>/dev/null; then
        APP_SECRET=$(openssl rand -hex 32)
    else
        APP_SECRET=$(date +%s | sha256sum | head -c 64)
    fi

    cat > "$PROJECT_ROOT/.env" << EOF
DB_HOST=${DB_HOST}
DB_USER=${DB_USER}
DB_PASS=${DB_PASS}
DB_NAME=${DB_NAME}
APP_SECRET=${APP_SECRET}
EOF

    echo "  .env generated with secure APP_SECRET."
fi
echo ""

# ---------------------------------------------------------------------------
# Step 6: Set permissions
# ---------------------------------------------------------------------------
echo -e "${CYAN}[6/6]${NC} Setting uploads/ directory permissions..."

if [ -d "$PROJECT_ROOT/uploads" ]; then
    chmod -R 775 "$PROJECT_ROOT/uploads"
    echo "  Permissions set: 775 on uploads/ and subdirectories."
else
    mkdir -p "$PROJECT_ROOT/uploads/users" "$PROJECT_ROOT/uploads/products"
    chmod -R 775 "$PROJECT_ROOT/uploads"
    echo "  Created uploads/ directories with 775 permissions."
fi

# Warn if Apache user isn't the owner
if command -v apache2 &>/dev/null; then
    APACHE_USER=$(apache2ctl -S 2>&1 | grep "User:" | head -1 | awk '{print $2}' || echo "www-data")
else
    APACHE_USER="www-data"
fi

CURRENT_OWNER=$(stat -c '%U' "$PROJECT_ROOT/uploads" 2>/dev/null || echo "unknown")
if [ "$CURRENT_OWNER" != "$APACHE_USER" ]; then
    echo -e "${YELLOW}  Note: uploads/ is owned by '$CURRENT_OWNER'.${NC}"
    echo "  To allow image uploads, run:"
    echo "    sudo chown -R ${APACHE_USER}:${APACHE_USER} $PROJECT_ROOT/uploads/"
fi

echo ""

# ---------------------------------------------------------------------------
# Summary
# ---------------------------------------------------------------------------
echo "========================================="
echo -e " ${GREEN}Installation Complete${NC}"
echo "========================================="
echo ""
echo "  Project root: $PROJECT_ROOT"
echo "  Database:     $DB_NAME @ $DB_HOST"
echo "  Config:       .env"
echo ""
echo "  Default logins (change passwords immediately!):"
echo "    Admin:      admin / admin"
echo "    Supervisor: special / special"
echo "    User:       user / user"
echo ""
echo "  Access via:   http://your-server/inventory/"
echo ""
echo "  Documentation: docs/README.md"
echo "  Blueprint:     Blueprint_Overview.html"
echo ""
