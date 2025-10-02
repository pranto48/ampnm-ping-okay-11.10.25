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
} from 'reactflow';
import 'reactflow/dist/style.css';
import { Button } from '@/components/ui/button';
import { PlusCircle } from 'lucide-react';
import { getDevices, addDevice, updateDevice, deleteDevice, NetworkDevice } from '@/services/networkDeviceService';
import { DeviceEditorDialog } from './DeviceEditorDialog';
import DeviceNode from './DeviceNode';
import { showSuccess, showError } from '@/utils/toast';

const NetworkMap = () => {
  const [nodes, setNodes, onNodesChange] = useNodesState([]);
  const [edges, setEdges, onEdgesChange] = useEdgesState([]);
  const [isEditorOpen, setIsEditorOpen] = useState(false);
  const [editingDevice, setEditingDevice] = useState<Partial<NetworkDevice> | undefined>(undefined);

  const nodeTypes = useMemo(() => ({ device: DeviceNode }), []);

  const loadNetworkDevices = useCallback(async () => {
    try {
      const devices = await getDevices();
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
    } catch (error) {
      showError('Failed to load network devices.');
    }
  }, [setNodes]);

  useEffect(() => {
    loadNetworkDevices();
  }, [loadNetworkDevices]);

  const onConnect = useCallback((params: Edge | Connection) => setEdges((eds) => addEdge(params, eds)), [setEdges]);

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
        // Update existing device
        const updatedDevice = await updateDevice(editingDevice.id, deviceData);
        setNodes((nds) =>
          nds.map((node) => {
            if (node.id === updatedDevice.id) {
              node.data = { ...node.data, ...updatedDevice };
            }
            return node;
          })
        );
        showSuccess('Device updated successfully.');
      } else {
        // Add new device
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
        // Optionally revert position change on error
        loadNetworkDevices();
      }
    },
    [loadNetworkDevices]
  );

  return (
    <div style={{ height: '70vh', width: '100%' }} className="relative border rounded-lg">
      <ReactFlow
        nodes={nodes}
        edges={edges}
        onNodesChange={onNodesChange}
        onEdgesChange={onEdgesChange}
        onConnect={onConnect}
        nodeTypes={nodeTypes}
        onNodeDragStop={onNodeDragStop}
        fitView
      >
        <Controls />
        <MiniMap />
        <Background gap={12} size={1} />
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