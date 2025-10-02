import { useState, useEffect } from "react";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Activity, Clock, Monitor, Server } from "lucide-react";
import { showError } from "@/utils/toast";
import NetworkStatus from "@/components/NetworkStatus";
import ServerPingTest from "@/components/ServerPingTest";
import PingHistory from "@/components/PingHistory";
import { MadeWithDyad } from "@/components/made-with-dyad";
import NetworkMap from "@/components/NetworkMap";
import { DeviceList } from "@/components/DeviceList";
import { getDevices } from "@/services/networkDeviceService";

const Index = () => {
  const [networkStatus, setNetworkStatus] = useState<boolean>(true);
  const [lastChecked, setLastChecked] = useState<Date>(new Date());
  const [deviceCount, setDeviceCount] = useState({ online: 0, total: 0 });

  const checkNetworkStatus = async () => {
    try {
      await fetch("https://www.google.com/favicon.ico", { mode: 'no-cors', cache: 'no-cache' });
      setNetworkStatus(true);
    } catch (error) {
      setNetworkStatus(false);
      showError("Internet connection appears to be offline");
    }
    setLastChecked(new Date());
  };

  const fetchDeviceStats = async () => {
    try {
      const devices = await getDevices();
      const online = devices.filter(d => d.status === 'online').length;
      setDeviceCount({ online, total: devices.length });
    } catch (error) {
      console.error("Failed to fetch device stats:", error);
    }
  };

  useEffect(() => {
    checkNetworkStatus();
    fetchDeviceStats();
    
    const networkInterval = setInterval(checkNetworkStatus, 60000);
    const deviceStatsInterval = setInterval(fetchDeviceStats, 30000);
    
    return () => {
      clearInterval(networkInterval);
      clearInterval(deviceStatsInterval);
    };
  }, []);

  return (
    <div className="min-h-screen bg-background">
      <div className="container mx-auto p-4">
        <div className="flex items-center justify-between mb-6">
          <div className="flex items-center gap-3">
            <Monitor className="h-8 w-8 text-primary" />
            <h1 className="text-3xl font-bold">Network Monitor</h1>
          </div>
          <Badge variant={networkStatus ? "default" : "destructive"} className="text-sm">
            {networkStatus ? "Internet Online" : "Internet Offline"}
          </Badge>
        </div>

        <Tabs defaultValue="dashboard" className="w-full">
          <TabsList className="mb-4 grid w-full grid-cols-3 md:grid-cols-6">
            <TabsTrigger value="dashboard">Dashboard</TabsTrigger>
            <TabsTrigger value="map">Network Map</TabsTrigger>
            <TabsTrigger value="devices">Device List</TabsTrigger>
            <TabsTrigger value="server-ping">Manual Ping</TabsTrigger>
            <TabsTrigger value="history">Ping History</TabsTrigger>
            <TabsTrigger value="status">Connectivity</TabsTrigger>
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
                    Last internet status check
                  </p>
                </CardContent>
              </Card>

              <Card>
                <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                  <CardTitle className="text-sm font-medium">Monitored Devices</CardTitle>
                  <Server className="h-4 w-4 text-muted-foreground" />
                </CardHeader>
                <CardContent>
                  <div className="text-2xl font-bold">
                    {deviceCount.online}/{deviceCount.total}
                  </div>
                  <p className="text-xs text-muted-foreground">
                    Online
                  </p>
                </CardContent>
              </Card>
            </div>
            <Card>
              <CardHeader>
                <CardTitle>Welcome to your Network Monitor</CardTitle>
                <CardContent className="pt-4">
                  <p>Use the tabs above to navigate:</p>
                  <ul className="list-disc list-inside mt-2 space-y-1">
                    <li><b>Network Map:</b> Visualize your devices and their connections.</li>
                    <li><b>Device List:</b> See a detailed table of all your monitored devices.</li>
                    <li><b>Manual Ping:</b> Run a one-off ICMP ping from the server.</li>
                    <li><b>Ping History:</b> Review historical ping results.</li>
                    <li><b>Connectivity:</b> Check your browser's internet connection history.</li>
                  </ul>
                </CardContent>
              </CardHeader>
            </Card>
          </TabsContent>

          <TabsContent value="map">
            <NetworkMap />
          </TabsContent>

          <TabsContent value="devices">
            <DeviceList />
          </TabsContent>

          <TabsContent value="server-ping">
            <ServerPingTest />
          </TabsContent>

          <TabsContent value="history">
            <PingHistory />
          </TabsContent>

          <TabsContent value="status">
            <NetworkStatus />
          </TabsContent>

        </Tabs>

        <MadeWithDyad />
      </div>
    </div>
  );
};

export default Index;