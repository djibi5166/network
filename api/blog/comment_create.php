<?php
// --- CONFIGURATION ---
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

// 1. Chemins Absolus
$blogFile = __DIR__ . '/../db/blogs.json';
$userFile = __DIR__ . '/../db/users.json';

// --- FONCTIONS INTERNES ---

// A. Auth : Récupérer ID depuis le Token
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

// B. Récupérer le pseudo de l'utilisateur (depuis la DB Users)
function getUsernameById($id, $path) {
    if (!file_exists($path)) return "Utilisateur Inconnu";
    $users = json_decode(file_get_contents($path), true);
    if (!is_array($users)) return "Utilisateur Inconnu";
    
    foreach ($users as $u) {
        if ((int)$u['id'] === $id) return $u['username'];
    }
    return "Utilisateur Inconnu";
}

// C. Générateur d'ID sûr pour les commentaires
function generateCommentId($comments) {
    $max = 0;
    foreach ($comments as $c) {
        if ($c['id'] > $max) $max = $c['id'];
    }
    return $max + 1;
}

// --- LOGIQUE PRINCIPALE ---

// 2. Vérification Auth
$userId = getUserIdFromToken();
if (!$userId) {
    http_response_code(401);
    exit(json_encode(["error" => "Vous devez être connecté pour commenter."]));
}

// 3. Récupération des données
$data = json_decode(file_get_contents("php://input"), true);
$blogId = (int)($data['blog_id'] ?? 0);
$content = trim($data['content'] ?? "");

if ($blogId <= 0 || $content === "") {
    http_response_code(400);
    exit(json_encode(["error" => "Contenu ou ID manquant"]));
}

// 4. Lecture DB Blogs
if (!file_exists($blogFile)) {
    http_response_code(500);
    exit(json_encode(["error" => "DB Blogs introuvable"]));
}
$blogs = json_decode(file_get_contents($blogFile), true);
if (!is_array($blogs)) $blogs = [];

$found = false;

// 5. Ajout du commentaire
foreach ($blogs as &$b) {
    if ((int)$b['id'] !== $blogId) continue;

    // Initialisation si pas de commentaires
    if (!isset($b['comments']) || !is_array($b['comments'])) {
        $b['comments'] = [];
    }

    // On récupère le vrai pseudo actuel
    $username = getUsernameById($userId, $userFile);

    // Création du commentaire
    $newComment = [
        "id" => generateCommentId($b['comments']), // ID sécurisé
        "user_id" => $userId,
        "username" => $username,
        "content" => htmlspecialchars($content, ENT_QUOTES), // Sécurité XSS
        "date" => date("Y-m-d H:i:s")
    ];

    $b['comments'][] = $newComment;
    $found = true;
    break;
}

// 6. Sauvegarde
if ($found) {
    if (file_put_contents($blogFile, json_encode($blogs, JSON_PRETTY_PRINT))) {
        echo json_encode(["status" => "comment_added", "comment" => $newComment]);
    } else {
        http_response_code(500);
        echo json_encode(["error" => "Erreur écriture DB"]);
    }
} else {
    http_response_code(404);
    echo json_encode(["error" => "Article introuvable"]);
}
?>
