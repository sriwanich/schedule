<?php
/**
 * Schedule Enterprise — Database Config
 * ★ Phase 3: PDO-based connection
 */

defined('APP_VERSION') || require_once __DIR__ . '/app.php';

// ── Credentials (override via .env) ──────────────────────────
$_DB = [
    'host'    => getenv('DB_HOST') ?: 'localhost',
    'name'    => getenv('DB_NAME') ?: 'dpmpry_schedule',
    'user'    => getenv('DB_USER') ?: 'dpmpryg_sriwanich',
    'pass'    => getenv('DB_PASS') ?: 'M@t34a053896077',
    'charset' => 'utf8mb4',
];

// ── PDO Singleton ────────────────────────────────────────────
function getPDO(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        global $_DB;
        $dsn = "mysql:host={$_DB['host']};dbname={$_DB['name']};charset={$_DB['charset']}";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
        ];
        try {
            $pdo = new PDO($dsn, $_DB['user'], $_DB['pass'], $options);
        } catch (PDOException $e) {
            error_log('DB Connection failed: ' . $e->getMessage());
            if (APP_DEBUG) {
                throw $e;
            }
            http_response_code(503);
            die(json_encode(['error' => 'Database unavailable']));
        }
    }
    return $pdo;
}

// ── Legacy mysqli for backward compat (config.php includes this) ──
$host   = $_DB['host'];
$dbname = $_DB['name'];
$dbuser = $_DB['user'];
$dbpass = $_DB['pass'];

$connect = mysqli_connect($host, $dbuser, $dbpass, $dbname);
if (!$connect) {
    error_log('mysqli connection failed: ' . mysqli_connect_error());
    die('การเชื่อมต่อฐานข้อมูลล้มเหลว');
}
mysqli_query($connect, 'SET NAMES utf8mb4');