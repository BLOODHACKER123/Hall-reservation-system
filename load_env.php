<?php
function loadEnv($filePath) {
    if (!file_exists($filePath)) {
        die(".env file not found");
    }

    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue; 
        }

        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value, " \t\n\r\0\x0B\"'");

       
        putenv(sprintf('%s=%s', $name, $value)); 
        
        
        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
    }
}

// Run the function
loadEnv(__DIR__ . '/.env');
?>