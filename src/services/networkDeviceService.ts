import { supabase } from '@/integrations/supabase/client';

export interface NetworkDevice {
  id?: string;
  user_id?: string;
  name: string;
  ip_address: string;
  position_x: number;
  position_y: number;
  icon: string;
  status?: 'online' | 'offline' | 'unknown';
  ping_interval?: number;
  icon_size?: number;
  name_text_size?: number;
}

export interface MapData {
  devices: Omit<NetworkDevice, 'user_id' | 'status'>[];
  edges: { source: string; target: string; connection_type: string }[];
}

export const getDevices = async () => {
  const { data, error } = await supabase.from('network_devices').select('*');
  if (error) throw new Error(error.message);
  return data;
};

export const addDevice = async (device: Omit<NetworkDevice, 'user_id'>) => {
  const { data: { user } } = await supabase.auth.getUser();
  if (!user) throw new Error('User not authenticated');
  
  const deviceWithUser = { ...device, user_id: user.id };
  const { data, error } = await supabase.from('network_devices').insert(deviceWithUser).select().single();
  if (error) throw new Error(error.message);
  return data;
};

export const updateDevice = async (id: string, updates: Partial<NetworkDevice>) => {
  const { data, error } = await supabase.from('network_devices').update(updates).eq('id', id).select().single();
  if (error) throw new Error(error.message);
  return data;
};

export const updateDeviceStatusByIp = async (ip_address: string, status: 'online' | 'offline') => {
  const { error } = await supabase.rpc('update_device_status_by_ip', {
    ip_address_in: ip_address,
    status_in: status,
  });
  if (error) throw new Error(error.message);
};

export const deleteDevice = async (id: string) => {
  const { error } = await supabase.from('network_devices').delete().eq('id', id);
  if (error) throw new Error(error.message);
};

export const getEdges = async () => {
  const { data, error } = await supabase.from('network_edges').select('id, source:source_id, target:target_id, connection_type');
  if (error) throw new Error(error.message);
  return data;
};

export const addEdgeToDB = async (edge: { source: string; target: string }) => {
  const { data: { user } } = await supabase.auth.getUser();
  if (!user) throw new Error('User not authenticated');

  const { data, error } = await supabase.from('network_edges').insert({ source_id: edge.source, target_id: edge.target, user_id: user.id, connection_type: 'cat5' }).select().single();
  if (error) throw new Error(error.message);
  return data;
};

export const updateEdgeInDB = async (id: string, updates: { connection_type: string }) => {
  const { data, error } = await supabase.from('network_edges').update(updates).eq('id', id).select().single();
  if (error) throw new Error(error.message);
  return data;
};

export const deleteEdgeFromDB = async (edgeId: string) => {
  const { error } = await supabase.from('network_edges').delete().eq('id', edgeId);
  if (error) throw new Error(error.message);
};

export const importMap = async (mapData: MapData) => {
  const { error } = await supabase.rpc('import_network_map', {
    devices_data: mapData.devices,
    edges_data: mapData.edges,
  });
  if (error) throw new Error(`Import failed: ${error.message}`);
};