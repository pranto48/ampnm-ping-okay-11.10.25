#!/bin/sh
# This script ensures the application waits for the database to be fully ready before starting.
set -e

# Use the root password environment variable passed from docker-compose
DB_HOST_VAR="$DB_HOST"
ROOT_PASSWORD_VAR="$MYSQL_ROOT_PASSWORD"

echo "--- Docker Entrypoint Script Started ---"
echo "Database Host: $DB_HOST_VAR"
echo "Waiting for database to become available..."

# Loop until the mysqladmin ping command is successful
# We use the 'root' user here because the application user may not exist on first startup.
while ! mysqladmin ping -h"$DB_HOST_VAR" -u"root" -p"$ROOT_PASSWORD_VAR" --silent; do
    echo "Database is not yet available. Retrying in 2 seconds..."
    sleep 2
done

echo "✅ Database connection successful!"

# Run the database setup script to create/migrate tables automatically.
echo "Running database setup script (database_setup.php)..."
php /var/www/html/database_setup.php
echo "✅ Database setup script finished."

echo "--- Handing over to Apache Web Server ---"
# Execute the main container command (CMD) which is 'apache2-foreground'
exec "$@"