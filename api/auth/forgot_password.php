<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

// 1. Activation de l'affichage des erreurs pour le débogage
ini_set('display_errors', 1);
error_reporting(E_ALL);

// 2. Importation des classes (Chemins relatifs depuis api/auth/)
// 1. On remonte de DEUX niveaux pour atteindre la racine du projet
require __DIR__ . '/../../libs/PHPMailer/Exception.php';
require __DIR__ . '/../../libs/PHPMailer/PHPMailer.php';
require __DIR__ . '/../../libs/PHPMailer/SMTP.php';

// Même chose pour la base de données à la ligne 15
//$file = __DIR__ . '/../../db/users.json';


header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

$dbFile = __DIR__ . '/../db/users.json';

// 3. Récupération des données
$input = json_decode(file_get_contents("php://input"), true);
$targetUser = trim($input['username'] ?? '');

if (empty($targetUser)) {
    http_response_code(400);
    exit(json_encode(["error" => "Pseudo requis"]));
}

// 4. Recherche de l'utilisateur
$users = json_decode(file_get_contents($dbFile), true);
$found = false;
$userEmail = "";
$otp = (string)random_int(10000, 99999);

foreach ($users as $key => $user) {
    if (strcasecmp(trim($user['username']), $targetUser) === 0) {
        $users[$key]['reset_token'] = $otp;
        $users[$key]['reset_exp'] = time() + 300; // 5 minutes
        $userEmail = $user['email'];
        $found = true;
        break;
    }
}

if (!$found) {
    http_response_code(404);
    exit(json_encode(["error" => "Utilisateur non trouvé"]));
}

// 5. ENVOI DE L'EMAIL VIA SMTP
$mail = new PHPMailer(true);

try {
    // Paramètres du serveur
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'djibi404@gmail.com';         // <--- METS TON GMAIL ICI
    $mail->Password   = 'yulk rvrg qvci vema';         // <--- TON MOT DE PASSE D'APPLICATION
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;    // SSL direct
    $mail->Port       = 465;                            // Port pour SSL
    $mail->CharSet    = 'UTF-8';

    // Destinataire
    $mail->setFrom('djibi404@gmail.com', 'Nexus App'); // <--- ENCORE TON GMAIL
    $mail->addAddress($userEmail);

    // Contenu de l'email
    $mail->isHTML(true);
    $mail->Subject = "Votre code de récupération Nexus";
    $mail->Body    = "Bonjour <b>$targetUser</b>,<br><br>Votre code de vérification est : <h2 style='color:#4361ee;'>$otp</h2>Il expire dans 5 minutes.";

    // 6. Sauvegarde en DB et envoi
    if ($mail->send()) {
        file_put_contents($dbFile, json_encode($users, JSON_PRETTY_PRINT));
        echo json_encode(["status" => "success", "message" => "Un code OTP a été envoyé à l'adresse liée à votre compte. Expire dans 5 minutes"]);
    }

}  catch (Exception $e) {
    http_response_code(500);
    // On renvoie l'erreur détaillée de PHPMailer
    echo json_encode([
        "error" => "Erreur mail : " . $mail->ErrorInfo,
        "debug_php" => $e->getMessage()
    ]);
}

?>
