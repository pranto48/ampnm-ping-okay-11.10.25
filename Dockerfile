FROM php:8.2-apache

# Install system dependencies required for PHP extensions and nmap
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev \
    libonig-dev \
    nmap \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install pdo_mysql zip mbstring

# Enable Apache modules
RUN a2enmod rewrite

# Copy custom Apache configuration
COPY docker/apache-conf/000-default.conf /etc/apache2/sites-available/000-default.conf

# Copy the custom entrypoint script
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# Set working directory
WORKDIR /var/www/html

# Expose port 80 (default for Apache)
EXPOSE 80

# Use the custom entrypoint script
ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
CMD ["apache2-foreground"]