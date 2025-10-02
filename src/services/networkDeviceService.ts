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

export const getEdges = async () => {
  const { data, error } = await supabase.from('network_edges').select('id, source:source_id, target:target_id');
  if (error) throw new Error(error.message);
  return data;
};

export const addEdgeToDB = async (edge: { source: string; target: string }) => {
  const { data, error } = await supabase.from('network_edges').insert({ source_id: edge.source, target_id: edge.target }).select().single();
  if (error) throw new Error(error.message);
  return data;
};

export const deleteEdgeFromDB = async (edgeId: string) => {
  const { error } = await supabase.from('network_edges').delete().eq('id', edgeId);
  if (error) throw new Error(error.message);
};