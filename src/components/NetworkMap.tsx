import { useState, useEffect, useCallback, useMemo } from 'react';
import ReactFlow, {
  MiniMap,
  Controls,
  Background,
  useNodesState,
  useEdgesState,
  addEdge,
  Node,
  Edge,
  Connection,
  NodeDragHandler,
  OnEdgesChange,
} from 'reactflow';
import 'reactflow/dist/style.css';
import { Button } from '@/components/ui/button';
import { PlusCircle } from 'lucide-react';
import {
  getDevices,
  addDevice,
  updateDevice,
  deleteDevice,
  NetworkDevice,
  getEdges,
  addEdgeToDB,
  deleteEdgeFromDB,
} from '@/services/networkDeviceService';
import { DeviceEditorDialog } from './DeviceEditorDialog';
import DeviceNode from './DeviceNode';
import { showSuccess, showError } from '@/utils/toast';

const NetworkMap = () => {
  const [nodes, setNodes, onNodesChange] = useNodesState([]);
  const [edges, setEdges, onEdgesChange] = useEdgesState([]);
  const [isEditorOpen, setIsEditorOpen] = useState(false);
  const [editingDevice, setEditingDevice] = useState<Partial<NetworkDevice> | undefined>(undefined);

  const nodeTypes = useMemo(() => ({ device: DeviceNode }), []);

  const loadNetworkData = useCallback(async () => {
    try {
      const [devices, edgesData] = await Promise.all([getDevices(), getEdges()]);
      const mappedNodes = devices.map((device) => ({
        id: device.id,
        type: 'device',
        position: { x: device.position_x, y: device.position_y },
        data: {
          id: device.id,
          name: device.name,
          ip_address: device.ip_address,
          icon: device.icon,
          onEdit: handleEdit,
          onDelete: handleDelete,
        },
      }));
      setNodes(mappedNodes);

      const mappedEdges = edgesData.map((edge: any) => ({
        id: edge.id,
        source: edge.source,
        target: edge.target,
        animated: true,
        style: { stroke: '#fff', strokeWidth: 2 },
      }));
      setEdges(mappedEdges);
    } catch (error) {
      showError('Failed to load network data.');
    }
  }, [setNodes, setEdges]);

  useEffect(() => {
    loadNetworkData();
  }, [loadNetworkData]);

  const onConnect = useCallback(
    async (params: Connection) => {
      const newEdge = {
        ...params,
        animated: true,
        style: { stroke: '#fff', strokeWidth: 2 },
      };
      setEdges((eds) => addEdge(newEdge, eds));
      try {
        await addEdgeToDB({ source: params.source!, target: params.target! });
        showSuccess('Connection saved.');
        loadNetworkData(); // Reload to get DB-generated ID
      } catch (error) {
        showError('Failed to save connection.');
        loadNetworkData(); // Revert optimistic update
      }
    },
    [setEdges, loadNetworkData]
  );

  const handleAddDevice = () => {
    setEditingDevice(undefined);
    setIsEditorOpen(true);
  };

  const handleEdit = (deviceId: string) => {
    const nodeToEdit = nodes.find((n) => n.id === deviceId);
    if (nodeToEdit) {
      setEditingDevice({
        id: nodeToEdit.id,
        name: nodeToEdit.data.name,
        ip_address: nodeToEdit.data.ip_address,
        icon: nodeToEdit.data.icon,
      });
      setIsEditorOpen(true);
    }
  };

  const handleDelete = async (deviceId: string) => {
    if (window.confirm('Are you sure you want to delete this device?')) {
      try {
        await deleteDevice(deviceId);
        setNodes((nds) => nds.filter((node) => node.id !== deviceId));
        showSuccess('Device deleted successfully.');
      } catch (error) {
        showError('Failed to delete device.');
      }
    }
  };

  const handleSaveDevice = async (deviceData: Omit<NetworkDevice, 'id' | 'position_x' | 'position_y'>) => {
    try {
      if (editingDevice?.id) {
        const updatedDevice = await updateDevice(editingDevice.id, deviceData);
        setNodes((nds) =>
          nds.map((node) => {
            if (node.id === updatedDevice.id) {
              node.data = { ...node.data, name: updatedDevice.name, ip_address: updatedDevice.ip_address, icon: updatedDevice.icon };
            }
            return node;
          })
        );
        showSuccess('Device updated successfully.');
      } else {
        const newDevice = await addDevice({ ...deviceData, position_x: 100, position_y: 100 });
        const newNode: Node = {
          id: newDevice.id,
          type: 'device',
          position: { x: newDevice.position_x, y: newDevice.position_y },
          data: {
            ...newDevice,
            onEdit: handleEdit,
            onDelete: handleDelete,
          },
        };
        setNodes((nds) => [...nds, newNode]);
        showSuccess('Device added successfully.');
      }
    } catch (error) {
      showError('Failed to save device.');
    }
  };

  const onNodeDragStop: NodeDragHandler = useCallback(
    async (_event, node) => {
      try {
        await updateDevice(node.id, { position_x: node.position.x, position_y: node.position.y });
      } catch (error) {
        showError('Failed to save device position.');
        loadNetworkData();
      }
    },
    [loadNetworkData]
  );

  const onEdgesChangeHandler: OnEdgesChange = useCallback((changes) => {
    onEdgesChange(changes);
    changes.forEach(async (change) => {
      if (change.type === 'remove') {
        try {
          await deleteEdgeFromDB(change.id);
          showSuccess('Connection deleted.');
        } catch (error) {
          showError('Failed to delete connection.');
          loadNetworkData();
        }
      }
    });
  }, [onEdgesChange, loadNetworkData]);

  return (
    <div style={{ height: '70vh', width: '100%' }} className="relative border rounded-lg bg-gray-900">
      <ReactFlow
        nodes={nodes}
        edges={edges}
        onNodesChange={onNodesChange}
        onEdgesChange={onEdgesChangeHandler}
        onConnect={onConnect}
        nodeTypes={nodeTypes}
        onNodeDragStop={onNodeDragStop}
        fitView
      >
        <Controls />
        <MiniMap nodeColor={(n) => '#4a5568'} nodeStrokeWidth={3} />
        <Background gap={16} size={1} color="#444" />
      </ReactFlow>
      <div className="absolute top-4 left-4">
        <Button onClick={handleAddDevice}>
          <PlusCircle className="h-4 w-4 mr-2" />
          Add Device
        </Button>
      </div>
      {isEditorOpen && (
        <DeviceEditorDialog
          isOpen={isEditorOpen}
          onClose={() => setIsEditorOpen(false)}
          onSave={handleSaveDevice}
          device={editingDevice}
        />
      )}
    </div>
  );
};

export default NetworkMap;