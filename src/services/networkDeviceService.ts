import { supabase } from '@/integrations/supabase/client';

export interface NetworkDevice {
  id?: string;
  name: string;
  ip_address: string;
  position_x: number;
  position_y: number;
  icon: string;
}

export const getDevices = async () => {
  const { data, error } = await supabase.from('network_devices').select('*');
  if (error) throw new Error(error.message);
  return data;
};

export const addDevice = async (device: NetworkDevice) => {
  const { data, error } = await supabase.from('network_devices').insert(device).select().single();
  if (error) throw new Error(error.message);
  return data;
};

export const updateDevice = async (id: string, updates: Partial<NetworkDevice>) => {
  const { data, error } = await supabase.from('network_devices').update(updates).eq('id', id).select().single();
  if (error) throw new Error(error.message);
  return data;
};

export const deleteDevice = async (id: string) => {
  const { error } = await supabase.from('network_devices').delete().eq('id', id);
  if (error) throw new Error(error.message);
};