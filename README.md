# Local Network Monitor

This is a React-based web application designed to monitor devices on your local network directly from your browser.

## How It Works

This application runs entirely in your web browser. It uses browser-based techniques (like WebSocket and HTTP requests) to "ping" other devices on your local network. Because the requests originate from your machine, it can see and report the status of local IPs (e.g., `192.168.x.x`).

- **Browser Ping:** Checks for open web or WebSocket ports to determine if a device is responsive. This is used for all local network monitoring.
- **Server Ping:** Uses a Supabase Edge Function to ping public hosts from the internet. This is useful for checking public websites but **cannot** be used for local IP addresses.
- **Network Map:** Provides a visual layout of your devices, with live status updates based on browser pings.
- **Database:** Uses Supabase to store your device configurations and ping history.

## Running Locally

To ensure the application can access your local network, you should run it on your own machine.

1.  **Install dependencies:**
    ```bash
    npm install
    ```

2.  **Start the development server:**
    ```bash
    npm run dev
    ```

3.  **Access the app:**
    Open your browser and navigate to the local URL provided by the development server (usually `http://localhost:8080`).