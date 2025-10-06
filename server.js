const express = require('express');
const path = require('path');
const mysql = require('mysql2/promise');
const { exec } = require('child_process');
const util = require('util');

const app = express();
const port = 3000;

// Middleware
app.use(express.json());
app.use(express.static(path.join(__dirname, 'dist')));

// Database configuration from environment variables
const dbConfig = {
  host: process.env.DB_HOST || 'localhost',
  user: process.env.DB_USER || 'root',
  password: process.env.DB_PASSWORD || '',
  database: process.env.DB_NAME || 'network_monitor',
  port: process.env.DB_PORT || 3306
};

// Execute ping command
const execPromise = util.promisify(exec);

// API endpoint for manual ping
app.post('/api/ping', async (req, res) => {
  const { host, count = 4 } = req.body;
  
  if (!host) {
    return res.status(400).json({ error: 'Host is required' });
  }

  try {
    // Determine the correct ping command based on the OS
    let command;
    if (process.platform === 'win32') {
      command = `ping -n ${count} ${host}`;
    } else {
      command = `ping -c ${count} ${host}`;
    }

    const { stdout, stderr } = await execPromise(command);
    const output = stdout || stderr;
    
    // Parse ping output
    let packetLoss = 100;
    let avgTime = 0;
    
    // Regex for Windows
    const windowsLossMatch = output.match(/Lost = \d+ \((\d+)% loss\)/);
    const windowsTimeMatch = output.match(/Average = (\d+)ms/);
    
    // Regex for Linux/Mac
    const unixLossMatch = output.match(/(\d+)% packet loss/);
    const unixTimeMatch = output.match(/avg = [\d.]+\/([\d.]+)\//);
    
    if (windowsLossMatch) {
      packetLoss = parseInt(windowsLossMatch[1]);
    }
    if (windowsTimeMatch) {
      avgTime = parseFloat(windowsTimeMatch[1]);
    }
    
    if (unixLossMatch) {
      packetLoss = parseInt(unixLossMatch[1]);
    }
    if (unixTimeMatch) {
      avgTime = parseFloat(unixTimeMatch[1]);
    }
    
    const success = packetLoss < 100;
    
    // Save to database
    try {
      const connection = await mysql.createConnection(dbConfig);
      await connection.execute(
        'INSERT INTO ping_results (host, packet_loss, avg_time, min_time, max_time, success, output) VALUES (?, ?, ?, ?, ?, ?, ?)',
        [host, packetLoss, avgTime, avgTime, avgTime, success, output]
      );
      await connection.end();
    } catch (dbError) {
      console.error('Database error:', dbError);
    }
    
    res.json({
      host,
      success,
      packet_loss: packetLoss,
      avg_time: avgTime,
      output
    });
  } catch (error) {
    console.error('Ping error:', error);
    res.status(500).json({ error: 'Failed to execute ping command', details: error.message });
  }
});

// Health check endpoint
app.get('/health', (req, res) => {
  res.status(200).json({ status: 'OK', timestamp: new Date().toISOString() });
});

// Serve React app for all other routes
app.get('*', (req, res) => {
  res.sendFile(path.join(__dirname, 'dist', 'index.html'));
});

app.listen(port, () => {
  console.log(`Server running at http://localhost:${port}`);
});