import { useState, useEffect } from "react";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Activity, Wifi, Server, Clock, RefreshCw, Monitor, Network, WifiOff, History } from "lucide-react";
import { showSuccess, showError } from "@/utils/toast";
import PingTest from "@/components/PingTest";
import NetworkStatus from "@/components/NetworkStatus";
import NetworkScanner from "@/components/NetworkScanner";
import ServerPingTest from "@/components/ServerPingTest";
import PingHistory from "@/components/PingHistory";
import { MadeWithDyad } from "@/components/made-with-dyad";

const Index = () => {
  const [networkStatus, setNetworkStatus] = useState<boolean>(true);
  const [lastChecked, setLastChecked] = useState<Date>(new Date());
  const [localDevices, setLocalDevices] = useState([
    { ip: "192.168.9.1", name: "Router", status: "unknown", lastSeen: null },
    { ip: "192.168.9.3", name: "Desktop PC", status: "unknown", lastSeen: null },
    { ip: "192.168.9.10", name: "NAS", status: "unknown", lastSeen: null },
    { ip: "192.168.9.20", name: "Printer", status: "unknown", lastSeen: null },
  ]);

  const checkNetworkStatus = async () => {
    try {
      // Simple network check by trying to fetch a small resource
      await fetch("https://www.google.com/favicon.ico", { mode: 'no-cors' });
      setNetworkStatus(true);
      showSuccess("Internet connection is online");
    } catch (error) {
      setNetworkStatus(false);
      showError("Internet connection is offline");
    }
    setLastChecked(new Date());
  };

  const checkLocalDevices = async () => {
    const updatedDevices = [...localDevices];
    
    for (let i = 0; i < updatedDevices.length; i++) {
      const device = updatedDevices[i];
      try {
        // Try WebSocket connection first
        const ws = new WebSocket(`ws://${device.ip}:80`);
        
        await new Promise((resolve, reject) => {
          ws.onopen = resolve;
          ws.onerror = reject;
          setTimeout(reject, 2000);
        });
        
        ws.close();
        updatedDevices[i] = {
          ...device,
          status: "online",
          lastSeen: new Date()
        };
        showSuccess(`${device.name} (${device.ip}) is online`);
      } catch (error) {
        // Fallback to HTTP ping
        try {
          const img = new Image();
          await new Promise((resolve, reject) => {
            img.onload = resolve;
            img.onerror = reject;
            img.src = `http://${device.ip}/?ping=${Date.now()}`;
            setTimeout(reject, 2000);
          });
          
          updatedDevices[i] = {
            ...device,
            status: "online",
            lastSeen: new Date()
          };
          showSuccess(`${device.name} (${device.ip}) is online`);
        } catch (httpError) {
          updatedDevices[i] = {
            ...device,
            status: "offline",
            lastSeen: device.lastSeen
          };
          showError(`${device.name} (${device.ip}) is offline`);
        }
      }
    }
    
    setLocalDevices(updatedDevices);
  };

  useEffect(() => {
    checkNetworkStatus();
    checkLocalDevices();
    
    const networkInterval = setInterval(checkNetworkStatus, 30000);
    const deviceInterval = setInterval(checkLocalDevices, 60000);
    
    return () => {
      clearInterval(networkInterval);
      clearInterval(deviceInterval);
    };
  }, []);

  return (
    <div className="min-h-screen bg-background">
      <div className="container mx-auto p-4">
        <div className="flex items-center justify-between mb-6">
          <div className="flex items-center gap-3">
            <Monitor className="h-8 w-8 text-primary" />
            <h1 className="text-3xl font-bold">Local Network Monitor</h1>
          </div>
          <Badge variant={networkStatus ? "default" : "destructive"} className="text-sm">
            {networkStatus ? "Internet Online" : "Internet Offline"}
          </Badge>
        </div>

        <Tabs defaultValue="dashboard" className="w-full">
          <TabsList className="mb-4">
            <TabsTrigger value="dashboard">Dashboard</TabsTrigger>
            <TabsTrigger value="devices">Local Devices</TabsTrigger>
            <TabsTrigger value="ping">Browser Ping</TabsTrigger>
            <TabsTrigger value="server-ping">Server Ping</TabsTrigger>
            <TabsTrigger value="status">Network Status</TabsTrigger>
            <TabsTrigger value="scanner">Network Scanner</TabsTrigger>
            <TabsTrigger value="history">Ping History</TabsTrigger>
          </TabsList>

          <TabsContent value="dashboard">
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
              <Card>
                <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                  <CardTitle className="text-sm font-medium">Internet Status</CardTitle>
                  <Activity className="h-4 w-4 text-muted-foreground" />
                </CardHeader>
                <CardContent>
                  <div className="text-2xl font-bold">
                    {networkStatus ? "Online" : "Offline"}
                  </div>
                  <p className="text-xs text-muted-foreground">
                    Internet connectivity
                  </p>
                </CardContent>
              </Card>

              <Card>
                <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                  <CardTitle className="text-sm font-medium">Last Check</CardTitle>
                  <Clock className="h-4 w-4 text-muted-foreground" />
                </CardHeader>
                <CardContent>
                  <div className="text-2xl font-bold">
                    {lastChecked.toLocaleTimeString()}
                  </div>
                  <p className="text-xs text-muted-foreground">
                    Last status check
                  </p>
                </CardContent>
              </Card>

              <Card>
                <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                  <CardTitle className="text-sm font-medium">Local Devices</CardTitle>
                  <Server className="h-4 w-4 text-muted-foreground" />
                </CardHeader>
                <CardContent>
                  <div className="text-2xl font-bold">
                    {localDevices.filter(d => d.status === "online").length}/{localDevices.length}
                  </div>
                  <p className="text-xs text-muted-foreground">
                    Devices online
                  </p>
                </CardContent>
              </Card>
            </div>

            <Card className="mb-6">
              <CardHeader>
                <CardTitle className="flex items-center gap-2">
                  <Network className="h-5 w-5" />
                  Quick Actions
                </CardTitle>
              </CardHeader>
              <CardContent className="flex gap-4">
                <Button onClick={checkNetworkStatus}>
                  <RefreshCw className="h-4 w-4 mr-2" />
                  Check Internet
                </Button>
                <Button onClick={checkLocalDevices}>
                  <RefreshCw className="h-4 w-4 mr-2" />
                  Check Local Devices
                </Button>
              </CardContent>
            </Card>
          </TabsContent>

          <TabsContent value="devices">
            <Card>
              <CardHeader>
                <CardTitle>Local Network Devices</CardTitle>
                <CardDescription>
                  Monitor the status of devices on your local network
                </CardDescription>
              </CardHeader>
              <CardContent>
                <div className="space-y-4">
                  {localDevices.map((device, index) => (
                    <div key={index} className="flex items-center justify-between p-4 border rounded-lg">
                      <div className="flex items-center gap-3">
                        {device.status === "online" ? (
                          <Wifi className="h-5 w-5 text-green-500" />
                        ) : device.status === "offline" ? (
                          <WifiOff className="h-5 w-5 text-red-500" />
                        ) : (
                          <Wifi className="h-5 w-5 text-gray-500" />
                        )}
                        <div>
                          <span className="font-medium">{device.name}</span>
                          <p className="text-sm text-muted-foreground">{device.ip}</p>
                        </div>
                      </div>
                      <div className="text-right">
                        <Badge variant={
                          device.status === "online" ? "default" :
                          device.status === "offline" ? "destructive" : "secondary"
                        }>
                          {device.status === "online" ? "Online" :
                           device.status === "offline" ? "Offline" : "Unknown"}
                        </Badge>
                        {device.lastSeen && (
                          <p className="text-xs text-muted-foreground mt-1">
                            Last seen: {device.lastSeen.toLocaleTimeString()}
                          </p>
                        )}
                      </div>
                    </div>
                  ))}
                </div>
              </CardContent>
            </Card>
          </TabsContent>

          <TabsContent value="ping">
            <PingTest />
          </TabsContent>

          <TabsContent value="server-ping">
            <ServerPingTest />
          </TabsContent>

          <TabsContent value="status">
            <NetworkStatus />
          </TabsContent>

          <TabsContent value="scanner">
            <NetworkScanner />
          </TabsContent>

          <TabsContent value="history">
            <PingHistory />
          </TabsContent>
        </Tabs>

        <MadeWithDyad />
      </div>
    </div>
  );
};

export default Index;