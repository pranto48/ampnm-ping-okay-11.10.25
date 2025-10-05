# Use the official PHP 8.2 image with Apache
FROM php:8.2-apache

# Set working directory
WORKDIR /var/www/html

# Install system dependencies
# - pdo_mysql for database connection
# - curl for http checks
# - iputils-ping for the ping command
# - nmap for network scanning
# - libcap2-bin to grant network capabilities
RUN apt-get update && apt-get install -y \
    libzip-dev \
    zip \
    unzip \
    curl \
    iputils-ping \
    nmap \
    libcap2-bin \
    && docker-php-ext-install pdo_mysql zip

# Grant ping the necessary capabilities to run without root
RUN setcap cap_net_raw+ep /bin/ping

# Copy application source
COPY . /var/www/html

# Copy custom entrypoint script
COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# Expose port 80 and start apache
EXPOSE 80
ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["apache2-foreground"]