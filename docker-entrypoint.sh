#!/bin/bash
set -e

# Wait for database to be ready using Symfony console
echo "Waiting for database..."
max_retries=30
count=0
until php bin/console dbal:run-sql "SELECT 1" > /dev/null 2>&1; do
    count=$((count + 1))
    if [ $count -ge $max_retries ]; then
        echo "Database not ready after $max_retries attempts, proceeding anyway..."
        break
    fi
    echo "Waiting for database... ($count/$max_retries)"
    sleep 2
done
echo "Database connection ready!"

# Run migrations
echo "Running database migrations..."
php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration || true

# Clear and warm up cache
echo "Warming up cache..."
php bin/console cache:clear --no-warmup || true
php bin/console cache:warmup || true

# Fix permissions for var directory
chown -R www-data:www-data /var/www/html/var

echo "Application ready!"

# Execute the main command
exec "$@"
