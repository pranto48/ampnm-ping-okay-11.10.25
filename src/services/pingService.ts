import { supabase } from '@/integrations/supabase/client'

export interface PingResult {
  host: string;
  timestamp: string;
  success: boolean;
  output: string;
  error: string;
  statusCode: number;
}

export const performServerPing = async (host: string, count: number = 1, timeout: number = 5000): Promise<PingResult> => {
  try {
    const { data, error } = await supabase.functions.invoke('ping-service', {
      body: { host, count, timeout }
    })

    if (error) {
      throw new Error(error.message)
    }

    return data
  } catch (error) {
    console.error('Ping service error:', error)
    throw new Error(`Failed to ping ${host}: ${error.message}`)
  }
}

export const parsePingOutput = (output: string): { packetLoss: number; avgTime: number; minTime: number; maxTime: number } => {
  // Parse standard ping output
  const packetLossMatch = output.match(/(\d+)% packet loss/)
  const timeMatch = output.match(/= ([\d.]+)\/([\d.]+)\/([\d.]+)\/([\d.]+) ms/)
  
  const packetLoss = packetLossMatch ? parseInt(packetLossMatch[1]) : 100
  const times = timeMatch ? timeMatch.slice(1).map(Number) : [0, 0, 0, 0]

  return {
    packetLoss,
    minTime: times[0] || 0,
    avgTime: times[1] || 0,
    maxTime: times[2] || 0
  }
}