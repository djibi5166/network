<?php
// --- CONFIGURATION ---
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Authorization, Content-Type");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// 1. Définition des chemins
 $dbFile = __DIR__ . '/../db/blogs.json';
// Chemin absolu vers le dossier uploads (Adapter si votre structure est différente)
 $baseDir = __DIR__ . '/../..'; 
 $uploadDir = $baseDir . '/uploads/'; 

// Création des dossiers s'ils n'existent pas
 $subDirs = ['images', 'audio', 'videos'];
foreach ($subDirs as $dir) {
    if (!is_dir($uploadDir . $dir)) {
        mkdir($uploadDir . $dir, 0777, true);
    }
}

// 2. FONCTIONS INTERNES

// A. Récupérer ID utilisateur depuis Token
function getUserIdFromToken() {
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? '';
    if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        $token = $matches[1];
        $parts = explode('.', $token);
        if (count($parts) === 3) {
            $payload = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $parts[1])), true);
            return $payload['id'] ?? null;
        }
    }
    return null;
}

// B. Upload Simplifié
function safeUpload($fileInput, $targetFolder, $allowedTypes, $maxSize) {
    // Vérifier si le fichier existe et n'a pas d'erreur
    if (!isset($fileInput) || $fileInput['error'] !== UPLOAD_ERR_OK) {
        return null;
    }
    
    // Vérification Type MIME
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($fileInput['tmp_name']);
    
    if (!in_array($mime, $allowedTypes)) {
        // Pour le debug, vous pouvez décommenter la ligne suivante :
        // error_log("MIME Type non autorisé: " . $mime);
        return null;
    }
    
    // Vérification Taille
    if ($fileInput['size'] > $maxSize) {
        return null;
    }

    // Nettoyage nom de fichier
    $ext = pathinfo($fileInput['name'], PATHINFO_EXTENSION);
    $filename = uniqid() . '.' . $ext;
    $targetPath = $targetFolder . $filename;

    // Déplacement
    if (move_uploaded_file($fileInput['tmp_name'], $targetPath)) {
        // On retourne le chemin RELATIF pour le frontend (ex: uploads/images/xyz.jpg)
        return "uploads/" . basename($targetFolder) . "/" . $filename;
    }
    return null;
}

// --- LOGIQUE PRINCIPALE ---

// 3. Vérification Auth
 $userId = getUserIdFromToken();
if (!$userId) {
    http_response_code(401);
    exit(json_encode(["error" => "Non autorisé"]));
}

// 4. Lecture DB
 $blogs = [];
if (file_exists($dbFile)) {
    $jsonContent = file_get_contents($dbFile);
    $blogs = json_decode($jsonContent, true);
    if (!is_array($blogs)) $blogs = [];
}

// 5. Gestion des Uploads
 $imagePath = safeUpload(
    $_FILES['image'] ?? null, 
    $uploadDir . 'images/', 
    ["image/jpeg", "image/png", "image/webp", "image/gif"], 
    5 * 1024 * 1024
);

 $audioPath = safeUpload(
    $_FILES['audio'] ?? null, 
    $uploadDir . 'audio/', 
    ["audio/mpeg", "audio/mp3", "audio/wav", "audio/ogg"], 
    10 * 1024 * 1024
);

 $videoPath = safeUpload(
    $_FILES['video'] ?? null, 
    $uploadDir . 'videos/', 
    ["video/mp4", "video/webm", "video/quicktime"], 
    50 * 1024 * 1024
);

// 6. Récupération des données textuelles (Title, Description)
// IMPORTANT: Lorsqu'on utilise FormData, les données sont dans $_POST, pas dans php://input
 $title = trim($_POST['title'] ?? "Sans titre");
 $description = trim($_POST['description'] ?? "Sans description !");

// 7. Création de l'article
 $newBlog = [
    "id" => isset($blogs[0]['id']) ? max(array_column($blogs, 'id')) + 1 : 1,
    "user_id" => $userId,
    "title" => $title,
    "description" => $description,
    "image" => $imagePath,    // Peut être null
    "audio" => $audioPath,    // Peut être null
    "video" => $videoPath,    // Peut être null
    "date" => date("Y-m-d H:i:s"),
    "likes" => 0,
    "liked_by" => []
];

// 8. Sauvegarde
 $blogs[] = $newBlog;

if (file_put_contents($dbFile, json_encode($blogs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
    echo json_encode(["status" => "created", "blog" => $newBlog]);
} else {
    http_response_code(500);
    echo json_encode(["error" => "Erreur d'écriture DB"]);
}
