import DockerUpdate from "@/components/DockerUpdate";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Settings } from "lucide-react";

const Maintenance = () => {
  return (
    <div className="space-y-6">
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <Settings className="h-5 w-5" />
            System Maintenance
          </CardTitle>
        </CardHeader>
        <CardContent>
          <DockerUpdate />
        </CardContent>
      </Card>
    </div>
  );
};

export default Maintenance;