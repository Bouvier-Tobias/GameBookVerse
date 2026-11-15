<?php
// Accès aux jeux, aux favoris, aux filtres et aux détails.
// Important : ce fichier ne doit contenir aucune balise de fermeture PHP.

// Adresse d'une image de jeu dans la taille voulue (IGDB : cover_big, 720p, 1080p...)
function cover_url(?string $url, string $size = '720p'): ?string {
    if (!$url) {
        return null;
    }
    return str_replace('/t_cover_big/', '/t_' . $size . '/', $url);
}

// Recherche avec filtres, tri et pagination.
// $filters accepte : genre, platform, mode, perspective, theme, sort (popular, rating, recent, title)
function search_games(PDO $pdo, string $search = '', int $page = 1, int $perPage = 24, array $filters = []): array {
    $page    = max(1, $page);
    $perPage = min(100, max(1, $perPage));
    $offset  = ($page - 1) * $perPage;

    $where  = [];
    $params = [];

    if ($search !== '') {
        $where[] = 'title LIKE :search';
        $params[':search'] = '%' . addcslashes($search, '%_\\') . '%';
    }

    // Ces colonnes contiennent des listes "A, B, C" : on cherche l'élément exact
    // en entourant la liste de ", ". Les noms de colonnes viennent de cette liste fixe.
    $facets = [
        'genre'       => 'genres',
        'platform'    => 'platforms',
        'mode'        => 'modes',
        'perspective' => 'perspectives',
        'theme'       => 'themes',
    ];
    foreach ($facets as $key => $column) {
        $value = trim((string) ($filters[$key] ?? ''));
        if ($value !== '') {
            $where[] = "CONCAT(', ', $column, ', ') LIKE :$key";
            $params[":$key"] = '%, ' . addcslashes($value, '%_\\') . ', %';
        }
    }

    // Tri : [condition supplémentaire, ORDER BY]
    $sorts = [
        'popular' => ['', 'reviews DESC, title ASC'],
        'rating'  => ['reviews >= 50', 'rating DESC, reviews DESC'],
        'recent'  => ['release_date IS NOT NULL AND release_date <= CURDATE()', 'release_date DESC, reviews DESC'],
        'title'   => ['', 'title ASC'],
    ];
    $sort = (string) ($filters['sort'] ?? 'popular');
    if (!isset($sorts[$sort])) {
        $sort = 'popular';
    }
    [$sortWhere, $order] = $sorts[$sort];
    if ($sortWhere !== '') {
        $where[] = $sortWhere;
    }

    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM games $whereSql");
    $stmt->execute($params);
    $total = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare(
        "SELECT id, title, slug, release_date, platforms, genres, developer, rating, cover_url
         FROM games $whereSql
         ORDER BY $order
         LIMIT :limit OFFSET :offset"
    );
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    return [
        'items'    => $stmt->fetchAll(),
        'total'    => $total,
        'page'     => $page,
        'per_page' => $perPage,
        'pages'    => max(1, (int) ceil($total / $perPage)),
    ];
}

function get_game_by_slug(PDO $pdo, string $slug): ?array {
    $stmt = $pdo->prepare('SELECT * FROM games WHERE slug = ? LIMIT 1');
    $stmt->execute([$slug]);
    return $stmt->fetch() ?: null;
}

// Rangées de la page d'accueil : 'popular', 'top' ou 'recent'
function get_games_list(PDO $pdo, string $mode = 'popular', int $limit = 12): array {
    $modes = [
        'popular' => ['WHERE reviews > 0', 'reviews DESC'],
        'top'     => ['WHERE reviews >= 100', 'rating DESC, reviews DESC'],
        'recent'  => ['WHERE release_date <= CURDATE() AND reviews >= 10', 'release_date DESC'],
    ];
    [$where, $order] = $modes[$mode] ?? $modes['popular'];
    $limit = min(50, max(1, $limit));

    $stmt = $pdo->prepare(
        "SELECT id, title, slug, release_date, platforms, genres, developer, rating, cover_url
         FROM games $where ORDER BY $order LIMIT :limit"
    );
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

// Valeurs les plus fréquentes d'une colonne "A, B, C" : renvoie [valeur => nombre de jeux]
function get_facet(PDO $pdo, string $column, int $minCount = 30, int $limit = 30): array {
    if (!in_array($column, ['genres', 'platforms', 'modes', 'perspectives', 'themes'], true)) {
        return [];
    }

    try {
        $rows = $pdo->query("SELECT $column AS list, COUNT(*) AS n FROM games WHERE $column IS NOT NULL AND $column <> '' GROUP BY $column");
    } catch (PDOException $e) {
        return []; // la colonne n'existe pas encore (mise à jour de la base pas faite)
    }

    $counts = [];
    foreach ($rows as $row) {
        foreach (explode(', ', $row['list']) as $item) {
            $item = trim($item);
            if ($item !== '') {
                $counts[$item] = ($counts[$item] ?? 0) + (int) $row['n'];
            }
        }
    }

    $counts = array_filter($counts, fn($n) => $n >= $minCount);
    arsort($counts);
    return array_slice($counts, 0, $limit, true);
}

// Jeux d'après leurs identifiants IGDB (jeux similaires)
function get_games_by_igdb_ids(PDO $pdo, array $ids, int $limit = 12): array {
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn($i) => $i > 0)));
    if (!$ids) {
        return [];
    }
    $limit = max(1, min(50, $limit));
    $marks = implode(',', array_fill(0, count($ids), '?'));

    $stmt = $pdo->prepare(
        "SELECT id, title, slug, release_date, platforms, genres, developer, rating, cover_url
         FROM games WHERE igdb_id IN ($marks) ORDER BY reviews DESC LIMIT $limit"
    );
    $stmt->execute($ids);
    return $stmt->fetchAll();
}

