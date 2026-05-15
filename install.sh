#!/bin/bash
#
# install.sh
#
# One-command setup for the Inventory Management System.
#
# Usage:
#   bash install.sh                Install (preserves existing deployment).
#   bash install.sh --reinstall    Wipe database, vhost, symlink, uploads,
#                                  and .env, then reinstall from scratch.
#   bash install.sh --reinstall -y Reinstall without confirmation prompt.
#
# Detects dependencies, creates database, imports schema,
# generates .env, sets permissions, and wires up the
# Apache virtual host on port 8080.

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

VHOST_PORT="${VHOST_PORT:-8080}"
VHOST_NAME="${VHOST_NAME:-blueberry.local}"
VHOST_ALIAS="${VHOST_ALIAS:-blueberry rhubarb.local rhubarb}"
DOCROOT="/var/www/html/inventory"
VHOST_CONF="/etc/apache2/sites-available/inventory.conf"

# ---------------------------------------------------------------------------
# Argument parsing
# ---------------------------------------------------------------------------
REINSTALL=0
ASSUME_YES=0
for arg in "$@"; do
    case "$arg" in
        --reinstall|-r) REINSTALL=1 ;;
        --yes|-y)       ASSUME_YES=1 ;;
        --help|-h)
            sed -n '2,15p' "$0" | sed 's/^# \{0,1\}//'
            exit 0
            ;;
        *)
            echo "Unknown option: $arg" >&2
            echo "Run with --help for usage." >&2
            exit 1
            ;;
    esac
done

# ---------------------------------------------------------------------------
# Reinstall: tear down existing deployment
# ---------------------------------------------------------------------------
if [ "$REINSTALL" = "1" ]; then
    echo ""
    echo -e "${RED}========================================="
    echo " REINSTALL MODE — destructive operation"
    echo -e "=========================================${NC}"
    echo ""
    echo "  This will permanently DELETE:"
    echo "    - Database (drop and recreate from schema.sql)"
    echo "    - Apache vhost config:  $VHOST_CONF"
    echo "    - DocumentRoot symlink: $DOCROOT"
    echo "    - All files in:         $PROJECT_ROOT/uploads/"
    echo "    - .env (a timestamped backup will be saved)"
    echo ""

    if [ "$ASSUME_YES" != "1" ]; then
        read -r -p "  Type 'RESET' to confirm: " CONFIRM
        if [ "$CONFIRM" != "RESET" ]; then
            echo "  Aborted."
            exit 1
        fi
    fi

    echo ""
    echo -e "${CYAN}[teardown]${NC} Removing existing deployment..."

    # Back up .env before wiping
    if [ -f "$PROJECT_ROOT/.env" ]; then
        BACKUP=".env.bak.$(date +%Y%m%d_%H%M%S)"
        cp "$PROJECT_ROOT/.env" "$PROJECT_ROOT/$BACKUP"
        echo "  .env backed up to $BACKUP"
        # Source existing values so we can use them as defaults
        # shellcheck disable=SC1090
        set -a; . "$PROJECT_ROOT/.env"; set +a
        rm -f "$PROJECT_ROOT/.env"
        echo "  .env removed."
    fi

    # Drop the database (need creds from .env or prompt)
    EXISTING_DB_USER="${DB_USER:-}"
    EXISTING_DB_PASS="${DB_PASS:-}"
    EXISTING_DB_NAME="${DB_NAME:-inventory}"

    if [ -z "$EXISTING_DB_USER" ]; then
        read -r -p "  MySQL admin username for teardown [root]: " EXISTING_DB_USER
        EXISTING_DB_USER="${EXISTING_DB_USER:-root}"
        read -r -s -p "  MySQL admin password (input hidden): " EXISTING_DB_PASS
        echo ""
    fi

    # Try sudo mysql first (socket auth), fall back to user/pass
    DROP_SQL="DROP DATABASE IF EXISTS \`$EXISTING_DB_NAME\`;"
    if sudo -n mysql -e "$DROP_SQL" 2>/dev/null; then
        echo "  Database '$EXISTING_DB_NAME' dropped (via sudo socket auth)."
    elif mysql -u "$EXISTING_DB_USER" ${EXISTING_DB_PASS:+-p"$EXISTING_DB_PASS"} -e "$DROP_SQL" 2>/dev/null; then
        echo "  Database '$EXISTING_DB_NAME' dropped."
    else
        echo -e "${YELLOW}  Warning: Could not drop database. Run manually:${NC}"
        echo "    sudo mysql -e \"DROP DATABASE IF EXISTS $EXISTING_DB_NAME;\""
    fi

    # Disable and remove vhost
    if [ -f "$VHOST_CONF" ]; then
        sudo a2dissite inventory.conf 2>/dev/null || true
        sudo rm -f "$VHOST_CONF"
        echo "  Apache vhost removed: $VHOST_CONF"
    fi

    # Remove symlink (only if it IS a symlink — never remove a real directory)
    if [ -L "$DOCROOT" ]; then
        sudo rm -f "$DOCROOT"
        echo "  Symlink removed: $DOCROOT"
    elif [ -e "$DOCROOT" ]; then
        echo -e "${YELLOW}  Warning: $DOCROOT exists but is not a symlink — leaving in place.${NC}"
    fi

    # Wipe user-uploaded content but preserve git-tracked seed files
    # (e.g., no_image.jpg placeholders shipped with the repo).
    if [ -d "$PROJECT_ROOT/uploads" ]; then
        if [ -d "$PROJECT_ROOT/.git" ] && command -v git &>/dev/null; then
            (cd "$PROJECT_ROOT" && git clean -fdx uploads/ >/dev/null 2>&1 || true)
            (cd "$PROJECT_ROOT" && git checkout HEAD -- uploads/ 2>/dev/null || true)
            echo "  uploads/ reset to repository state (seed placeholders preserved)."
        else
            find "$PROJECT_ROOT/uploads" -mindepth 1 -delete
            echo "  uploads/ contents removed (no git — full wipe)."
        fi
    fi

    # Reload Apache to clear the disabled vhost
    sudo systemctl reload apache2 2>/dev/null || true

    echo ""
    echo -e "${GREEN}  Teardown complete. Proceeding with fresh install...${NC}"
    echo ""

    # Clear env vars so the install flow re-prompts cleanly
    unset DB_USER DB_PASS DB_HOST DB_NAME APP_SECRET
