import { useEffect, useState } from 'react';
import { BrowserRouter as Router, Route, Routes, useNavigate } from 'react-router-dom';
import { Session } from '@supabase/supabase-js';
import { supabase } from './integrations/supabase/client';
import Index from './pages/Index';
import Login from './pages/Login';

function App() {
  const [session, setSession] = useState<Session | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const getSession = async () => {
      const { data: { session } } = await supabase.auth.getSession();
      setSession(session);
      setLoading(false);
    };

    getSession();

    const { data: { subscription } } = supabase.auth.onAuthStateChange((_event, session) => {
      setSession(session);
    });

    return () => subscription.unsubscribe();
  }, []);

  if (loading) {
    return <div>Loading...</div>;
  }

  return (
    <Router>
      <Routes>
        <Route path="/login" element={<Login />} />
        <Route path="/" element={session ? <Index session={session} /> : <AuthRedirect />} />
      </Routes>
    </Router>
  );
}

const AuthRedirect = () => {
  const navigate = useNavigate();
  useEffect(() => {
    navigate('/login');
  }, [navigate]);
  return null;
};

export default App;