# Local Network Deployment Guide

## How to Deploy on Your Local Network

### 1. Build the Application
```bash
npm run build
```

### 2. Choose a Deployment Method

**Option A: Using a Local Web Server**
```bash
# Install a simple HTTP server
npm install -g http-server

# Serve the built files
cd dist
http-server -p 8080 --host 0.0.0.0
```

**Option B: Using Python**
```bash
cd dist
python -m http.server 8080
```

**Option C: Using Node.js**
```bash
cd dist
npx serve -p 8080
```

### 3. Access from Other Devices
Once running, access the dashboard from:
- Your machine: `http://localhost:8080`
- Other devices: `http://[YOUR-SERVER-IP]:8080`

### 4. Find Your Server IP
```bash
# On Linux/Mac
ifconfig | grep "inet "

# On Windows
ipconfig
```

## Important Notes
- The app must run on the same local network as the devices you want to monitor
- Browser security allows local network access when the app is served from a local IP
- All devices will be able to access the monitoring dashboard
- For production use, consider adding authentication

## Example Usage
If your server has IP `192.168.9.100`, other devices can access:
`http://192.168.9.100:8080`