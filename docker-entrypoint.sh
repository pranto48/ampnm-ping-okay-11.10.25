#!/bin/sh
set -e

# Wait for the database to be ready using root credentials
echo "Waiting for database connection..."
while ! mysqladmin ping -h"$DB_HOST" -u"root" -p"$MYSQL_ROOT_PASSWORD" --silent; do
    echo "Still waiting for DB..."
    sleep 1
done
echo "Database is up!"

# Run the database setup script automatically
# This script creates the DB, tables, and the application user if they don't exist.
echo "Running database setup/migration..."
php /var/www/html/database_setup.php
echo "Database setup complete."

# Execute the main container command (apache2-foreground)
exec "$@"