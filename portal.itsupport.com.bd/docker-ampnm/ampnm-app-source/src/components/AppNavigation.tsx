"use client";

import React from 'react';
import { Link, useLocation, useNavigate } from "react-router-dom";
import { Tabs, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Button } from "@/components/ui/button";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import {
  Sheet,
  SheetContent,
  SheetHeader,
  SheetTitle,
  SheetTrigger,
} from "@/components/ui/sheet";
import {
  Activity,
  Server,
  Wifi,
  Desktop,
  Network,
  Search,
  History,
  Map,
  ShieldHalf,
  BoxOpen,
  UserCog,
  Tools,
  Menu,
  ChevronDown,
  LogOut,
} from "lucide-react";
import { useIsMobile } from "@/hooks/use-mobile"; // Assuming this hook exists

interface AppNavigationProps {
  activeTab: string;
  handleTabChange: (value: string) => void;
  isAdmin: boolean;
}

const AppNavigation = ({ activeTab, handleTabChange, isAdmin }: AppNavigationProps) => {
  const isMobile = useIsMobile();
  const navigate = useNavigate();

  const handleLogout = () => {
    // Redirect to the PHP logout script
    window.location.href = "/logout.php";
  };

  const mainNavItems = [
    { value: "dashboard", icon: Activity, label: "Dashboard" },
    { value: "devices", icon: Server, label: "Devices" },
    { value: "ping", icon: Wifi, label: "Browser Ping" },
    { value: "server-ping", icon: Desktop, label: "Server Ping" },
    { value: "status", icon: Network, label: "Network Status" },
    { value: "scanner", icon: Search, label: "Network Scanner" },
    { value: "history", icon: History, label: "Ping History" },
    { value: "map", icon: Map, label: "Network Map" },
  ];

  const licensingSubmenuItems = [
    { value: "license", icon: ShieldHalf, label: "License" },
    { value: "products", icon: BoxOpen, label: "Products" },
  ];

  const adminSubmenuItems = [
    { value: "users", icon: UserCog, label: "Users" },
    { value: "maintenance", icon: Tools, label: "Maintenance" },
  ];

  const renderNavLinks = (isMobileMenu: boolean = false) => (
    <>
      {mainNavItems.map((item) => (
        <TabsTrigger
          key={item.value}
          value={item.value}
          asChild
          className={isMobileMenu ? "w-full justify-start" : ""}
        >
          <Link to={item.value === "dashboard" ? "/" : `/${item.value}`}>
            <item.icon className="mr-2 h-4 w-4" />
            {item.label}
          </Link>
        </TabsTrigger>
      ))}

      {/* Licensing Dropdown/Submenu */}
      {isMobileMenu ? (
        <>
          <DropdownMenuSeparator className="my-2" />
          <DropdownMenuLabel className="px-4 py-2 text-sm font-semibold text-muted-foreground">Licensing</DropdownMenuLabel>
          {licensingSubmenuItems.map((item) => (
            <TabsTrigger
              key={item.value}
              value={item.value}
              asChild
              className="w-full justify-start pl-6"
            >
              <Link to={`/${item.value}`}>
                <item.icon className="mr-2 h-4 w-4" />
                {item.label}
              </Link>
            </TabsTrigger>
          ))}
        </>
      ) : (
        <DropdownMenu>
          <DropdownMenuTrigger asChild>
            <Button variant="ghost" className="nav-link">
              <ShieldHalf className="mr-2 h-4 w-4" />
              Licensing <ChevronDown className="ml-2 h-4 w-4" />
            </Button>
          </DropdownMenuTrigger>
          <DropdownMenuContent className="bg-card text-foreground border-border">
            <DropdownMenuLabel>Licensing</DropdownMenuLabel>
            <DropdownMenuSeparator />
            {licensingSubmenuItems.map((item) => (
              <DropdownMenuItem key={item.value} asChild>
                <Link to={`/${item.value}`} onClick={() => handleTabChange(item.value)}>
                  <item.icon className="mr-2 h-4 w-4" />
                  {item.label}
                </Link>
              </DropdownMenuItem>
            ))}
          </DropdownMenuContent>
        </DropdownMenu>
      )}

      {/* Admin Dropdown/Submenu (only for admin) */}
      {isAdmin && (
        isMobileMenu ? (
          <>
            <DropdownMenuSeparator className="my-2" />
            <DropdownMenuLabel className="px-4 py-2 text-sm font-semibold text-muted-foreground">Admin Tools</DropdownMenuLabel>
            {adminSubmenuItems.map((item) => (
              <TabsTrigger
                key={item.value}
                value={item.value}
                asChild
                className="w-full justify-start pl-6"
              >
                <Link to={`/${item.value}`}>
                  <item.icon className="mr-2 h-4 w-4" />
                  {item.label}
                </Link>
              </TabsTrigger>
            ))}
          </>
        ) : (
          <DropdownMenu>
            <DropdownMenuTrigger asChild>
              <Button variant="ghost" className="nav-link">
                <UserCog className="mr-2 h-4 w-4" />
                Admin <ChevronDown className="ml-2 h-4 w-4" />
              </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent className="bg-card text-foreground border-border">
              <DropdownMenuLabel>Admin Tools</DropdownMenuLabel>
              <DropdownMenuSeparator />
              {adminSubmenuItems.map((item) => (
                <DropdownMenuItem key={item.value} asChild>
                  <Link to={`/${item.value}`} onClick={() => handleTabChange(item.value)}>
                    <item.icon className="mr-2 h-4 w-4" />
                    {item.label}
                  </Link>
                </DropdownMenuItem>
              ))}
            </DropdownMenuContent>
          </DropdownMenu>
        )
      )}

      {/* Logout Button */}
      <Button
        variant="ghost"
        onClick={handleLogout}
        className={isMobileMenu ? "w-full justify-start text-destructive hover:text-destructive/90" : "nav-link text-destructive hover:text-destructive/90"}
      >
        <LogOut className="mr-2 h-4 w-4" />
        Logout
      </Button>
    </>
  );

  return (
    <nav className="bg-slate-800/50 backdrop-blur-lg shadow-lg sticky top-0 z-50">
      <div className="container mx-auto px-4">
        <div className="flex items-center justify-between h-16">
          <div className="flex items-center">
            <Link to="/" className="flex items-center gap-2 text-white font-bold">
              <ShieldHalf className="text-cyan-400 text-2xl" />
              <span>AMPNM</span>
            </Link>
          </div>

          {isMobile ? (
            <Sheet>
              <SheetTrigger asChild>
                <Button variant="ghost" size="icon" className="text-white">
                  <Menu className="h-6 w-6" />
                </Button>
              </SheetTrigger>
              <SheetContent side="right" className="w-[250px] sm:w-[300px] bg-card text-foreground border-border">
                <SheetHeader>
                  <SheetTitle className="text-primary">Navigation</SheetTitle>
                </SheetHeader>
                <Tabs
                  value={activeTab}
                  onValueChange={handleTabChange}
                  orientation="vertical"
                  className="mt-4 flex flex-col items-start space-y-2"
                >
                  <TabsList className="flex flex-col h-auto p-0 bg-transparent border-none space-y-1">
                    {renderNavLinks(true)}
                  </TabsList>
                </Tabs>
              </SheetContent>
            </Sheet>
          ) : (
            <div id="main-nav" className="ml-10 flex items-baseline space-x-1">
              <Tabs value={activeTab} onValueChange={handleTabChange} className="flex items-center">
                <TabsList className="flex flex-wrap h-auto p-1 bg-transparent border-none">
                  {renderNavLinks()}
                </TabsList>
              </Tabs>
            </div>
          )}
        </div>
      </div>
    </nav>
  );
};

export default AppNavigation;