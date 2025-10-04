// A service for pinging local network devices from the browser.
// Browsers cannot send traditional ICMP packets, so we simulate a "ping"
// by checking for common open ports using WebSocket and HTTP requests.

export const performBrowserPing = (ip: string): Promise<number> => {
  return new Promise((resolve, reject) => {
    const startTime = performance.now();
    
    // Prioritize WebSocket as it's a lightweight way to check for an open port.
    const ws = new WebSocket(`ws://${ip}`);
    
    const timeout = setTimeout(() => {
      ws.close();
      // If WebSocket fails, fall back to an HTTP check.
      performHttpFallback(ip, startTime, resolve, reject);
    }, 1500); // 1.5 second timeout for the WebSocket attempt.

    ws.onopen = () => {
      clearTimeout(timeout);
      const endTime = performance.now();
      ws.close();
      resolve(Math.round(endTime - startTime));
    };

    ws.onerror = () => {
      clearTimeout(timeout);
      // Fallback to HTTP check on error.
      performHttpFallback(ip, startTime, resolve, reject);
    };
  });
};

const performHttpFallback = (
  ip: string,
  startTime: number,
  resolve: (value: number) => void,
  reject: (reason?: any) => void
) => {
  const img = new Image();
  
  const timeout = setTimeout(() => {
    // Clean up the image source to prevent it from loading in the background.
    img.src = ''; 
    reject(new Error("HTTP fallback timed out"));
  }, 1500); // 1.5 second timeout for the image load attempt.

  img.onload = () => {
    clearTimeout(timeout);
    const endTime = performance.now();
    resolve(Math.round(endTime - startTime));
  };

  img.onerror = () => {
    clearTimeout(timeout);
    reject(new Error("Device not responsive to WebSocket or HTTP"));
  };

  // Append a timestamp to bypass browser cache.
  img.src = `http://${ip}/favicon.ico?t=${Date.now()}`;
};