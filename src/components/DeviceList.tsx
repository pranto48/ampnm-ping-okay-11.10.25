import { useState, useEffect, useCallback } from 'react';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { MoreHorizontal, Trash2, Activity, RefreshCw } from 'lucide-react';
import { getDevices, deleteDevice, updateDevice, NetworkDevice } from '@/services/networkDeviceService';
import { performServerPing } from '@/services/pingService';
import { showError, showSuccess } from '@/utils/toast';

export const DeviceList = () => {
  const [devices, setDevices] = useState<NetworkDevice[]>([]);
  const [isLoading, setIsLoading] = useState(true);

  const fetchDevices = useCallback(async () => {
    setIsLoading(true);
    try {
      const data = await getDevices();
      setDevices(data);
    } catch (error) {
      showError('Failed to load devices.');
    } finally {
      setIsLoading(false);
    }
  }, []);

  useEffect(() => {
    fetchDevices();
  }, [fetchDevices]);

  const handlePingDevice = async (device: NetworkDevice) => {
    if (!device.id) return;
    try {
      const result = await performServerPing(device.ip_address, 1);
      const newStatus = result.success ? 'online' : 'offline';
      
      await updateDevice(device.id, { status: newStatus });
      
      setDevices(prevDevices => 
        prevDevices.map(d => d.id === device.id ? { ...d, status: newStatus } : d)
      );
      showSuccess(`Ping to ${device.name} was ${result.success ? 'successful' : 'unsuccessful'}.`);
    } catch (error) {
      showError(`Failed to ping ${device.name}.`);
      setDevices(prevDevices => 
        prevDevices.map(d => d.id === device.id ? { ...d, status: 'offline' } : d)
      );
    }
  };

  const handleDeleteDevice = async (deviceId: string) => {
    if (window.confirm('Are you sure you want to delete this device?')) {
      try {
        await deleteDevice(deviceId);
        setDevices(prevDevices => prevDevices.filter(d => d.id !== deviceId));
        showSuccess('Device deleted successfully.');
      } catch (error) {
        showError('Failed to delete device.');
      }
    }
  };

  const getStatusBadge = (status?: string) => {
    switch (status) {
      case 'online':
        return <Badge variant="default" className="bg-green-500">Online</Badge>;
      case 'offline':
        return <Badge variant="destructive">Offline</Badge>;
      default:
        return <Badge variant="secondary">Unknown</Badge>;
    }
  };

  return (
    <div>
      <div className="flex justify-end mb-4">
        <Button onClick={fetchDevices} disabled={isLoading}>
          <RefreshCw className={`mr-2 h-4 w-4 ${isLoading ? 'animate-spin' : ''}`} />
          Refresh List
        </Button>
      </div>
      <div className="border rounded-lg">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>Name</TableHead>
              <TableHead>IP Address</TableHead>
              <TableHead>Status</TableHead>
              <TableHead>Auto Ping</TableHead>
              <TableHead className="text-right">Actions</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {isLoading ? (
              <TableRow>
                <TableCell colSpan={5} className="text-center">Loading devices...</TableCell>
              </TableRow>
            ) : devices.length > 0 ? (
              devices.map((device) => (
                <TableRow key={device.id}>
                  <TableCell className="font-medium">{device.name}</TableCell>
                  <TableCell>{device.ip_address}</TableCell>
                  <TableCell>{getStatusBadge(device.status)}</TableCell>
                  <TableCell>{device.ping_interval ? `${device.ping_interval}s` : 'Disabled'}</TableCell>
                  <TableCell className="text-right">
                    <DropdownMenu>
                      <DropdownMenuTrigger asChild>
                        <Button variant="ghost" className="h-8 w-8 p-0">
                          <span className="sr-only">Open menu</span>
                          <MoreHorizontal className="h-4 w-4" />
                        </Button>
                      </DropdownMenuTrigger>
                      <DropdownMenuContent align="end">
                        <DropdownMenuItem onClick={() => handlePingDevice(device)}>
                          <Activity className="mr-2 h-4 w-4" />
                          <span>Ping Now</span>
                        </DropdownMenuItem>
                        <DropdownMenuItem onClick={() => handleDeleteDevice(device.id!)} className="text-red-500">
                          <Trash2 className="mr-2 h-4 w-4" />
                          <span>Delete</span>
                        </DropdownMenuItem>
                      </DropdownMenuContent>
                    </DropdownMenu>
                  </TableCell>
                </TableRow>
              ))
            ) : (
              <TableRow>
                <TableCell colSpan={5} className="text-center">No devices found. Add devices in the Network Map tab.</TableCell>
              </TableRow>
            )}
          </TableBody>
        </Table>
      </div>
    </div>
  );
};