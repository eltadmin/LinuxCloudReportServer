<?php
// Ensure no output is sent before session_start
ob_start();
session_start();

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Load configuration
require_once 'database.class.php';
require_once 'language.php';

// Get database instance
$db = Database::getInstance();

// Get server configuration from database
$query = $db->query("SELECT * FROM t_settings");
if ($query) {
    while ($row = mysqli_fetch_assoc($query)) {
        $_SESSION[$row['s_name']] = $row['s_value'];
    }
}
?> 