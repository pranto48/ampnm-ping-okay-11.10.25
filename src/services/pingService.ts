export interface PingResult {
  host: string;
  timestamp: string;
  success: boolean;
  output: string;
  error: string;
  statusCode: number;
}

const LOCAL_API_URL = 'http://localhost/network-monitor/api.php';

export const performServerPing = async (host: string, count: number = 4): Promise<PingResult> => {
  try {
    // This now calls your local PHP script instead of a Supabase function.
    const response = await fetch(`${LOCAL_API_URL}?action=manual_ping`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      // The body sends the host to the PHP script.
      body: JSON.stringify({ host, count }),
    });

    if (!response.ok) {
      throw new Error(`Network response was not ok: ${response.statusText}`);
    }

    const phpResult = await response.json();

    if (phpResult.return_code === -1) {
      throw new Error(phpResult.output || 'Ping failed in PHP script.');
    }

    const success = phpResult.return_code === 0;

    return {
      host,
      timestamp: new Date().toISOString(),
      success: success,
      output: phpResult.output,
      error: success ? '' : phpResult.output,
      statusCode: phpResult.return_code,
    };
  } catch (error: any) {
    console.error('Local ping service error:', error);
    const errorMessage = `Failed to connect to local ping service. Please ensure your XAMPP server is running and the PHP files are in the 'htdocs/network-monitor' directory. Error: ${error.message}`;
    
    // Return a structured error that the UI can display.
    return {
      host,
      timestamp: new Date().toISOString(),
      success: false,
      output: errorMessage,
      error: errorMessage,
      statusCode: -1,
    };
  }
};

export const parsePingOutput = (output: string): { packetLoss: number; avgTime: number; minTime: number; maxTime: number } => {
  // This function works for both Windows and Linux/macOS ping output.
  let packetLoss = 100;
  let avgTime = 0;
  let minTime = 0;
  let maxTime = 0;

  // Regex for Windows
  const windowsLossMatch = output.match(/Lost = \d+ \((\d+)% loss\)/);
  const windowsTimeMatch = output.match(/Minimum = (\d+)ms, Maximum = (\d+)ms, Average = (\d+)ms/);

  if (windowsLossMatch) {
    packetLoss = parseInt(windowsLossMatch[1]);
  }
  if (windowsTimeMatch) {
    minTime = parseFloat(windowsTimeMatch[1]);
    maxTime = parseFloat(windowsTimeMatch[2]);
    avgTime = parseFloat(windowsTimeMatch[3]);
  }

  // Regex for Linux/macOS
  const unixLossMatch = output.match(/(\d+)% packet loss/);
  const unixTimeMatch = output.match(/rtt min\/avg\/max\/mdev = ([\d.]+)\/([\d.]+)\/([\d.]+)\/([\d.]+) ms/);

  if (unixLossMatch) {
    packetLoss = parseInt(unixLossMatch[1]);
  }
  if (unixTimeMatch) {
    minTime = parseFloat(unixTimeMatch[1]);
    avgTime = parseFloat(unixTimeMatch[2]);
    maxTime = parseFloat(unixTimeMatch[3]);
  }

  return {
    packetLoss,
    minTime,
    avgTime,
    maxTime,
  };
};