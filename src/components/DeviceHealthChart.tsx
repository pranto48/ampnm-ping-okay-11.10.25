import { useState, useEffect } from 'react';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { ChartContainer, ChartTooltip, ChartTooltipContent } from '@/components/ui/chart';
import { Bar, BarChart, CartesianGrid, XAxis, YAxis, Line, LineChart, ResponsiveContainer } from 'recharts';
import { getPingStats, type PingStorageResult } from '@/services/pingStorage';
import { Skeleton } from '@/components/ui/skeleton';

interface DeviceHealthChartProps {
  deviceIp: string;
}

const DeviceHealthChart = ({ deviceIp }: DeviceHealthChartProps) => {
  const [chartData, setChartData] = useState<PingStorageResult[]>([]);
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {
    const fetchData = async () => {
      if (!deviceIp) return;
      setIsLoading(true);
      try {
        const data = await getPingStats(deviceIp, 24); // Get stats for the last 24 hours
        setChartData(data as PingStorageResult[]);
      } catch (error) {
        console.error("Failed to fetch ping stats:", error);
      } finally {
        setIsLoading(false);
      }
    };
    fetchData();
  }, [deviceIp]);

  const formattedData = chartData.map(item => ({
    time: new Date(item.created_at!).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
    latency: item.avg_time,
    packetLoss: item.packet_loss,
  }));

  if (isLoading) {
    return <Skeleton className="h-[300px] w-full" />;
  }

  if (formattedData.length === 0) {
    return (
      <div className="flex items-center justify-center h-[300px] text-muted-foreground">
        No ping data available for the last 24 hours.
      </div>
    );
  }

  return (
    <div className="space-y-4">
      <Card>
        <CardHeader>
          <CardTitle>Latency (ms)</CardTitle>
          <CardDescription>Average ping response time over the last 24 hours.</CardDescription>
        </CardHeader>
        <CardContent>
          <ChartContainer config={{}} className="h-[250px] w-full">
            <ResponsiveContainer>
              <LineChart data={formattedData}>
                <CartesianGrid vertical={false} />
                <XAxis dataKey="time" tickLine={false} axisLine={false} tickMargin={8} />
                <YAxis />
                <ChartTooltip content={<ChartTooltipContent />} />
                <Line type="monotone" dataKey="latency" stroke="var(--color-primary)" strokeWidth={2} dot={false} />
              </LineChart>
            </ResponsiveContainer>
          </ChartContainer>
        </CardContent>
      </Card>
      <Card>
        <CardHeader>
          <CardTitle>Packet Loss (%)</CardTitle>
          <CardDescription>Percentage of packets lost during pings.</CardDescription>
        </CardHeader>
        <CardContent>
          <ChartContainer config={{}} className="h-[250px] w-full">
            <ResponsiveContainer>
              <BarChart data={formattedData}>
                <CartesianGrid vertical={false} />
                <XAxis dataKey="time" tickLine={false} axisLine={false} tickMargin={8} />
                <YAxis />
                <ChartTooltip content={<ChartTooltipContent />} />
                <Bar dataKey="packetLoss" fill="var(--color-destructive)" radius={4} />
              </BarChart>
            </ResponsiveContainer>
          </ChartContainer>
        </CardContent>
      </Card>
    </div>
  );
};

export default DeviceHealthChart;