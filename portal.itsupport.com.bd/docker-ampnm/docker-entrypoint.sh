#!/bin/bash
set -e

# Check if the database is up and running
/var/www/html/ampnm-app-source/includes/db_check.php
while [ $? -ne 0 ]; do
    echo "Waiting for database to be ready..."
    sleep 5
    /var/www/html/ampnm-app-source/includes/db_check.php
done

echo "Database is ready. Running setup script..."

# Run the database setup script
php /var/www/html/ampnm-app-source/database_setup.php

echo "Database setup script finished. Starting Apache..."

# Start Apache in the foreground
exec apache2-foreground