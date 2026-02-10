<?php
session_start();
// --- CONFIGURATION ---
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

// 1. Chemin absolu vers la base de données
$file = __DIR__ . '/../db/users.json';
$secret_key = "TON_CODE_SECRET_TRES_LONG"; // Change ceci !

// --- FONCTION JWT INTÉGRÉE (Évite le require jwt.php) ---
function generate_jwt($payload, $secret) {
    $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
    $base64UrlHeader = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
    $base64UrlPayload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode(json_encode($payload)));
    $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, $secret, true);
    $base64UrlSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
    return $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;
}

// 2. Récupération des données
$input = file_get_contents("php://input");
$d = json_decode($input, true);

if (empty($d['username']) || empty($d['password'])) {
    http_response_code(400);
    exit(json_encode(["error" => "Pseudo et mot de passe requis"]));
}

// 3. Lecture de la DB
if (!file_exists($file)) {
    http_response_code(500);
    exit(json_encode(["error" => "Base de données introuvable"]));
}
$users = json_decode(file_get_contents($file), true);
if (!is_array($users)) $users = [];

// 4. VERIFICATION
foreach ($users as $u) {
    // Comparaison du pseudo (insensible à la casse pour plus de souplesse)
    if (strcasecmp($u['username'], $d['username']) === 0) {
        
        // A. Vérification du mot de passe
        if (password_verify($d['password'], $u['password'])) {
            
            // B. Vérification du statut actif
            if (isset($u['active']) && $u['active'] === false) {
                http_response_code(403);
                exit(json_encode(["error" => "Votre compte a été suspendu par l'administrateur"]));
            }

            // C. Succès : Génération du Token
            $token = generate_jwt([
                "id" => (int)$u['id'],
                "username" => $u['username'],
                "role" => $u['role'] ?? 'user',
                "exp" => time() + (60*60) // Expire dans 24h
            ], $secret_key);
            
            $_SESSION['auth']=true;
            
            echo json_encode([
                "status" => "success",
                "token" => $token,
                "role" => $u['role'] ?? 'user'
            ]);
            exit;
        }
    }
}

// 5. Échec par défaut
http_response_code(401);
echo json_encode(["error" => "Identifiants incorrects"]);
?>
