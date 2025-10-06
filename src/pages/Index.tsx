import { useState, useEffect, useCallback, useMemo } from "react";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Activity, Wifi, Server, Clock, RefreshCw, Monitor, Network, WifiOff, BarChart3 } from "lucide-react";
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
  type NetworkDevice, 
  updateDeviceStatusByIp, 
  subscribeToDeviceChanges 
} from "@/services/networkDeviceService";
import { performServerPing } from "@/services/pingService";
import { supabase } from "@/integrations/supabase/client";
import { Skeleton } from "@/components/ui/skeleton";
import DeviceDetailsSheet from "@/components/DeviceDetailsSheet";

const Index = () => {
  const [networkStatus, setNetworkStatus] = useState<boolean>(true);
  const [lastChecked, setLastChecked] = useState<Date>(new Date());
  const [devices, setDevices] = useState<NetworkDevice[]>([]);
  const [isCheckingDevices, setIsCheckingDevices] = useState(false);
  const [isLoading, setIsLoading] = useState(true);
  const [selectedDevice, setSelectedDevice] = useState<NetworkDevice | null>(null);
  const [isSheetOpen, setIsSheetOpen] = useState(false);

  const fetchDevices = useCallback(async () => {
    try {
      const dbDevices = await getDevices();
      setDevices(dbDevices as NetworkDevice[]);
    } catch (error) {
      showError("Failed to load devices from database.");
    } finally {
      setIsLoading(false);
    }
  }, []);

  useEffect(() => {
    fetchDevices();

    const channel = subscribeToDeviceChanges((payload) => {
      console.log('Device change received:', payload);
      fetchDevices();
    });

    return () => {
      supabase.removeChannel(channel);
    };
  }, [fetchDevices]);

  useEffect(() => {
    const intervals: NodeJS.Timeout[] = [];
    
    devices.forEach((device) => {
      if (device.ping_interval && device.ping_interval > 0 && device.ip_address) {
        const intervalId = setInterval(async () => {
          try {
            const result = await performServerPing(device.ip_address, 1);
            const newStatus = result.success ? 'online' : 'offline';
            await updateDeviceStatusByIp(device.ip_address, newStatus);
          } catch (error) {
            console.error(`Auto-ping failed for ${device.ip_address}:`, error);
            await updateDeviceStatusByIp(device.ip_address, 'offline');
          }
        }, device.ping_interval * 1000);
        
        intervals.push(intervalId);
      }
    });

    return () => {
      intervals.forEach(clearInterval);
    };
  }, [devices]);

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
      const pingPromises = devices.map(async (device) => {
        if (device.ip_address) {
          const result = await performServerPing(device.ip_address, 1);
          const newStatus = result.success ? 'online' : 'offline';
          await updateDeviceStatusByIp(device.ip_address, newStatus);
        }
      });

      await Promise.all(pingPromises);
      
      dismissToast(toastId);
      showSuccess(`Finished checking all devices.`);
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

  const handleViewDetails = (deviceId: string) => {
    const device = devices.find(d => d.id === deviceId);
    if (device) {
      setSelectedDevice(device);
      setIsSheetOpen(true);
    }
  };

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
            <TabsTrigger value="map">Network Map</TabsTrigger>
            <TabsTrigger value="devices">Devices</TabsTrigger>
            <TabsTrigger value="history">Ping History</TabsTrigger>
            <TabsTrigger value="tools">Tools</TabsTrigger>
          </TabsList>

          <TabsContent value="dashboard">
            {isLoading ? (
              <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                {[...Array(4)].map((_, i) => (
                  <Card key={i}><CardHeader><Skeleton className="h-4 w-3/4" /></CardHeader><CardContent><Skeleton className="h-8 w-1/2" /></CardContent></Card>
                ))}
              </div>
            ) : (
              <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                <Card><CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2"><CardTitle className="text-sm font-medium">Internet Status</CardTitle><Activity className="h-4 w-4 text-muted-foreground" /></CardHeader><CardContent><div className="text-2xl font-bold">{networkStatus ? "Online" : "Offline"}</div><p className="text-xs text-muted-foreground">Internet connectivity</p></CardContent></Card>
                <Card><CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2"><CardTitle className="text-sm font-medium">Last Check</CardTitle><Clock className="h-4 w-4 text-muted-foreground" /></CardHeader><CardContent><div className="text-2xl font-bold">{lastChecked.toLocaleTimeString()}</div><p className="text-xs text-muted-foreground">Last status check</p></CardContent></Card>
                <Card><CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2"><CardTitle className="text-sm font-medium">Devices Online</CardTitle><Wifi className="h-4 w-4 text-muted-foreground" /></CardHeader><CardContent><div className="text-2xl font-bold">{onlineDevicesCount}/{devices.length}</div><p className="text-xs text-muted-foreground">Devices online</p></CardContent></Card>
                <Card><CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2"><CardTitle className="text-sm font-medium">Device Status</CardTitle><Server className="h-4 w-4 text-muted-foreground" /></CardHeader><CardContent><div className="flex gap-2"><Badge variant="default" className="text-xs">Online {deviceStatusCounts.online || 0}</Badge><Badge variant="destructive" className="text-xs">Offline {deviceStatusCounts.offline || 0}</Badge></div></CardContent></Card>
              </div>
            )}
            <Card className="mb-6"><CardHeader><CardTitle className="flex items-center gap-2"><Network className="h-5 w-5" />Quick Actions</CardTitle></CardHeader><CardContent className="flex flex-wrap gap-4"><Button onClick={checkNetworkStatus} variant="outline"><RefreshCw className="h-4 w-4 mr-2" />Check Internet</Button><Button onClick={handleCheckAllDevices} disabled={isCheckingDevices || isLoading} variant="outline"><RefreshCw className={`h-4 w-4 mr-2 ${isCheckingDevices ? 'animate-spin' : ''}`} />{isCheckingDevices ? 'Checking...' : 'Check All Devices'}</Button></CardContent></Card>
            <ServerPingTest />
          </TabsContent>

          <TabsContent value="devices">
            <Card>
              <CardHeader><CardTitle>Local Network Devices</CardTitle><CardDescription>Monitor the status of devices on your local network</CardDescription></CardHeader>
              <CardContent>
                {isLoading ? (
                  <div className="space-y-4">{[...Array(5)].map((_, i) => (<Skeleton key={i} className="h-16 w-full rounded-lg" />))}</div>
                ) : devices.length === 0 ? (
                  <div className="text-center py-8 text-muted-foreground"><Server className="h-12 w-12 mx-auto mb-4" /><p>No devices found. Add devices to start monitoring.</p></div>
                ) : (
                  <div className="space-y-4">
                    {devices.map((device) => (
                      <div key={device.id} className="flex items-center justify-between p-4 border rounded-lg transition-colors hover:bg-muted">
                        <div className="flex items-center gap-3">
                          {device.status === "online" ? <Wifi className="h-5 w-5 text-green-500" /> : device.status === "offline" ? <WifiOff className="h-5 w-5 text-red-500" /> : <Wifi className="h-5 w-5 text-gray-500" />}
                          <div><span className="font-medium">{device.name}</span><p className="text-sm text-muted-foreground">{device.ip_address}</p></div>
                        </div>
                        <div className="flex items-center gap-4">
                          <Button variant="outline" size="sm" onClick={() => handleViewDetails(device.id!)}><BarChart3 className="h-4 w-4 mr-2" />Details</Button>
                          <Badge variant={device.status === "online" ? "default" : device.status === "offline" ? "destructive" : "secondary"}>{device.status || 'unknown'}</Badge>
                        </div>
                      </div>
                    ))}
                  </div>
                )}
              </CardContent>
            </Card>
          </TabsContent>
          
          <TabsContent value="history"><PingHistory /></TabsContent>
          
          <TabsContent value="map"><NetworkMap devices={devices} onMapUpdate={fetchDevices} onViewDetails={handleViewDetails} /></TabsContent>

          <TabsContent value="tools" className="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <PingTest />
            <NetworkStatus />
            <NetworkScanner />
          </TabsContent>
        </Tabs>

        <MadeWithDyad />
      </div>
      <DeviceDetailsSheet isOpen={isSheetOpen} onClose={() => setIsSheetOpen(false)} device={selectedDevice} />
    </div>
  );
};

export default Index;