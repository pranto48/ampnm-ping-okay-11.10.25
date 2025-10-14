# Use the official PHP image with Apache
FROM php:8.2-apache

# Install system dependencies
# - libzip-dev and zlib1g-dev for zip extension
# - iputils-ping for the ping command
# - nmap for network scanning
# - default-mysql-client for database health checks
RUN apt-get update && apt-get install -y \
    libzip-dev \
    zlib1g-dev \
    iputils-ping \
    nmap \
    default-mysql-client \
    git \
    unzip \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install pdo pdo_mysql zip

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set the working directory
WORKDIR /var/www/html

# Copy composer files and install dependencies
COPY composer.json composer.lock* ./
RUN composer install --no-dev --optimize-autoloader

# Copy the rest of the application files
COPY . .

# Ensure the entrypoint script is executable
RUN chmod +x /var/www/html/docker-entrypoint.sh

# Expose port 80
EXPOSE 80