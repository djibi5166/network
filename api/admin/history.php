<?php
// --- CONFIGURATION ---
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");

// 1. Chemins Absolus
$historyFile = __DIR__ . '/../db/history.json';
$userFile = __DIR__ . '/../db/users.json';

// --- FONCTIONS INTERNES ---

// Auth : Récupérer ID depuis le Token
function getUserIdFromToken() {
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? '';
    if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        $parts = explode('.', $matches[1]);
        if (count($parts) === 3) {
            $payload = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $parts[1])), true);
            return $payload['id'] ?? null;
        }
    }
    return null;
}

// --- LOGIQUE PRINCIPALE ---

// 2. SÉCURITÉ : VÉRIFICATION ADMIN
$requesterId = getUserIdFromToken();
if (!$requesterId) {
    http_response_code(401);
    exit(json_encode(["error" => "Non autorisé"]));
}

// Lecture des utilisateurs pour vérifier le rôle
if (!file_exists($userFile)) exit(json_encode([]));
$users = json_decode(file_get_contents($userFile), true);

$isAdmin = false;
foreach ($users as $u) {
    if ((int)$u['id'] === $requesterId && ($u['role'] ?? '') === 'admin') {
        $isAdmin = true;
        break;
    }
}

if (!$isAdmin) {
    http_response_code(403);
    exit(json_encode(["error" => "Réservé aux admins"]));
}

// 3. LECTURE DE L'HISTORIQUE
if (!file_exists($historyFile)) {
    echo json_encode([]); // Si pas encore de logs, on renvoie une liste vide
    exit;
}


$jsonContent = file_get_contents($historyFile);
$logs = json_decode($jsonContent, true);


if (!is_array($logs)) {
    $logs = [];
}

//if(count($logs) >= 3){
//	$logs[] = [];
//}

// 4. RÉPONSE
// On renvoie les logs (ils sont déjà triés par date grâce à array_unshift dans les autres fichiers)
echo json_encode($logs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>
