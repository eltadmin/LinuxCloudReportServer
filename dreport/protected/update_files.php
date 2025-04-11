<?php
// Script to update all PHP files to use the new initialization system

$files_to_skip = array(
    'init.php',
    'language.php',
    'database.class.php',
    'Slim/Middleware/SessionCookie.php'
);

$dir = __DIR__;
$files = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($dir)
);

foreach ($files as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $relative_path = str_replace($dir . '/', '', $file->getPathname());
        
        // Skip certain files
        if (in_array($relative_path, $files_to_skip)) {
            continue;
        }
        
        $content = file_get_contents($file->getPathname());
        
        // Skip files that already use the new system
        if (strpos($content, "define('DREPORT_INIT'") !== false) {
            continue;
        }
        
        // Check if file starts with <?php
        if (strpos($content, '<?php') === 0) {
            $new_content = "<?php\ndefine('DREPORT_INIT', true);\nrequire_once __DIR__ . '/init.php';\ncheckAuth();\n\n";
            $content = preg_replace('/^<\?php\s*(session_start\(\);)?\s*(.*)/s', $new_content . '$2', $content);
        } else {
            $new_content = "<?php\ndefine('DREPORT_INIT', true);\nrequire_once __DIR__ . '/init.php';\ncheckAuth();\n?>\n";
            $content = $new_content . $content;
        }
        
        file_put_contents($file->getPathname(), $content);
        echo "Updated: " . $relative_path . "\n";
    }
}

echo "Update complete!\n";
?> 