import { useState, useEffect, useCallback, useMemo } from "react";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Activity, Wifi, Server, Clock, RefreshCw, Monitor, Network, WifiOff } from "lucide-react";
import { showSuccess, showError, showLoading, dismissToast } from "@/utils/toast";
import PingTest from "@/components/PingTest";
import NetworkStatus from "@/components/NetworkStatus";
import NetworkScanner from "@/components/NetworkScanner";
import ServerPingTest from "@/components/ServerPingTest";
import PingHistory from "@/components/PingHistory";
import { MadeWithDyad } from "@/components/made-with-dyad";
import NetworkMap from "@/components/NetworkMap";
import {
  getDevices,
  NetworkDevice,
  updateDeviceStatusByIp,
} from "@/services/networkDeviceService";
import { performServerPing } from "@/services/pingService";
import { Skeleton } from "@/components/ui/skeleton";

// Define a type for Map data from PHP backend
interface Map {
  id: string;
  name: string;
}

interface DashboardStats {
  total: number;
  online: number;
  warning: number;
  critical: number;
  offline: number;
}

interface RecentActivity {
  created_at: string;
  status: string;
  details: string;
  device_name: string;
  device_ip: string;
}

const Index = () => {
  const [networkStatus, setNetworkStatus] = useState<boolean>(true);
  const [lastChecked, setLastChecked] = useState<Date>(new Date());
  const [devices, setDevices] = useState<NetworkDevice[]>([]);
  const [isCheckingDevices, setIsCheckingDevices] = useState(false);
  const [isLoading, setIsLoading] = useState(true);
  const [maps, setMaps] = useState<Map[]>([]);
  const [currentMapId, setCurrentMapId] = useState<string | null>(null);
  const [dashboardStats, setDashboardStats] = useState<DashboardStats | null>(null);
  const [recentActivity, setRecentActivity] = useState<RecentActivity[]>([]);

  const fetchMaps = useCallback(async () => {
    try {
      const response = await fetch('/api.php?action=get_maps'); // Changed to relative path
      if (!response.ok) throw new Error('Failed to fetch maps');
      const data = await response.json();
      const phpMaps = data.map((m: any) => ({ id: String(m.id), name: m.name }));
      setMaps(phpMaps);
      if (phpMaps.length > 0 && !currentMapId) {
        setCurrentMapId(phpMaps[0].id);
      } else if (phpMaps.length === 0) {
        setCurrentMapId(null);
      }
    } catch (error) {
      showError("Failed to load maps from database.");
      console.error("Failed to load maps:", error);
    }
  }, [currentMapId]);

  const fetchDevices = useCallback(async () => {
    if (!currentMapId) {
      setDevices([]);
      setIsLoading(false);
      return;
    }
    try {
      const dbDevices = await getDevices(currentMapId);
      setDevices(dbDevices as NetworkDevice[]);
    } catch (error) {
      showError("Failed to load devices from database.");
      console.error("Failed to load devices:", error);
    } finally {
      setIsLoading(false);
    }
  }, [currentMapId]);

  const fetchDashboardData = useCallback(async () => {
    if (!currentMapId) {
      setDashboardStats(null);
      setRecentActivity([]);
      return;
    }
    try {
      const response = await fetch(`/api.php?action=get_dashboard_data&map_id=${currentMapId}`); // Changed to relative path
      if (!response.ok) throw new Error('Failed to fetch dashboard data');
      const data = await response.json();
      setDashboardStats(data.stats);
      setRecentActivity(data.recent_activity);
    } catch (error) {
      showError("Failed to load dashboard data.");
      console.error("Failed to load dashboard data:", error);
    }
  }, [currentMapId]);

  useEffect(() => {
    fetchMaps();
  }, [fetchMaps]);

  useEffect(() => {
    fetchDevices();
    fetchDashboardData();
  }, [fetchDevices, fetchDashboardData]);

  // Auto-ping devices based on their ping interval
  useEffect(() => {
    const intervals: NodeJS.Timeout[] = [];

    devices.forEach((device) => {
      if (device.ping_interval && device.ping_interval > 0 && device.ip_address) {
        const intervalId = setInterval(async () => {
          try {
            console.log(`Auto-pinging ${device.ip_address}`);
            const result = await performServerPing(device.ip_address, 1);
            const newStatus = result.success ? 'online' : 'offline';

            fetchDevices();
            fetchDashboardData(); // Refresh dashboard data after auto-ping

            console.log(`Ping result for ${device.ip_address}: ${newStatus}`);
          } catch (error) {
            console.error(`Auto-ping failed for ${device.ip_address}:`, error);
            await updateDeviceStatusByIp(device.ip_address, 'offline');
            fetchDevices();
            fetchDashboardData(); // Refresh dashboard data after auto-ping
          }
        }, device.ping_interval * 1000);

        intervals.push(intervalId);
      }
    });

    // Cleanup intervals on component unmount or devices change
    return () => {
      intervals.forEach(clearInterval);
    };
  }, [devices, fetchDevices, fetchDashboardData]);

  const checkNetworkStatus = useCallback(async () => {
    try {
      await fetch("https://www.google.com/favicon.ico", { mode: 'no-cors', cache: 'no-cache' });
      setNetworkStatus(true);
    } catch (error) {
      setNetworkStatus(false);
    }
    setLastChecked(new Date());
  }, []);

  const handleCheckAllDevices = async () => {
    setIsCheckingDevices(true);
    const toastId = showLoading(`Pinging ${devices.length} devices...`);
    try {
      const response = await fetch('/api.php?action=ping_all_devices', { // Changed to relative path
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ map_id: currentMapId })
      });
      if (!response.ok) throw new Error('Failed to ping all devices via server.');
      const result = await response.json();

      if (result.success) {
        dismissToast(toastId);
        showSuccess(`Finished checking all devices. ${result.updated_devices.length} devices updated.`);
        fetchDevices();
        fetchDashboardData(); // Refresh dashboard data after bulk ping
      } else {
        throw new Error(result.error || "Unknown error during bulk ping.");
      }
    } catch (error: any) {
      dismissToast(toastId);
      showError(error.message || "An error occurred while checking devices.");
    } finally {
      setIsCheckingDevices(false);
    }
  };

  useEffect(() => {
    checkNetworkStatus();
    const networkInterval = setInterval(checkNetworkStatus, 60000);
    return () => clearInterval(networkInterval);
  }, [checkNetworkStatus]);

  const onlineDevicesCount = useMemo(() =>
    devices.filter(d => d.status === "online").length,
    [devices]
  );

  const deviceStatusCounts = useMemo(() => {
    return devices.reduce((acc, device) => {
      const status = device.status || 'unknown';
      acc[status] = (acc[status] || 0) + 1;
      return acc;
    }, {} as Record<string, number>);
  }, [devices]);

  const statusColorMap: Record<string, string> = {
    online: 'text-green-500',
    warning: 'text-yellow-500',
    critical: 'text-red-500',
    offline: 'text-gray-500',
    unknown: 'text-gray-500',
  };

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
            <TabsTrigger value="dashboard" className="flex items-center gap-2">
              <Activity className="h-4 w-4" />
              Dashboard
            </TabsTrigger>
            <TabsTrigger value="devices" className="flex items-center gap-2">
              <Server className="h-4 w-4" />
              Devices
            </TabsTrigger>
            <TabsTrigger value="ping" className="flex items-center gap-2">
              <Wifi className="h-4 w-4" />
              Browser Ping
            </TabsTrigger>
            <TabsTrigger value="server-ping" className="flex items-center gap-2">
              <Server className="h-4 w-4" />
              Server Ping
            </TabsTrigger>
            <TabsTrigger value="status" className="flex items-center gap-2">
              <Network className="h-4 w-4" />
              Network Status
            </TabsTrigger>
            <TabsTrigger value="scanner" className="flex items-center gap-2">
              <RefreshCw className="h-4 w-4" />
              Network Scanner
            </TabsTrigger>
            <TabsTrigger value="history" className="flex items-center gap-2">
              <Clock className="h-4 w-4" />
              Ping History
            </TabsTrigger>
            <TabsTrigger value="map" className="flex items-center gap-2">
              <Network className="h-4 w-4" />
              Network Map
            </TabsTrigger>
          </TabsList>

          <TabsContent value="dashboard">
            {isLoading || !dashboardStats ? (
              <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                {[...Array(4)].map((_, i) => (
                  <Card key={i}>
                    <CardHeader>
                      <Skeleton className="h-4 w-3/4" />
                    </CardHeader>
                    <CardContent>
                      <Skeleton className="h-8 w-1/2" />
                    </CardContent>
                  </Card>
                ))}
              </div>
            ) : (
              <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                <Card>
                  <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                    <CardTitle className="text-sm font-medium">Internet Status</CardTitle>
                    <Activity className="h-4 w-4 text-muted-foreground" />
                  </CardHeader>
                  <CardContent>
                    <div className="text-2xl font-bold">{networkStatus ? "Online" : "Offline"}</div>
                    <p className="text-xs text-muted-foreground">Internet connectivity</p>
                  </CardContent>
                </Card>
                <Card>
                  <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                    <CardTitle className="text-sm font-medium">Last Check</CardTitle>
                    <Clock className="h-4 w-4 text-muted-foreground" />
                  </CardHeader>
                  <CardContent>
                    <div className="text-2xl font-bold">{lastChecked.toLocaleTimeString()}</div>
                    <p className="text-xs text-muted-foreground">Last status check</p>
                  </CardContent>
                </Card>
                <Card>
                  <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                    <CardTitle className="text-sm font-medium">Devices Online</CardTitle>
                    <Wifi className="h-4 w-4 text-muted-foreground" />
                  </CardHeader>
                  <CardContent>
                    <div className="text-2xl font-bold">{dashboardStats.online}/{dashboardStats.total}</div>
                    <p className="text-xs text-muted-foreground">Devices online</p>
                  </CardContent>
                </Card>
                <Card>
                  <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                    <CardTitle className="text-sm font-medium">Device Status</CardTitle>
                    <Server className="h-4 w-4 text-muted-foreground" />
                  </CardHeader>
                  <CardContent>
                    <div className="flex flex-wrap gap-2">
                      <Badge variant="default" className="text-xs">
                        Online {dashboardStats.online}
                      </Badge>
                      <Badge variant="secondary" className="text-xs">
                        Warning {dashboardStats.warning}
                      </Badge>
                      <Badge variant="destructive" className="text-xs">
                        Critical {dashboardStats.critical}
                      </Badge>
                      <Badge variant="destructive" className="text-xs">
                        Offline {dashboardStats.offline}
                      </Badge>
                    </div>
                  </CardContent>
                </Card>
              </div>
            )}

            <Card className="mb-6">
              <CardHeader>
                <CardTitle className="flex items-center gap-2">
                  <Network className="h-5 w-5" />Quick Actions
                </CardTitle>
              </CardHeader>
              <CardContent className="flex flex-wrap gap-4">
                <Button onClick={checkNetworkStatus} variant="outline">
                  <RefreshCw className="h-4 w-4 mr-2" />Check Internet
                </Button>
                <Button
                  onClick={handleCheckAllDevices}
                  disabled={isCheckingDevices || isLoading || !currentMapId}
                  variant="outline"
                >
                  <RefreshCw className={`h-4 w-4 mr-2 ${isCheckingDevices ? 'animate-spin' : ''}`} />
                  {isCheckingDevices ? 'Checking...' : 'Check All Devices'}
                </Button>
              </CardContent>
            </Card>

            <Card>
              <CardHeader>
                <CardTitle className="flex items-center gap-2">
                  <Clock className="h-5 w-5" />Recent Activity
                </CardTitle>
                <CardDescription>Latest status changes and events across your network.</CardDescription>
              </CardHeader>
              <CardContent>
                {recentActivity.length === 0 ? (
                  <div className="text-center py-8 text-muted-foreground">
                    <Server className="h-12 w-12 mx-auto mb-4" />
                    <p>No recent activity for this map.</p>
                  </div>
                ) : (
                  <div className="space-y-4">
                    {recentActivity.map((activity, index) => (
                      <div key={index} className="flex items-center justify-between p-4 border rounded-lg transition-colors hover:bg-muted">
                        <div className="flex items-center gap-3">
                          <Server className="h-5 w-5 text-muted-foreground" />
                          <div>
                            <span className="font-medium">{activity.device_name}</span>
                            <p className="text-sm text-muted-foreground">{activity.device_ip || 'N/A'}</p>
                          </div>
                        </div>
                        <div className="flex items-center gap-4">
                          <Badge variant="secondary" className={`${statusColorMap[activity.status]}`}>
                            {activity.status}
                          </Badge>
                          <div className="text-xs text-muted-foreground">
                            {new Date(activity.created_at).toLocaleTimeString()}
                          </div>
                        </div>
                      </div>
                    ))}
                  </div>
                )}
              </CardContent>
            </Card>
          </TabsContent>

          <TabsContent value="devices">
            <Card>
              <CardHeader>
                <CardTitle>Local Network Devices</CardTitle>
                <CardDescription>Monitor the status of devices on your local network</CardDescription>
              </CardHeader>
              <CardContent>
                {isLoading ? (
                  <div className="space-y-4">
                    {[...Array(5)].map((_, i) => (
                      <Skeleton key={i} className="h-16 w-full rounded-lg" />
                    ))}
                  </div>
                ) : devices.length === 0 ? (
                  <div className="text-center py-8 text-muted-foreground">
                    <Server className="h-12 w-12 mx-auto mb-4" />
                    <p>No devices found. Add devices to start monitoring.</p>
                  </div>
                ) : (
                  <div className="space-y-4">
                    {devices.map((device) => (
                      <div
                        key={device.id}
                        className="flex items-center justify-between p-4 border rounded-lg transition-colors hover:bg-muted"
                      >
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
                            <p className="text-sm text-muted-foreground">{device.ip_address}</p>
                          </div>
                        </div>
                        <div className="flex items-center gap-4">
                          {device.last_ping && (
                            <div className="text-xs text-muted-foreground">
                              Last ping: {new Date(device.last_ping).toLocaleTimeString()}
                            </div>
                          )}
                          <Badge
                            variant={
                              device.status === "online" ? "default" :
                              device.status === "offline" ? "destructive" : "secondary"
                            }
                          >
                            {device.status || 'unknown'}
                          </Badge>
                        </div>
                      </div>
                    ))}
                  </div>
                )}
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

          <TabsContent value="map">
            <div className="flex items-center gap-2 mb-4">
              <label htmlFor="map-select" className="text-sm font-medium">Select Map:</label>
              <select
                id="map-select"
                className="flex h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                value={currentMapId || ''}
                onChange={(e) => setCurrentMapId(e.target.value)}
              >
                {maps.length === 0 ? (
                  <option value="">No maps available</option>
                ) : (
                  maps.map((map) => (
                    <option key={map.id} value={map.id}>
                      {map.name}
                    </option>
                  ))
                )}
              </select>
              <Button onClick={fetchMaps} variant="outline" size="sm">
                <RefreshCw className="h-4 w-4" />
              </Button>
            </div>
            {currentMapId ? (
              <NetworkMap devices={devices} onMapUpdate={fetchDevices} mapId={currentMapId} />
            ) : (
              <Card className="h-[70vh] flex items-center justify-center">
                <CardContent className="text-center">
                  <Network className="h-12 w-12 text-muted-foreground mx-auto mb-4" />
                  <p className="text-muted-foreground">No map selected or available. Please create one in the PHP frontend or select an existing one.</p>
                </CardContent>
              </Card>
            )}
          </TabsContent>
        </Tabs>

        <MadeWithDyad />
      </div>
    </div>
  );
};

export default Index;