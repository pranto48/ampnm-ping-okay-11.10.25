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
import { performServerPing } from '@/services/pingService';

const NetworkMap = () => {
  const [nodes, setNodes, onNodesChange] = useNodesState([]);
  const [edges, setEdges, onEdgesChange] = useEdgesState([]);
  const [isEditorOpen, setIsEditorOpen] = useState(false);
  const [editingDevice, setEditingDevice] = useState<Partial<NetworkDevice> | undefined>(undefined);

  const nodeTypes = useMemo(() => ({ device: DeviceNode }), []);

  const handleStatusChange = useCallback(
    async (nodeId: string, status: 'online' | 'offline') => {
      setNodes((nds) =>
        nds.map((node) => {
          if (node.id === nodeId) {
            return { ...node, data: { ...node.data, status } };
          }
          return node;
        })
      );
      try {
        await updateDevice(nodeId, { status });
      } catch (error) {
        showError('Failed to update device status in DB.');
      }
    },
    [setNodes]
  );

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
          status: device.status,
          ping_interval: device.ping_interval,
          onEdit: handleEdit,
          onDelete: handleDelete,
          onStatusChange: handleStatusChange,
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
  }, [setNodes, setEdges, handleStatusChange]);

  useEffect(() => {
    loadNetworkData();
  }, [loadNetworkData]);

  useEffect(() => {
    const intervals: NodeJS.Timeout[] = [];

    nodes.forEach((node) => {
      if (node.data.ping_interval && node.data.ping_interval > 0) {
        const intervalId = setInterval(async () => {
          try {
            const result = await performServerPing(node.data.ip_address, 1);
            const newStatus = result.success ? 'online' : 'offline';
            handleStatusChange(node.id, newStatus);
          } catch (error) {
            handleStatusChange(node.id, 'offline');
          }
        }, node.data.ping_interval * 1000);
        intervals.push(intervalId);
      }
    });

    return () => {
      intervals.forEach(clearInterval);
    };
  }, [nodes, handleStatusChange]);

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
        loadNetworkData();
      } catch (error) {
        showError('Failed to save connection.');
        loadNetworkData();
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
        ping_interval: nodeToEdit.data.ping_interval,
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
        await updateDevice(editingDevice.id, deviceData);
        showSuccess('Device updated successfully.');
      } else {
        await addDevice({ ...deviceData, position_x: 100, position_y: 100, status: 'unknown' });
        showSuccess('Device added successfully.');
      }
      loadNetworkData();
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

  const onEdgesChangeHandler: OnEdgesChange = useCallback(
    (changes) => {
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
    },
    [onEdgesChange, loadNetworkData]
  );

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