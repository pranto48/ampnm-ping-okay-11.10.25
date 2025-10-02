import { serve } from "https://deno.land/std@0.190.0/http/server.ts"
import { exec } from "https://deno.land/x/exec@0.0.5/mod.ts"

const corsHeaders = {
  'Access-Control-Allow-Origin': '*',
  'Access-Control-Allow-Headers': 'authorization, x-client-info, apikey, content-type',
}

serve(async (req) => {
  if (req.method === 'OPTIONS') {
    return new Response(null, { headers: corsHeaders })
  }

  try {
    const { host, count = 1, timeout = 5000 } = await req.json()
    
    if (!host) {
      return new Response(
        JSON.stringify({ error: 'Host is required' }),
        { status: 400, headers: { ...corsHeaders, 'Content-Type': 'application/json' } }
      )
    }

    // Validate host format
    const hostRegex = /^[a-zA-Z0-9.-]+$/
    if (!hostRegex.test(host)) {
      return new Response(
        JSON.stringify({ error: 'Invalid host format' }),
        { status: 400, headers: { ...corsHeaders, 'Content-Type': 'application/json' } }
      )
    }

    // Use system ping command (works on both Linux and macOS)
    const command = Deno.build.os === "windows" 
      ? `ping -n ${count} -w ${timeout} ${host}`
      : `ping -c ${count} -W ${Math.ceil(timeout / 1000)} ${host}`

    const output = await exec(command)
    
    // Parse ping output
    const result = {
      host,
      timestamp: new Date().toISOString(),
      success: output.status.success,
      output: output.stdout,
      error: output.stderr,
      statusCode: output.status.code
    }

    return new Response(
      JSON.stringify(result),
      { headers: { ...corsHeaders, 'Content-Type': 'application/json' } }
    )

  } catch (error) {
    console.error('Ping error:', error)
    return new Response(
      JSON.stringify({ error: 'Internal server error', details: error.message }),
      { status: 500, headers: { ...corsHeaders, 'Content-Type': 'application/json' } }
    )
  }
})