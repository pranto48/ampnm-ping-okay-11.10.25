import { useState } from 'react';
import { Handle, Position } from 'reactflow';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Server, Router, Printer, Laptop, Wifi, Database, MoreVertical, Trash2, Edit, Activity } from 'lucide-react';
import { performBrowserPing } from '@/services/browserPingService';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { showError, showSuccess } from '@/utils/toast';
import { updateDeviceStatusByIp } from '@/services/networkDeviceService';

const iconMap: { [key: string]: React.ReactNode } = {
  server: <Server className="h-6 w-6" />,
  router: <Router className="h-6 w-6" />,
  printer: <Printer className="h-6 w-6" />,
  laptop: <Laptop className="h-6 w-6" />,
  wifi: <Wifi className="h-6 w-6" />,
  database: <Database className="h-6 w-6" />,
};

const DeviceNode = ({ data }: { data: any }) => {
  const [pingTime, setPingTime] = useState<number | null>(null);
  const [isPinging, setIsPinging] = useState(false);

  const handlePing = async () => {
    setIsPinging(true);
    setPingTime(null);
    try {
      const responseTime = await performBrowserPing(data.ip_address);
      setPingTime(responseTime);
      data.onStatusChange(data.id, 'online');
      await updateDeviceStatusByIp(data.ip_address, 'online');
      showSuccess(`Device ${data.ip_address} is online (${responseTime}ms).`);
    } catch (error) {
      setPingTime(-1); // Indicate failure
      data.onStatusChange(data.id, 'offline');
      await updateDeviceStatusByIp(data.ip_address, 'offline');
      showError(`Device ${data.ip_address} is offline or not responding.`);
    } finally {
      setIsPinging(false);
    }
  };

  const IconComponent = iconMap[data.icon] || <Server className="h-6 w-6" />;
  const statusBorderColor =
    data.status === 'online'
      ? 'border-green-500'
      : data.status === 'offline'
      ? 'border-red-500'
      : 'border-yellow-500';

  return (
    <>
      <Handle type="source" position={Position.Top} />
      <Handle type="source" position={Position.Right} />
      <Handle type="source" position={Position.Bottom} />
      <Handle type="source" position={Position.Left} />
      <Card className={`w-64 shadow-lg bg-gray-800 border-gray-700 text-white border-2 ${statusBorderColor}`}>
        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
          <CardTitle className="text-sm font-medium text-white">{data.name}</CardTitle>
          {IconComponent}
        </CardHeader>
        <CardContent>
          <div className="font-mono text-xs text-gray-400">{data.ip_address}</div>
          <div className="mt-2 flex items-center justify-between">
            <Button size="sm" onClick={handlePing} disabled={isPinging}>
              <Activity className={`mr-2 h-4 w-4 ${isPinging ? 'animate-spin' : ''}`} />
              Ping
            </Button>
            {pingTime !== null && (
              <Badge variant={pingTime >= 0 ? 'default' : 'destructive'}>
                {pingTime >= 0 ? `${pingTime}ms` : 'Failed'}
              </Badge>
            )}
          </div>
        </CardContent>
        <div className="absolute top-1 right-1">
          <DropdownMenu>
            <DropdownMenuTrigger asChild>
              <Button variant="ghost" size="icon" className="h-6 w-6">
                <MoreVertical className="h-4 w-4" />
              </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent>
              <DropdownMenuItem onClick={() => data.onEdit(data.id)}>
                <Edit className="mr-2 h-4 w-4" />
                Edit
              </DropdownMenuItem>
              <DropdownMenuItem onClick={() => data.onDelete(data.id)} className="text-red-500">
                <Trash2 className="mr-2 h-4 w-4" />
                Delete
              </DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>
        </div>
      </Card>
    </>
  );
};

export default DeviceNode;