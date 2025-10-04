#!/bin/sh
# docker-entrypoint.sh

# Abort on any error
set -e

# Wait for the database to be ready
echo "Waiting for database to be ready..."
until mysqladmin ping -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASSWORD" --silent; do
    echo "Database is unavailable - sleeping"
    sleep 2
done

echo "Database is up - continuing..."

# Run the database setup script automatically
# This will create the database and tables if they don't exist
echo "Running database setup..."
php /var/www/html/database_setup.php

echo "Database setup complete."

# Execute the main command (e.g., apache2-foreground)
exec "$@"