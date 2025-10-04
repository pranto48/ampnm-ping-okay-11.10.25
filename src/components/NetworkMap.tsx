// This component is a work in progress and will be improved in future steps.
// For now, it provides a basic structure for the network map.
import React, { useEffect, useRef } from 'react';
import { DataSet } from 'vis-data/peer';
import { Network } from 'vis-network/peer';
import 'vis-network/styles/vis-network.css';

const NetworkMap = () => {
  const visJsRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    const nodes = new DataSet([
      { id: 1, label: 'Welcome!' },
      { id: 2, label: 'Add devices to start' },
    ]);

    const edges = new DataSet([
      { from: 1, to: 2 },
    ]);

    const data = { nodes, edges };
    const options = {
        nodes: {
            shape: 'dot',
            size: 16,
            font: {
                color: '#ffffff'
            },
            borderWidth: 2
        },
        edges: {
            width: 2,
            color: {
                color: '#ffffff'
            }
        },
        physics: {
            enabled: true
        },
        layout: {
            hierarchical: {
                enabled: false
            }
        },
        interaction: {
            dragNodes: true
        }
    };

    if (visJsRef.current) {
      new Network(visJsRef.current, data, options);
    }
  }, []);

  return (
    <div className="bg-slate-800 border border-slate-700 rounded-lg shadow-xl p-4">
        <h2 className="text-xl font-semibold text-white mb-4">My Network</h2>
        <div ref={visJsRef} style={{ height: '75vh', backgroundColor: '#1e293b' }} />
    </div>
  );
};

export default NetworkMap;