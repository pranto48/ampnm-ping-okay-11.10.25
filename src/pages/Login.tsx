import { supabase } from '@/integrations/supabase/client';
import { Auth } from '@supabase/auth-ui-react';
import { ThemeSupa } from '@supabase/auth-ui-shared';
import { Monitor } from 'lucide-react';

const Login = () => {
  return (
    <div className="min-h-screen bg-gray-100 flex flex-col justify-center items-center">
      <div className="mb-8 text-center">
        <div className="flex items-center justify-center gap-3 mb-4">
          <Monitor className="h-10 w-10 text-primary" />
          <h1 className="text-4xl font-bold">Network Monitor</h1>
        </div>
        <p className="text-gray-600">Sign in to access your dashboard</p>
      </div>
      <div className="w-full max-w-md p-8 bg-white rounded-lg shadow-md">
        <Auth
          supabaseClient={supabase}
          appearance={{ theme: ThemeSupa }}
          providers={[]}
          theme="light"
        />
      </div>
    </div>
  );
};

export default Login;