<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database configuration
require_once __DIR__ . '/../config.php'; // Adjusted path to config.php

// PHPMailer Autoload (assuming it's installed via Composer)
require_once __DIR__ . '/../vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Function to get database connection (defined in config.php)
// function getLicenseDbConnection() is already defined in config.php

// Function to check if the license database connection is active
function checkLicenseDbConnection() {
    try {
        $pdo = getLicenseDbConnection();
        $pdo->query("SELECT 1"); // A simple query to check connection
        return true;
    } catch (PDOException $e) {
        error_log("License DB connection check failed: " . $e->getMessage());
        return false;
    }
}

// Helper function to check if a table exists in the current database connection
function tableExists($pdo, $tableName) {
    try {
        $result = $pdo->query("SELECT 1 FROM `$tableName` LIMIT 1");
    } catch (PDOException $e) {
        // We only care about "table not found" errors
        if (strpos($e->getMessage(), 'Base table or view not found') !== false) {
            return false;
        }
        // For other errors, re-throw or log
        throw $e;
    }
    return $result !== false;
}

// Function to generate a unique license key
function generateLicenseKey($prefix = 'AMPNM') {
    // Generate a UUID (Universally Unique Identifier)
    // This is a simple way to get a unique string. For stronger keys, consider more complex algorithms.
    $data = random_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // set version to 0100
    $data[8] = chr(ord(ord($data[8]) & 0x3f | 0x80)); // set bits 6-7 to 10
    $uuid = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    return strtoupper($prefix . '-' . $uuid);
}

// --- Application Settings Functions ---
function getAppLicenseKey() {
    $pdo = getDbConnection();
    // Check if app_settings table exists before querying
    if (!tableExists($pdo, 'app_settings')) {
        return null;
    }
    $stmt = $pdo->prepare("SELECT setting_value FROM `app_settings` WHERE setting_key = 'app_license_key'");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ? $result['setting_value'] : null;
}

