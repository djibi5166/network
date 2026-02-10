<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

// 1. Désactiver l'affichage des erreurs pour ne pas polluer le JSON
ini_set('display_errors', 0);

// 2. Importation des classes (Chemin corrigé vers PHPMailer dans libs/)
require_once __DIR__ . '/../../libs/PHPMailer/Exception.php';
require_once __DIR__ . '/../../libs/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/../../libs/PHPMailer/SMTP.php';

// 3. Fonction de Log avec le BON CHEMIN
function logMailEvent($email, $username, $status, $details = "") {
    // 1. Définition des chemins (Remonte de 2 niveaux depuis libs/mailFunc/)
    $logFileDir = __DIR__ . '/../../api/db';
    $logFile = $logFileDir . '/mail_logs.json';
    $logs = [];

    // 2. Création du dossier s'il n'existe pas (AJOUT DU POINT-VIRGULE ICI)
    if (!is_dir($logFileDir)) {
        mkdir($logFileDir, 0777, true);
    }

    // 3. Lecture du fichier existant
    if (file_exists($logFile)) {
        $content = file_get_contents($logFile);
        $logs = json_decode($content, true);
        if (!is_array($logs)) {
            $logs = [];
        }
    }

    // 4. Ajout de la nouvelle entrée
    $logs[] = [
        "id" => uniqid(),
        "date" => date("d/m/Y H:i:s"),
        "user" => $username,
        "email" => $email,
        "subject" => "Bienvenue chez Nexus",
        "status" => $status,
        "details" => $details
    ];

    // 5. Sauvegarde sans LOCK_EX pour éviter l'alerte sur Android
    file_put_contents($logFile, json_encode($logs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}


// 4. Logique d'envoi
if (isset($_SESSION['toNewUserEmail']) && isset($_SESSION['toNewUserName'])) {
    $targetEmail = $_SESSION['toNewUserEmail'];
    $targetName = htmlspecialchars($_SESSION['toNewUserName']); 
    
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'djibi404@gmail.com'; 
        $mail->Password   = 'yulk rvrg qvci vema'; 
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = 465;
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom('djibi404@gmail.com', 'Nexus App');
        $mail->addAddress($targetEmail, $targetName);

        $mail->isHTML(true);
        $mail->Subject = "Bienvenue chez Nexus !";
        $mail->Body    = "<h2>Félicitations, {$targetName} ! 🎉</h2><p>Votre compte a été créé avec succès.</p>";
        $mail->AltBody = "Salut {$targetName}, votre compte Nexus a été créé avec succès.";

        if ($mail->send()) {
            logMailEvent($targetEmail, $targetName, "success");
        }

    } catch (Exception $e) {
        logMailEvent($targetEmail, $targetName, "error", $mail->ErrorInfo);
    }

    // Nettoyage de la session
    unset($_SESSION['toNewUserEmail'], $_SESSION['toNewUserName']);
}
