#!/bin/sh
set -e

# Run composer install to ensure all dependencies are present
echo "Installing Composer dependencies..."
composer install --no-interaction --optimize-autoloader

# Wait for the database to be ready.
# This is a simple loop that uses a separate script to check DB connection.
echo "Waiting for database to be ready..."
until php includes/db_check.php; do
  >&2 echo "Database is unavailable - sleeping"
  sleep 1
done
>&2 echo "Database is up - continuing..."

# Run the database setup/migration script
# This will create tables and the admin user if they don't exist.
echo "Running database setup..."
php database_setup.php

# Start the cron service in the background
echo "Starting cron service for background monitoring..."
service cron start

# Start Apache in the foreground
echo "Starting Apache server..."
exec apache2-foreground