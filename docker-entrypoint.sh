#!/bin/sh
# This script ensures the application waits for the database to be fully ready before starting.
set -e

# Use environment variables passed from docker-compose
DB_HOST_VAR="$DB_HOST"
ROOT_PASSWORD_VAR="$MYSQL_ROOT_PASSWORD"
TIMEOUT=60 # seconds
ELAPSED=0

echo "--- Docker Entrypoint Script Started ---"
echo "Database Host: $DB_HOST_VAR"
echo "Waiting for database to become available (timeout: ${TIMEOUT}s)..."

# Loop until the mysqladmin ping command is successful or timeout is reached
while ! mysqladmin ping -h"$DB_HOST_VAR" -u"root" -p"$ROOT_PASSWORD_VAR" --silent; do
    if [ $ELAPSED -ge $TIMEOUT ]; then
        echo "❌ Database connection timeout after $TIMEOUT seconds."
        echo "Please check the database container logs for errors."
        exit 1
    fi
    echo "Database is not yet available. Retrying in 2 seconds..."
    sleep 2
    ELAPSED=$(($ELAPSED + 2))
done

echo "✅ Database connection successful!"

# Run the database setup script to create/migrate tables automatically.
echo "Running database setup script (database_setup.php)..."
php /var/www/html/database_setup.php
echo "✅ Database setup script finished."

echo "--- Handing over to Apache Web Server ---"
# Execute the main container command (CMD) which is 'apache2-foreground'
exec "$@"