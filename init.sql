CREATE DATABASE IF NOT EXISTS network_monitor;
USE network_monitor;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS maps (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    type VARCHAR(50) NOT NULL,
    description TEXT,
    is_default BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS network_devices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    ip_address VARCHAR(45),
    name VARCHAR(100) NOT NULL,
    status ENUM('online', 'offline', 'unknown') DEFAULT 'unknown',
    last_ping TIMESTAMP NULL,
    type VARCHAR(50) NOT NULL DEFAULT 'server',
    description TEXT,
    enabled BOOLEAN DEFAULT TRUE,
    position_x DECIMAL(10, 4) NULL,
    position_y DECIMAL(10, 4) NULL,
    map_id INT,
    ping_interval INT NULL,
    icon_size INT DEFAULT 50,
    name_text_size INT DEFAULT 14,
    last_ping_result BOOLEAN NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (map_id) REFERENCES maps(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS network_edges (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    source_id INT NOT NULL,
    target_id INT NOT NULL,
    map_id INT NOT NULL,
    connection_type VARCHAR(50) DEFAULT 'cat5',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (source_id) REFERENCES network_devices(id) ON DELETE CASCADE,
    FOREIGN KEY (target_id) REFERENCES network_devices(id) ON DELETE CASCADE,
    FOREIGN KEY (map_id) REFERENCES maps(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS ping_results (
    id INT AUTO_INCREMENT PRIMARY KEY,
    host VARCHAR(100) NOT NULL,
    packet_loss INT NOT NULL,
    avg_time DECIMAL(10,2) NOT NULL,
    min_time DECIMAL(10,2) NOT NULL,
    max_time DECIMAL(10,2) NOT NULL,
    success BOOLEAN NOT NULL,
    output TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create default admin user (password is 'admin')
INSERT INTO users (username, password) VALUES ('admin', '$2b$10$8K1p/a0dURXAm7QiTRqNa.E3YPWs8UkrpC4VAmT6y49CgRcDBuaba');