fi

echo ""
echo "========================================="
echo " Inventory Management System — Installer"
echo "========================================="
echo ""

# ---------------------------------------------------------------------------
# Step 1: Dependency checks
# ---------------------------------------------------------------------------
echo -e "${CYAN}[1/7]${NC} Checking dependencies..."

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
# Step 2: Database configuration
# ---------------------------------------------------------------------------
# We use TWO sets of credentials:
#   - ADMIN: needs CREATE/DROP DATABASE rights. On Debian/Ubuntu MariaDB,
#            'sudo mysql' (unix_socket auth) is the canonical admin path.
#            Falls back to prompted user/pass if sudo isn't available.
#   - APP:   limited-rights user written to .env, used by PHP at runtime.
#            Default: 'webuser' with no password, GRANT on inventory.* only.
# ---------------------------------------------------------------------------
echo -e "${CYAN}[2/7]${NC} Database configuration..."

DB_HOST="${DB_HOST:-localhost}"
DB_NAME="${DB_NAME:-inventory}"
APP_USER="${APP_USER:-webuser}"
APP_PASS="${APP_PASS:-}"

# Probe for admin access: prefer sudo mysql (socket auth), fall back to prompts.
ADMIN_MYSQL=""
if sudo -n mysql -e "SELECT 1;" &>/dev/null; then
    ADMIN_MYSQL="sudo -n mysql"
    echo "  Admin access: sudo mysql (unix_socket auth)"
elif mysql -e "SELECT 1;" &>/dev/null; then
    ADMIN_MYSQL="mysql"
    echo "  Admin access: mysql (passwordless local socket)"
else
    echo "  sudo mysql unavailable — falling back to prompted credentials."
    read -r -p "  MySQL admin username [root]: " ADMIN_USER
    ADMIN_USER="${ADMIN_USER:-root}"
    read -r -s -p "  MySQL admin password (blank if none): " ADMIN_PASS
    echo ""
    if [ -n "$ADMIN_PASS" ]; then
        ADMIN_MYSQL="mysql -h $DB_HOST -u $ADMIN_USER -p$ADMIN_PASS"
    else
        ADMIN_MYSQL="mysql -h $DB_HOST -u $ADMIN_USER"
    fi
fi

echo "  Host:     $DB_HOST"
echo "  Database: $DB_NAME"
echo "  App user: $APP_USER"
echo ""

# ---------------------------------------------------------------------------
# Step 3: Test admin connection and create database
# ---------------------------------------------------------------------------
echo -e "${CYAN}[3/7]${NC} Testing database connection..."

