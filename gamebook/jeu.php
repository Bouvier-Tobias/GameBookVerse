<?php
require __DIR__ . '/config/db.php';
require __DIR__ . '/includes/functions.php';
require __DIR__ . '/includes/games.php';
require __DIR__ . '/includes/game_card.php';

$slug = trim($_GET['slug'] ?? '');
$game = $slug !== '' ? get_game_by_slug($pdo, $slug) : null;

if (!$game) {
    http_response_code(404);
}

$pageTitle   = $game ? $game['title'] : 'Jeu introuvable';
$currentPage = 'catalogue';
$scripts     = ['assets/js/gallery.js', 'assets/js/video.js'];

require __DIR__ . '/includes/header.php';
?>

<?php if (!$game): ?>
    <h1>Jeu introuvable</h1>
    <p><a href="catalogue.php">← Retour au catalogue</a></p>
<?php else: ?>
    <?php
    $details      = get_game_details($game);
    $cover        = cover_url($game['cover_url'], '720p');
    $images       = get_game_images($game);
    $backdrop     = $images ? image_url($images[0], 'screenshot_huge') : $cover;
    $genres       = split_list($game['genres']);
    $modes        = split_list($game['modes'] ?? '');
    $perspectives = split_list($game['perspectives'] ?? '');
    $themes       = split_list($game['themes'] ?? '');
    $platforms    = split_list($game['platforms']);
    $links        = prepare_links($details['links'] ?? []);
    $videos       = $details['videos'] ?? [];
    $critic       = $details['critic'] ?? null;
    $age          = (string) ($details['age'] ?? '');
    $storyline    = trim((string) ($details['storyline'] ?? ''));
    $engines      = $details['engines'] ?? [];
    $franchises   = $details['franchises'] ?? [];
    $publisher    = trim((string) ($game['publisher'] ?? ''));
    $franchise    = trim((string) ($game['franchise'] ?? ''));
    $similar      = get_games_by_igdb_ids($pdo, $details['similar'] ?? [], 12);
    $series       = $franchise !== '' ? get_games_same_franchise($pdo, $franchise, (int) $game['id'], 12) : [];
    ?>
    <a class="back" href="catalogue.php">← Retour au catalogue</a>

    <section class="game-hero" <?= $backdrop ? 'style="--hero-image: url(\'' . e($backdrop) . '\')"' : '' ?>>
        <div class="game-hero-inner">
            <div class="cover detail-cover">
                <?php if ($cover): ?>
                    <img src="<?= e($cover) ?>" alt="Jaquette de <?= e($game['title']) ?>">
                <?php else: ?>
                    <span><?= e(mb_substr($game['title'], 0, 1)) ?></span>
                <?php endif; ?>
            </div>

            <div class="game-head">
                <h1 class="game-title"><?= e($game['title']) ?></h1>

                <?php if ($genres || $modes || $perspectives || $themes): ?>
                    <div class="tags">
                        <?php foreach ($genres as $genre): ?>
                            <a class="tag" href="catalogue.php?genre=<?= e(urlencode($genre)) ?>"><?= e($genre) ?></a>
                        <?php endforeach; ?>
                        <?php foreach ($modes as $mode): ?>
                            <a class="tag alt" href="catalogue.php?mode=<?= e(urlencode($mode)) ?>"><?= icon('users') ?><?= e($mode) ?></a>
                        <?php endforeach; ?>
                        <?php foreach ($perspectives as $perspective): ?>
                            <a class="tag alt" href="catalogue.php?perspective=<?= e(urlencode($perspective)) ?>"><?= icon('eye') ?><?= e($perspective) ?></a>
                        <?php endforeach; ?>
                        <?php foreach ($themes as $theme): ?>
                            <a class="tag alt" href="catalogue.php?theme=<?= e(urlencode($theme)) ?>"><?= icon('tag') ?><?= e($theme) ?></a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div class="stats">
                    <div class="stat">
                        <span class="stat-label"><?= icon('star') ?> Note</span>
                        <?php if ($game['rating'] !== null): ?>
                            <span class="stat-value"><?= e($game['rating']) ?><small>/10</small></span>
                            <span class="stat-sub"><?= number_format((int) $game['reviews'], 0, ',', ' ') ?> avis</span>
                        <?php else: ?>
                            <span class="stat-value">—</span>
                        <?php endif; ?>
                    </div>
                    <?php if (is_array($critic) && isset($critic['score'])): ?>
                        <div class="stat">
                            <span class="stat-label"><?= icon('award') ?> Critiques</span>
                            <span class="stat-value"><?= e((string) $critic['score']) ?><small>/10</small></span>
                            <span class="stat-sub"><?= (int) ($critic['count'] ?? 0) ?> critique(s)</span>
                        </div>
                    <?php endif; ?>
                    <div class="stat">
                        <span class="stat-label"><?= icon('calendar') ?> Sortie</span>
                        <span class="stat-value"><?= e(format_date_fr($game['release_date'])) ?></span>
                    </div>
                    <div class="stat">
                        <span class="stat-label"><?= icon('code') ?> Développeur</span>
                        <span class="stat-value"><?= e($game['developer'] ?: 'Inconnu') ?></span>
                    </div>
                    <?php if ($publisher !== '' && $publisher !== (string) $game['developer']): ?>
                        <div class="stat">
                            <span class="stat-label"><?= icon('briefcase') ?> Éditeur</span>
                            <span class="stat-value"><?= e($publisher) ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if ($age !== ''): ?>
                        <div class="stat">
                            <span class="stat-label"><?= icon('shield') ?> Classification</span>
                            <span class="stat-value"><?= e($age) ?></span>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if (is_logged_in()): ?>
                    <?php $isFav = is_favorite($pdo, (int) $_SESSION['user_id'], (int) $game['id']); ?>
                    <form method="post" action="favori_toggle.php" class="fav-form">
                        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="slug" value="<?= e($game['slug']) ?>">
                        <button type="submit" class="btn-fav <?= $isFav ? 'on' : '' ?>">
                            <?= icon('heart') ?>
                            <?= $isFav ? 'Retirer des favoris' : 'Ajouter aux favoris' ?>
                        </button>
                    </form>
                <?php else: ?>
                    <a class="btn-fav" href="connexion.php"><?= icon('heart') ?> Connecte-toi pour ajouter aux favoris</a>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <?php if (trim((string) $game['description']) !== ''): ?>
        <section class="game-section">
            <h2>À propos</h2>
            <p class="desc"><?= nl2br(e($game['description'])) ?></p>
        </section>
    <?php endif; ?>

    <?php if ($storyline !== ''): ?>
        <section class="game-section">
            <h2>Histoire</h2>
            <p class="desc"><?= nl2br(e($storyline)) ?></p>
        </section>
    <?php endif; ?>

    <?php if ($links['stores'] || $links['others']): ?>
        <section class="game-section">
            <?php if ($links['stores']): ?>
                <h2>Où jouer</h2>
                <div class="stores">
                    <?php foreach ($links['stores'] as $store): ?>
                        <a class="store" style="--brand: <?= e($store['color']) ?>" href="<?= e($store['url']) ?>"
                           target="_blank" rel="noopener noreferrer nofollow">
                            <?= brand_icon($store['key']) ?>
                            <span><?= e($store['label']) ?></span>
                            <?= icon('external') ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($links['others']): ?>
                <h3>Liens utiles</h3>
                <div class="links-row">
                    <?php foreach ($links['others'] as $link): ?>
                        <a class="link-chip" href="<?= e($link['url']) ?>" target="_blank" rel="noopener noreferrer nofollow">
                            <?= brand_icon($link['key']) ?>
                            <span><?= e($link['label']) ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <?php if ($videos): ?>
        <section class="game-section wide-section">
            <h2>Vidéos</h2>
            <div class="videos">
                <?php foreach ($videos as $video): ?>
                    <?php
                    $videoId = (string) ($video['id'] ?? '');
                    if (!preg_match('/^[A-Za-z0-9_-]{6,20}$/', $videoId)) {
                        continue;
                    }
                    $videoName = (string) ($video['name'] ?? '');
                    ?>
                    <div class="video" data-video="<?= e($videoId) ?>" data-title="<?= e($videoName) ?>">
                        <img src="https://img.youtube.com/vi/<?= e($videoId) ?>/hqdefault.jpg" alt="" loading="lazy">
                        <button type="button" class="video-play" aria-label="Lire la vidéo">
                            <span class="circle">
                                <svg viewBox="0 0 24 24" width="30" height="30" fill="#fff" aria-hidden="true"><polygon points="8 5 19 12 8 19"/></svg>
                            </span>
                            <?php if ($videoName !== ''): ?>
                                <span class="video-title"><?= e($videoName) ?></span>
                            <?php endif; ?>
                        </button>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($images): ?>
        <section class="gallery" data-gallery>
            <h2>Images du jeu</h2>

            <div class="gallery-main">
                <img src="<?= e(image_url($images[0], 'screenshot_huge')) ?>"
                     alt="Image du jeu <?= e($game['title']) ?>" data-main>
                <?php if (count($images) > 1): ?>
                    <button type="button" class="gallery-nav prev" aria-label="Image précédente" data-prev>&#8249;</button>
                    <button type="button" class="gallery-nav next" aria-label="Image suivante" data-next>&#8250;</button>
                <?php endif; ?>
            </div>

            <?php if (count($images) > 1): ?>
                <div class="gallery-thumbs" data-thumbs>
                    <?php foreach ($images as $i => $imageId): ?>
                        <button type="button" class="thumb <?= $i === 0 ? 'active' : '' ?>"
                                data-full="<?= e(image_url($imageId, 'screenshot_huge')) ?>"
                                aria-label="Voir l'image <?= $i + 1 ?>">
                            <img src="<?= e(image_url($imageId, 'screenshot_med')) ?>" alt="" loading="lazy">
                        </button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <?php if ($platforms): ?>
        <section class="game-section">
            <h2>Plateformes</h2>
            <div class="platforms">
                <?php foreach ($platforms as $platform): ?>
                    <a class="platform" href="catalogue.php?platform=<?= e(urlencode($platform)) ?>">
                        <?= icon(platform_icon($platform)) ?>
                        <span><?= e($platform) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($franchises || $engines): ?>
        <section class="game-section">
            <h2>Informations</h2>
            <dl class="infos">
                <?php if ($franchises): ?>
                    <dt><?= icon('layers') ?> Série</dt>
                    <dd><?= e(implode(', ', $franchises)) ?></dd>
                <?php endif; ?>
                <?php if ($engines): ?>
                    <dt><?= icon('cpu') ?> Moteur de jeu</dt>
                    <dd><?= e(implode(', ', $engines)) ?></dd>
                <?php endif; ?>
            </dl>
        </section>
    <?php endif; ?>

    <?php if ($series): ?>
        <section class="home-section rail">
            <div class="section-head">
                <h2>Dans la même série : <?= e($franchise) ?></h2>
            </div>
            <div class="scroller">
                <?php foreach ($series as $g): ?>
                    <?php render_game_card($g); ?>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($similar): ?>
        <section class="home-section rail">
            <div class="section-head">
                <h2>Si tu aimes ce jeu</h2>
            </div>
            <div class="scroller">
                <?php foreach ($similar as $g): ?>
                    <?php render_game_card($g); ?>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
