#!/bin/sh
set -e

echo "--- Docker Entrypoint Script Started ---"

# 1. Install PHP dependencies
echo "Installing Composer dependencies..."
composer install --no-interaction --prefer-dist --optimize-autoloader

# 2. Wait for MySQL database to be ready
echo "Waiting for MySQL database to be ready..."
/usr/bin/php /var/www/html/includes/db_check.php
while [ $? -ne 0 ]; do
    echo "MySQL not yet available, retrying in 2 seconds..."
    sleep 2
    /usr/bin/php /var/www/html/includes/db_check.php
done
echo "MySQL database is ready."

# 3. Run database setup script
echo "Running database setup script..."
/usr/bin/php /var/www/html/database_setup.php

# 4. Ensure correct permissions for uploads
echo "Setting permissions for uploads directory..."
mkdir -p /var/www/html/uploads/icons
mkdir -p /var/www/html/uploads/map_backgrounds
chown -R www-data:www-data /var/www/html/uploads
chmod -R 755 /var/www/html/uploads

# 5. Start cron service in the background
echo "Starting cron service..."
cron -f &

# 6. Start Apache in the foreground
echo "Starting Apache in foreground..."
exec apache2-foreground

echo "--- Docker Entrypoint Script Finished ---"