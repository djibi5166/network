<?php
// --- CONFIGURATION ---
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// 1. Définition du chemin
 $file = __DIR__ . '/../db/users.json';

// 2. Récupération des données
 $input = file_get_contents("php://input");
 $data = json_decode($input, true);

if (empty($data['token'])) {
    http_response_code(400);
    exit(json_encode(["error" => "Token manquant"]));
}

 $token = trim($data['token']);

// 3. Lecture de la base
if (!file_exists($file)) {
    http_response_code(500);
    exit(json_encode(["error" => "Base de données introuvable"]));
}

 $users = json_decode(file_get_contents($file), true);
if (!is_array($users)) {
    http_response_code(500);
    exit(json_encode(["error" => "Erreur format DB"]));
}

// 4. Recherche et Vérification
 $found = false;
 $userId = null;

foreach ($users as $user) {
    // Vérifier si le token correspond
    if (isset($user['reset_token']) && $user['reset_token'] === $token) {
        
        // Vérifier l'expiration
        if (isset($user['reset_exp']) && time() > $user['reset_exp']) {
            http_response_code(400);
            exit(json_encode(["error" => "Le code a expiré. Veuillez recommencer."]));
        }
        
        // TROUVÉ ET VALIDE
        $found = true;
        $userId = $user['id'];
        break; // On arrête la boucle
    } 
}

// 5. Réponse finale
if ($found) {
    // On renvoie l'ID utilisateur pour que le frontend puisse le réutiliser
    echo json_encode([
        'status' => 'valide', 
        'message' => 'OTP valide. Vous pouvez maintenant changer le mot de passe.',
        'user_id' => $userId
    ]);
} else {
    // Si on sort de la boucle sans trouver le token
    http_response_code(401);
    echo json_encode(["error" => "Otp invalide voyez ressayer"]);
}
