# Use an official PHP image with Apache
FROM php:8.2-apache

# Install necessary system dependencies and PHP extensions
RUN apt-get update && apt-get install -y \
    libzip-dev \
    unzip \
    default-mysql-client \
    curl \
    && docker-php-ext-install pdo_mysql zip

# Copy application source code to the web server's root directory
COPY . /var/www/html/

# Set the correct permissions for the web server
RUN chown -R www-data:www-data /var/www/html

# Copy and set permissions for the custom entrypoint script
COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# Set the entrypoint
ENTRYPOINT ["docker-entrypoint.sh"]

# The default command is to start Apache
CMD ["apache2-foreground"]