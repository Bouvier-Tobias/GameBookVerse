<?php
require_once __DIR__ . '/functions.php';

$pageTitle   = $pageTitle ?? 'GameBook';
$currentPage = $currentPage ?? '';

$links = [
    'index'     => ['Accueil',   'index.php'],
    'catalogue' => ['Catalogue', 'catalogue.php'],
    'favoris'   => ['Favoris',   'favoris.php'],
    'contact'   => ['Contact',   'contact.php'],
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> - GameBook</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<header class="navbar">
    <a class="brand" href="index.php">GameBook</a>

    <button class="burger" type="button" aria-label="Ouvrir le menu" aria-expanded="false" aria-controls="menu">
        <span></span><span></span><span></span>
    </button>

    <nav id="menu" class="menu">
        <ul class="links">
            <?php foreach ($links as $key => [$label, $url]): ?>
                <li>
                    <a href="<?= e($url) ?>" class="<?= $currentPage === $key ? 'active' : '' ?>"><?= e($label) ?></a>
                </li>
            <?php endforeach; ?>
        </ul>

        <div class="account">
            <?php if (is_logged_in()): ?>
                <a href="profil.php" class="<?= $currentPage === 'profil' ? 'active' : '' ?>"><?= e($_SESSION['username']) ?></a>
                <a href="deconnexion.php">Déconnexion</a>
            <?php else: ?>
                <a href="connexion.php" class="<?= $currentPage === 'connexion' ? 'active' : '' ?>">Connexion</a>
                <a href="inscription.php" class="cta">Inscription</a>
            <?php endif; ?>
        </div>
    </nav>
</header>

<main class="container">
