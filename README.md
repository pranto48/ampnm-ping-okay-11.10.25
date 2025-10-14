# Local Network Monitor - PHP/MySQL Version

## Requirements
- XAMPP installed on your computer (for local development outside Docker)
- PHP 7.4 or higher
- MySQL/MariaDB
- Docker and Docker Compose (for production/containerized deployment)

## Installation Steps (Local XAMPP)

1.  **Start XAMPP**
    -   Open XAMPP Control Panel
    -   Start Apache and MySQL services

2.  **Place Files in htdocs**
    -   Create a new folder in `C:\xampp\htdocs\network-monitor\`
    -   Copy all files to this folder:
        -   `index.php` (main dashboard)
        -   `devices.php` (device management)
        -   `history.php` (ping history with filtering)
        -   `api.php` (AJAX API endpoints)
        -   `export.php` (CSV export functionality)
        -   `config.php` (configuration file)
        -   `database_setup.php` (database setup script)
        -   `README.md` (this file)

3.  **Setup Database**
    -   Open your browser and go to: `http://localhost/network-monitor/database_setup.php`
    -   This will automatically create the database and tables

4.  **Access Application**
    -   Open your browser and go to: `http://localhost/network-monitor/`
    -   The network monitor application will load

## Installation Steps (Docker Compose)

1.  **Ensure Docker and Docker Compose are installed.**
2.  **Configure Environment Variables:**
    -   **For the `app` service (Network Monitor):**
        -   `ADMIN_PASSWORD`: Set the admin user's password.
3.  **Build and Run:**
    ```bash
    docker-compose up --build -d
    ```
4.  **Access Application:**
    -   **Network Monitor:** Open your browser and go to: `http://localhost:2266`

## Features
- Ping any host or IP address
- View ping history stored in MySQL database
- Monitor local network devices
- Real-time network status monitoring
- Device management (add, remove, check status)
- Historical data with filtering and pagination
- Export data to CSV
- Responsive design with Tailwind CSS
- AJAX-powered interface for smooth interactions
- **Local License Management:** License checks are now handled directly within the application's MySQL database.

## Usage
1.  **Dashboard**: Main overview of network status and recent activity
2.  **Device Management**: Add/remove devices and check their status
3.  **Ping History**: View historical ping results with filtering and export options
4.  **Real-time Updates**: AJAX-powered updates without page refresh

## Security Notes
- This is designed for local network use primarily.
- The database uses default XAMPP credentials (root with no password) or Docker environment variables.
- For production use, always change database credentials and ensure strong passwords.

## Troubleshooting
1.  If ping doesn't work, ensure PHP can execute shell commands (especially in Docker).
2.  Check that Apache and MySQL are running (XAMPP) or Docker containers are healthy.
3.  Verify database connection in `config.php` if needed.
4.  Make sure your firewall allows ping requests.