function setAppLicenseKey($license_key) {
    $pdo = getDbConnection();
    // Use UPSERT (INSERT ... ON DUPLICATE KEY UPDATE) to either insert or update the key
    $stmt = $pdo->prepare("INSERT INTO `app_settings` (setting_key, setting_value) VALUES ('app_license_key', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
    return $stmt->execute([$license_key, $license_key]);
}

// --- Customer Authentication Functions ---
function authenticateCustomer($email, $password) {
    $pdo = getLicenseDbConnection();
    $stmt = $pdo->prepare("SELECT id, password, first_name, last_name, email FROM `customers` WHERE email = ?");
    $stmt->execute([$email]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($customer && password_verify($password, $customer['password'])) {
        $_SESSION['customer_id'] = $customer['id'];
        $_SESSION['customer_email'] = $customer['email'];
        $_SESSION['customer_name'] = $customer['first_name'] . ' ' . $customer['last_name'];
        return true;
    }
    return false;
}

function isCustomerLoggedIn() {
    return isset($_SESSION['customer_id']);
}

function logoutCustomer() {
    unset($_SESSION['customer_id']);
    unset($_SESSION['customer_email']);
    unset($_SESSION['customer_name']);
    session_destroy();
    session_start(); // Start a new session for potential new login
}

/**
 * Updates a customer's password.
 *
 * @param int $customer_id The ID of the customer.
 * @param string $current_password The current password provided by the customer.
 * @param string $new_password The new password to set.
 * @return bool True on success, false on failure (e.g., current password mismatch).
 */
function updateCustomerPassword($customer_id, $current_password, $new_password) {
    $pdo = getLicenseDbConnection();
    
    // First, verify the current password
    $stmt = $pdo->prepare("SELECT password FROM `customers` WHERE id = ?");
    $stmt->execute([$customer_id]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$customer || !password_verify($current_password, $customer['password'])) {
        return false; // Current password mismatch
    }

    // Hash the new password and update
    $hashed_new_password = password_hash($new_password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("UPDATE `customers` SET password = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
    return $stmt->execute([$hashed_new_password, $customer_id]);
}


// --- Admin Authentication Functions ---
function authenticateAdmin($username, $password) {
    $pdo = getLicenseDbConnection();
    $stmt = $pdo->prepare("SELECT id, password, username, email FROM `admin_users` WHERE username = ?");
    $stmt->execute([$username]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($admin && password_verify($password, $admin['password'])) {
        $_SESSION['admin_id'] = $admin['id'];
        $_SESSION['admin_username'] = $admin['username'];
        $_SESSION['admin_email'] = $admin['email'];
        return true;
    }
    return false;
}

function isAdminLoggedIn() {
    return isset($_SESSION['admin_id']);
}

function logoutAdmin() {
    unset($_SESSION['admin_id']);
    unset($_SESSION['admin_username']);
    unset($_SESSION['admin_email']);
    session_destroy();
    session_start(); // Start a new session for potential new login
}

/**
 * Updates an admin user's password.
 *
 * @param int $admin_id The ID of the admin user.
 * @param string $current_password The current password provided by the admin.
 * @param string $new_password The new password to set.
 * @return bool True on success, false on failure (e.g., current password mismatch).
 */
function updateAdminPassword($admin_id, $current_password, $new_password) {
    $pdo = getLicenseDbConnection();
    
    // First, verify the current password
    $stmt = $pdo->prepare("SELECT password FROM `admin_users` WHERE id = ?");
    $stmt->execute([$admin_id]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$admin || !password_verify($current_password, $admin['password'])) {
        return false; // Current password mismatch
    }

    // Hash the new password and update
    $hashed_new_password = password_hash($new_password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("UPDATE `admin_users` SET password = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
    return $stmt->execute([$hashed_new_password, $admin_id]);
}


// --- Common Redirects ---
function redirectToLogin() {
    header('Location: login.php');
    exit;
}

function redirectToAdminLogin() {
    header('Location: adminpanel.php');
    exit;
}

function redirectToDashboard() {
    header('Location: dashboard.php');
    exit;
}

function redirectToAdminDashboard() {
    header('Location: admin/index.php'); // Assuming admin dashboard is in admin/index.php
    exit;
}

// Helper function to upload ticket attachments
function uploadTicketAttachments($files, $entity_id, $type = 'ticket') {
    $uploaded_paths = [];
    $upload_dir = __DIR__ . '/../uploads/tickets/'; // Relative to functions.php

    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    if (isset($files['name']) && is_array($files['name'])) {
        foreach ($files['name'] as $index => $name) {
            if ($files['error'][$index] === UPLOAD_ERR_OK) {
                $file_tmp_name = $files['tmp_name'][$index];
                $file_extension = pathinfo($name, PATHINFO_EXTENSION);
                $new_file_name = "{$type}_{$entity_id}_" . uniqid() . ".{$file_extension}";
                $destination = $upload_dir . $new_file_name;

                if (move_uploaded_file($file_tmp_name, $destination)) {
                    $uploaded_paths[] = 'uploads/tickets/' . $new_file_name; // Store relative path
                } else {
                    error_log("Failed to move uploaded file: {$name} to {$destination}");
                }
            } else {
                error_log("File upload error for {$name}: " . $files['error'][$index]);
            }
        }
    }
    return $uploaded_paths;
}


// --- Support Ticket System Functions ---

function createSupportTicket($customer_id, $subject, $message, $files = []) { // Added $files parameter
    $pdo = getLicenseDbConnection();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("INSERT INTO `support_tickets` (customer_id, subject, message, attachments) VALUES (?, ?, ?, ?)");
        // Placeholder for attachments, will update after getting ticket ID
        $stmt->execute([$customer_id, $subject, $message, '[]']);
        $ticket_id = $pdo->lastInsertId();

        $attachment_paths = [];
        if (!empty($files) && isset($files['name'][0]) && $files['name'][0] !== '') {
            $attachment_paths = uploadTicketAttachments($files, $ticket_id, 'ticket');
        }
        
        // Update the ticket with attachment paths
        $stmt_update = $pdo->prepare("UPDATE `support_tickets` SET attachments = ? WHERE id = ?");
        $stmt_update->execute([json_encode($attachment_paths), $ticket_id]);

        $pdo->commit();
        return true;
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Error creating support ticket: " . $e->getMessage());
        return false;
    }
}

function getTicketDetails($ticket_id, $customer_id = null) {
    $pdo = getLicenseDbConnection();
    $sql = "SELECT st.*, c.first_name, c.last_name, c.email FROM `support_tickets` st JOIN `customers` c ON st.customer_id = c.id WHERE st.id = ?";
    $params = [$ticket_id];
    if ($customer_id !== null) { // Restrict by customer_id if provided (for customer view)
        $sql .= " AND st.customer_id = ?";
        $params[] = $customer_id;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $ticket = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($ticket && isset($ticket['attachments'])) {
        $ticket['attachments'] = json_decode($ticket['attachments'], true);
        if (!is_array($ticket['attachments'])) {
            $ticket['attachments'] = []; // Ensure it's an array even if JSON is invalid
        }
    } else if ($ticket) {
        $ticket['attachments'] = [];
    }
    return $ticket;
}

function getTicketReplies($ticket_id) {
    $pdo = getLicenseDbConnection();
    $stmt = $pdo->prepare("SELECT * FROM `ticket_replies` WHERE ticket_id = ? ORDER BY created_at ASC");
    $stmt->execute([$ticket_id]);
    $replies = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($replies as &$reply) {
        if (isset($reply['attachments'])) {
            $reply['attachments'] = json_decode($reply['attachments'], true);
            if (!is_array($reply['attachments'])) {
                $reply['attachments'] = [];
            }
        } else {
            $reply['attachments'] = [];
        }
    }
    return $replies;
}

function addTicketReply($ticket_id, $sender_id, $sender_type, $message, $files = []) { // Added $files parameter
    $pdo = getLicenseDbConnection();
    $pdo->beginTransaction();
    try {
        $attachment_paths = [];
        if (!empty($files) && isset($files['name'][0]) && $files['name'][0] !== '') {
            $attachment_paths = uploadTicketAttachments($files, $ticket_id, 'reply'); // Use ticket_id for replies too
        }

        $stmt = $pdo->prepare("INSERT INTO `ticket_replies` (ticket_id, sender_id, sender_type, message, attachments) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$ticket_id, $sender_id, $sender_type, $message, json_encode($attachment_paths)]);
        
        // Update ticket's updated_at timestamp and status if it's a customer reply
        if ($sender_type === 'customer') {
            $stmt = $pdo->prepare("UPDATE `support_tickets` SET updated_at = CURRENT_TIMESTAMP, status = 'in progress' WHERE id = ?");
            $stmt->execute([$ticket_id]);
        } else if ($sender_type === 'admin') {
             $stmt = $pdo->prepare("UPDATE `support_tickets` SET updated_at = CURRENT_TIMESTAMP, status = 'in progress' WHERE id = ?");
            $stmt->execute([$ticket_id]);
        }
        $pdo->commit();

        // Send email notification
        $ticket = getTicketDetails($ticket_id);
        if ($ticket) {
            $recipient_email = ($sender_type === 'admin') ? $ticket['email'] : getAdminSmtpSettings()['from_email']; // Send to customer or admin from_email
            $subject = "Ticket #{$ticket_id} - New Reply: {$ticket['subject']}";
            $body = "A new reply has been added to your support ticket #{$ticket_id} - '{$ticket['subject']}'.\n\n";
            $body .= "Sender: " . ($sender_type === 'admin' ? 'Admin' : $ticket['first_name'] . ' ' . $ticket['last_name']) . "\n";
            $body .= "Message: {$message}\n\n";
            $body .= "View ticket: " . ($_SERVER['HTTP_HOST'] ?? 'localhost') . "/support.php?ticket_id={$ticket_id}\n";
            sendEmail($recipient_email, $subject, $body);
        }

        return true;
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Error adding ticket reply: " . $e->getMessage());
        return false;
    }
}

function updateTicketStatus($ticket_id, $status) {
    $pdo = getLicenseDbConnection();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("UPDATE `support_tickets` SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$status, $ticket_id]);
        $pdo->commit();

        // Send email notification
        $ticket = getTicketDetails($ticket_id);
        if ($ticket) {
            $recipient_email = $ticket['email'];
            $subject = "Ticket #{$ticket_id} - Status Updated to " . ucfirst($status) . ": {$ticket['subject']}";
            $body = "The status of your support ticket #{$ticket_id} - '{$ticket['subject']}' has been updated to " . ucfirst($status) . ".\n\n";
            $body .= "View ticket: " . ($_SERVER['HTTP_HOST'] ?? 'localhost') . "/support.php?ticket_id={$ticket_id}\n";
            sendEmail($recipient_email, $subject, $body);
        }

        return true;
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Error updating ticket status: " . $e->getMessage());
        return false;
    }
}

function getAllTickets($filter_status = null) {
    $pdo = getLicenseDbConnection();
    $sql = "SELECT st.*, c.first_name, c.last_name, c.email FROM `support_tickets` st JOIN `customers` c ON st.customer_id = c.id";
    $params = [];
    if ($filter_status && $filter_status !== 'all') {
        $sql .= " WHERE st.status = ?";
        $params[] = $filter_status;
    }
    $sql .= " ORDER BY st.updated_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($tickets as &$ticket) {
        if (isset($ticket['attachments'])) {
            $ticket['attachments'] = json_decode($ticket['attachments'], true);
            if (!is_array($ticket['attachments'])) {
                $ticket['attachments'] = [];
            }
        } else {
            $ticket['attachments'] = [];
        }
    }
    return $tickets;
}

/**
 * Recursively adds a folder and its contents to a ZipArchive.
 *
 * @param ZipArchive $zip The ZipArchive object.
 * @param string $folderPath The full path to the folder to add.
 * @param string $zipPath The path inside the zip file (e.g., 'my-project/subfolder').
 */
function addFolderToZip(ZipArchive $zip, string $folderPath, string $zipPath) {
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($folderPath, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
    );

    foreach ($files as $name => $file) {
        // Get real path for current file
        $filePath = $file->getRealPath();
        $relativePath = substr($filePath, strlen($folderPath) + 1);

        // Add current file to zip
        $zip->addFile($filePath, $zipPath . '/' . $relativePath);
    }
}


// --- Functions to generate Docker setup file contents ---
function getDockerfileContent() {
    $dockerfile_lines = [
        "FROM php:8.2-apache",
        "",
        "# Install system dependencies",
        "RUN apt-get update && apt-get install -y \\",
        "    git \\",
        "    unzip \\",
        "    libzip-dev \\",
        "    libpng-dev \\",
        "    libjpeg-dev \\",
        "    libfreetype6-dev \\",
        "    libicu-dev \\",
        "    libonig-dev \\",
        "    libxml2-dev \\",
        "    nmap \\",
        "    mysql-client \\", # Added mysql-client for mysqldump/mysql commands
        "    && rm -rf /var/lib/apt/lists/*",
        "",
        "# Install PHP extensions",
        "RUN docker-php-ext-configure gd --with-freetype --with-jpeg \\",
        "    && docker-php-ext-install -j\$(nproc) gd pdo_mysql zip intl opcache bcmath exif",
        "",
        "# Enable Apache modules",
        "RUN a2enmod rewrite",
        "",
        "# Copy application files from the ampnm-app-source directory",
        "COPY ampnm-app-source/ /var/www/html/",
        "",
        "# Copy the entrypoint script from the build context root",
        "COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh",
        "RUN chmod +x /usr/local/bin/docker-entrypoint.sh",
        "",
        "# Set permissions",
        "RUN chown -R www-data:www-data /var/www/html \\",
        "    && chmod -R 755 /var/www/html",
        "",
        "# Expose port 2266 (or whatever port your app runs on)",
        "EXPOSE 2266",
        "",
        "# Update Apache configuration to listen on 2266",
        "RUN echo \"Listen 2266\" >> /etc/apache2/ports.conf \\",
        "    && sed -i -e 's/VirtualHost \\*:80/VirtualHost \\*:2266/g' /etc/apache2/sites-available/000-default.conf \\",
        "    && sed -i -e 's/VirtualHost \\*:80/VirtualHost \\*:2266/g' /etc/apache2/sites-enabled/000-default.conf",
        "",
        "# Ensure the uploads directory exists and has correct permissions",
        "RUN mkdir -p /var/www/html/uploads/icons \\",
        "    mkdir -p /var/www/html/uploads/map_backgrounds \\",
        "    mkdir -p /var/www/html/uploads/backups \\", # Added backups directory
        "    && chown -R www-data:www-data /var/www/html/uploads \\",
        "    && chmod -R 775 /var/www/html/uploads",
        "",
        "# Use the copied entrypoint script",
        "ENTRYPOINT [\"/usr/local/bin/docker-entrypoint.sh\"]"
    ];
    return implode("\n", $dockerfile_lines);
}

function getDockerComposeContent($license_key) {
    // Define the LICENSE_API_URL for the AMPNM app
    // This should point to the verify_license.php endpoint on your portal
    $license_api_url = 'https://portal.itsupport.com.bd/verify_license.php'; // Ensure this matches your deployment

    $docker_compose_content = <<<EOT
version: '3.8'

services:
  app:
    build:
      context: . # Build context is the docker-ampnm folder
      dockerfile: Dockerfile
    # The entrypoint is now handled by the Dockerfile itself
    volumes:
      - ./ampnm-app-source/:/var/www/html/ # Mount the application source into the container
    depends_on:
      db:
        condition: service_healthy
    environment:
      - DB_HOST=db # Changed from 127.0.0.1 to 'db' (the service name)
      - DB_NAME=network_monitor
      - DB_USER=user
      - DB_PASSWORD=password
      - MYSQL_ROOT_PASSWORD=rootpassword
      - ADMIN_PASSWORD=password
      - LICENSE_API_URL={$license_api_url}
      # APP_LICENSE_KEY is no longer set here. It is configured via the web UI after initial setup.
    ports:
      - "2266:2266" # Main app will now run on port 2266
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
    return $docker_compose_content;
}

// --- Profile Management Functions ---

// Fetches basic customer data from the 'customers' table
function getCustomerData($customer_id) {
    $pdo = getLicenseDbConnection();
    $stmt = $pdo->prepare("SELECT id, first_name, last_name, email FROM `customers` WHERE id = ?");
    $stmt->execute([$customer_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Fetches additional profile data from the 'profiles' table
function getProfileData($customer_id) {
    $pdo = getLicenseDbConnection();
    $stmt = $pdo->prepare("SELECT avatar_url, address, phone FROM `profiles` WHERE customer_id = ?");
    $stmt->execute([$customer_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: []; // Return empty array if no profile exists
}

// Updates customer and profile data
function updateCustomerProfile($customer_id, $first_name, $last_name, $address, $phone, $avatar_url) {
    $pdo = getLicenseDbConnection();
    $pdo->beginTransaction();
    try {
        // Update customers table
        $stmt = $pdo->prepare("UPDATE `customers` SET first_name = ?, last_name = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$first_name, $last_name, $customer_id]);

        // Check if a profile entry exists
        $stmt = $pdo->prepare("SELECT id FROM `profiles` WHERE customer_id = ?");
        $stmt->execute([$customer_id]);
        $profile_exists = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($profile_exists) {
            // Update existing profile
            $stmt = $pdo->prepare("UPDATE `profiles` SET avatar_url = ?, address = ?, phone = ?, updated_at = CURRENT_TIMESTAMP WHERE customer_id = ?");
            $stmt->execute([$avatar_url, $address, $phone, $customer_id]);
        } else {
            // Create new profile
            $stmt = $pdo->prepare("INSERT INTO `profiles` (customer_id, avatar_url, address, phone) VALUES (?, ?, ?, ?)");
            $stmt->execute([$customer_id, $avatar_url, $address, $phone]);
        }

        $pdo->commit();
        return true;
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Error updating customer profile: " . $e->getMessage());
        return false;
    }
}

// --- SMTP Settings Functions (for Portal Admin) ---
function getAdminSmtpSettings() {
    $pdo = getLicenseDbConnection();
    $admin_id = $_SESSION['admin_id'] ?? null;
    if (!$admin_id) return null;

    $stmt = $pdo->prepare("SELECT host, port, username, password, encryption, from_email, from_name FROM smtp_settings WHERE admin_id = ?");
    $stmt->execute([$admin_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function saveAdminSmtpSettings($host, $port, $username, $password, $encryption, $from_email, $from_name) {
    $pdo = getLicenseDbConnection();
    $admin_id = $_SESSION['admin_id'] ?? null;
    if (!$admin_id) return false;

    // Check if settings already exist for this admin
    $stmt = $pdo->prepare("SELECT id, password FROM smtp_settings WHERE admin_id = ?");
    $stmt->execute([$admin_id]);
    $existingSettings = $stmt->fetch(PDO::FETCH_ASSOC);

    $hashed_password = null;
    if ($password !== '********' && !empty($password)) {
        // Only hash and update password if it's not the masked value and not empty
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    } elseif ($existingSettings) {
        // If password is '********' (masked), keep the existing hashed password
        $hashed_password = $existingSettings['password'];
    } else {
        // New settings, but empty password provided (should be caught by frontend or required)
        return false; // Password is required for new SMTP settings
    }

    if ($existingSettings) {
        $sql = "UPDATE smtp_settings SET host = ?, port = ?, username = ?, password = ?, encryption = ?, from_email = ?, from_name = ?, updated_at = CURRENT_TIMESTAMP WHERE admin_id = ?";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([$host, $port, $username, $hashed_password, $encryption, $from_email, $from_name, $admin_id]);
    } else {
        $sql = "INSERT INTO smtp_settings (admin_id, host, port, username, password, encryption, from_email, from_name) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([$admin_id, $host, $port, $username, $hashed_password, $encryption, $from_email, $from_name]);
    }
}

// --- Email Sending Function ---
function sendEmail($to, $subject, $body) {
    $smtpSettings = getAdminSmtpSettings();

    if (!$smtpSettings || empty($smtpSettings['host']) || empty($smtpSettings['username']) || empty($smtpSettings['password']) || empty($smtpSettings['from_email'])) {
        error_log("Email sending failed: SMTP settings are incomplete or not configured.");
        return false;
    }

    $mail = new PHPMailer(true);
    try {
        //Server settings
        $mail->isSMTP();                                            // Send using SMTP
        $mail->Host       = $smtpSettings['host'];                  // Set the SMTP server to send through
        $mail->SMTPAuth   = true;                                   // Enable SMTP authentication
        $mail->Username   = $smtpSettings['username'];              // SMTP username
        $mail->Password   = $smtpSettings['password'];              // SMTP password
        $mail->SMTPSecure = $smtpSettings['encryption'] === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS; // Enable TLS encryption; `PHPMailer::ENCRYPTION_SMTPS` encouraged
        $mail->Port       = $smtpSettings['port'];                  // TCP port to connect to

        //Recipients
        $mail->setFrom($smtpSettings['from_email'], $smtpSettings['from_name'] ?: 'IT Support BD Portal');
        $mail->addAddress($to);     // Add a recipient

        // Content
        $mail->isHTML(false);                                  // Set email format to plain text
        $mail->Subject = $subject;
        $mail->Body    = $body;

        $mail->send();
        error_log("Email sent to {$to} for subject: {$subject}");
        return true;
    } catch (Exception $e) {
        error_log("Email sending failed to {$to}. Mailer Error: {$mail->ErrorInfo}");
        return false;
    }
}


// --- Basic HTML Header/Footer for the portal ---
function portal_header($title = "IT Support BD Portal") {
    $current_page = basename($_SERVER['PHP_SELF']);
    echo '<!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>' . htmlspecialchars($title) . '</title>
        <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
        <link rel="stylesheet" href="assets/css/portal-style.css">
    </head>
    <body class="flex flex-col min-h-screen">
        <nav class="glass-navbar py-4 shadow-lg sticky top-0 z-50">
            <div class="container mx-auto px-4 flex flex-col md:flex-row justify-between items-center">
                <a href="index.php" class="text-2xl font-bold text-primary-light mb-3 md:mb-0">
                    <i class="fas fa-shield-alt mr-2 text-blue-400"></i>IT Support BD Portal
                </a>
                <div class="flex flex-col md:flex-row space-y-2 md:space-y-0 md:space-x-4">';
    
    $nav_links = [
        'products.php' => 'Products',
        'dashboard.php' => 'Dashboard',
        'cart.php' => '<i class="fas fa-shopping-cart mr-1"></i> Cart',
    ];

    if (isCustomerLoggedIn()) {
        foreach ($nav_links as $href => $text) {
            $active_class = ($current_page === $href) ? 'active' : '';
            echo '<a href="' . htmlspecialchars($href) . '" class="nav-link ' . $active_class . '">' . $text . '</a>';
        }
        
        // User Icon Dropdown Menu
        echo '<div class="relative group">
                <button class="nav-link flex items-center space-x-2">
                    <i class="fas fa-user-circle text-lg"></i>
                    <span class="hidden md:inline">' . htmlspecialchars($_SESSION['customer_name']) . '</span>
                    <i class="fas fa-chevron-down text-xs ml-1 transition-transform duration-200 group-hover:rotate-180"></i>
                </button>
                <div class="absolute right-0 mt-2 w-48 bg-slate-800/90 backdrop-blur-lg border border-slate-700 rounded-lg shadow-xl opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 z-50">
                    <a href="profile.php" class="block px-4 py-2 text-sm text-gray-200 hover:bg-slate-700 rounded-t-lg"><i class="fas fa-user-edit mr-2"></i> Profile</a>
                    <a href="change_password.php" class="block px-4 py-2 text-sm text-gray-200 hover:bg-slate-700"><i class="fas fa-key mr-2"></i> Change Password</a>
                    <a href="support.php" class="block px-4 py-2 text-sm text-gray-200 hover:bg-slate-700"><i class="fas fa-headset mr-2"></i> My Tickets</a>
                    <a href="dashboard.php" class="block px-4 py-2 text-sm text-gray-200 hover:bg-slate-700"><i class="fas fa-box-open mr-2"></i> My Products</a>
                    <a href="#" class="block px-4 py-2 text-sm text-gray-200 hover:bg-slate-700 cursor-not-allowed opacity-70"><i class="fas fa-file-invoice-dollar mr-2"></i> Billing Information <span class="text-xs text-gray-400">(Coming Soon)</span></a>
                    <div class="border-t border-slate-700 my-1"></div>
                    <a href="logout.php" class="block px-4 py-2 text-sm text-red-400 hover:bg-red-900/30 rounded-b-lg"><i class="fas fa-sign-out-alt mr-2"></i> Logout</a>
                </div>
            </div>';
    } else {
        $public_nav_links = [
            'products.php' => 'Products',
            'login.php' => 'Login',
            'registration.php' => 'Register',
        ];
        foreach ($public_nav_links as $href => $text) {
            $active_class = ($current_page === $href) ? 'active' : '';
            echo '<a href="' . htmlspecialchars($href) . '" class="nav-link ' . $active_class . '">' . $text . '</a>';
        }
    }
    echo '</div>
            </div>
        </nav>
        <main class="container mx-auto py-8 flex-grow page-content">'; // Added page-content class
}

function portal_footer() {
    echo '</main>
        <footer class="text-center py-6 text-gray-300 text-sm mt-auto">
            <p>&copy; ' . date("Y") . ' IT Support BD. All rights reserved.</p>
        </footer>
    </body>
    </html>';
}

function admin_header($title = "Admin Panel") {
    $current_page = basename($_SERVER['PHP_SELF']);
    echo '<!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>' . htmlspecialchars($title) . '</title>
        <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
        <link rel="stylesheet" href="../assets/css/portal-style.css">
    </head>
    <body class="admin-body flex flex-col min-h-screen">
        <nav class="admin-navbar py-4 shadow-md sticky top-0 z-50">
            <div class="container mx-auto px-4 flex flex-col md:flex-row justify-between items-center">
                <a href="adminpanel.php" class="text-2xl font-bold text-blue-400 mb-3 md:mb-0">
                    <i class="fas fa-user-shield mr-2"></i>Admin Panel
                </a>
                <div class="flex flex-col md:flex-row space-y-2 md:space-y-0 md:space-x-4">';
    
    $admin_nav_links = [
        'index.php' => 'Dashboard',
        'orders.php' => '<i class="fas fa-shopping-bag mr-1"></i> Orders', // NEW ORDERS LINK
        'users.php' => 'Customers',
        'license-manager.php' => 'Licenses',
        'products.php' => 'Products',
        'tickets.php' => '<i class="fas fa-headset mr-1"></i> Tickets', // New Admin Tickets link
        'smtp_settings.php' => '<i class="fas fa-envelope mr-1"></i> SMTP Settings', // NEW SMTP SETTINGS LINK
        'change_password.php' => '<i class="fas fa-key mr-1"></i> Change Password', // New Admin Password Change link
    ];

    if (isAdminLoggedIn()) {
        foreach ($admin_nav_links as $href => $text) {
            $active_class = (basename($current_page) === $href) ? 'active' : '';
            echo '<a href="' . htmlspecialchars($href) . '" class="admin-nav-link ' . $active_class . '">' . $text . '</a>';
        }
        echo '<a href="../logout.php?admin=true" class="admin-nav-link"><i class="fas fa-sign-out-alt mr-1"></i> Logout (' . htmlspecialchars($_SESSION['admin_username']) . ')</a>';
    } else {
        $admin_public_nav_links = [
            'adminpanel.php' => 'Login',
        ];
        foreach ($admin_public_nav_links as $href => $text) {
            $active_class = (basename($current_page) === basename($href)) ? 'active' : ''; // Use basename for adminpanel.php
            echo '<a href="' . htmlspecialchars($href) . '" class="admin-nav-link ' . $active_class . '">' . $text . '</a>';
        }
    }
    echo '</div>
            </div>
        </nav>
        <main class="container mx-auto py-8 flex-grow page-content">'; // Added page-content class
}

function admin_footer() {
    echo '</main>
        <footer class="text-center py-6 text-gray-400 text-sm mt-auto">
            <p>&copy; ' . date("Y") . ' IT Support BD Admin. All rights reserved.</p>
        </footer>
    </body>
    </html>';
}