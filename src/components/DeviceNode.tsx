import { useState } from 'react';
import { Handle, Position } from 'reactflow';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Server, Router, Printer, Laptop, Wifi, Database, MoreVertical, Trash2, Edit, Activity } from 'lucide-react';
import { performServerPing, parsePingOutput } from '@/services/pingService';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { showError } from '@/utils/toast';

const iconMap: { [key: string]: React.ReactNode } = {
  server: <Server className="h-6 w-6" />,
  router: <Router className="h-6 w-6" />,
  printer: <Printer className="h-6 w-6" />,
  laptop: <Laptop className="h-6 w-6" />,
  wifi: <Wifi className="h-6 w-6" />,
  database: <Database className="h-6 w-6" />,
};

const DeviceNode = ({ data }: { data: any }) => {
  const [pingResult, setPingResult] = useState<{ time: number; loss: number } | null>(null);
  const [isPinging, setIsPinging] = useState(false);

  const handlePing = async () => {
    setIsPinging(true);
    setPingResult(null);
    try {
      const result = await performServerPing(data.ip_address, 1);
      if (result.success) {
        const parsed = parsePingOutput(result.output);
        setPingResult({ time: parsed.avgTime, loss: parsed.packetLoss });
      } else {
        setPingResult({ time: -1, loss: 100 });
      }
    } catch (error) {
      showError(`Ping failed: ${error.message}`);
      setPingResult({ time: -1, loss: 100 });
    } finally {
      setIsPinging(false);
    }
  };

  const IconComponent = iconMap[data.icon] || <Server className="h-6 w-6" />;

  return (
    <>
      <Handle type="source" position={Position.Top} />
      <Handle type="source" position={Position.Right} />
      <Handle type="source" position={Position.Bottom} />
      <Handle type="source" position={Position.Left} />
      <Card className="w-64 shadow-lg">
        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
          <CardTitle className="text-sm font-medium">{data.name}</CardTitle>
          {IconComponent}
        </CardHeader>
        <CardContent>
          <div className="font-mono text-xs text-muted-foreground">{data.ip_address}</div>
          <div className="mt-2 flex items-center justify-between">
            <Button size="sm" onClick={handlePing} disabled={isPinging}>
              <Activity className={`mr-2 h-4 w-4 ${isPinging ? 'animate-spin' : ''}`} />
              Ping
            </Button>
            {pingResult && (
              <Badge variant={pingResult.loss > 0 ? 'destructive' : 'default'}>
                {pingResult.time >= 0 ? `${pingResult.time}ms` : 'Failed'}
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