// Autres jeux de la même série
function get_games_same_franchise(PDO $pdo, string $franchise, int $excludeId, int $limit = 12): array {
    $limit = max(1, min(50, $limit));
    $stmt = $pdo->prepare(
        "SELECT id, title, slug, release_date, platforms, genres, developer, rating, cover_url
         FROM games WHERE franchise = ? AND id <> ?
         ORDER BY release_date IS NULL, release_date ASC, reviews DESC LIMIT $limit"
    );
    $stmt->execute([$franchise, $excludeId]);
    return $stmt->fetchAll();
}

// ---------- Favoris ----------

function is_favorite(PDO $pdo, int $userId, int $gameId): bool {
    $stmt = $pdo->prepare('SELECT 1 FROM favorites WHERE user_id = ? AND game_id = ?');
    $stmt->execute([$userId, $gameId]);
    return (bool) $stmt->fetchColumn();
}

// Ajoute le favori s'il n'existe pas, le retire sinon. Renvoie le nouvel état.
function toggle_favorite(PDO $pdo, int $userId, int $gameId): bool {
    $stmt = $pdo->prepare('DELETE FROM favorites WHERE user_id = ? AND game_id = ?');
    $stmt->execute([$userId, $gameId]);
    if ($stmt->rowCount() > 0) {
        return false;
    }

    $stmt = $pdo->prepare('INSERT IGNORE INTO favorites (user_id, game_id) VALUES (?, ?)');
    $stmt->execute([$userId, $gameId]);
    return true;
}

function get_favorites(PDO $pdo, int $userId): array {
    $stmt = $pdo->prepare(
        'SELECT g.id, g.title, g.slug, g.release_date, g.platforms, g.genres, g.developer, g.rating, g.cover_url
         FROM favorites f
         JOIN games g ON g.id = f.game_id
         WHERE f.user_id = ?
         ORDER BY f.created_at DESC'
    );
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

// ---------- Images du jeu ----------

// Adresse d'une image IGDB à partir de son identifiant (screenshot_med, screenshot_huge, 720p...)
function image_url(string $imageId, string $size = 'screenshot_huge'): string {
    return 'https://images.igdb.com/igdb/image/upload/t_' . $size . '/' . rawurlencode($imageId) . '.jpg';
}

// Identifiants des images d'un jeu (illustrations puis captures d'écran)
function get_game_images(array $game): array {
    $list = json_decode($game['images'] ?? '', true);
    return is_array($list) ? array_values(array_filter($list, 'is_string')) : [];
}

// ---------- Affichage de la fiche d'un jeu ----------

// Date en français : "12 mars 2017"
function format_date_fr(?string $date): string {
    if (!$date) {
        return 'Inconnue';
    }
    $months = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet',
               'août', 'septembre', 'octobre', 'novembre', 'décembre'];
    $d = new DateTime($date);
    return $d->format('j') . ' ' . $months[(int) $d->format('n') - 1] . ' ' . $d->format('Y');
}

// Icône à afficher à côté d'une plateforme : smartphone, monitor (ordinateur) ou gamepad (console)
function platform_icon(string $name): string {
    if (preg_match('/\b(ios|android|mobile|phone)\b/i', $name)) {
        return 'smartphone';
    }
    if (preg_match('/\b(pc|windows|mac|macintosh|linux|dos|web browser)\b/i', $name)) {
        return 'monitor';
    }
    return 'gamepad';
}

// Transforme "A, B, C" en liste [A, B, C]
function split_list(?string $list): array {
    return array_values(array_filter(array_map('trim', explode(', ', (string) $list)), fn($v) => $v !== ''));
}

// Détails supplémentaires d'un jeu (vidéos, liens, classification, histoire...)
function get_game_details(array $game): array {
    $details = json_decode($game['details'] ?? '', true);
    return is_array($details) ? $details : [];
}

