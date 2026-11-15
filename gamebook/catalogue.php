<?php
require __DIR__ . '/config/db.php';
require __DIR__ . '/includes/functions.php';
require __DIR__ . '/includes/games.php';
require __DIR__ . '/includes/game_card.php';

$pageTitle   = 'Catalogue';
$currentPage = 'catalogue';

$search = trim($_GET['q'] ?? '');
$sort   = trim($_GET['sort'] ?? 'popular');
$page   = (int) ($_GET['page'] ?? 1);

$filters = ['sort' => $sort];
foreach (['genre', 'platform', 'mode', 'perspective', 'theme'] as $key) {
    $filters[$key] = trim($_GET[$key] ?? '');
}

$result       = search_games($pdo, $search, $page, 24, $filters);
$genres       = get_facet($pdo, 'genres', 30, 24);
$platforms    = get_facet($pdo, 'platforms', 100, 25);
$modes        = get_facet($pdo, 'modes', 20, 20);
$perspectives = get_facet($pdo, 'perspectives', 20, 20);
$themes       = get_facet($pdo, 'themes', 30, 30);

$sortLabels = [
    'popular' => 'Popularité',
    'rating'  => 'Mieux notés',
    'recent'  => 'Plus récents',
    'title'   => 'Ordre alphabétique',
];

// Construit une adresse du catalogue en gardant les filtres actuels
function catalogue_url(array $changes = []): string {
    $params = ['q' => trim($_GET['q'] ?? '')];
    foreach (['genre', 'platform', 'mode', 'perspective', 'theme', 'sort'] as $key) {
        $params[$key] = trim($_GET[$key] ?? '');
    }
    $params['page'] = '';
    $params = array_merge($params, $changes);

    if ($params['sort'] === 'popular') {
        $params['sort'] = '';
    }
    if ((int) $params['page'] <= 1) {
        $params['page'] = '';
    }
    $params = array_filter($params, fn($v) => $v !== '');

    return 'catalogue.php' . ($params ? '?' . http_build_query($params) : '');
}

// Liste déroulante de filtre (n'affiche rien s'il n'y a aucune valeur)
function filter_select(string $name, string $placeholder, array $options, string $selected): void {
    if (!$options) {
        return;
    } ?>
    <select name="<?= e($name) ?>" aria-label="<?= e($placeholder) ?>" onchange="this.form.submit()">
        <option value=""><?= e($placeholder) ?></option>
        <?php foreach ($options as $label => $count): ?>
            <option value="<?= e((string) $label) ?>" <?= $selected === (string) $label ? 'selected' : '' ?>><?= e((string) $label) ?></option>
        <?php endforeach; ?>
    </select>
<?php }

require __DIR__ . '/includes/header.php';
?>

<h1>Catalogue</h1>

<form class="filters" method="get" action="catalogue.php">
    <div class="search">
        <input type="search" name="q" value="<?= e($search) ?>" placeholder="Rechercher un jeu..." aria-label="Rechercher un jeu">
        <button type="submit">Rechercher</button>
    </div>

    <?php if ($filters['genre'] !== ''): ?>
        <input type="hidden" name="genre" value="<?= e($filters['genre']) ?>">
    <?php endif; ?>

    <?php filter_select('platform', 'Toutes les plateformes', $platforms, $filters['platform']); ?>
    <?php filter_select('mode', 'Tous les modes de jeu', $modes, $filters['mode']); ?>
    <?php filter_select('perspective', 'Toutes les perspectives', $perspectives, $filters['perspective']); ?>
    <?php filter_select('theme', 'Tous les thèmes', $themes, $filters['theme']); ?>

    <select name="sort" aria-label="Tri" onchange="this.form.submit()">
        <?php foreach ($sortLabels as $value => $label): ?>
            <option value="<?= e($value) ?>" <?= $sort === $value ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
    </select>
</form>

<nav class="chips" aria-label="Genres">
    <a class="chip <?= $filters['genre'] === '' ? 'active' : '' ?>" href="<?= e(catalogue_url(['genre' => ''])) ?>">Tous</a>
    <?php foreach ($genres as $name => $count): ?>
        <a class="chip <?= $filters['genre'] === (string) $name ? 'active' : '' ?>" href="<?= e(catalogue_url(['genre' => (string) $name])) ?>"><?= e((string) $name) ?></a>
    <?php endforeach; ?>
</nav>

<p class="count"><?= number_format($result['total'], 0, ',', ' ') ?> jeu(x) trouvé(s)</p>

<?php if (!$result['items']): ?>
    <p>Aucun jeu ne correspond à ta recherche. Essaie avec moins de filtres.</p>
<?php else: ?>
    <div class="grid">
        <?php foreach ($result['items'] as $g): ?>
            <?php render_game_card($g); ?>
        <?php endforeach; ?>
    </div>

    <?php if ($result['pages'] > 1): ?>
        <nav class="pagination">
            <?php if ($result['page'] > 1): ?>
                <a href="<?= e(catalogue_url(['page' => $result['page'] - 1])) ?>">← Précédent</a>
            <?php endif; ?>
            <span>Page <?= $result['page'] ?> / <?= $result['pages'] ?></span>
            <?php if ($result['page'] < $result['pages']): ?>
                <a href="<?= e(catalogue_url(['page' => $result['page'] + 1])) ?>">Suivant →</a>
            <?php endif; ?>
        </nav>
    <?php endif; ?>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
