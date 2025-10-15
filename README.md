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
        -   `LICENSE_API_URL`: This should point to the external URL of your license verification service (e.g., `http://portal.itsupport.com.bd/verify_license.php`).
        -   `APP_LICENSE_KEY`: This is the unique license key for this deployment, which will be verified by your external license service.
    -   **For the `db` service:**
        -   `MYSQL_ROOT_PASSWORD`, `MYSQL_DATABASE`, `MYSQL_USER`, `MYSQL_PASSWORD` for the main AMPNM app's database.
3.  **Build and Run:**
    ```bash
    docker-compose up --build -d
    ```
4.  **Access Applications:**
    -   **Network Monitor:** Open your browser and go to: `http://localhost:2266`
    -   **External License Service:** This will be hosted separately (e.g., `http://portal.itsupport.com.bd`).

## External License Verification Service Setup (portal.itsupport.com.bd)

This service will run on a separate server (e.g., your cPanel hosting) and use its own MySQL database to manage licenses.

1.  **Upload Files:**
    -   Create a directory (e.g., `license-service`) on your cPanel hosting.
    -   Upload `portal.itsupport.com.bd/config.php`, `portal.itsupport.com.bd/license_setup.php`, and `portal.itsupport.com.bd/verify_license.php` to this directory.
    -   Ensure these files are accessible via a URL like `http://portal.itsupport.com.bd/license-service/`.

2.  **Run Setup Script:**
    -   Open your browser and navigate to `http://portal.itsupport.com.bd/license-service/license_setup.php`.
    -   **Step 1: Configure Database Connection:** Enter your MySQL database host, name, username, and password for the *license service's database*. This will create the database (if it doesn't exist) and save the credentials to `config.php`.
    -   **Step 2: Setup License Tables & Add Initial License:** After configuring the database, you can create the `licenses` table and optionally add an initial license key with its `max_devices` and `expires_at` date.

3.  **Verify Endpoint:**
    -   You can test the verification endpoint by visiting `http://portal.itsupport.com.bd/license-service/verify_license.php` (though it expects POST data, so a direct browser visit will likely show an error).

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
- **External License Validation Service:** License checks are now handled by a dedicated microservice using its own MySQL database.

## Usage
1.  **Dashboard**: Main overview of network status and recent activity
2.  **Device Management**: Add/remove devices and check their status
3.  **Ping History**: View historical ping results with filtering and export options
4.  **Real-time Updates**: AJAX-powered updates without page refresh

## Security Notes
- This is designed for local network use primarily.
- The database uses default XAMPP credentials (root with no password) or Docker environment variables.
- For production use, always change database credentials and ensure strong passwords.
- **License API:** Ensure your external License Validation API is secure and protected. The `license-service` should have its own secure MySQL database for license keys.

## Troubleshooting
1.  If ping doesn't work, ensure PHP can execute shell commands (especially in Docker).
2.  Check that Apache and MySQL are running (XAMPP) or Docker containers are healthy.
3.  Verify database connection in `config.php` if needed.
4.  Make sure your firewall allows ping requests.
5.  **License Validation Issues:**
    -   Ensure `LICENSE_API_URL` and `APP_LICENSE_KEY` are correctly set for the `app` service in `docker-compose.yml`.
    -   Verify that the external license service's MySQL database is correctly configured and the `licenses` table exists with valid license keys.
    -   Check PHP error logs for the external license service for any MySQL connection or query errors.
    -   Ensure the external license service is accessible from your AMPNM Docker container (network connectivity).