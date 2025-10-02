import { AreaChart, Area, XAxis, YAxis, CartesianGrid, Tooltip, Legend, ResponsiveContainer } from 'recharts';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';

interface ChartData {
  created_at: string;
  avg_time: number;
  packet_loss: number;
}

interface PingHistoryChartProps {
  data: ChartData[];
  host: string;
}

const PingHistoryChart = ({ data, host }: PingHistoryChartProps) => {
  if (!data || data.length === 0) {
    return (
      <div className="text-center p-8 border rounded-lg bg-muted">
        <p className="text-sm text-muted-foreground">
          No data available for {host} in the last 24 hours to generate a chart.
        </p>
      </div>
    );
  }

  const formattedData = data.map(item => ({
    ...item,
    time: new Date(item.created_at).toLocaleTimeString(),
  }));

  return (
    <Card>
      <CardHeader>
        <CardTitle>Latency for {host}</CardTitle>
        <CardDescription>Average ping time (ms) and packet loss (%) over the last 24 hours.</CardDescription>
      </CardHeader>
      <CardContent>
        <ResponsiveContainer width="100%" height={300}>
          <AreaChart data={formattedData} margin={{ top: 5, right: 30, left: 20, bottom: 5 }}>
            <CartesianGrid strokeDasharray="3 3" />
            <XAxis dataKey="time" />
            <YAxis yAxisId="left" label={{ value: 'ms', angle: -90, position: 'insideLeft' }} />
            <YAxis yAxisId="right" orientation="right" label={{ value: '%', angle: -90, position: 'insideRight' }} />
            <Tooltip
              contentStyle={{ backgroundColor: 'hsl(var(--background))', border: '1px solid hsl(var(--border))' }}
              labelStyle={{ color: 'hsl(var(--foreground))' }}
            />
            <Legend />
            <Area yAxisId="left" type="monotone" dataKey="avg_time" name="Avg Time (ms)" stroke="#8884d8" fill="#8884d8" fillOpacity={0.3} />
            <Area yAxisId="right" type="monotone" dataKey="packet_loss" name="Packet Loss (%)" stroke="#82ca9d" fill="#82ca9d" fillOpacity={0.3} />
          </AreaChart>
        </ResponsiveContainer>
      </CardContent>
    </Card>
  );
};

export default PingHistoryChart;