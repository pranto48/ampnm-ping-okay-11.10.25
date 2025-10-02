# Local Network Monitor - PHP/MySQL Version

## Requirements
- XAMPP installed on your computer
- PHP 7.4 or higher
- MySQL/MariaDB

## Installation Steps

1. **Start XAMPP**
   - Open XAMPP Control Panel
   - Start Apache and MySQL services

2. **Place Files in htdocs**
   - Create a new folder in `C:\xampp\htdocs\network-monitor\`
   - Copy all files to this folder:
     - `index.php` (main application)
     - `config.php` (configuration file)
     - `database_setup.php` (database setup script)
     - `README.md` (this file)

3. **Setup Database**
   - Open your browser and go to: `http://localhost/network-monitor/database_setup.php`
   - This will automatically create the database and tables

4. **Access Application**
   - Open your browser and go to: `http://localhost/network-monitor/`
   - The network monitor application will load

## Features
- Ping any host or IP address
- View ping history stored in MySQL database
- Monitor local network devices
- Real-time network status monitoring
- Responsive design with Tailwind CSS

## Usage
1. **Ping Test**: Enter any IP address or hostname and click "Ping" to test connectivity
2. **Device Monitoring**: The application will automatically monitor devices on your network
3. **History**: All ping results are stored in the database for historical analysis
4. **Refresh**: Click the "Refresh" button to update device statuses

## Security Notes
- This is designed for local network use only
- The database uses default XAMPP credentials (root with no password)
- For production use, change database credentials and add authentication

## Troubleshooting
1. If ping doesn't work, ensure PHP can execute shell commands
2. Check that Apache and MySQL are running in XAMPP
3. Verify database connection in config.php if needed
4. Make sure your firewall allows ping requests