// Reconnaît un lien (Steam, GOG, Wikipédia...) d'après son adresse.
// Renvoie [clé, libellé, genre, couleur, url] ou null si le lien n'est pas reconnu.
function classify_link(string $url, ?int $type = null): ?array {
    $host = strtolower((string) parse_url($url, PHP_URL_HOST));
    $host = preg_replace('/^www\./', '', $host);
    if ($host === '') {
        return null;
    }

    // [domaine, clé, libellé, genre de lien, couleur de la marque]
    $rules = [
        ['steampowered.com', 'steam', 'Steam', 'store', '#66c0f4'],
        ['gog.com', 'gog', 'GOG', 'store', '#b565d9'],
        ['epicgames.com', 'epic', 'Epic Games Store', 'store', '#e8e8ec'],
        ['itch.io', 'itch', 'itch.io', 'store', '#fa5c5c'],
        ['apps.apple.com', 'appstore', 'App Store', 'store', '#3d9bf5'],
        ['itunes.apple.com', 'appstore', 'App Store', 'store', '#3d9bf5'],
        ['play.google.com', 'googleplay', 'Google Play', 'store', '#3ddc84'],
        ['xbox.com', 'xbox', 'Xbox', 'store', '#52b043'],
        ['apps.microsoft.com', 'microsoft', 'Microsoft Store', 'store', '#00a4ef'],
        ['microsoft.com', 'microsoft', 'Microsoft Store', 'store', '#00a4ef'],
        ['playstation.com', 'playstation', 'PlayStation Store', 'store', '#2d6bd8'],
        ['nintendo.com', 'nintendo', 'Nintendo eShop', 'store', '#e60012'],
        ['humblebundle.com', 'humble', 'Humble Bundle', 'store', '#cb272c'],
        ['battle.net', 'battlenet', 'Battle.net', 'store', '#148eff'],
        ['ubisoft.com', 'ubisoft', 'Ubisoft', 'store', '#0070ff'],
        ['ea.com', 'ea', 'EA app', 'store', '#ff4747'],
        ['riotgames.com', 'riot', 'Riot Games', 'store', '#d13639'],
        ['wikipedia.org', 'wikipedia', 'Wikipédia', 'wiki', ''],
        ['fandom.com', 'fandom', 'Fandom', 'wiki', ''],
        ['youtube.com', 'youtube', 'YouTube', 'media', ''],
        ['youtu.be', 'youtube', 'YouTube', 'media', ''],
        ['twitch.tv', 'twitch', 'Twitch', 'media', ''],
        ['reddit.com', 'reddit', 'Reddit', 'community', ''],
        ['discord.gg', 'discord', 'Discord', 'community', ''],
        ['discord.com', 'discord', 'Discord', 'community', ''],
        ['facebook.com', 'facebook', 'Facebook', 'community', ''],
        ['twitter.com', 'twitter', 'X (Twitter)', 'community', ''],
        ['x.com', 'twitter', 'X (Twitter)', 'community', ''],
        ['instagram.com', 'instagram', 'Instagram', 'community', ''],
        ['bsky.app', 'bluesky', 'Bluesky', 'community', ''],
    ];

    $path = (string) parse_url($url, PHP_URL_PATH);
    foreach ($rules as [$domain, $key, $label, $kind, $color]) {
        if ($host !== $domain && !str_ends_with($host, '.' . $domain)) {
            continue;
        }
        // microsoft.com : seulement les pages de la boutique
        if ($domain === 'microsoft.com' && !str_contains($path, '/store')) {
            continue;
        }
        return ['key' => $key, 'label' => $label, 'kind' => $kind, 'color' => $color, 'url' => $url];
    }

    // Adresse inconnue : on garde seulement celle que IGDB désigne comme site officiel (type 1)
    if ($type === 1) {
        return ['key' => 'official', 'label' => 'Site officiel', 'kind' => 'official', 'color' => '', 'url' => $url];
    }
    return null;
}

// Trie les liens d'un jeu : ['stores' => boutiques, 'others' => site officiel, wiki, réseaux...]
function prepare_links(array $raw): array {
    $stores = [];
    $others = [];
    $seen   = [];

    foreach ($raw as $link) {
        $url = (string) ($link['url'] ?? '');
        if (!preg_match('#^https?://#i', $url)) {
            continue;
        }
        $type = isset($link['type']) ? (int) $link['type'] : null;
        $info = classify_link($url, $type);
        if (!$info || isset($seen[$info['key']])) {
            continue;
        }
        $seen[$info['key']] = true;

        if ($info['kind'] === 'store') {
            $stores[] = $info;
        } else {
            $others[] = $info;
        }
    }

    // Le site officiel en premier
    usort($others, fn($a, $b) => ($b['kind'] === 'official') <=> ($a['kind'] === 'official'));

    return ['stores' => $stores, 'others' => $others];
}
