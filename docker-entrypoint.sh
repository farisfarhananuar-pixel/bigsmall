#!/bin/bash
set -e

# Write .env from environment variables (Railway injects these)
cat > /var/www/html/.env << EOF
APP_NAME="Big Small Game"
APP_ENV=${APP_ENV:-production}
APP_KEY=${APP_KEY:-}
APP_DEBUG=${APP_DEBUG:-false}
APP_URL=${APP_URL:-http://localhost}

LOG_CHANNEL=stderr
LOG_LEVEL=error

DB_CONNECTION=mysql
DB_HOST=${MYSQLHOST:-127.0.0.1}
DB_PORT=${MYSQLPORT:-3306}
DB_DATABASE=${MYSQLDATABASE:-railway}
DB_USERNAME=${MYSQLUSER:-root}
DB_PASSWORD=${MYSQLPASSWORD:-}

SESSION_DRIVER=file
SESSION_LIFETIME=120
CACHE_STORE=file
QUEUE_CONNECTION=sync
EOF

cd /var/www/html

# Generate APP_KEY if not set
if [ -z "$APP_KEY" ]; then
    php artisan key:generate --no-interaction
fi

# Run migrations
echo "Running migrations..."
php artisan migrate --force --no-interaction

# Seed only if DB is empty (first deploy)
php artisan db:seed --force --no-interaction 2>/dev/null || true

# Clear & cache config
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "✅ Big Small Game ready!"

# Start Apache
exec "$@"
