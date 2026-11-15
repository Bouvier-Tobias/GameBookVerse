<?php
require __DIR__ . '/config/db.php';
require __DIR__ . '/includes/functions.php';

if (is_logged_in()) {
    header('Location: index.php');
    exit;
}

$errors   = [];
$username = '';
$email    = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf'] ?? null)) {
        $errors[] = 'Formulaire invalide, réessaie.';
    }

    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm'] ?? '';

    if (!preg_match('/^[A-Za-z0-9_]{3,50}$/', $username)) {
        $errors[] = 'Pseudo : 3 à 50 caractères (lettres, chiffres, underscore).';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Adresse email invalide.';
    }
    if (strlen($password) < 8) {
        $errors[] = 'Le mot de passe doit faire au moins 8 caractères.';
    }
    if ($password !== $confirm) {
        $errors[] = 'Les mots de passe ne correspondent pas.';
    }

    // Pseudo ou email déjà pris ?
    if (!$errors) {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ? OR email = ?');
        $stmt->execute([$username, $email]);
        if ($stmt->fetch()) {
            $errors[] = 'Ce pseudo ou cet email est déjà utilisé.';
        }
    }

    if (!$errors) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare('INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)');
        $stmt->execute([$username, $email, $hash]);

        header('Location: connexion.php?inscrit=1');
        exit;
    }
}

$pageTitle   = 'Inscription';
$currentPage = 'inscription';
$scripts     = ['assets/js/auth.js'];
require __DIR__ . '/includes/header.php';
?>

<div class="auth-wrap">
    <div class="auth-bg"><span></span><span></span></div>

    <section class="auth-card">
        <div class="auth-badge"><?= icon('user') ?></div>
        <h1>Créer un compte</h1>
        <p class="auth-sub">Rejoins GameBook et garde tes jeux favoris</p>

        <?php foreach ($errors as $error): ?>
            <p class="msg err"><?= e($error) ?></p>
        <?php endforeach; ?>

        <form method="post" class="auth-form">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

            <label class="field">
                <span class="field-label">Pseudo</span>
                <span class="field-box">
                    <?= icon('user') ?>
                    <input type="text" name="username" value="<?= e($username) ?>" autocomplete="username" required>
                </span>
            </label>

            <label class="field">
                <span class="field-label">Email</span>
                <span class="field-box">
                    <?= icon('mail') ?>
                    <input type="email" name="email" value="<?= e($email) ?>" autocomplete="email" required>
                </span>
            </label>

            <label class="field">
                <span class="field-label">Mot de passe</span>
                <span class="field-box has-toggle">
                    <?= icon('lock') ?>
                    <input type="password" name="password" autocomplete="new-password" required>
                    <button type="button" class="toggle-pw" data-toggle-pw aria-label="Afficher le mot de passe">
                        <?= icon('eye') ?><?= icon('eye-off') ?>
                    </button>
                </span>
            </label>

            <label class="field">
                <span class="field-label">Confirmer le mot de passe</span>
                <span class="field-box has-toggle">
                    <?= icon('lock') ?>
                    <input type="password" name="confirm" autocomplete="new-password" required>
                    <button type="button" class="toggle-pw" data-toggle-pw aria-label="Afficher le mot de passe">
                        <?= icon('eye') ?><?= icon('eye-off') ?>
                    </button>
                </span>
            </label>

            <button type="submit" class="btn-main">S'inscrire</button>
        </form>

        <p class="auth-switch">Déjà un compte ? <a href="connexion.php">Se connecter</a></p>
    </section>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