if ! $ADMIN_MYSQL -e "SELECT 1;" &>/dev/null; then
    echo -e "${RED}  Cannot connect to MySQL. Verify credentials and that the server is running.${NC}"
    exit 1
fi

echo "  Connection successful."

# Check if database exists
DB_EXISTS=$($ADMIN_MYSQL -sN -e "SELECT COUNT(*) FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME='$DB_NAME';" 2>/dev/null || echo "0")

if [ "$DB_EXISTS" -eq 0 ]; then
    echo "  Creating database '$DB_NAME'..."
    $ADMIN_MYSQL -e "CREATE DATABASE IF NOT EXISTS \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
    echo "  Created."
else
    echo -e "${YELLOW}  Warning: Database '$DB_NAME' already exists.${NC}"
    if [ "$REINSTALL" = "1" ]; then
        RECREATE="y"
        echo "  Reinstall mode: dropping and recreating."
    elif [ "$ASSUME_YES" = "1" ]; then
        RECREATE="n"
        echo "  Using existing database (-y mode)."
    else
        read -r -p "  Drop and recreate? Tables will be lost. [y/N]: " RECREATE
    fi
    if [[ "$RECREATE" =~ ^[Yy]$ ]]; then
        $ADMIN_MYSQL -e "DROP DATABASE IF EXISTS \`$DB_NAME\`; CREATE DATABASE \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
        echo "  Recreated."
    else
        echo "  Using existing database."
    fi
fi

echo ""

# ---------------------------------------------------------------------------
# Step 4: Import schema and create app user
# ---------------------------------------------------------------------------
echo -e "${CYAN}[4/7]${NC} Importing schema.sql and creating app user..."

if [ ! -f "$PROJECT_ROOT/schema.sql" ]; then
    echo -e "${RED}  Error: schema.sql not found in project root.${NC}"
    exit 1
fi

$ADMIN_MYSQL "$DB_NAME" < "$PROJECT_ROOT/schema.sql"
echo "  Schema imported (9 tables with indexes, constraints, and seed data)."

