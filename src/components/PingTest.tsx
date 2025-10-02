import { useState } from "react";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Badge } from "@/components/ui/badge";
import { Network, Clock, AlertCircle, Wifi, WifiOff } from "lucide-react";
import { showSuccess, showError } from "@/utils/toast";

interface PingResult {
  host: string;
  time: number;
  status: "success" | "error";
  timestamp: Date;
}

const PingTest = () => {
  const [host, setHost] = useState("192.168.9.3");
  const [isPinging, setIsPinging] = useState(false);
  const [pingResults, setPingResults] = useState<PingResult[]>([]);

  // Simple ICMP-like ping using WebRTC (works for local network detection)
  const performWebRTCPing = async (ip: string): Promise<number> => {
    return new Promise((resolve, reject) => {
      const startTime = performance.now();
      
      // Create a temporary image load to test connectivity
      const img = new Image();
      img.onload = () => {
        const endTime = performance.now();
        resolve(Math.round(endTime - startTime));
      };
      img.onerror = () => reject(new Error("Device not responding"));
      
      // Try various common endpoints for local devices
      const endpoints = [
        `http://${ip}/`,
        `http://${ip}:80/`,
        `http://${ip}:8080/`,
        `http://${ip}:3000/`,
        `http://${ip}:8000/`,
        `http://${ip}:8081/`
      ];
      
      let currentIndex = 0;
      const tryNextEndpoint = () => {
        if (currentIndex >= endpoints.length) {
          reject(new Error("No web service found on common ports"));
          return;
        }
        
        img.src = endpoints[currentIndex] + `?ping=${Date.now()}`;
        currentIndex++;
        
        // If this endpoint fails, try the next one after a short delay
        setTimeout(tryNextEndpoint, 300);
      };
      
      tryNextEndpoint();
    });
  };

  // Alternative method: Use fetch with timeout for public hosts
  const performFetchPing = async (hostname: string): Promise<number> => {
    const startTime = performance.now();
    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), 5000);

    try {
      await fetch(`https://${hostname}`, {
        method: 'HEAD',
        mode: 'no-cors',
        signal: controller.signal
      });
      
      clearTimeout(timeoutId);
      return Math.round(performance.now() - startTime);
    } catch (error) {
      clearTimeout(timeoutId);
      throw new Error("Network request failed");
    }
  };

  const performPing = async () => {
    if (!host.trim()) {
      showError("Please enter a hostname or IP address");
      return;
    }

    setIsPinging(true);

    try {
      let pingTime: number;
      const isLocalIP = /^(192\.168\.|10\.|172\.(1[6-9]|2[0-9]|3[0-1])\.|127\.|localhost)/.test(host);
      
      if (isLocalIP) {
        pingTime = await performWebRTCPing(host);
      } else {
        pingTime = await performFetchPing(host);
      }
      
      const result: PingResult = {
        host,
        time: pingTime,
        status: "success",
        timestamp: new Date()
      };

      setPingResults(prev => [result, ...prev.slice(0, 9)]);
      showSuccess(`Ping to ${host} successful (${pingTime}ms)`);
    } catch (error) {
      const result: PingResult = {
        host,
        time: 0,
        status: "error",
        timestamp: new Date()
      };

      setPingResults(prev => [result, ...prev.slice(0, 9)]);
      showError(`Ping to ${host} failed - Device may be offline or not running a web service`);
    } finally {
      setIsPinging(false);
    }
  };

  return (
    <div className="space-y-4">
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <Network className="h-5 w-5" />
            Ping Test
          </CardTitle>
          <CardDescription>
            Test network connectivity to any host (local and public)
          </CardDescription>
        </CardHeader>
        <CardContent>
          <div className="flex gap-2 mb-4">
            <Input
              placeholder="Enter hostname or IP (e.g., 192.168.1.1 or google.com)"
              value={host}
              onChange={(e) => setHost(e.target.value)}
              onKeyPress={(e) => e.key === 'Enter' && performPing()}
            />
            <Button onClick={performPing} disabled={isPinging}>
              {isPinging ? "Pinging..." : "Ping"}
            </Button>
          </div>

          <div className="text-sm text-muted-foreground mb-4 p-3 bg-muted rounded-lg">
            <div className="flex items-center gap-2 mb-2">
              <Wifi className="h-4 w-4" />
              <span className="font-medium">Local IP Tips:</span>
            </div>
            <ul className="list-disc list-inside space-y-1">
              <li>Device must be powered on and connected to the same network</li>
              <li>For best results, ensure the device has a web server running</li>
              <li>Common web server ports: 80, 8080, 3000, 8000</li>
              <li>Try pinging router IP (usually 192.168.1.1 or 192.168.0.1)</li>
            </ul>
          </div>

          {pingResults.length > 0 && (
            <div className="space-y-2">
              <h3 className="text-sm font-medium">Recent Pings</h3>
              {pingResults.map((result, index) => (
                <div key={index} className="flex items-center justify-between p-3 border rounded-lg">
                  <div className="flex items-center gap-3">
                    {result.status === "success" ? (
                      <Wifi className="h-5 w-5 text-green-500" />
                    ) : (
                      <WifiOff className="h-5 w-5 text-red-500" />
                    )}
                    <div>
                      <span className="font-mono text-sm">{result.host}</span>
                      <p className="text-xs text-muted-foreground">
                        {result.timestamp.toLocaleTimeString()}
                      </p>
                    </div>
                  </div>
                  <Badge variant={result.status === "success" ? "default" : "destructive"}>
                    {result.status === "success" ? `${result.time}ms` : "Offline"}
                  </Badge>
                </div>
              ))}
            </div>
          )}
        </CardContent>
      </Card>
    </div>
  );
};

export default PingTest;