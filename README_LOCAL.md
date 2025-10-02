# Running Network Monitor Locally

To ping local devices (192.168.x.x), you need to run this web app on your local machine:

1. **Install dependencies:**
   ```bash
   npm install
   ```

2. **Start the development server:**
   ```bash
   npm run dev
   ```

3. **Access the app at:** `http://localhost:8080`

When running locally, the browser can access your local network and the ping functionality will work properly.

## Why this works:
- The web app runs on your local machine (`localhost`)
- Browser security policies allow localhost to access local network resources
- No need for Supabase or external servers

## Limitations:
- Only works when running on your local machine
- Cannot be hosted online and access local networks
- Each user must run their own local instance