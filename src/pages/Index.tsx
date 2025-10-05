import { useState, useEffect, useCallback } from "react";
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
import { getDevices, NetworkDevice, updateDeviceStatusByIp } from "@/services/networkDeviceService";
import { performServerPing } from "@/services/pingService";
import { supabase } from "@/integrations/supabase/client";
import { Skeleton } from "@/components/ui/skeleton";

const Index = () => {
  const [networkStatus, setNetworkStatus] = useState<boolean>(true);
  const [lastChecked, setLastChecked] = useState<Date>(new Date());
  const [devices, setDevices] = useState<NetworkDevice[]>([]);
  const [isCheckingDevices, setIsCheckingDevices] = useState(false);
  const [isLoading, setIsLoading] = useState(true);

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

    const channel = supabase
      .channel('network-devices-changes-index')
      .on(
        'postgres_changes',
        { event: '*', schema: 'public', table: 'network_devices' },
        (payload) => {
          console.log('Change received!', payload);
          fetchDevices();
        }
      )
      .subscribe();

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

  const checkNetworkStatus = async () => {
    try {
      await fetch("https://www.google.com/favicon.ico", { mode: 'no-cors', cache: 'no-cache' });
      setNetworkStatus(true);
    } catch (error) {
      setNetworkStatus(false);
    }
    setLastChecked(new Date());
  };

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
  }, []);

  const onlineDevicesCount = devices.filter(d => d.status === "online").length;

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
            <TabsTrigger value="map">Network Map</TabsTrigger>
          </TabsList>

          <TabsContent value="dashboard">
            {isLoading ? (
              <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
                <Card><CardHeader><Skeleton className="h-5 w-3/5" /></CardHeader><CardContent><Skeleton className="h-8 w-4/5" /></CardContent></Card>
                <Card><CardHeader><Skeleton className="h-5 w-3/5" /></CardHeader><CardContent><Skeleton className="h-8 w-4/5" /></CardContent></Card>
                <Card><CardHeader><Skeleton className="h-5 w-3/5" /></CardHeader><CardContent><Skeleton className="h-8 w-4/5" /></CardContent></Card>
              </div>
            ) : (
              <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
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
                    <CardTitle className="text-sm font-medium">Local Devices</CardTitle>
                    <Server className="h-4 w-4 text-muted-foreground" />
                  </CardHeader>
                  <CardContent>
                    <div className="text-2xl font-bold">{onlineDevicesCount}/{devices.length}</div>
                    <p className="text-xs text-muted-foreground">Devices online</p>
                  </CardContent>
                </Card>
              </div>
            )}

            <Card className="mb-6">
              <CardHeader><CardTitle className="flex items-center gap-2"><Network className="h-5 w-5" />Quick Actions</CardTitle></CardHeader>
              <CardContent className="flex gap-4">
                <Button onClick={checkNetworkStatus}><RefreshCw className="h-4 w-4 mr-2" />Check Internet</Button>
                <Button onClick={handleCheckAllDevices} disabled={isCheckingDevices || isLoading}>
                  <RefreshCw className={`h-4 w-4 mr-2 ${isCheckingDevices ? 'animate-spin' : ''}`} />
                  {isCheckingDevices ? 'Checking...' : 'Check Local Devices'}
                </Button>
              </CardContent>
            </Card>
          </TabsContent>

          <TabsContent value="devices">
            <Card>
              <CardHeader><CardTitle>Local Network Devices</CardTitle><CardDescription>Monitor the status of devices on your local network</CardDescription></CardHeader>
              <CardContent>
                {isLoading ? (
                  <div className="space-y-4">
                    <Skeleton className="h-16 w-full rounded-lg" />
                    <Skeleton className="h-16 w-full rounded-lg" />
                    <Skeleton className="h-16 w-full rounded-lg" />
                  </div>
                ) : (
                  <div className="space-y-4">
                    {devices.map((device) => (
                      <div key={device.id} className="flex items-center justify-between p-4 border rounded-lg">
                        <div className="flex items-center gap-3">
                          {device.status === "online" ? <Wifi className="h-5 w-5 text-green-500" /> : device.status === "offline" ? <WifiOff className="h-5 w-5 text-red-500" /> : <Wifi className="h-5 w-5 text-gray-500" />}
                          <div>
                            <span className="font-medium">{device.name}</span>
                            <p className="text-sm text-muted-foreground">{device.ip_address}</p>
                          </div>
                        </div>
                        <div className="text-right">
                          <Badge variant={device.status === "online" ? "default" : device.status === "offline" ? "destructive" : "secondary"}>{device.status || 'unknown'}</Badge>
                        </div>
                      </div>
                    ))}
                  </div>
                )}
              </CardContent>
            </Card>
          </TabsContent>

          <TabsContent value="ping"><PingTest /></TabsContent>
          <TabsContent value="server-ping"><ServerPingTest /></TabsContent>
          <TabsContent value="status"><NetworkStatus /></TabsContent>
          <TabsContent value="scanner"><NetworkScanner /></TabsContent>
          <TabsContent value="history"><PingHistory /></TabsContent>
          <TabsContent value="map"><NetworkMap devices={devices} onMapUpdate={fetchDevices} /></TabsContent>
        </Tabs>

        <MadeWithDyad />
      </div>
    </div>
  );
};

export default Index;