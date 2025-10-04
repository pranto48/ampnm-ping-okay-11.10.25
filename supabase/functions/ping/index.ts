import { serve } from "https://deno.land/std@0.168.0/http/server.ts";

const corsHeaders = {
  'Access-Control-Allow-Origin': '*',
  'Access-Control-Allow-Headers': 'authorization, x-client-info, apikey, content-type',
};

serve(async (req) => {
  if (req.method === 'OPTIONS') {
    return new Response('ok', { headers: corsHeaders });
  }

  try {
    const { host } = await req.json();
    if (!host) {
      return new Response(JSON.stringify({ error: 'Host is required' }), {
        headers: { ...corsHeaders, 'Content-Type': 'application/json' },
        status: 400,
      });
    }

    // Basic validation to prevent command injection
    if (!/^[a-zA-Z0-9\.\-]+$/.test(host)) {
        return new Response(JSON.stringify({ error: 'Invalid host format' }), {
            headers: { ...corsHeaders, 'Content-Type': 'application/json' },
            status: 400,
        });
    }

    const command = new Deno.Command("ping", {
      args: ["-c", "4", host], // -c 4 for 4 packets, common on Linux
    });

    const { code, stdout, stderr } = await command.output();
    const output = new TextDecoder().decode(stdout);
    const error = new TextDecoder().decode(stderr);

    return new Response(JSON.stringify({ code, output, error }), {
      headers: { ...corsHeaders, 'Content-Type': 'application/json' },
      status: 200,
    });
  } catch (error) {
    return new Response(JSON.stringify({ error: error.message }), {
      headers: { ...corsHeaders, 'Content-Type': 'application/json' },
      status: 500,
    });
  }
});