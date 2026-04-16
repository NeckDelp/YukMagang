<?php
// Deployment runner - DELETE THIS FILE AFTER USE
set_time_limit(300);
error_reporting(E_ALL);
ini_set('display_errors', 1);

chdir('/home/ayomagang/pklbackend');

echo "<pre>";
echo "<h2>Laravel Deployment Runner</h2>\n";

// Commands to run
$commands = [
    'php artisan config:clear',
    'php artisan cache:clear',
    'php artisan migrate --force',
    'php artisan config:cache',
    'php artisan route:cache',
    'php artisan storage:link',
    'chmod -R 775 storage/',
    'chmod -R 775 bootstrap/cache/',
];

foreach ($commands as $cmd) {
    echo "<strong>Running: $cmd</strong>\n";
    $output = shell_exec("cd /home/ayomagang/pklbackend && $cmd 2>&1");
    echo htmlspecialchars($output ?? '(no output)');
    echo "\n---\n";
}

echo "</pre>";
echo "<p style='color:green'><strong>Done! Delete this file immediately.</strong></p>";
