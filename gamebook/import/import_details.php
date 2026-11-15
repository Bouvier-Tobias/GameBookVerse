<?php
// Complète les jeux déjà importés avec les détails d'IGDB :
// vidéos, liens (Steam, GOG...), modes de jeu, perspectives, thèmes, éditeur, série,
// moteur de jeu, classification d'âge, histoire, notes de la presse, jeux similaires.
//
// À lancer APRÈS import_igdb.php (les jeux doivent déjà être dans la base).
// Test rapide (500 jeux) :  php import/import_details.php --test
// Import complet :          php import/import_details.php
//
// Chaque type de donnée est demandé séparément. Si IGDB refuse un champ (il en renomme
// de temps en temps), le script essaie une variante, puis ignore ce type de donnée en
// le signalant, sans casser le reste.

if (PHP_SAPI !== 'cli') {
    exit('À lancer en ligne de commande.');
}
if (!function_exists('curl_init')) {
    exit("L'extension PHP curl est désactivée. Dans php.ini, enlève le point-virgule devant extension=curl, puis redémarre Apache.\n");
}

require __DIR__ . '/../config/db.php';
$config = require __DIR__ . '/../config/igdb.php';

if (str_starts_with($config['client_id'], 'COLLE_ICI') || str_starts_with($config['client_secret'], 'COLLE_ICI')) {
    exit("Renseigne ton client_id et ton client_secret dans config/igdb.php.\n");
}

$BATCH_SIZE = 500;
$test       = in_array('--test', $argv, true);

// Types de données : clé => [nom affiché, variantes de champs à essayer dans l'ordre]
$GROUPS = [
    'media'      => ['vidéos', ['videos.video_id, videos.name']],
    'links'      => ['liens', ['websites.url, websites.type', 'websites.url, websites.category', 'websites.url']],
    'age'        => ['classification d\'âge', ['age_ratings.organization.name, age_ratings.rating_category.rating']],
    'style'      => ['modes, perspectives et thèmes', ['game_modes.name, player_perspectives.name, themes.name']],
    'info'       => ['histoire, critiques et jeux similaires', ['storyline, aggregated_rating, aggregated_rating_count, similar_games']],
    'series'     => ['série et moteur de jeu', ['franchises.name, game_engines.name']],
    'publishers' => ['éditeurs', ['involved_companies.publisher, involved_companies.company.name']],
];

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

// Requête IGDB qui renvoie [statut, corps]. Réessaie en cas de limite de débit ou de panne passagère.
function igdb_try(string $endpoint, string $query, string $clientId, string $token): array {
    for ($try = 1; $try <= 5; $try++) {
        [$status, $body] = http_post("https://api.igdb.com/v4/$endpoint", $query, [
            "Client-ID: $clientId",
            "Authorization: Bearer $token",
            'Accept: application/json',
        ]);
        if ($status === 0 || $status === 429 || $status >= 500) {
            sleep($try * 2);
            continue;
        }
        return [$status, (string) $body];
    }
    return [503, 'IGDB ne répond pas.'];
}

// Liste de noms à partir de [{id, name}, ...]
function names(array $items): array {
    return array_values(array_filter(array_map(fn($i) => is_array($i) ? ($i['name'] ?? '') : '', $items)));
}

// Texte "A, B, C" limité en longueur, ou null s'il n'y a rien
function list_value(array $items, int $max = 255): ?string {
    return $items ? mb_substr(implode(', ', array_unique($items)), 0, $max) : null;
}

// Enregistre (ou retire, si c'est vide) une information dans le tableau de détails
function set_detail(array &$details, string $key, $value): void {
    if ($value === null || $value === '' || $value === []) {
        unset($details[$key]);
    } else {
        $details[$key] = $value;
    }
}

// Range dans $cols (colonnes) et $details (JSON) les données d'un type pour un jeu.
// $row est vide si IGDB n'a rien pour ce jeu : on remet alors la donnée à zéro.
function apply_group(string $key, array $row, array &$cols, array &$details): void {
    switch ($key) {
        case 'media':
            $videos = [];
            foreach ($row['videos'] ?? [] as $video) {
                $id = (string) ($video['video_id'] ?? '');
                if (preg_match('/^[A-Za-z0-9_-]{6,20}$/', $id)) {
                    $videos[] = ['id' => $id, 'name' => mb_substr((string) ($video['name'] ?? ''), 0, 120)];
                }
            }
            set_detail($details, 'videos', array_slice($videos, 0, 6));
            break;

        case 'links':
            $links = [];
            foreach ($row['websites'] ?? [] as $site) {
                $url = trim((string) ($site['url'] ?? ''));
                if (!preg_match('#^https?://#i', $url)) {
                    continue;
                }
                $type = $site['type'] ?? ($site['category'] ?? null);
                if (is_array($type)) {
                    $type = $type['id'] ?? null;
                }
                $links[] = ['url' => mb_substr($url, 0, 500), 'type' => is_numeric($type) ? (int) $type : null];
            }
            set_detail($details, 'links', array_slice($links, 0, 25));
            break;

        case 'age':
            $pegi = $esrb = $other = null;
            foreach ($row['age_ratings'] ?? [] as $rating) {
                $org   = trim((string) ($rating['organization']['name'] ?? ''));
                $value = trim((string) ($rating['rating_category']['rating'] ?? ''));
                if ($org === '' || $value === '') {
                    continue;
                }
                $label = $org . ' ' . $value;
                if ($org === 'PEGI') {
                    $pegi ??= $label;
                } elseif ($org === 'ESRB') {
                    $esrb ??= $label;
                } else {
                    $other ??= $label;
                }
            }
            set_detail($details, 'age', $pegi ?? $esrb ?? $other);
            break;

        case 'style':
            $cols['modes']        = list_value(names($row['game_modes'] ?? []));
            $cols['perspectives'] = list_value(names($row['player_perspectives'] ?? []));
            $cols['themes']       = list_value(names($row['themes'] ?? []));
            break;

        case 'info':
            set_detail($details, 'storyline', mb_substr(trim((string) ($row['storyline'] ?? '')), 0, 4000));
            set_detail($details, 'critic', isset($row['aggregated_rating'])
                ? ['score' => round($row['aggregated_rating'] / 10, 1), 'count' => (int) ($row['aggregated_rating_count'] ?? 0)]
                : null);
            $similar = array_values(array_filter(array_map('intval', $row['similar_games'] ?? [])));
            set_detail($details, 'similar', array_slice($similar, 0, 20));
            break;

        case 'series':
            $franchises = names($row['franchises'] ?? []);
            set_detail($details, 'franchises', $franchises);
            set_detail($details, 'engines', names($row['game_engines'] ?? []));
            $cols['franchise'] = $franchises ? mb_substr($franchises[0], 0, 255) : null;
            break;

        case 'publishers':
            $publishers = [];
            foreach ($row['involved_companies'] ?? [] as $company) {
                if (!empty($company['publisher']) && !empty($company['company']['name'])) {
                    $publishers[] = $company['company']['name'];
                }
            }
            $cols['publisher'] = list_value($publishers);
            break;
    }
}

