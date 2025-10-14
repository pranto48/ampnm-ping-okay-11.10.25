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

# Add custom Apache configuration
COPY docker/apache-conf/000-default.conf /etc/apache2/sites-available/000-default.conf

# Enable mod_rewrite and the default site configuration
RUN a2enmod rewrite && a2ensite 000-default.conf

# Add cron job
COPY cron/ampnm-cron /etc/cron.d/ampnm-cron
RUN chmod 0644 /etc/cron.d/ampnm-cron
RUN crontab /etc/cron.d/ampnm-cron

# The entrypoint script will handle Composer install and starting the server.
# Ensure the entrypoint script is executable.
RUN chmod +x /var/www/html/docker-entrypoint.sh