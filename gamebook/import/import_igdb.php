<?php
// Copie les jeux d'IGDB (API Twitch) dans la table games.
//
// Test rapide (500 jeux au maximum) :
//   /opt/lampp/bin/php import/import_igdb.php --test
// Import complet (peut durer un moment, IGDB limite à 4 requêtes par seconde) :
//   /opt/lampp/bin/php import/import_igdb.php
//
// On peut le relancer sans risque : les jeux déjà présents sont mis à jour, pas dupliqués.

if (PHP_SAPI !== 'cli') {
    exit('À lancer en ligne de commande.');
}
if (!function_exists('curl_init')) {
    exit("L'extension PHP curl est désactivée. Dans /opt/lampp/etc/php.ini, enlève le point-virgule devant extension=curl, puis : sudo /opt/lampp/lampp restart\n");
}

require __DIR__ . '/../config/db.php';
$config = require __DIR__ . '/../config/igdb.php';

if (str_starts_with($config['client_id'], 'COLLE_ICI') || str_starts_with($config['client_secret'], 'COLLE_ICI')) {
    exit("Renseigne ton client_id et ton client_secret dans config/igdb.php.\n");
}

// ---------- Réglages ----------
$MIN_RATINGS = 5;    // ignore les jeux notés par moins de personnes (enlève la plupart des fiches vides)
$BATCH_SIZE  = 500;  // maximum autorisé par IGDB
$test        = in_array('--test', $argv, true);

// ---------- Fonctions utiles ----------
function http_post(string $url, string $body, array $headers = []): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $body,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 60,
    ]);
    $response = curl_exec($ch);
    $status   = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error    = curl_error($ch);
    curl_close($ch);

    return [$status, $response === false ? $error : $response];
}

// Jeton d'accès Twitch (valable environ 60 jours, on en demande un nouveau à chaque import)
function get_token(string $clientId, string $clientSecret): string {
    [$status, $body] = http_post('https://id.twitch.tv/oauth2/token', http_build_query([
        'client_id'     => $clientId,
        'client_secret' => $clientSecret,
        'grant_type'    => 'client_credentials',
    ]));

    $data = json_decode($body, true);
    if ($status !== 200 || empty($data['access_token'])) {
        exit("Impossible d'obtenir le jeton Twitch (HTTP $status) : $body\n");
    }
    return $data['access_token'];
}

function igdb_post(string $endpoint, string $query, string $clientId, string $token): array {
    for ($try = 1; $try <= 5; $try++) {
        [$status, $body] = http_post("https://api.igdb.com/v4/$endpoint", $query, [
            "Client-ID: $clientId",
            "Authorization: Bearer $token",
            'Accept: application/json',
        ]);

        if ($status === 200) {
            return json_decode($body, true) ?? [];
        }
        // Trop de requêtes ou panne passagère : on patiente puis on réessaie
        if ($status === 429 || $status >= 500) {
            sleep($try * 2);
            continue;
        }
        exit("Erreur IGDB (HTTP $status) : $body\n");
    }
    exit("IGDB ne répond pas après plusieurs essais.\n");
}

function slugify(string $text): string {
    $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
    $t = strtolower($t === false ? '' : $t);
    return substr(trim(preg_replace('/[^a-z0-9]+/', '-', $t), '-'), 0, 200);
}

// Liste de noms à partir de [{id, name}, ...]
function names(array $items): array {
    return array_values(array_filter(array_map(fn($i) => $i['name'] ?? '', $items)));
}

// ---------- Connexion à IGDB ----------
$clientId = $config['client_id'];
$token    = get_token($clientId, $config['client_secret']);
echo "Connexion à IGDB réussie.\n";

$sql = 'INSERT INTO games
            (igdb_id, title, slug, description, release_date, platforms, genres, developer, rating, reviews, cover_url, images)
        VALUES
            (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            title = VALUES(title),
            description = VALUES(description),
            release_date = VALUES(release_date),
            platforms = VALUES(platforms),
            genres = VALUES(genres),
            developer = VALUES(developer),
            rating = VALUES(rating),
            reviews = VALUES(reviews),
            cover_url = VALUES(cover_url),
            images = VALUES(images)';
$insert = $pdo->prepare($sql);

$fields = 'id, name, slug, summary, first_release_date, total_rating, total_rating_count, '
        . 'cover.image_id, genres.name, themes.name, platforms.name, '
        . 'involved_companies.developer, involved_companies.company.name, '
        . 'screenshots.image_id, artworks.image_id';

$lastId   = 0;
$imported = 0;
$skipped  = 0;
$start    = microtime(true);

// On avance par identifiant croissant (plus fiable qu'un décalage "offset" sur de gros volumes)
do {
    $query = "fields $fields; "
           . "where total_rating_count >= $MIN_RATINGS & id > $lastId; "
           . "sort id asc; limit $BATCH_SIZE;";

    $games = igdb_post('games', $query, $clientId, $token);

    $pdo->beginTransaction();
    foreach ($games as $g) {
        $lastId = max($lastId, (int) $g['id']);

        $name = trim($g['name'] ?? '');
        if ($name === '') {
            $skipped++;
            continue;
        }
        // On laisse de côté les jeux au thème érotique
        if (in_array('Erotic', names($g['themes'] ?? []), true)) {
            $skipped++;
            continue;
        }

        $developers = [];
        foreach ($g['involved_companies'] ?? [] as $company) {
            if (!empty($company['developer']) && !empty($company['company']['name'])) {
                $developers[] = $company['company']['name'];
            }
        }

        $slug = trim($g['slug'] ?? '');
        if ($slug === '') {
            $slug = slugify($name) . '-' . $g['id'];
        }

        $release = isset($g['first_release_date']) ? gmdate('Y-m-d', (int) $g['first_release_date']) : null;
        $rating  = isset($g['total_rating']) ? round($g['total_rating'] / 10, 1) : null; // note sur 10
        $cover   = isset($g['cover']['image_id'])
            ? 'https://images.igdb.com/igdb/image/upload/t_cover_big/' . $g['cover']['image_id'] . '.jpg'
            : null;

        // Images du jeu : jusqu'à 3 illustrations puis 8 captures d'écran (on garde seulement les identifiants)
        $images = [];
        foreach (array_slice($g['artworks'] ?? [], 0, 3) as $art) {
            if (!empty($art['image_id'])) {
                $images[] = $art['image_id'];
            }
        }
        foreach (array_slice($g['screenshots'] ?? [], 0, 8) as $shot) {
            if (!empty($shot['image_id'])) {
                $images[] = $shot['image_id'];
            }
        }

        $insert->execute([
            (int) $g['id'],
            mb_substr($name, 0, 255),
            mb_substr($slug, 0, 255),
            mb_substr(trim($g['summary'] ?? ''), 0, 2000),
            $release,
            mb_substr(implode(', ', names($g['platforms'] ?? [])), 0, 500),
            mb_substr(implode(', ', names($g['genres'] ?? [])), 0, 255),
            mb_substr(implode(', ', array_unique($developers)), 0, 255),
            $rating,
            (int) ($g['total_rating_count'] ?? 0),
            $cover,
            $images ? json_encode($images) : null,
        ]);
        $imported++;
    }
    $pdo->commit();

    printf("%d jeux importés (dernier id IGDB : %d, %.0f s)\n", $imported, $lastId, microtime(true) - $start);

    usleep(300000); // 4 requêtes par seconde au maximum
} while (count($games) === $BATCH_SIZE && !$test);

printf("\nTerminé : %d jeux importés, %d ignorés, en %.0f s.\n", $imported, $skipped, microtime(true) - $start);
