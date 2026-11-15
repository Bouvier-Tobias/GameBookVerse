<?php
// API locale : liste de jeux.
// Exemple : /gamebook/api/games.php?search=zelda&genre=Adventure&mode=Co-operative&sort=rating&page=1&per_page=24
// Filtres possibles : genre, platform, mode, perspective, theme, sort (popular, rating, recent, title)
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../includes/games.php';

header('Content-Type: application/json; charset=utf-8');

$filters = ['sort' => trim($_GET['sort'] ?? 'popular')];
foreach (['genre', 'platform', 'mode', 'perspective', 'theme'] as $key) {
    $filters[$key] = trim($_GET[$key] ?? '');
}

$result = search_games(
    $pdo,
    trim($_GET['search'] ?? ''),
    (int) ($_GET['page'] ?? 1),
    (int) ($_GET['per_page'] ?? 24),
    $filters
);

echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
