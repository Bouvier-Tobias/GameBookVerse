<?php
require __DIR__ . '/config/db.php';
require __DIR__ . '/includes/functions.php';
require __DIR__ . '/includes/games.php';
require __DIR__ . '/includes/game_card.php';
require_login();

$favorites = get_favorites($pdo, (int) $_SESSION['user_id']);

$pageTitle   = 'Mes favoris';
$currentPage = 'favoris';
require __DIR__ . '/includes/header.php';
?>

<h1>Mes favoris</h1>

<?php if (!$favorites): ?>
    <p class="muted">Tu n'as pas encore de favoris. <a href="catalogue.php">Parcourir le catalogue</a></p>
<?php else: ?>
    <p class="count"><?= count($favorites) ?> jeu(x) en favori</p>
    <div class="grid">
        <?php foreach ($favorites as $g): ?>
            <?php render_game_card($g); ?>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
