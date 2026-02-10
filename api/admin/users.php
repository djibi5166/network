<?php
// --- CONFIGURATION ---
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");

// 1. Chemin Absolu
$file = __DIR__ . '/../db/users.json';

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

// --- LOGIQUE PRINCIPALE ---

// 2. Lecture de la base de données
if (!file_exists($file)) {
    // Pas de base = liste vide (pas d'erreur)
    echo json_encode([]);
    exit;
}

$content = file_get_contents($file);
$users = json_decode($content, true);
if (!is_array($users)) $users = [];

// 3. SÉCURITÉ : VÉRIFICATION ADMIN
// On cherche qui fait la demande
$requesterId = getUserIdFromToken();
$isAdmin = false;

if ($requesterId) {
    foreach ($users as $u) {
        if ((int)$u['id'] === $requesterId) {
            if (isset($u['role']) && $u['role'] === 'admin') {
                $isAdmin = true;
            }
            break;
        }
    }
}

if (!$isAdmin) {
    http_response_code(403);
    exit(json_encode(["error" => "Accès refusé : Réservé aux administrateurs"]));
}

// 4. Nettoyage et Préparation des données
$cleanUsers = array_map(function($u) {
    return [
        "id" => (int)$u['id'],
        "username" => $u['username'] ?? "Inconnu",
        "role" => $u['role'] ?? "user",
        // Si 'active' n'existe pas, on considère que c'est true
        "active" => isset($u['active']) ? (bool)$u['active'] : true,
        "created_at" => $u['created_at'] ?? ""
        // SECURITY : On ne renvoie JAMAIS le password
    ];
}, $users);

// 5. Envoi
echo json_encode($cleanUsers, JSON_PRETTY_PRINT);
?>
