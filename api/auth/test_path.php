<?php
header("Content-Type: text/plain");

$files = [
    'Exception' => '../../libs/PHPMailer/Exception.php',
    'PHPMailer' => '../../libs/PHPMailer/PHPMailer.php',
    'SMTP'      => '../../libs/PHPMailer/SMTP.php',
    'DB'        => '../db/users.json'
];

foreach ($files as $name => $path) {
    echo $name . ": " . (file_exists($path) ? "✅ TROUVÉ" : "❌ INTROUVABLE ($path)") . "\n";
}

echo "\nExtension OpenSSL: " . (extension_loaded('openssl') ? "✅ ACTIVE" : "❌ DÉSACTIVÉE (Indispensable pour Gmail)");
