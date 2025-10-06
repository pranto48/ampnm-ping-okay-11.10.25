# Network Monitor Docker Deployment

This repository contains a Dockerized version of the Network Monitor application with MySQL database integration.

## Quick Start

### Using Docker Compose (Recommended)

1. Create a `docker-compose.yml` file:
```yaml
version: '3.8'

services:
  app:
    image: your-dockerhub-username/network-monitor:latest
    ports:
      - "3000:3000"
    environment:
      - DB_HOST=db
      - DB_USER=user
      - DB_PASSWORD=password
      - DB_NAME=network_monitor
    depends_on:
      db:
        condition: service_healthy
    restart: unless-stopped

  db:
    image: mysql:8.0
    environment:
      MYSQL_ROOT_PASSWORD: rootpassword
      MYSQL_DATABASE: network_monitor
      MYSQL_USER: user
      MYSQL_PASSWORD: password
    volumes:
      - db_data:/var/lib/mysql
    restart: unless-stopped
    healthcheck:
      test: ["CMD", "mysqladmin", "ping", "-h", "localhost"]
      timeout: 20s
      retries: 10

volumes:
  db_data:
```

2. Run the application:
```bash
docker-compose up -d
```

3. Access the application at `http://localhost:3000`
   - Default login: admin / admin

### Using Docker Run

```bash
# Start MySQL database
docker run -d \
  --name network-monitor-db \
  -e MYSQL_ROOT_PASSWORD=rootpassword \
  -e MYSQL_DATABASE=network_monitor \
  -e MYSQL_USER=user \
  -e MYSQL_PASSWORD=password \
  -v db_data:/var/lib/mysql \
  mysql:8.0

# Start the application
docker run -d \
  --name network-monitor-app \
  -p 3000:3000 \
  -e DB_HOST=network-monitor-db \
  -e DB_USER=user \
  -e DB_PASSWORD=password \
  -e DB_NAME=network_monitor \
  --link network-monitor-db \
  your-dockerhub-username/network-monitor:latest
```

## Environment Variables

| Variable | Description | Default |
|----------|-------------|---------|
| DB_HOST | Database host | localhost |
| DB_USER | Database user | root |
| DB_PASSWORD | Database password |  |
| DB_NAME | Database name | network_monitor |
| DB_PORT | Database port | 3306 |

## Building from Source

1. Clone the repository
2. Build the Docker image:
```bash
docker build -t your-dockerhub-username/network-monitor:latest .
```

3. Push to Docker Hub:
```bash
docker push your-dockerhub-username/network-monitor:latest
```

## Features

- Real-time network device monitoring
- Interactive network map visualization
- Ping history and performance charts
- Device status tracking
- Responsive web interface
- MySQL database integration

## Default Credentials

- Username: `admin`
- Password: `admin`

**Important**: Change the default password after first login for security.

## Health Checks

The application includes health check endpoints:
- Application: `http://localhost:3000/health`
- Database: Built-in MySQL health check

## Volumes

The MySQL database uses a named volume `db_data` to persist data between container restarts.

## Networking

The application exposes port 3000 for the web interface.