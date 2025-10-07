#!/bin/sh
# This script ensures the application waits for the database to be fully ready before starting.
set -e

# Use environment variables passed from docker-compose
DB_HOST_VAR="$DB_HOST"
TIMEOUT=60 # seconds
ELAPSED=0

echo "--- Docker Entrypoint Script Started ---"
echo "Waiting for database service at $DB_HOST_VAR (timeout: ${TIMEOUT}s)..."

# Loop until our custom PHP db_check script is successful or timeout is reached
until php /var/www/html/includes/db_check.php; do
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