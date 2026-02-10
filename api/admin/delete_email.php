<?php
// 1. Empêcher l'affichage d'erreurs texte qui cassent le JSON
ini_set('display_errors', 0);
error_reporting(0);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// 2. Chemins corrigés (Remonter de 2 niveaux depuis api/admin/)
$userFile = __DIR__ . '/../db/users.json';
$mailsFile = __DIR__ . '/../db/mail_logs.json';

// 3. Récupération sécurisée
$input = json_decode(file_get_contents('php://input'), true);
$targetMailId = $input['id'] ?? null;

if (!$targetMailId) {
    echo json_encode(["error" => "ID du mail manquant !"]);
    exit;
}

// 4. Authentification (Logique simplifiée et robuste)
function getUserIdFromToken() {
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        $parts = explode('.', $matches[1]);
        if (count($parts) === 3) {
            $payload = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $parts[1])), true);
            return $payload['id'] ?? null;
        }
    }
    return null;
}

$adminId = getUserIdFromToken();
if (!$adminId) {
    http_response_code(401);
    exit(json_encode(["error" => "Authentification requise"]));
}

// 5. Vérification existence fichiers avant lecture
if (!file_exists($userFile) || !file_exists($mailsFile)) {
    http_response_code(500);
    exit(json_encode(["error" => "Base de données introuvable"]));
}

$users = json_decode(file_get_contents($userFile), true) ?: [];
$mails = json_decode(file_get_contents($mailsFile), true) ?: [];

// 6. Vérification droits Admin
$isAdmin = false;
foreach ($users as $u) {
    if ((int)$u['id'] === (int)$adminId && ($u['role'] ?? '') === 'admin') {
        $isAdmin = true;
        break;
    }
}

if (!$isAdmin) {
    http_response_code(403);
    exit(json_encode(["error" => "Accès refusé"]));
}

// 7. Filtrage
$newMails = [];
$found = false;
foreach ($mails as $mail) {
    if ($mail['id'] === $targetMailId) {
        $found = true;
        continue;
    }
    $newMails[] = $mail;
}

if ($found) {
    file_put_contents($mailsFile, json_encode(array_values($newMails), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    echo json_encode(['status' => 'success', 'message' => 'Mail supprimé avec succès']);
} else {
    http_response_code(404);
    echo json_encode(['error' => 'Aucun mail trouvé avec cet ID']);
}
