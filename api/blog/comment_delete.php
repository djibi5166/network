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

// B. Récupérer le Rôle de l'utilisateur (pour savoir s'il est Admin)
function getUserRole($id, $path) {
    if (!file_exists($path)) return "user";
    $users = json_decode(file_get_contents($path), true);
    if (!is_array($users)) return "user";
    
    foreach ($users as $u) {
        if ((int)$u['id'] === $id) return $u['role'] ?? "user";
    }
    return "user";
}

// --- LOGIQUE PRINCIPALE ---

// 2. Vérification Auth
$userId = getUserIdFromToken();
if (!$userId) {
    http_response_code(401);
    exit(json_encode(["error" => "Non autorisé"]));
}

// 3. Récupération des Rôles
$userRole = getUserRole($userId, $userFile);

// 4. Données Entrantes
$data = json_decode(file_get_contents("php://input"), true);
$blogId    = (int)($data['blog_id'] ?? 0);
$commentId = (int)($data['comment_id'] ?? 0);

if ($blogId <= 0 || $commentId <= 0) {
    http_response_code(400);
    exit(json_encode(["error" => "IDs manquants"]));
}

// 5. Lecture DB Blogs
if (!file_exists($blogFile)) {
    http_response_code(500);
    exit(json_encode(["error" => "DB introuvable"]));
}
$blogs = json_decode(file_get_contents($blogFile), true);

$foundBlog = false;
$deleted = false;

// 6. Parcours et Suppression
foreach ($blogs as &$b) {
    // On cherche le bon article
    if ((int)$b['id'] !== $blogId) continue;
    
    $foundBlog = true;

    if (!isset($b['comments']) || !is_array($b['comments'])) {
        break; // Pas de commentaires, on sort
    }

    // On cherche le commentaire dans l'article
    foreach ($b['comments'] as $index => $c) {
        if ((int)$c['id'] !== $commentId) continue;

        // VÉRIFICATION DES DROITS :
        // Soit c'est MON commentaire, soit je suis ADMIN
        if ((int)$c['user_id'] !== $userId && $userRole !== "admin") {
            http_response_code(403); // Interdit
            exit(json_encode(["error" => "Vous ne pouvez supprimer que vos commentaires"]));
        }

        // Suppression propre (array_splice réindexe le tableau pour éviter les trous)
        array_splice($b['comments'], $index, 1);
        $deleted = true;
        break; // Commentaire trouvé et supprimé, on sort de la boucle commentaires
    }
    
    if ($deleted) break; // On sort de la boucle blogs
}

// 7. Sauvegarde
if ($deleted) {
    if (file_put_contents($blogFile, json_encode($blogs, JSON_PRETTY_PRINT))) {
        echo json_encode(["status" => "comment_deleted"]);
    } else {
        http_response_code(500);
        echo json_encode(["error" => "Erreur écriture DB"]);
    }
} else {
    http_response_code(404);
    echo json_encode(["error" => "Commentaire introuvable"]);
}
?>
