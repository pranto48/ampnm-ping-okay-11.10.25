# Use an official PHP image with Apache
FROM php:8.1-apache

# Set working directory
WORKDIR /var/www/html

# Install system dependencies required for the application and Composer
# nmap is needed for network scanning, iputils-ping for ping command, cron for background jobs
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev \
    iputils-ping \
    nmap \
    cron \
    && docker-php-ext-install zip pdo pdo_mysql

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy the rest of the application files
COPY . .

# Add cron job
COPY cron/ampnm-cron /etc/cron.d/ampnm-cron
RUN chmod 0644 /etc/cron.d/ampnm-cron
RUN crontab /etc/cron.d/ampnm-cron

# The entrypoint script will handle Composer install and starting the server.
# Ensure the entrypoint script is executable.
RUN chmod +x /var/www/html/docker-entrypoint.sh