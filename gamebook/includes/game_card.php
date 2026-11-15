<?php
// Carte d'un jeu, utilisée par l'accueil, le catalogue et les favoris.
// Nécessite includes/functions.php et includes/games.php.
function render_game_card(array $g): void {
    $image = cover_url($g['cover_url'], '720p'); ?>
    <a class="card" href="jeu.php?slug=<?= e(urlencode($g['slug'])) ?>">
        <div class="cover">
            <?php if ($image): ?>
                <img src="<?= e($image) ?>" alt="" loading="lazy">
            <?php else: ?>
                <span><?= e(mb_substr($g['title'], 0, 1)) ?></span>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <h2><?= e($g['title']) ?></h2>
            <p class="meta"><?= e($g['genres']) ?></p>
            <p class="meta"><?= $g['release_date'] ? e(substr($g['release_date'], 0, 4)) : '—' ?></p>
            <?php if ($g['rating'] !== null): ?>
                <p class="rating">★ <?= e($g['rating']) ?></p>
            <?php endif; ?>
        </div>
    </a>
<?php }
