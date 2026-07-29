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
php artisan migrate --force

echo "🌱 Seeding default data if needed..."
php artisan db:seed --force || true

# 5. Optimize caches for production
echo "⚡ Caching Laravel configuration & routes..."
php artisan config:clear || true
php artisan route:clear || true
php artisan view:clear || true
php artisan config:cache || true
php artisan route:cache || true
php artisan view:cache || true

# 6. Ensure full write permissions for www-data on storage & logs
echo "🔒 Securing storage & log permissions for www-data..."
mkdir -p /var/www/html/storage/logs
touch /var/www/html/storage/logs/laravel.log
chmod -R 777 /var/www/html/storage /var/www/html/bootstrap/cache /var/www/html/database || true
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache /var/www/html/database || true

echo "✅ Startup complete. Launching Supervisord..."
exec /usr/bin/supervisord -c /etc/supervisord.conf
