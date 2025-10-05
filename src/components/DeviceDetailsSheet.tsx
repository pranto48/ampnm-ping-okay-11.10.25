import { Sheet, SheetContent, SheetHeader, SheetTitle, SheetDescription } from '@/components/ui/sheet';
import { type NetworkDevice } from '@/services/networkDeviceService';
import DeviceHealthChart from './DeviceHealthChart';
import { Badge } from './ui/badge';

interface DeviceDetailsSheetProps {
  isOpen: boolean;
  onClose: () => void;
  device: NetworkDevice | null;
}

const DeviceDetailsSheet = ({ isOpen, onClose, device }: DeviceDetailsSheetProps) => {
  if (!device) return null;

  return (
    <Sheet open={isOpen} onOpenChange={onClose}>
      <SheetContent className="w-full sm:max-w-2xl overflow-y-auto">
        <SheetHeader>
          <SheetTitle className="text-2xl">{device.name}</SheetTitle>
          <SheetDescription>
            {device.ip_address} - Detailed health and performance statistics.
          </SheetDescription>
        </SheetHeader>
        <div className="py-6 space-y-6">
          <div className="grid grid-cols-2 gap-4 text-sm">
            <div>
              <p className="text-muted-foreground">Status</p>
              <Badge variant={device.status === 'online' ? 'default' : 'destructive'}>
                {device.status || 'unknown'}
              </Badge>
            </div>
            <div>
              <p className="text-muted-foreground">Icon</p>
              <p className="capitalize">{device.icon}</p>
            </div>
            <div>
              <p className="text-muted-foreground">Auto-Ping Interval</p>
              <p>{device.ping_interval ? `${device.ping_interval} seconds` : 'Disabled'}</p>
            </div>
            <div>
              <p className="text-muted-foreground">Last Ping</p>
              <p>{device.last_ping ? new Date(device.last_ping).toLocaleString() : 'Never'}</p>
            </div>
          </div>
          
          {device.ip_address && <DeviceHealthChart deviceIp={device.ip_address} />}

        </div>
      </SheetContent>
    </Sheet>
  );
};

export default DeviceDetailsSheet;