<?php
// =========================================================
// LASSO — Database connection (Supabase / PostgreSQL)
// Credentials come from environment variables, set in Render's
// dashboard (Settings -> Environment). Never hardcode them here.
//
// Get these values from Supabase: Project Settings -> Database
// -> Connection string -> "Session pooler" (recommended for
// long-running app servers) or "Transaction pooler".
//   SUPABASE_DB_HOST     e.g. aws-0-ap-southeast-1.pooler.supabase.com
//   SUPABASE_DB_PORT     e.g. 5432 (session) or 6543 (transaction)
//   SUPABASE_DB_NAME     usually "postgres"
//   SUPABASE_DB_USER     e.g. postgres.xxxxxxxxxxxx
//   SUPABASE_DB_PASS     the database password you set in Supabase
// =========================================================
define('DB_HOST', getenv('SUPABASE_DB_HOST') ?: '');
define('DB_PORT', getenv('SUPABASE_DB_PORT') ?: '5432');
define('DB_NAME', getenv('SUPABASE_DB_NAME') ?: 'postgres');
define('DB_USER', getenv('SUPABASE_DB_USER') ?: '');
define('DB_PASS', getenv('SUPABASE_DB_PASS') ?: '');

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                "pgsql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";sslmode=require",
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage()]);
            exit;
        }
    }
    return $pdo;
}
