#!/bin/sh
# docker-entrypoint.sh

# Abort on any error
set -e

echo "Container started, running database setup..."
# Run the database setup script automatically
php /var/www/html/database_setup.php
echo "Database setup complete."

# Execute the main command (e.g., apache2-foreground)
exec "$@"