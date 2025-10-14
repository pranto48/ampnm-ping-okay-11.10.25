#!/bin/bash
set -e

# Disable default Apache site if it exists
a2dissite 000-default.conf || true # Ignore error if it's already disabled

# Enable our custom site
a2ensite 000-default.conf

# Ensure mod_rewrite is enabled (already done in Dockerfile, but good to double check)
a2enmod rewrite

# Run composer install if composer.json exists
if [ -f "composer.json" ]; then
    echo "Running composer install..."
    composer install --no-dev --optimize-autoloader
fi

# Execute the main command (apache2-foreground)
exec "$@"