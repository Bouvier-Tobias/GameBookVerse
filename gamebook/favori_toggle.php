<?php
require __DIR__ . '/config/db.php';
require __DIR__ . '/includes/functions.php';
require __DIR__ . '/includes/games.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check($_POST['csrf'] ?? null)) {
    http_response_code(400);
    exit('Requête invalide.');
}

$slug = trim($_POST['slug'] ?? '');
$game = $slug !== '' ? get_game_by_slug($pdo, $slug) : null;

if (!$game) {
    http_response_code(404);
    exit('Jeu introuvable.');
}

toggle_favorite($pdo, (int) $_SESSION['user_id'], (int) $game['id']);

header('Location: jeu.php?slug=' . urlencode($game['slug']));
exit;