# Create the app user with minimal privileges on this database only.
$ADMIN_MYSQL <<SQL
CREATE USER IF NOT EXISTS '${APP_USER}'@'localhost' IDENTIFIED BY '${APP_PASS}';
GRANT SELECT, INSERT, UPDATE, DELETE ON \`${DB_NAME}\`.* TO '${APP_USER}'@'localhost';
FLUSH PRIVILEGES;
SQL
echo "  App user '${APP_USER}'@'localhost' created and granted SELECT/INSERT/UPDATE/DELETE on ${DB_NAME}.*"
echo ""

# ---------------------------------------------------------------------------
# Step 5: Generate .env
# ---------------------------------------------------------------------------
echo -e "${CYAN}[5/7]${NC} Generating .env configuration..."

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
DB_USER=${APP_USER}
DB_PASS=${APP_PASS}
DB_NAME=${DB_NAME}
APP_SECRET=${APP_SECRET}
EOF

    echo "  .env generated with app credentials and secure APP_SECRET."
fi
echo ""

# ---------------------------------------------------------------------------
# Step 6: Set permissions
# ---------------------------------------------------------------------------
echo -e "${CYAN}[6/7]${NC} Setting uploads/ directory permissions..."

if [ -d "$PROJECT_ROOT/uploads" ]; then
    chmod -R 775 "$PROJECT_ROOT/uploads"
    echo "  Permissions set: 775 on uploads/ and subdirectories."
else
    mkdir -p "$PROJECT_ROOT/uploads/users" "$PROJECT_ROOT/uploads/products"
    chmod -R 775 "$PROJECT_ROOT/uploads"
    echo "  Created uploads/ directories with 775 permissions."
fi

# Determine Apache user. On newer Apache, `apache2ctl -S` prints
# `User: name="www-data" id=33` so we strip the name=" wrapper.
if command -v apache2 &>/dev/null; then
    APACHE_USER=$(apache2ctl -S 2>&1 | grep "User:" | head -1 \
        | sed -n 's/.*name="\([^"]*\)".*/\1/p')
    APACHE_USER="${APACHE_USER:-www-data}"
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
# Step 7: Apache virtual host setup
# ---------------------------------------------------------------------------
echo -e "${CYAN}[7/7]${NC} Configuring Apache virtual host..."

# Ensure Apache can traverse the home directory to follow symlinks.
# o+x (traverse) is needed when DocumentRoot is a symlink into a home dir.
HOME_DIR="$(dirname "$PROJECT_ROOT")"
if [ "$(stat -c '%a' "$HOME_DIR" 2>/dev/null | tail -c 2 | head -c 1)" = "0" ]; then
    chmod o+x "$HOME_DIR" 2>/dev/null \
        && echo "  Added o+x to $HOME_DIR (required for Apache symlink traversal)." \
        || echo -e "${YELLOW}  Warning: Could not chmod $HOME_DIR — run: chmod o+x $HOME_DIR${NC}"
else
    echo "  Home directory traversal OK: $HOME_DIR"
fi

# Create symlink from DocumentRoot to project root if missing
if [ ! -e "$DOCROOT" ]; then
    if sudo ln -s "$PROJECT_ROOT" "$DOCROOT" 2>/dev/null; then
        echo "  Symlink created: $DOCROOT -> $PROJECT_ROOT"
    else
        echo -e "${YELLOW}  Warning: Could not create $DOCROOT symlink (need sudo).${NC}"
        echo "  Run manually: sudo ln -s $PROJECT_ROOT $DOCROOT"
    fi
elif [ -L "$DOCROOT" ]; then
    echo "  Symlink already exists: $DOCROOT"
else
    echo -e "${YELLOW}  Warning: $DOCROOT exists but is not a symlink — skipping.${NC}"
fi

# Ensure port is declared in ports.conf
if [ -f /etc/apache2/ports.conf ]; then
    if ! grep -q "Listen ${VHOST_PORT}" /etc/apache2/ports.conf; then
        if sudo bash -c "echo 'Listen ${VHOST_PORT}' >> /etc/apache2/ports.conf" 2>/dev/null; then
            echo "  Added 'Listen ${VHOST_PORT}' to ports.conf."
        else
            echo -e "${YELLOW}  Warning: Could not update ports.conf — add 'Listen ${VHOST_PORT}' manually.${NC}"
        fi
    else
        echo "  Port ${VHOST_PORT} already declared in ports.conf."
    fi
fi

# Write vhost config if it doesn't exist
if [ ! -f "$VHOST_CONF" ]; then
    sudo tee "$VHOST_CONF" > /dev/null << EOF
# Inventory System — Apache Virtual Host
# Generated by install.sh on $(date)

<VirtualHost *:${VHOST_PORT}>
    ServerName ${VHOST_NAME}
    ServerAlias ${VHOST_ALIAS}
    DocumentRoot ${DOCROOT}

    <Directory ${DOCROOT}>
        Options -Indexes -MultiViews
        AllowOverride All
        Require all granted
        DirectoryIndex index.php index.html
        <FilesMatch "\.php$">
            SetHandler application/x-httpd-php
        </FilesMatch>
    </Directory>

    # Font MIME types for Bootstrap 3 Glyphicons
    AddType font/woff2                      .woff2
    AddType font/woff                       .woff
    AddType font/ttf                        .ttf
    AddType application/vnd.ms-fontobject  .eot
    AddType image/svg+xml                  .svg

    # Block direct access to sensitive files
    <FilesMatch "\.(env|sql|sh|md|gitignore)$">
        Require all denied
    </FilesMatch>

    ErrorLog  \${APACHE_LOG_DIR}/inventory_error.log
    CustomLog \${APACHE_LOG_DIR}/inventory_access.log combined
</VirtualHost>
EOF
    echo "  Virtual host config written: $VHOST_CONF"
else
    echo "  Virtual host config already exists: $VHOST_CONF"
fi

# Enable site and reload Apache
if command -v a2ensite &>/dev/null; then
    sudo a2ensite inventory.conf 2>/dev/null && echo "  Site enabled." || true
fi

# apache2ctl writes "Syntax OK" to stderr — merge streams before grepping
if sudo apache2ctl configtest 2>&1 | grep -q "Syntax OK"; then
    sudo systemctl reload apache2 2>/dev/null && echo "  Apache reloaded." || true
else
    echo -e "${YELLOW}  Warning: Apache config test failed — check config before reloading.${NC}"
    sudo apache2ctl configtest 2>&1 | tail -5
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
echo "  Document root: $DOCROOT"
echo "  Database:     $DB_NAME @ $DB_HOST"
echo "  Config:       .env"
echo ""
echo "  Default logins (change passwords immediately!):"
echo "    Admin:      admin / admin"
echo "    Supervisor: special / special"
echo "    User:       user / user"
echo ""
echo "  Access via:   http://${VHOST_NAME}:${VHOST_PORT}"
echo "             or http://localhost:${VHOST_PORT}  (from this machine)"
echo ""
echo "  Documentation: docs/README.md"
echo "  Blueprint:     Blueprint_Overview.html"
echo ""