// ---------- Connexion à IGDB ----------
$clientId = $config['client_id'];
$token    = get_token($clientId, $config['client_secret']);
echo "Connexion à IGDB réussie.\n";

$selectIds = $pdo->prepare('SELECT igdb_id FROM games WHERE igdb_id IS NOT NULL AND igdb_id > ? ORDER BY igdb_id LIMIT ' . $BATCH_SIZE);

$lastId   = 0;
$done     = 0;
$working  = [];   // variante de champs qui fonctionne, pour chaque type
$disabled = [];   // types de données ignorés car IGDB les refuse
$updates  = [];   // requêtes UPDATE déjà préparées
$start    = microtime(true);

do {
    // On avance dans NOTRE base, par identifiant IGDB croissant
    $selectIds->execute([$lastId]);
    $ids = array_map('intval', $selectIds->fetchAll(PDO::FETCH_COLUMN));
    if (!$ids) {
        break;
    }
    $lastId = max($ids);
    $idList = implode(',', $ids);

    // Détails déjà enregistrés : on les complète sans rien écraser d'autre
    $marks = implode(',', array_fill(0, count($ids), '?'));
    $stmt  = $pdo->prepare("SELECT igdb_id, details FROM games WHERE igdb_id IN ($marks)");
    $stmt->execute($ids);
    $details = [];
    foreach ($stmt->fetchAll() as $row) {
        $saved = json_decode($row['details'] ?? '', true);
        $details[(int) $row['igdb_id']] = is_array($saved) ? $saved : [];
    }
    $cols = [];
    foreach ($ids as $id) {
        $details[$id] ??= [];
        $cols[$id] = [];
    }

    foreach ($GROUPS as $key => [$label, $alternatives]) {
        if (isset($disabled[$key])) {
            continue;
        }

        $rows      = null;
        $lastError = '';
        for ($a = $working[$key] ?? 0; $a < count($alternatives); $a++) {
            [$status, $body] = igdb_try(
                'games',
                "fields id, {$alternatives[$a]}; where id = ($idList); limit $BATCH_SIZE;",
                $clientId,
                $token
            );
            usleep(300000); // 4 requêtes par seconde au maximum

            if ($status === 200) {
                $rows = json_decode($body, true) ?? [];
                $working[$key] = $a;
                break;
            }
            $lastError = "HTTP $status : " . mb_substr($body, 0, 200);
            if ($status !== 400) {
                exit("Erreur IGDB ($label) : $lastError\n");
            }
            // 400 : un champ est refusé, on essaie la variante suivante
        }

        if ($rows === null) {
            $disabled[$key] = true;
            echo "Attention : les données « $label » sont ignorées ($lastError)\n";
            continue;
        }

        $byId = [];
        foreach ($rows as $r) {
            $byId[(int) $r['id']] = $r;
        }
        foreach ($ids as $id) {
            apply_group($key, $byId[$id] ?? [], $cols[$id], $details[$id]);
        }
    }

    // Écriture : seules les colonnes des types de données qui ont fonctionné sont mises à jour
    $pdo->beginTransaction();
    foreach ($ids as $id) {
        $cols[$id]['details'] = $details[$id]
            ? json_encode($details[$id], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            : null;

        $names = array_keys($cols[$id]);
        $sql   = 'UPDATE games SET ' . implode(', ', array_map(fn($c) => "$c = ?", $names)) . ' WHERE igdb_id = ?';
        $updates[$sql] ??= $pdo->prepare($sql);
        $updates[$sql]->execute([...array_values($cols[$id]), $id]);
    }
    $pdo->commit();

    $done += count($ids);
    printf("%d jeux complétés (dernier id IGDB : %d, %.0f s)\n", $done, $lastId, microtime(true) - $start);
} while (count($ids) === $BATCH_SIZE && !$test);

printf("\nTerminé : %d jeux complétés en %.0f s.\n", $done, microtime(true) - $start);
if ($disabled) {
    echo 'Types de données ignorés : ' . implode(', ', array_map(fn($k) => $GROUPS[$k][0], array_keys($disabled))) . "\n";
}
