<?php
// --- CONFIGURATION ---
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Authorization, Content-Type");
header('Content-Type: application/json');
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// --- DB FILE SETUP ---
 $file = __DIR__ . '/../db/users.json';

if (!file_exists($file)) {
    // Create file if it doesn't exist
    file_put_contents($file, json_encode([]));
}

// --- FONCTIONS INTERNES ---

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

function generateId($users) {
    $max = 0;
    foreach ($users as $u) {
        if (isset($u['id']) && (int)$u['id'] > $max) $max = (int)$u['id'];
    }
    return $max + 1;
}

// --- LOGIQUE PRINCIPALE ---

 $users = json_decode(file_get_contents($file), true);
if (!is_array($users)) $users = [];

// 1. VÉRIFICATION ADMIN (Sécurité)
 $requesterId = getUserIdFromToken();
 $isAdmin = false;
foreach ($users as $u) {
    if ((int)$u['id'] === $requesterId && ($u['role'] ?? '') === 'admin') {
        $isAdmin = true;
        break;
    }
}

if (!$isAdmin) {
    http_response_code(403);
    exit(json_encode(["error" => "Accès refusé"]));
}

// 2. RÉCUPÉRATION ET VALIDATION
 $data = json_decode(file_get_contents("php://input"), true);

// NOTE: If you are testing via Postman/Form, keep this. For your JS, this is likely ignored.
// Removed the bad code block here.

if (empty($data['username']) || empty($data['password']) || empty($data['email'])) {
    http_response_code(400);
    exit(json_encode(["error" => "Pseudo, mot de passe et email requis"]));
}

 $newUsername = trim($data['username']);
 $newEmail = trim($data['email']);
 $newRole = $data['role'] ?? "user";

// 3. VÉRIFICATION DOUBLONS (Nom ET Email)
foreach ($users as $u) {
    if (strcasecmp($u['username'], $newUsername) === 0) {
        http_response_code(409);
        exit(json_encode(["error" => "Ce pseudo est déjà utilisé"]));
    }
    if (isset($u['email']) && strcasecmp($u['email'], $newEmail) === 0) {
        http_response_code(409);
        exit(json_encode(["error" => "Cet email est déjà utilisé"]));
    }
}

// 4. CRÉATION
 $newUser = [
    "id" => generateId($users),
    "username" => $newUsername,
    "email" => $newEmail,
    "password" => password_hash($data['password'], PASSWORD_DEFAULT),
    "role" => $newRole,
    "active" => true,
    "created_at" => date("Y-m-d H:i:s")
];

 $users[] = $newUser;

// 5. SAUVEGARDE (Moved INSIDE the logic so it only happens on success)
if (file_put_contents($file, json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
    http_response_code(200); // Explicit 200 OK
    echo json_encode(["status" => "user added", "user" => ["username" => $newUsername]]);
} else {
    http_response_code(500);
    echo json_encode(["error" => "Erreur d'écriture DB"]);
}
?>