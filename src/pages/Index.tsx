import { Session } from '@supabase/supabase-js';
import NetworkMap from '../components/NetworkMap';
import { supabase } from '../integrations/supabase/client';
import { useNavigate } from 'react-router-dom';

interface IndexProps {
  session: Session;
}

const Index = ({ session }: IndexProps) => {
  const navigate = useNavigate();

  const handleSignOut = async () => {
    await supabase.auth.signOut();
    navigate('/login');
  };

  return (
    <div className="min-h-screen bg-slate-900 text-slate-300">
       <nav className="bg-slate-800/50 backdrop-blur-lg shadow-lg sticky top-0 z-50">
        <div className="container mx-auto px-4">
            <div className="flex items-center justify-between h-16">
                <div className="flex items-center">
                    <a href="/" className="flex items-center gap-2 text-white font-bold">
                        <i className="fas fa-shield-halved text-cyan-400 text-2xl"></i>
                        <span>Network Security</span>
                    </a>
                </div>
                <div className="flex items-center gap-4">
                    <span className="text-sm text-slate-400">{session.user.email}</span>
                    <button 
                        onClick={handleSignOut}
                        className="px-3 py-2 rounded-md text-sm font-medium text-slate-300 hover:bg-slate-700 hover:text-white"
                    >
                        Sign Out
                    </button>
                </div>
            </div>
        </div>
    </nav>
      <main className="p-4">
        <NetworkMap />
      </main>
    </div>
  );
};

export default Index;