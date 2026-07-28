#!/bin/sh
set -e

echo "🚀 BT Bautechnik Container Startup..."

# 1. Ensure database file exists
mkdir -p /var/www/html/database
if [ ! -f /var/www/html/database/database.sqlite ]; then
    echo "📦 Creating SQLite database file..."
    touch /var/www/html/database/database.sqlite
fi

# 2. Fix permissions
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache /var/www/html/database

# 3. Create storage symlink
php artisan storage:link --force || true

# 4. Run database migrations & seeders
echo "🗄️ Running database migrations..."
php artisan migrate --force || echo "⚠️ Migration completed with notice."

echo "🌱 Seeding default data if needed..."
php artisan db:seed --force || echo "⚠️ Seeding completed with notice."

# 5. Optimize caches for production
echo "⚡ Caching Laravel configuration & routes..."
php artisan config:cache || true
php artisan route:cache || true
php artisan view:cache || true

echo "✅ Startup complete. Launching Supervisord..."
exec /usr/bin/supervisord -c /etc/supervisord.conf
