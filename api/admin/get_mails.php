<?php
// 1. Autorisations et Headers
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");

// 2. Sécurité : Vérification du Token (Optionnel mais recommandé pour un Admin)
// Si tu as un middleware de vérification, inclus-le ici.

// 3. Définition du chemin vers le fichier de logs des mails
// Assure-toi que ce fichier est bien créé par ton script sendeMail.php
$file = __DIR__ . '/../db/mail_logs.json';

// 4. Lecture et envoi des données
if (file_exists($file)) {
    $content = file_get_contents($file);
    
    // On décode et ré-encode pour être sûr que le JSON est propre
    $data = json_decode($content, true);
    
    if ($data === null) {
        echo json_encode([]); // Si le fichier est vide ou corrompu
    } else {
        echo json_encode($data);
    }
} else {
    // Si le fichier n'existe pas encore, on renvoie un tableau vide
    echo json_encode([]);
}
