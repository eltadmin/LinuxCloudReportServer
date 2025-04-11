<?php
// Start output buffering first
ob_start();

// Start session before anything else
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Authentication check function - defined globally
function checkAuth() {
    if (!isset($_SESSION['s_authenticated']) || empty($_SESSION['s_authenticated'])) {
        header("Location: ../index.php");
        exit();
    }
}

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Load required files
require_once __DIR__ . '/database.class.php';
require_once __DIR__ . '/language.php';

// Initialize database connection
$pDatabase = Database::getInstance();
$pDatabase->query("SET NAMES 'utf8'");

// Load configuration from database if not already loaded
if (!isset($_SESSION['config_loaded']) || $_SESSION['config_loaded'] !== true) {
    $config_query = $pDatabase->query("SELECT * FROM t_settings");
    if ($config_query) {
        while ($row = mysqli_fetch_assoc($config_query)) {
            $_SESSION[$row['s_name']] = $row['s_value'];
        }
        $_SESSION['config_loaded'] = true;
    }
}

// Default configuration values if not set
$default_config = array(
    's_rpt_server_host' => 'localhost',
    's_rpt_server_port' => '8016',
    's_rpt_server_user' => 'admin',
    'rpt_server_pswd' => 'admin'
);

foreach ($default_config as $key => $value) {
    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = $value;
    }
}
?> 