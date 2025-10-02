# Network Monitor - PHP Installation Guide

## Requirements
- XAMPP installed on your computer
- PHP 7.4 or higher
- MySQL/MariaDB

## Installation Steps

1. **Start XAMPP**
   - Open XAMPP Control Panel
   - Start Apache and MySQL services

2. **Place Files in htdocs**
   - Copy all PHP files to: `C:\xampp\htdocs\network-monitor\` (create the folder)
   - Files needed:
     - `index.php` (main application)
     - `database_setup.php` (database setup script)

3. **Setup Database**
   - Open your browser and go to: `http://localhost/network-monitor/database_setup.php`
   - This will automatically create the database and table

4. **Access Application**
   - Open your browser and go to: `http://localhost/network-monitor/`
   - The network monitor application will load

## Features
- Ping any host or IP address
- View ping history stored in MySQL database
- Real-time network status monitoring
- Responsive design with Tailwind CSS

## Security Notes
- This is designed for local network use only
- The database uses default XAMPP credentials (root with no password)
- For production use, change database credentials and add authentication

## Troubleshooting
1. If ping doesn't work, ensure PHP can execute shell commands
2. Check that Apache and MySQL are running in XAMPP
3. Verify database connection in the code if needed