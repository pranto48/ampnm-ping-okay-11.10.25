import { useState, useEffect, useCallback, useMemo } from "react";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import {
  Activity,
  Wifi,
  Server,
  Network,
  Key,
  Users,
  Package,
  Settings,
  Map,
  Desktop,
  Search,
  History,
  ShieldHalf,
  BoxOpen,
  UserCog,
  Tools,
  Menu, // For mobile menu icon
  ChevronDown, // For dropdown indicator
  LogOut, // Import LogOut icon
} from "lucide-react";
import PingTest from "@/components/PingTest";
import NetworkStatus from "@/components/NetworkStatus";
import NetworkScanner from "@/components/NetworkScanner";
import ServerPingTest from "@/components/ServerPingTest";
import PingHistory from "@/components/PingHistory";
import { MadeWithDyad } from "@/components/made-with-dyad";
import NetworkMap from "@/components/NetworkMap";
import { getLicenseStatus, LicenseStatus, User } from "@/services/networkDeviceService";
import { Skeleton } from "@/components/ui/skeleton";
import DashboardContent from "@/components/DashboardContent";
import { useDashboardData } from "@/hooks/useDashboardData";
import LicenseManager from "@/components/LicenseManager";
import UserManagement from "@/components/UserManagement";
import Maintenance from "./Maintenance";
import Products from "./Products";
import { Card, CardContent } from "@/components/ui/card";
import { useIsMobile } from "@/hooks/use-mobile"; // Import the mobile hook
import { Sheet, SheetContent, SheetTrigger } from "@/components/ui/sheet"; // Import Sheet components
import { Button } from "@/components/ui/button";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
  DropdownMenuSeparator,
} from "@/components/ui/dropdown-menu";

// Helper to get initial tab from URL hash
const getInitialTab = () => {
  const hash = window.location.hash.substring(1);
  const validTabs = [
    "dashboard", "devices", "ping", "server-ping", "status", "scanner", 
    "history", "map", "products", "users", "license", "maintenance", // Include all possible tabs for initial check
  ];
  if (validTabs.includes(hash)) {
    return hash;
  }
  return "dashboard";
};

