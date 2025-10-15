<?php
require_once 'includes/functions.php';

// Ensure customer is logged in
if (!isCustomerLoggedIn()) {
    http_response_code(403);
    echo "Unauthorized access.";
    exit;
}

$license_key = $_GET['license_key'] ?? null;

if (!$license_key) {
    http_response_code(400);
    echo "License key is required.";
    exit;
}

// Define the LICENSE_API_URL for the AMPNM app
// This should point to the verify_license.php endpoint on your portal
$license_api_url = 'https://portal.itsupport.com.bd/verify_license.php'; // Ensure this matches your deployment

$docker_compose_content = <<<EOT
version: '3.8'

services:
  app:
    build:
      context: .
      dockerfile: Dockerfile
    entrypoint: ["/bin/sh", "-c", "chmod +x /var/www/html/docker-entrypoint.sh && /var/www/html/docker-entrypoint.sh"]
    volumes:
      - ./:/var/www/html/
    depends_on:
      db:
        condition: service_healthy
    environment:
      - DB_HOST=127.0.0.1
      - DB_NAME=network_monitor
      - DB_USER=user
      - DB_PASSWORD=password
      - MYSQL_ROOT_PASSWORD=rootpassword
      - ADMIN_PASSWORD=password
      - LICENSE_API_URL={$license_api_url}
      - APP_LICENSE_KEY={$license_key}
    ports:
      - "2266:2266"
    restart: unless-stopped

  db:
    image: mysql:8.0
    command: --default-authentication-plugin=mysql_native_password
    environment:
      MYSQL_ROOT_PASSWORD: rootpassword
      MYSQL_DATABASE: network_monitor
      MYSQL_USER: user
      MYSQL_PASSWORD: password
    volumes:
      - db_data:/var/lib/mysql
    ports:
      - "3306:3306"
    restart: unless-stopped
    healthcheck:
      test: ["CMD-SHELL", "mysqladmin ping -h localhost -u root -p\$\$MYSQL_ROOT_PASSWORD"]
      interval: 10s
      timeout: 5s
      retries: 10

volumes:
  db_data:
EOT;

header('Content-Type: application/x-yaml');
header('Content-Disposition: attachment; filename="docker-compose.yml"');
echo $docker_compose_content;
exit;
?>