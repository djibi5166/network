<?php
// --- CONFIGURATION ---
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

// 1. Chemins Absolus
$userFile = __DIR__ . '/../db/users.json';
$historyFile = __DIR__ . '/../db/history.json';

// --- FONCTIONS INTERNES ---

// A. Auth (Récupérer ID depuis Token)
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

// B. Fonction de Log (Intégrée)
function addToHistory($file, $adminName, $action, $targetName) {
    $logs = [];
    if (file_exists($file)) {
        $logs = json_decode(file_get_contents($file), true);
        if (!is_array($logs)) $logs = [];
    }
    
    $newLog = [
        "date" => date("Y-m-d H:i:s"),
        "admin" => $adminName,
        "action" => $action,
        "target" => $targetName
    ];
    
    array_unshift($logs, $newLog); // Ajoute au début
    if (count($logs) > 50) $logs = array_slice($logs, 0, 50); // Garde les 50 derniers
    
    file_put_contents($file, json_encode($logs, JSON_PRETTY_PRINT));
}

// --- LOGIQUE PRINCIPALE ---

// 2. Lecture DB
if (!file_exists($userFile)) {
    http_response_code(500);
    exit(json_encode(["error" => "DB introuvable"]));
}
$users = json_decode(file_get_contents($userFile), true);

// 3. Identification de l'Admin (Celui qui fait la demande)
$requesterId = getUserIdFromToken();
$adminUser = null;

if ($requesterId) {
    foreach ($users as $u) {
        if ((int)$u['id'] === $requesterId) {
            $adminUser = $u;
            break;
        }
    }
}

// Vérification des droits
if (!$adminUser || ($adminUser['role'] ?? 'user') !== 'admin') {
    http_response_code(403);
    exit(json_encode(["error" => "Accès refusé"]));
}

// 4. Récupération de la Cible
$data = json_decode(file_get_contents("php://input"), true);
$targetId = isset($data['id']) ? (int)$data['id'] : 0;

if ($targetId <= 0) {
    http_response_code(400);
    exit(json_encode(["error" => "ID invalide"]));
}

// PROTECTIONS
if ($targetId === $requesterId) {
    http_response_code(403);
    exit(json_encode(["error" => "Vous ne pouvez pas vous bloquer vous-même"]));
}
if ($targetId === 1) {
    http_response_code(403);
    exit(json_encode(["error" => "Impossible de bloquer le Super Admin"]));
}

// 5. Modification et Sauvegarde
$found = false;
$targetUsername = "Inconnu";
$newStatus = false;

foreach ($users as &$u) {
    if ((int)$u['id'] === $targetId) {
        // Toggle (Inverse le statut)
        $u['active'] = !($u['active'] ?? true); // Si 'active' manque, on suppose true avant d'inverser
        
        $newStatus = $u['active'];
        $targetUsername = $u['username'];
        $found = true;
        break;
    }
}

if (!$found) {
    http_response_code(404);
    exit(json_encode(["error" => "Utilisateur introuvable"]));
}

// 6. Écriture DB Users
if (file_put_contents($userFile, json_encode($users, JSON_PRETTY_PRINT))) {
    
    // 7. Écriture Historique (Log)
    $actionName = $newStatus ? "Débloqué" : "Bloqué";
    addToHistory($historyFile, $adminUser['username'], $actionName, "User @" . $targetUsername);

    echo json_encode(["status" => "updated", "active" => $newStatus]);
} else {
    http_response_code(500);
    echo json_encode(["error" => "Erreur d'écriture DB"]);
}
?>
