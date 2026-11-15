<?php
// API locale : un seul jeu. Exemple : /gamebook/api/game.php?slug=hades
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../includes/games.php';

header('Content-Type: application/json; charset=utf-8');

$slug = trim($_GET['slug'] ?? '');
$game = $slug !== '' ? get_game_by_slug($pdo, $slug) : null;

if (!$game) {
    http_response_code(404);
    echo json_encode(['error' => 'Jeu introuvable']);
    exit;
}

echo json_encode($game, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
