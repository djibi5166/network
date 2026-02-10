<?php
// --- CONFIGURATION ---
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

// 1. Chemins Absolus
$blogFile = __DIR__ . '/../db/blogs.json';
$userFile = __DIR__ . '/../db/users.json';
$historyFile = __DIR__ . '/../db/history.json';
$projectRoot = __DIR__ . '/../../'; // Pour supprimer les fichiers uploads/

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

// B. Logger (Historique)
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
    array_unshift($logs, $newLog);
    if (count($logs) > 50) $logs = array_slice($logs, 0, 50);
    file_put_contents($file, json_encode($logs, JSON_PRETTY_PRINT));
}

// --- LOGIQUE PRINCIPALE ---

// 2. Vérification ADMIN
// On doit savoir QUI supprime pour le noter dans les logs et vérifier les droits
$requesterId = getUserIdFromToken();
if (!$requesterId) {
    http_response_code(401);
    exit(json_encode(["error" => "Non connecté"]));
}

// Lecture users pour vérifier le rôle Admin
if (!file_exists($userFile)) exit(json_encode(["error" => "DB User introuvable"]));
$users = json_decode(file_get_contents($userFile), true);

$adminUser = null;
foreach ($users as $u) {
    if ((int)$u['id'] === $requesterId) {
        $adminUser = $u;
        break;
    }
}

if (!$adminUser || ($adminUser['role'] ?? 'user') !== 'admin') {
    http_response_code(403);
    exit(json_encode(["error" => "Réservé aux administrateurs"]));
}

// 3. Récupération ID Blog
$data = json_decode(file_get_contents("php://input"), true);
$id = isset($data['id']) ? (int)$data['id'] : 0;

if ($id <= 0) {
    http_response_code(400);
    exit(json_encode(["error" => "ID invalide"]));
}

// 4. Lecture Blogs
if (!file_exists($blogFile)) exit(json_encode(["error" => "DB Blog introuvable"]));
$blogs = json_decode(file_get_contents($blogFile), true);

$found = false;
$newBlogs = [];
$blogTitle = "Sans titre";

// 5. Suppression
foreach ($blogs as $b) {
    if ((int)$b['id'] === $id) {
        // On a trouvé le blog
        $found = true;
        
        // On garde le titre pour le log (coupé à 30 caractères)
        $blogTitle = isset($b['title']) ? substr($b['title'], 0, 30) . "..." : "Sans titre";

        // SUPPRESSION PHYSIQUE DES FICHIERS
        // On vérifie que le chemin existe avant de tenter unlink
        if (!empty($b['image']) && file_exists($projectRoot . $b['image'])) unlink($projectRoot . $b['image']);
        if (!empty($b['audio']) && file_exists($projectRoot . $b['audio'])) unlink($projectRoot . $b['audio']);
        if (!empty($b['video']) && file_exists($projectRoot . $b['video'])) unlink($projectRoot . $b['video']);

        // On ne l'ajoute pas à $newBlogs -> il est donc supprimé de la liste
        continue;
    }
    $newBlogs[] = $b;
}

if (!$found) {
    http_response_code(404);
    exit(json_encode(["error" => "Blog introuvable"]));
}

// 6. Sauvegarde
if (file_put_contents($blogFile, json_encode($newBlogs, JSON_PRETTY_PRINT))) {
    
    // 7. Log de l'action
    addToHistory($historyFile, $adminUser['username'], "Supprimé", "Blog: " . $blogTitle);

    echo json_encode(["status" => "blog deleted"]);
} else {
    http_response_code(500);
    echo json_encode(["error" => "Erreur d'écriture DB"]);
}
?>
