<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Supabase\Client as SupabaseClient;

function getSupabaseClient(): SupabaseClient {
    $supabaseUrl = getenv('SUPABASE_URL');
    $supabaseKey = getenv('SUPABASE_KEY');

    if (!$supabaseUrl || !$supabaseKey) {
        throw new Exception("Supabase URL or Key environment variables are not set for the license app.");
    }

    return new SupabaseClient($supabaseUrl, $supabaseKey);
}
?>