<?php
// =========================================================
// LASSO — Supabase Storage helper
// Replaces local disk uploads (move_uploaded_file) so files
// survive Render's ephemeral filesystem across redeploys.
//
// Requires these environment variables in Render:
//   SUPABASE_URL               e.g. https://xxxxxxxx.supabase.co
//   SUPABASE_SERVICE_ROLE_KEY  Project Settings -> API -> service_role key
//                               (NOT the anon/public key — this one bypasses
//                               storage policies, needed for server-side writes)
//
// Two buckets are used (create both in Supabase -> Storage):
//   lasso-public     (Public bucket)  - covers, ID photos, profile photos
//   lasso-materials  (Private bucket) - instructional material page images
// =========================================================

define('STORAGE_PUBLIC_BUCKET', 'lasso-public');
define('STORAGE_MATERIALS_BUCKET', 'lasso-materials');

/**
 * Upload a local temp file (from $_FILES[...]['tmp_name']) to a Supabase
 * Storage bucket. Returns true on success, false on failure.
 */
function uploadToStorage(string $bucket, string $objectPath, string $localTmpFile, string $mimeType): bool {
    $baseUrl = getenv('SUPABASE_URL');
    $key = getenv('SUPABASE_SERVICE_ROLE_KEY');
    if (!$baseUrl || !$key) {
        error_log('SUPABASE_URL or SUPABASE_SERVICE_ROLE_KEY is not set.');
        return false;
    }

    $fileData = file_get_contents($localTmpFile);
    if ($fileData === false) return false;

    $url = rtrim($baseUrl, '/') . '/storage/v1/object/' . $bucket . '/' . ltrim($objectPath, '/');

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $key,
            'Content-Type: ' . $mimeType,
            'x-upsert: true', // overwrite if the same path is uploaded again
        ],
        CURLOPT_POSTFIELDS => $fileData,
        CURLOPT_TIMEOUT => 30,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode >= 200 && $httpCode < 300) return true;
    error_log("Supabase Storage upload failed ($httpCode): $response");
    return false;
}

/**
 * Download an object's raw bytes from a Supabase Storage bucket.
 * Used to proxy PRIVATE bucket files (material pages) through a PHP
 * endpoint that still enforces subscription/preview access control.
 * Returns [bytes, mimeType] on success, or null if not found/failed.
 */
function downloadFromStorage(string $bucket, string $objectPath): ?array {
    $baseUrl = getenv('SUPABASE_URL');
    $key = getenv('SUPABASE_SERVICE_ROLE_KEY');
    if (!$baseUrl || !$key) return null;

    $url = rtrim($baseUrl, '/') . '/storage/v1/object/' . $bucket . '/' . ltrim($objectPath, '/');

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $key],
        CURLOPT_TIMEOUT => 30,
    ]);
    $body = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);

    if ($httpCode !== 200 || $body === false) return null;
    return [$body, $contentType ?: 'application/octet-stream'];
}

/** Builds a public URL for an object in the PUBLIC bucket only. */
function publicStorageUrl(string $objectPath): string {
    $baseUrl = rtrim(getenv('SUPABASE_URL') ?: '', '/');
    return $baseUrl . '/storage/v1/object/public/' . STORAGE_PUBLIC_BUCKET . '/' . ltrim($objectPath, '/');
}

/**
 * List object names under a prefix (folder) in a Storage bucket.
 * Used to find all page images for a material before deleting them.
 */
function listStorageObjects(string $bucket, string $prefix): array {
    $baseUrl = getenv('SUPABASE_URL');
    $key = getenv('SUPABASE_SERVICE_ROLE_KEY');
    if (!$baseUrl || !$key) return [];

    $url = rtrim($baseUrl, '/') . '/storage/v1/object/list/' . $bucket;
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $key,
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode(['prefix' => rtrim($prefix, '/'), 'limit' => 1000]),
        CURLOPT_TIMEOUT => 15,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) return [];
    $items = json_decode($response, true) ?: [];
    $paths = [];
    foreach ($items as $item) {
        if (!empty($item['name'])) $paths[] = rtrim($prefix, '/') . '/' . $item['name'];
    }
    return $paths;
}

/**
 * Permanently delete one or more objects (by full path) from a bucket.
 * Safe to call with an empty array. Returns true on success.
 */
function deleteStorageObjects(string $bucket, array $paths): bool {
    if (empty($paths)) return true;
    $baseUrl = getenv('SUPABASE_URL');
    $key = getenv('SUPABASE_SERVICE_ROLE_KEY');
    if (!$baseUrl || !$key) return false;

    $url = rtrim($baseUrl, '/') . '/storage/v1/object/' . $bucket;
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => 'DELETE',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $key,
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode(['prefixes' => array_values($paths)]),
        CURLOPT_TIMEOUT => 15,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return $httpCode >= 200 && $httpCode < 300;
}
