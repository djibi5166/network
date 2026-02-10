<?php
// --- CONFIGURATION ---
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");

// La même clé que dans login.php
$secret = "TON_CODE_SECRET_TRES_LONG"; 
$dbFile = __DIR__ . '/../../db/users.json';

// --- 1. DÉCODAGE DU TOKEN JWT ---
$headers = getallheaders();
$authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
$jwt = str_replace('Bearer ', '', $authHeader);

$parts = explode('.', $jwt);
if (count($parts) !== 3) {
    http_response_code(401);
    exit(json_encode(["error" => "Non connecté"]));
}

list($header64, $payload64, $signature) = $parts;

// Vérification de la signature
$expectedSig = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode(hash_hmac('sha256', "$header64.$payload64", $secret, true)));

if ($signature !== $expectedSig) {
    http_response_code(401);
    exit(json_encode(["error" => "Session invalide"]));
}

// Extraction des infos du Payload
$userData = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $payload64)), true);

// --- 2. RÉCUPÉRATION DES INFOS DANS LA DB ---
if (!file_exists($dbFile)) {
    http_response_code(500);
    exit(json_encode(["error" => "Base de données introuvable"]));
}

$users = json_decode(file_get_contents($dbFile), true);
$currentUser = null;

foreach ($users as $u) {
    if ((int)$u['id'] === (int)$userData['id']) {
        // On prépare les infos à renvoyer (SANS LE MOT DE PASSE !)
        $currentUser = [
            "id" => $u['id'],
            "username" => $u['username'],
            "email" => $u['email'] ?? 'Non renseigné',
            "role" => $u['role'] ?? 'user',
            "created_at" => $u['created_at'] ?? null
        ];
        break;
    }
}

if ($currentUser) {
    echo json_encode($currentUser);
} else {
    http_response_code(404);
    echo json_encode(["error" => "Utilisateur non trouvé"]);
}
?>
