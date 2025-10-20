import { Card, CardContent, CardHeader, CardTitle, CardDescription } from "@/components/ui/card";
import { Mail } from "lucide-react";

const EmailNotifications = () => {
  return (
    <div className="space-y-6">
      <Card className="bg-card text-foreground border-border">
        <CardHeader>
          <CardTitle className="flex items-center gap-2 text-primary">
            <Mail className="h-5 w-5" />
            Email Notifications
          </CardTitle>
          <CardDescription>
            Configure email alerts for device status changes. (This page is under construction)
          </CardDescription>
        </CardHeader>
        <CardContent>
          <p className="text-muted-foreground">
            This feature will allow you to set up SMTP settings and subscribe to notifications for specific devices.
          </p>
          <p className="text-muted-foreground mt-2">
            Please check back later for updates!
          </p>
        </CardContent>
      </Card>
    </div>
  );
};

export default EmailNotifications;