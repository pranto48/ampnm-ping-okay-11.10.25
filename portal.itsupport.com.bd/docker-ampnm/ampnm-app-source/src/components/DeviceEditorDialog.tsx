import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import * as z from 'zod';
import { Button } from '@/components/ui/button';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Form, FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form';
import { NetworkDevice } from '@/services/networkDeviceService';
import { Textarea } from '@/components/ui/textarea'; // Import Textarea

const deviceSchema = z.object({
  name: z.string().min(1, 'Name is required'),
  ip_address: z.string().optional().nullable(), // IP can be null for 'box' type
  icon: z.string().min(1, 'Type/Icon is required'), // Corresponds to 'type' in PHP
  ping_interval: z.coerce.number().int().positive().optional().nullable(),
  icon_size: z.coerce.number().int().min(20).max(100).optional().nullable(),
  name_text_size: z.coerce.number().int().min(8).max(24).optional().nullable(),
  check_port: z.coerce.number().int().positive().optional().nullable(),
  description: z.string().optional().nullable(),
  warning_latency_threshold: z.coerce.number().int().positive().optional().nullable(),
  warning_packetloss_threshold: z.coerce.number().int().positive().optional().nullable(),
  critical_latency_threshold: z.coerce.number().int().positive().optional().nullable(),
  critical_packetloss_threshold: z.coerce.number().int().positive().optional().nullable(),
  show_live_ping: z.boolean().optional(),
}).refine((data) => {
  // If not a 'box' type, IP address is required
  if (data.icon !== 'box' && !data.ip_address) {
    return false;
  }
  return true;
}, {
  message: "IP Address is required for non-Box devices",
  path: ["ip_address"],
});

interface DeviceEditorDialogProps {
  isOpen: boolean;
  onClose: () => void;
  onSave: (device: Omit<NetworkDevice, 'id' | 'position_x' | 'position_y' | 'user_id' | 'status' | 'last_ping' | 'last_ping_result' | 'map_name' | 'last_ping_output'>) => void;
  device?: Partial<NetworkDevice>;
}

// These correspond to the 'type' enum in the PHP backend
const deviceTypes = [
  'box', 'camera', 'cloud', 'database', 'firewall', 'ipphone', 'laptop',
  'mobile', 'nas', 'rack', 'printer', 'punchdevice', 'radio-tower',
  'router', 'server', 'switch', 'tablet', 'wifi-router', 'other'
];

