# Use the official PHP image with Apache
FROM php:8.2-apache

# Install system dependencies
RUN apt-get update && apt-get install -y \
    libpq-dev \
    iputils-ping \
    nmap \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions for PostgreSQL
RUN docker-php-ext-install pdo pdo_pgsql

# Copy application source
COPY . /var/www/html/

# Set permissions for Apache
RUN chown -R www-data:www-data /var/www/html
RUN a2enmod rewrite

# Expose port 80
EXPOSE 80