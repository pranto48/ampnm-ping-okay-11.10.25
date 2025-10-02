import { supabase } from '@/integrations/supabase/client';

export interface NetworkDevice {
  id?: string;
  name: string;
  ip_address: string;
  position_x: number;
  position_y: number;
  icon: string;
  status?: 'online' | 'offline' | 'unknown';
  ping_interval?: number;
  user_id?: string;
}

export const getDevices = async () => {
  const { data, error } = await supabase.from('network_devices').select('*');
  if (error) throw new Error(error.message);
  return data;
};

export const addDevice = async (device: Omit<NetworkDevice, 'id' | 'user_id'>) => {
  const { data: { user } } = await supabase.auth.getUser();
  if (!user) throw new Error("User not authenticated");

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

export const deleteDevice = async (id: string) => {
  const { error } = await supabase.from('network_devices').delete().eq('id', id);
  if (error) throw new Error(error.message);
};

export const getEdges = async () => {
  const { data, error } = await supabase.from('network_edges').select('id, source:source_id, target:target_id');
  if (error) throw new Error(error.message);
  return data;
};

export const addEdgeToDB = async (edge: { source: string; target: string }) => {
  const { data: { user } } = await supabase.auth.getUser();
  if (!user) throw new Error("User not authenticated");

  const edgeWithUser = { source_id: edge.source, target_id: edge.target, user_id: user.id };
  const { data, error } = await supabase.from('network_edges').insert(edgeWithUser).select().single();
  if (error) throw new Error(error.message);
  return data;
};

export const deleteEdgeFromDB = async (edgeId: string) => {
  const { error } = await supabase.from('network_edges').delete().eq('id', edgeId);
  if (error) throw new Error(error.message);
};