const MainApp = () => {
  const {
    maps,
    currentMapId,
    setCurrentMapId,
    devices,
    dashboardStats,
    recentActivity,
    isLoading: isDashboardLoading,
    fetchMaps,
    fetchDashboardData,
  } = useDashboardData();

  const [licenseStatus, setLicenseStatus] = useState<LicenseStatus>({
    app_license_key: "",
    can_add_device: false,
    max_devices: 0,
    license_message: "Loading license status...",
    license_status_code: "unknown",
    license_grace_period_end: null,
    installation_id: "",
  });
  const [userRole, setUserRole] = useState<User["role"]>("user");
  const [isUserRoleLoading, setIsUserRoleLoading] = useState(true);
  const [isLicenseStatusLoading, setIsLicenseStatusLoading] = useState(true);
  const [activeTab, setActiveTab] = useState(getInitialTab());
  const isMobile = useIsMobile(); // Use the mobile hook

  const fetchLicenseStatus = useCallback(async () => {
    setIsLicenseStatusLoading(true);
    try {
      const status = await getLicenseStatus();
      setLicenseStatus(status);
    } catch (error) {
      console.error("Failed to load license status:", error);
      setLicenseStatus(prev => ({
        ...prev,
        license_message: "Error loading license status.",
        license_status_code: "error",
      }));
    } finally {
      setIsLicenseStatusLoading(false);
    }
  }, []);

  const fetchUserRole = useCallback(async () => {
    setIsUserRoleLoading(true);
    try {
      const response = await fetch('/api.php?action=get_user_info'); 
      if (response.ok) {
        const data = await response.json();
        setUserRole(data.role);
      } else {
        setUserRole('user');
      }
    } catch (error) {
      console.error("Failed to fetch user role:", error);
      setUserRole('user'); 
    } finally {
      setIsUserRoleLoading(false);
    }
  }, []);

  const isAdmin = useMemo(() => userRole === "admin", [userRole]);
  const isAppLoading = isUserRoleLoading || isLicenseStatusLoading;

  useEffect(() => {
    fetchUserRole();
    fetchLicenseStatus();
  }, [fetchUserRole, fetchLicenseStatus]);

  useEffect(() => {
    // Fetch dashboard data only after we know the license status and user role
    if (!isAppLoading) {
      fetchDashboardData();
      fetchMaps();
    }
  }, [isAppLoading, fetchDashboardData, fetchMaps]);

  // Update URL hash when tab changes
  const handleTabChange = (value: string) => {
    setActiveTab(value);
    window.location.hash = value;
  };

  // Listen for hash changes (e.g., back button)
  useEffect(() => {
    const handleHashChange = () => {
      setActiveTab(getInitialTab());
    };
    window.addEventListener('hashchange', handleHashChange);
    return () => window.removeEventListener('hashchange', handleHashChange);
  }, []);

  const navItems = useMemo(() => [
    { value: "dashboard", label: "Dashboard", icon: Activity },
    { value: "devices", label: "Devices", icon: Server },
    { value: "ping", label: "Browser Ping", icon: Wifi },
    { value: "server-ping", label: "Server Ping", icon: Desktop },
    { value: "status", label: "Network Status", icon: Network },
    { value: "scanner", label: "Network Scanner", icon: Search },
    { value: "history", label: "Ping History", icon: History },
    { value: "map", label: "Network Map", icon: Map },
    { value: "products", label: "Products", icon: BoxOpen },
  ], []);

  const adminMaintenanceSubItems = useMemo(() => [
    { value: "users", label: "Users", icon: UserCog },
    { value: "license", label: "Licensing", icon: ShieldHalf },
    { value: "maintenance", label: "System Maintenance", icon: Tools },
  ], []);

  const renderNavigation = (isMobileMenu: boolean = false) => (
    <>
      {navItems.map((item) => {
        const Icon = item.icon;
        return isMobileMenu ? (
          <a
            key={item.value}
            href={`#/${item.value}`}
            className="flex items-center gap-2 p-2 text-foreground hover:bg-muted rounded-md"
            onClick={() => handleTabChange(item.value)}
          >
            <Icon className="h-4 w-4" />
            {item.label}
          </a>
        ) : (
          <TabsTrigger key={item.value} value={item.value} onClick={() => handleTabChange(item.value)}>
            <Icon className="mr-2 h-4 w-4" />
            {item.label}
          </TabsTrigger>
        );
      })}
      {isAdmin && (
        isMobileMenu ? (
          <>
            <DropdownMenuSeparator className="my-2" />
            <span className="text-sm font-semibold text-muted-foreground px-2">Maintenance</span>
            {adminMaintenanceSubItems.map((item) => {
              const Icon = item.icon;
              return (
                <a
                  key={item.value}
                  href={`#/${item.value}`}
                  className="flex items-center gap-2 p-2 text-foreground hover:bg-muted rounded-md"
                  onClick={() => handleTabChange(item.value)}
                >
                  <Icon className="h-4 w-4" />
                  {item.label}
                </a>
              );
            })}
          </>
        ) : (
          <DropdownMenu>
            <DropdownMenuTrigger asChild>
              <Button variant="ghost" className="flex items-center gap-1 text-foreground hover:bg-muted">
                <Settings className="mr-2 h-4 w-4" />
                Maintenance <ChevronDown className="h-4 w-4" />
              </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent className="bg-card text-foreground border-border">
              {adminMaintenanceSubItems.map((item) => {
                const Icon = item.icon;
                return (
                  <DropdownMenuItem key={item.value} onClick={() => handleTabChange(item.value)} className="hover:bg-secondary">
                    <Icon className="mr-2 h-4 w-4" />
                    {item.label}
                  </DropdownMenuItem>
                );
              })}
              <DropdownMenuSeparator />
              <DropdownMenuItem onClick={() => window.location.href = 'logout.php'} className="text-destructive hover:bg-destructive/10">
                <LogOut className="mr-2 h-4 w-4" />
                Logout
              </DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>
        )
      )}
      {!isAdmin && !isMobileMenu && (
        <Button variant="ghost" className="flex items-center gap-1 text-foreground hover:bg-muted" onClick={() => window.location.href = 'logout.php'}>
          <LogOut className="mr-2 h-4 w-4" />
          Logout
        </Button>
      )}
    </>
  );

  if (isAppLoading) {
    return (
      <div className="flex w-full flex-col items-center justify-center min-h-[80vh]">
        <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-primary mx-auto mb-4"></div>
        <p className="text-lg text-muted-foreground">Loading application data...</p>
        <p className="text-sm text-muted-foreground mt-2">Fetching user permissions and license status.</p>
      </div>
    );
  }

  return (
    <div className="flex w-full flex-col">
      <div className="flex-1 space-y-4 p-4 pt-6 sm:p-8">
        {isMobile ? (
          <Sheet>
            <SheetTrigger asChild>
              <Button variant="outline" size="icon" className="mb-4 bg-card text-foreground border-border">
                <Menu className="h-6 w-6" />
              </Button>
            </SheetTrigger>
            <SheetContent side="left" className="w-[240px] bg-card text-foreground border-border p-4">
              <h2 className="text-xl font-bold mb-4 text-primary">AMPNM Menu</h2>
              <nav className="flex flex-col gap-2">
                {renderNavigation(true)}
                <a href="logout.php" className="flex items-center gap-2 p-2 text-destructive hover:bg-destructive/10 rounded-md mt-4">
                  <LogOut className="h-4 w-4" />
                  Logout
                </a>
              </nav>
            </SheetContent>
          </Sheet>
        ) : (
          <Tabs value={activeTab} onValueChange={handleTabChange}>
            <TabsList className="flex flex-wrap h-auto p-1 justify-start">
              {renderNavigation()}
            </TabsList>

            <TabsContent value="dashboard">
              <DashboardContent
                maps={maps}
                currentMapId={currentMapId}
                setCurrentMapId={setCurrentMapId}
                devices={devices}
                dashboardStats={dashboardStats}
                recentActivity={recentActivity}
                isLoading={isDashboardLoading}
                fetchMaps={fetchMaps}
                fetchDashboardData={fetchDashboardData}
                licenseStatus={licenseStatus}
                fetchLicenseStatus={fetchLicenseStatus}
              />
            </TabsContent>

            <TabsContent value="devices">
              <Card className="bg-card text-foreground border-border">
                <CardHeader>
                  <CardTitle>Local Network Devices</CardTitle>
                  <CardDescription>Monitor the status of devices on your local network</CardDescription>
                </CardHeader>
                <CardContent>
                  {isDashboardLoading ? (
                    <div className="space-y-4">
                      {[...Array(5)].map((_, i) => (
                        <Skeleton key={i} className="h-16 w-full rounded-lg" />
                      ))}
                    </div>
                  ) : devices.length === 0 ? (
                    <div className="text-center py-8 text-muted-foreground">
                      <Server className="h-12 w-12 mx-auto mb-4" />
                      <p>No devices found. Add devices to start monitoring.</p>
                    </div>
                  ) : (
                    <div className="space-y-4">
                      {devices.map((device) => (
                        <div
                          key={device.id}
                          className="flex items-center justify-between p-4 border rounded-lg transition-colors hover:bg-muted bg-background border-border"
                        >
                          <div className="flex items-center gap-3">
                            {device.status === "online" ? (
                              <Wifi className="h-5 w-5 text-green-500" />
                            ) : device.status === "offline" ? (
                              <WifiOff className="h-5 w-5 text-red-500" />
                            ) : (
                              <Wifi className="h-5 w-5 text-gray-500" />
                            )}
                            <div>
                              <span className="font-medium">{device.name}</span>
                              <p className="text-sm text-muted-foreground">{device.ip_address}</p>
                            </div>
                          </div>
                          <div className="flex items-center gap-4">
                            {device.last_ping && (
                              <div className="text-xs text-muted-foreground">
                                Last ping: {new Date(device.last_ping).toLocaleTimeString()}
                              </div>
                            )}
                            <Badge
                              variant={
                                device.status === "online" ? "default" :
                                device.status === "offline" ? "destructive" : "secondary"
                              }
                            >
                              {device.status || 'unknown'}
                            </Badge>
                          </div>
                        </div>
                      ))}
                    </div>
                  )}
                </CardContent>
              </Card>
            </TabsContent>

            <TabsContent value="ping">
              <PingTest />
            </TabsContent>

            <TabsContent value="server-ping">
              <ServerPingTest />
            </TabsContent>

            <TabsContent value="status">
              <NetworkStatus />
            </TabsContent>

            <TabsContent value="scanner">
              <NetworkScanner />
            </TabsContent>

            <TabsContent value="history">
              <PingHistory />
            </TabsContent>

            <TabsContent value="map">
              <div className="flex items-center gap-2 mb-4">
                <label htmlFor="map-select" className="text-sm font-medium text-muted-foreground">Select Map:</label>
                <select
                  id="map-select"
                  className="flex h-9 rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm transition-colors file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                  value={currentMapId || ''}
                  onChange={(e) => setCurrentMapId(e.target.value)}
                >
                  {maps.length === 0 ? (
                    <option value="">No maps available</option>
                  ) : (
                    maps.map((map) => (
                      <option key={map.id} value={map.id}>
                        {map.name}
                      </option>
                    ))
                  )}
                </select>
                <Button onClick={fetchMaps} variant="outline" size="sm" className="bg-secondary hover:bg-secondary/80 text-foreground border-border">
                  <RefreshCw className="h-4 w-4" />
                </Button>
              </div>
              {currentMapId ? (
                <NetworkMap 
                  devices={devices} 
                  onMapUpdate={fetchDashboardData}
                  mapId={currentMapId} 
                  canAddDevice={licenseStatus.can_add_device}
                  licenseMessage={licenseStatus.license_message}
                  userRole={userRole}
                />
              ) : (
                <Card className="h-[70vh] flex items-center justify-center bg-card text-foreground border-border">
                  <CardContent className="text-center">
                    <Network className="h-12 w-12 text-muted-foreground mx-auto mb-4" />
                    <p className="text-muted-foreground">No map selected or available. Please create one in the PHP frontend or select an existing one.</p>
                  </CardContent>
                </Card>
              )}
            </TabsContent>

            <TabsContent value="products">
              <Products />
            </TabsContent>

            {/* Admin-only tabs, now under Maintenance dropdown */}
            {isAdmin && (
              <>
                <TabsContent value="users">
                  <UserManagement />
                </TabsContent>
                <TabsContent value="license">
                  <LicenseManager licenseStatus={licenseStatus} fetchLicenseStatus={fetchLicenseStatus} />
                </TabsContent>
                <TabsContent value="maintenance">
                  <Maintenance />
                </TabsContent>
              </>
            )}
          </Tabs>
        )}

        <MadeWithDyad />
      </div>
    </div>
  );
};

export default MainApp;