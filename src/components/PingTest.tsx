import { useState } from "react";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Badge } from "@/components/ui/badge";
import { Network, Clock, AlertCircle } from "lucide-react";
import { showSuccess, showError } from "@/utils/toast";

interface PingResult {
  host: string;
  time: number;
  status: "success" | "error";
  timestamp: Date;
}

const PingTest = () => {
  const [host, setHost] = useState("google.com");
  const [isPinging, setIsPinging] = useState(false);
  const [pingResults, setPingResults] = useState<PingResult[]>([]);

  const performPing = async () => {
    if (!host.trim()) {
      showError("Please enter a hostname or IP address");
      return;
    }

    setIsPinging(true);
    const startTime = performance.now();

    try {
      // Create a more robust ping method using Image object for local IPs
      const pingPromise = new Promise<void>((resolve, reject) => {
        const img = new Image();
        let timeoutId: NodeJS.Timeout;

        const cleanup = () => {
          clearTimeout(timeoutId);
          img.onload = null;
          img.onerror = null;
        };

        img.onload = () => {
          cleanup();
          resolve();
        };

        img.onerror = () => {
          cleanup();
          reject(new Error("Ping failed"));
        };

        // Use a unique query parameter to avoid caching
        const timestamp = Date.now();
        
        // For local IPs, try HTTP and different ports
        const isLocalIP = /^(192\.168\.|10\.|172\.(1[6-9]|2[0-9]|3[0-1])\.|127\.)/.test(host);
        
        if (isLocalIP) {
          // Try common local services
          const urls = [
            `http://${host}/?ping=${timestamp}`,
            `http://${host}:80/?ping=${timestamp}`,
            `http://${host}:8080/?ping=${timestamp}`,
            `http://${host}:3000/?ping=${timestamp}`
          ];
          
          let currentUrlIndex = 0;
          
          const tryNextUrl = () => {
            if (currentUrlIndex >= urls.length) {
              reject(new Error("All local ping attempts failed"));
              return;
            }
            
            img.src = urls[currentUrlIndex];
            currentUrlIndex++;
            
            // Set timeout for this specific attempt
            timeoutId = setTimeout(() => {
              tryNextUrl();
            }, 1000);
          };
          
          tryNextUrl();
        } else {
          // For public hosts, use HTTPS
          img.src = `https://${host}/?ping=${timestamp}`;
          
          timeoutId = setTimeout(() => {
            cleanup();
            reject(new Error("Ping timeout"));
          }, 5000);
        }
      });

      await pingPromise;
      
      const endTime = performance.now();
      const pingTime = Math.round(endTime - startTime);
      
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
      showError(`Ping to ${host} failed - ${error instanceof Error ? error.message : 'Unknown error'}`);
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
            Test network connectivity to any host (supports local IPs)
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

          <div className="text-sm text-muted-foreground mb-4">
            <p>For local IPs: Make sure the device is online and has a web server running</p>
            <p>Common ports tried: 80, 8080, 3000</p>
          </div>

          {pingResults.length > 0 && (
            <div className="space-y-2">
              <h3 className="text-sm font-medium">Recent Pings</h3>
              {pingResults.map((result, index) => (
                <div key={index} className="flex items-center justify-between p-2 border rounded">
                  <div className="flex items-center gap-2">
                    {result.status === "success" ? (
                      <Clock className="h-4 w-4 text-green-500" />
                    ) : (
                      <AlertCircle className="h-4 w-4 text-red-500" />
                    )}
                    <span className="font-mono">{result.host}</span>
                  </div>
                  <div className="flex items-center gap-2">
                    <Badge variant={result.status === "success" ? "default" : "destructive"}>
                      {result.status === "success" ? `${result.time}ms` : "Failed"}
                    </Badge>
                    <span className="text-xs text-muted-foreground">
                      {result.timestamp.toLocaleTimeString()}
                    </span>
                  </div>
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