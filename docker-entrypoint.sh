#!/bin/sh

# Wait for the database to be ready
echo "Waiting for database to be ready..."
while ! mysqladmin ping -h"db" --silent; do
    sleep 1
done
echo "Database is ready."

# Run the database setup script
echo "Running database setup..."
curl http://localhost/database_setup.php
echo "Database setup complete."

# Execute the default command (start apache)
exec "$@"