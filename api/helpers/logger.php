<?php
function logAction($adminName, $action, $target) {
    $file = __DIR__ . '/../db/history.json';
    
    // 1. Lire le fichier
    $content = @file_get_contents($file);
    
    // 2. Décoder. Si vide ou invalide, on crée un tableau vide
    $logs = json_decode($content, true);
    if (!is_array($logs)) {
        $logs = [];
    }

    // 3. Préparer la ligne
    $newLog = [
        "date" => date("Y-m-d H:i:s"),
        "admin" => $adminName ?? "Système", // Sécurité si le nom est vide
        "action" => $action,
        "target" => $target
    ];

    // 4. Ajouter au début (Historique inversé)
    array_unshift($logs, $newLog);

    // 5. Garder seulement les 50 derniers
    if (count($logs) > 50) {
        $logs = array_slice($logs, 0, 50);
    }

    // 6. Écrire le fichier
    file_put_contents($file, json_encode($logs, JSON_PRETTY_PRINT));
}
?>
