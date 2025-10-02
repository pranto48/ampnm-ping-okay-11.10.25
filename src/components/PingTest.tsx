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
      // Using fetch with no-cors to simulate ping (limited but works for demo)
      await fetch(`https://${host}`, { 
        mode: 'no-cors',
        method: 'HEAD'
      });
      
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
      showError(`Ping to ${host} failed`);
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
            Test network connectivity to any host
          </CardDescription>
        </CardHeader>
        <CardContent>
          <div className="flex gap-2 mb-4">
            <Input
              placeholder="Enter hostname or IP (e.g., google.com)"
              value={host}
              onChange={(e) => setHost(e.target.value)}
              onKeyPress={(e) => e.key === 'Enter' && performPing()}
            />
            <Button onClick={performPing} disabled={isPinging}>
              {isPinging ? "Pinging..." : "Ping"}
            </Button>
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