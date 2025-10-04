import { useEffect, useRef, useState } from 'react';
import { getDevices, updateDeviceStatusByIp, NetworkDevice } from '@/services/networkDeviceService';
import { performServerPing } from '@/services/pingService';

const DevicePinger = () => {
  const [devices, setDevices] = useState<NetworkDevice[]>([]);
  const intervals = useRef<NodeJS.Timeout[]>([]);
  const deviceStatusRef = useRef<Map<string, string | undefined>>(new Map());

  useEffect(() => {
    devices.forEach(d => deviceStatusRef.current.set(d.id!, d.status));
  }, [devices]);

  // Effect to fetch devices initially and on a long interval
  useEffect(() => {
    const fetchAndSetDevices = async () => {
      try {
        const dbDevices = await getDevices();
        setDevices(dbDevices as NetworkDevice[]);
      } catch (error) {
        console.error("DevicePinger: Failed to fetch devices", error);
      }
    };

    fetchAndSetDevices();
    const recheckInterval = setInterval(fetchAndSetDevices, 60 * 1000); // Re-fetch devices every minute

    return () => clearInterval(recheckInterval);
  }, []);

  // Effect to re-setup intervals whenever the device list changes
  useEffect(() => {
    intervals.current.forEach(clearInterval);
    intervals.current = [];

    devices.forEach((device) => {
      if (device.ping_interval && device.ping_interval > 0 && device.ip_address) {
        const intervalId = setInterval(async () => {
          try {
            const result = await performServerPing(device.ip_address!, 1);
            const newStatus = result.success ? 'online' : 'offline';
            
            const currentStatus = deviceStatusRef.current.get(device.id!);
            if (currentStatus !== newStatus) {
              await updateDeviceStatusByIp(device.ip_address!, newStatus);
              deviceStatusRef.current.set(device.id!, newStatus);
            }
          } catch (error) {
            const currentStatus = deviceStatusRef.current.get(device.id!);
            if (currentStatus !== 'offline') {
              await updateDeviceStatusByIp(device.ip_address!, 'offline');
              deviceStatusRef.current.set(device.id!, 'offline');
            }
          }
        }, device.ping_interval * 1000);
        intervals.current.push(intervalId);
      }
    });

    return () => {
      intervals.current.forEach(clearInterval);
    };
  }, [devices]);

  return null; // This is a background component, it doesn't render anything
};

export default DevicePinger;