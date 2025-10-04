# Use an official PHP image with Apache
FROM php:8.2-apache

# Install necessary system dependencies and PHP extensions
RUN apt-get update && apt-get install -y \
    libzip-dev \
    unzip \
    default-mysql-client \
    curl \
    sed \
    && docker-php-ext-install pdo_mysql zip

# Set the working directory
WORKDIR /var/www/html

# Copy application files explicitly to avoid cache key issues
COPY api.php .
COPY config.php .
COPY database_setup.php .
COPY devices.php .
COPY export.php .
COPY footer.php .
COPY header.php .
COPY history.php .
COPY index.php .
COPY map.php .

# Copy application directories
COPY api ./api
COPY assets ./assets
COPY includes ./includes

# Set the correct permissions for the web server
RUN chown -R www-data:www-data /var/www/html

# Copy and set permissions for the custom entrypoint script
COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh
# Fix Windows line endings that can cause script execution errors
RUN sed -i 's/\r$//' /usr/local/bin/docker-entrypoint.sh

# Set the entrypoint
ENTRYPOINT ["docker-entrypoint.sh"]

# The default command is to start Apache
CMD ["apache2-foreground"]