import { useState, useEffect, useCallback, useMemo, useRef } from 'react';
import ReactFlow, {
  MiniMap,
  Controls,
  Background,
  Node,
} from 'reactflow';
import 'reactflow/dist/style.css';
import { Button } from '@/components/ui/button';
import { PlusCircle, Upload, Download, Network } from 'lucide-react';
import {
  NetworkDevice,
  MapData,
} from '@/services/networkDeviceService';
import { DeviceEditorDialog } from './DeviceEditorDialog';
import { EdgeEditorDialog } from './EdgeEditorDialog';
import DeviceNode from './DeviceNode';
import { showError } from '@/utils/toast';
import { useNetworkMapLogic } from '@/hooks/useNetworkMapLogic'; // Import the new hook

const NetworkMap = ({ devices, onMapUpdate, mapId, canAddDevice, licenseMessage }: { devices: NetworkDevice[]; onMapUpdate: () => void; mapId: string | null; canAddDevice: boolean; licenseMessage: string }) => {
  const importInputRef = useRef<HTMLInputElement>(null);

  const {
    nodes,
    edges,
    onNodesChange,
    onEdgesChange,
    onConnect,
    onNodeDragStop,
    onEdgeClick,
    isDeviceEditorOpen,
    setIsDeviceEditorOpen,
    editingDevice,
    setEditingDevice,
    isEdgeEditorOpen,
    setIsEdgeEditorOpen,
    editingEdge,
    setEditingEdge,
    handleAddDevice,
    handleSaveDevice,
    handleSaveEdge,
    handleImportMap,
    handleExportMap,
  } = useNetworkMapLogic({
    initialDevices: devices,
    mapId,
    canAddDevice,
    licenseMessage,
    onMapUpdate,
  });

  const nodeTypes = useMemo(() => ({ device: DeviceNode }), []);

  const handleFileChange = async (event: React.ChangeEvent<HTMLInputElement>) => {
    const file = event.target.files?.[0];
    if (!file) return;

    const reader = new FileReader();
    reader.onload = async (e) => {
      try {
        const mapData = JSON.parse(e.target?.result as string) as MapData;
        await handleImportMap(mapData);
      } catch (error: any) {
        console.error('Failed to read map file:', error);
        showError(error.message || 'Failed to read map file.');
      } finally {
        if (importInputRef.current) importInputRef.current.value = '';
      }
    };
    reader.readAsText(file);
  };

  return (
    <div style={{ height: '70vh', width: '100%' }} className="relative border rounded-lg bg-gray-900">
      <ReactFlow
        nodes={nodes}
        edges={edges}
        onNodesChange={onNodesChange}
        onEdgesChange={onEdgesChange}
        onConnect={onConnect}
        nodeTypes={nodeTypes}
        onNodeDragStop={onNodeDragStop}
        onEdgeClick={onEdgeClick}
        fitView
        fitViewOptions={{ padding: 0.1 }}
      >
        <Controls />
        <MiniMap
          nodeColor={(n) => {
            switch (n.data.status) {
              case 'online': return '#22c55e';
              case 'offline': return '#ef4444';
              default: return '#94a3b8';
            }
          }}
          nodeStrokeWidth={3}
          maskColor="rgba(15, 23, 42, 0.8)"
        />
        <Background gap={16} size={1} color="#444" />
      </ReactFlow>
      <div className="absolute top-4 left-4 flex flex-wrap gap-2">
        <Button onClick={handleAddDevice} size="sm" disabled={!mapId || !canAddDevice} title={!canAddDevice ? licenseMessage : ''}>
          <PlusCircle className="h-4 w-4 mr-2" />Add Device
        </Button>
        <Button onClick={handleExportMap} variant="outline" size="sm" disabled={!mapId}>
          <Download className="h-4 w-4 mr-2" />Export
        </Button>
        <Button onClick={() => importInputRef.current?.click()} variant="outline" size="sm" disabled={!mapId}>
          <Upload className="h-4 w-4 mr-2" />Import
        </Button>
        <input
          type="file"
          ref={importInputRef}
          onChange={handleFileChange}
          accept="application/json"
          className="hidden"
        />
      </div>
      {isDeviceEditorOpen && (
        <DeviceEditorDialog
          isOpen={isDeviceEditorOpen}
          onClose={() => setIsDeviceEditorOpen(false)}
          onSave={handleSaveDevice}
          device={editingDevice}
        />
      )}
      {isEdgeEditorOpen && (
        <EdgeEditorDialog
          isOpen={isEdgeEditorOpen}
          onClose={() => setIsEdgeEditorOpen(false)}
          onSave={handleSaveEdge}
          edge={editingEdge}
        />
      )}
    </div>
  );
};

export default NetworkMap;