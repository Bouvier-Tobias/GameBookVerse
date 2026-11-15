<?php
require __DIR__ . '/config/db.php';
require __DIR__ . '/includes/functions.php';
require __DIR__ . '/includes/games.php';
require __DIR__ . '/includes/game_card.php';

$pageTitle   = 'Accueil';
$currentPage = 'index';

$total = (int) $pdo->query('SELECT COUNT(*) FROM games')->fetchColumn();

$sections = [
    'popular' => 'Les plus populaires',
    'top'     => 'Les mieux notés',
    'recent'  => 'Sorties récentes',
];

require __DIR__ . '/includes/header.php';
?>

<div class="auth-bg"><span></span><span></span></div>

<section class="hero">
    <h1>Trouve ton prochain jeu</h1>
    <p class="hero-sub"><?= number_format($total, 0, ',', ' ') ?> jeux à explorer : notes, genres, plateformes.</p>

    <form class="search hero-search" method="get" action="catalogue.php">
        <input type="search" name="q" placeholder="Rechercher un jeu..." aria-label="Rechercher un jeu">
        <button type="submit">Rechercher</button>
    </form>
</section>

<?php foreach ($sections as $mode => $label): ?>
    <?php $games = get_games_list($pdo, $mode, 12); ?>
    <?php if ($games): ?>
        <section class="home-section">
            <div class="section-head">
                <h2><?= e($label) ?></h2>
                <a href="catalogue.php<?= $mode === 'popular' ? '' : '?sort=' . ($mode === 'top' ? 'rating' : 'recent') ?>">Tout voir →</a>
            </div>
            <div class="scroller">
                <?php foreach ($games as $g): ?>
                    <?php render_game_card($g); ?>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>
<?php endforeach; ?>

<?php if ($total === 0): ?>
    <p class="muted">La base est vide : lance l'import des jeux (voir le README).</p>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
