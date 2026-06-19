<?php
header('Content-Type: text/plain; charset=utf-8');
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "=== DIAGNOSTICS & DEPLOY FIX ===\n";

$prodTokenFile = __DIR__ . '/.deploy_token';
$testTokenFile = __DIR__ . '/test/.deploy_token';

echo "Prod token file path: $prodTokenFile\n";
if (file_exists($prodTokenFile)) {
    $tokenValue = trim(file_get_contents($prodTokenFile));
    echo "Prod token exists. Length: " . strlen($tokenValue) . "\n";
    
    // Check if test dir exists
    $testDir = __DIR__ . '/test';
    if (is_dir($testDir)) {
        echo "Test directory exists.\n";
        
        // Write the token to test/.deploy_token
        if (file_put_contents($testTokenFile, $tokenValue) !== false) {
            echo "Successfully wrote token to test/.deploy_token\n";
            chmod($testTokenFile, 0600);
        } else {
            echo "Failed to write token to test/.deploy_token\n";
        }
        
        // Check if upload_receiver.php exists in test/
        $testReceiver = $testDir . '/upload_receiver.php';
        if (!file_exists($testReceiver)) {
            echo "upload_receiver.php is missing in test/, trying to copy...\n";
            if (file_exists(__DIR__ . '/deploy/upload_receiver.php')) {
                copy(__DIR__ . '/deploy/upload_receiver.php', $testReceiver);
                echo "Copied from deploy/upload_receiver.php\n";
            } elseif (file_exists(__DIR__ . '/upload_receiver.php')) {
                copy(__DIR__ . '/upload_receiver.php', $testReceiver);
                echo "Copied from upload_receiver.php\n";
            } else {
                echo "Could not find source upload_receiver.php to copy!\n";
            }
        } else {
            echo "upload_receiver.php exists in test/.\n";
        }
    } else {
        echo "Test directory does NOT exist at $testDir\n";
    }
} else {
    echo "Prod token does NOT exist at $prodTokenFile!\n";
}

echo "=== DIR LISTING ===\n";
echo "Files in " . __DIR__ . ":\n";
print_r(scandir(__DIR__));

if (is_dir(__DIR__ . '/test')) {
    echo "Files in " . __DIR__ . "/test:\n";
    print_r(scandir(__DIR__ . '/test'));
}