export const DeviceEditorDialog = ({ isOpen, onClose, onSave, device }: DeviceEditorDialogProps) => {
  const form = useForm<z.infer<typeof deviceSchema>>({
    resolver: zodResolver(deviceSchema),
    defaultValues: {
      name: device?.name || '',
      ip_address: device?.ip_address || '',
      icon: device?.icon || 'server', // PHP uses 'type' for this
      ping_interval: device?.ping_interval || undefined,
      icon_size: device?.icon_size || 50,
      name_text_size: device?.name_text_size || 14,
      check_port: device?.check_port || undefined,
      description: device?.description || '',
      warning_latency_threshold: device?.warning_latency_threshold || undefined,
      warning_packetloss_threshold: device?.warning_packetloss_threshold || undefined,
      critical_latency_threshold: device?.critical_latency_threshold || undefined,
      critical_packetloss_threshold: device?.critical_packetloss_threshold || undefined,
      show_live_ping: device?.show_live_ping || false,
    },
  });

  const handleSubmit = (values: z.infer<typeof deviceSchema>) => {
    onSave(values);
    onClose();
  };

  const deviceType = form.watch('icon'); // Watch the 'icon' field to determine device type
  const isBoxType = deviceType === 'box';

  useEffect(() => {
    // Reset IP and port if device type becomes 'box'
    if (isBoxType) {
      form.setValue('ip_address', null);
      form.setValue('check_port', null);
      form.clearErrors('ip_address');
    }
  }, [isBoxType, form]);

  return (
    <Dialog open={isOpen} onOpenChange={onClose}>
      <DialogContent className="sm:max-w-[425px] max-h-[90vh] overflow-y-auto">
        <DialogHeader>
          <DialogTitle>{device?.id ? 'Edit Device' : 'Add Device'}</DialogTitle>
          <DialogDescription>
            {device?.id ? 'Update the details for your network device.' : 'Add a new device to your network map.'}
          </DialogDescription>
        </DialogHeader>
        <Form {...form}>
          <form onSubmit={form.handleSubmit(handleSubmit)} className="space-y-4">
            <FormField
              control={form.control}
              name="name"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>Name</FormLabel>
                  <FormControl>
                    <Input placeholder="e.g., Main Router" {...field} />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />
            <FormField
              control={form.control}
              name="icon" // This maps to 'type' in PHP backend
              render={({ field }) => (
                <FormItem>
                  <FormLabel>Type / Default Icon</FormLabel>
                  <Select onValueChange={field.onChange} defaultValue={field.value}>
                    <FormControl>
                      <SelectTrigger>
                        <SelectValue placeholder="Select a type" />
                      </SelectTrigger>
                    </FormControl>
                    <SelectContent>
                      {deviceTypes.map((type) => (
                        <SelectItem key={type} value={type} className="capitalize">
                          {type}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                  <FormMessage />
                </FormItem>
              )}
            />
            {!isBoxType && (
              <>
                <FormField
                  control={form.control}
                  name="ip_address"
                  render={({ field }) => (
                    <FormItem>
                      <FormLabel>IP Address</FormLabel>
                      <FormControl>
                        <Input placeholder="e.g., 192.168.1.1" {...field} />
                      </FormControl>
                      <FormMessage />
                    </FormItem>
                  )}
                />
                <FormField
                  control={form.control}
                  name="check_port"
                  render={({ field }) => (
                    <FormItem>
                      <FormLabel>Service Port (Optional)</FormLabel>
                      <FormControl>
                        <Input
                          type="number"
                          placeholder="e.g., 80 for HTTP"
                          {...field}
                          value={field.value ?? ''}
                          onChange={(event) => field.onChange(event.target.value === '' ? null : +event.target.value)}
                        />
                      </FormControl>
                      <FormDescription>If set, status is based on this port. If empty, it will use ICMP (ping).</FormDescription>
                      <FormMessage />
                    </FormItem>
                  )}
                />
              </>
            )}
            <FormField
              control={form.control}
              name="description"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>Description (Optional)</FormLabel>
                  <FormControl>
                    <Textarea placeholder="Optional notes about the device" {...field} />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />
            {!isBoxType && (
              <>
                <FormField
                  control={form.control}
                  name="ping_interval"
                  render={({ field }) => (
                    <FormItem>
                      <FormLabel>Ping Interval (seconds)</FormLabel>
                      <FormControl>
                        <Input
                          type="number"
                          placeholder="e.g., 60 (leave blank for no auto ping)"
                          {...field}
                          value={field.value ?? ''}
                          onChange={(event) => field.onChange(event.target.value === '' ? null : +event.target.value)}
                        />
                      </FormControl>
                      <FormMessage />
                    </FormItem>
                  )}
                />
                <fieldset className="border border-border rounded-lg p-4">
                  <legend className="text-sm font-medium px-2">Status Thresholds (optional)</legend>
                  <div className="grid grid-cols-2 gap-4 py-2">
                    <FormField
                      control={form.control}
                      name="warning_latency_threshold"
                      render={({ field }) => (
                        <FormItem>
                          <FormLabel className="text-xs">Warn Latency (ms)</FormLabel>
                          <FormControl>
                            <Input
                              type="number"
                              {...field}
                              value={field.value ?? ''}
                              onChange={(event) => field.onChange(event.target.value === '' ? null : +event.target.value)}
                            />
                          </FormControl>
                          <FormMessage />
                        </FormItem>
                      )}
                    />
                    <FormField
                      control={form.control}
                      name="warning_packetloss_threshold"
                      render={({ field }) => (
                        <FormItem>
                          <FormLabel className="text-xs">Warn Packet Loss (%)</FormLabel>
                          <FormControl>
                            <Input
                              type="number"
                              {...field}
                              value={field.value ?? ''}
                              onChange={(event) => field.onChange(event.target.value === '' ? null : +event.target.value)}
                            />
                          </FormControl>
                          <FormMessage />
                        </FormItem>
                      )}
                    />
                    <FormField
                      control={form.control}
                      name="critical_latency_threshold"
                      render={({ field }) => (
                        <FormItem>
                          <FormLabel className="text-xs">Critical Latency (ms)</FormLabel>
                          <FormControl>
                            <Input
                              type="number"
                              {...field}
                              value={field.value ?? ''}
                              onChange={(event) => field.onChange(event.target.value === '' ? null : +event.target.value)}
                            />
                          </FormControl>
                          <FormMessage />
                        </FormItem>
                      )}
                    />
                    <FormField
                      control={form.control}
                      name="critical_packetloss_threshold"
                      render={({ field }) => (
                        <FormItem>
                          <FormLabel className="text-xs">Critical Packet Loss (%)</FormLabel>
                          <FormControl>
                            <Input
                              type="number"
                              {...field}
                              value={field.value ?? ''}
                              onChange={(event) => field.onChange(event.target.value === '' ? null : +event.target.value)}
                            />
                          </FormControl>
                          <FormMessage />
                        </FormItem>
                      )}
                    />
                  </div>
                </fieldset>
              </>
            )}
            <FormField
              control={form.control}
              name="icon_size"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>{isBoxType ? 'Width' : 'Icon Size'} (20-100px)</FormLabel>
                  <FormControl>
                    <Input
                      type="number"
                      placeholder="e.g., 50"
                      {...field}
                      value={field.value ?? ''}
                      onChange={(event) => field.onChange(event.target.value === '' ? null : +event.target.value)}
                    />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />
            <FormField
              control={form.control}
              name="name_text_size"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>{isBoxType ? 'Height' : 'Name Text Size'} (8-24px)</FormLabel>
                  <FormControl>
                    <Input
                      type="number"
                      placeholder="e.g., 14"
                      {...field}
                      value={field.value ?? ''}
                      onChange={(event) => field.onChange(event.target.value === '' ? null : +event.target.value)}
                    />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />
            {!isBoxType && (
              <FormField
                control={form.control}
                name="show_live_ping"
                render={({ field }) => (
                  <FormItem className="flex flex-row items-start space-x-3 space-y-0 rounded-md border p-4 shadow-sm">
                    <FormControl>
                      <input
                        type="checkbox"
                        checked={field.value}
                        onChange={field.onChange}
                        className="h-4 w-4 rounded border-primary text-primary focus:ring-primary"
                      />
                    </FormControl>
                    <div className="space-y-1 leading-none">
                      <FormLabel>Show live ping status on map</FormLabel>
                      <FormDescription>
                        Display real-time ping latency and TTL directly on the device node.
                      </FormDescription>
                    </div>
                    <FormMessage />
                  </FormItem>
                )}
              />
            )}
            <DialogFooter>
              <Button type="button" variant="ghost" onClick={onClose}>
                Cancel
              </Button>
              <Button type="submit">Save</Button>
            </DialogFooter>
          </form>
        </Form>
      </DialogContent>
    </Dialog>
  );
};