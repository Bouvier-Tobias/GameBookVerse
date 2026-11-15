<?php
require __DIR__ . '/config/db.php';
require __DIR__ . '/includes/functions.php';

if (is_logged_in()) {
    header('Location: index.php');
    exit;
}

$errors = [];
$login  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf'] ?? null)) {
        $errors[] = 'Formulaire invalide, réessaie.';
    }

    $login    = trim($_POST['login'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!$errors) {
        // On accepte le pseudo OU l'email
        $stmt = $pdo->prepare('SELECT id, username, password_hash FROM users WHERE username = ? OR email = ?');
        $stmt->execute([$login, $login]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            // Nouvel identifiant de session : empêche la fixation de session
            session_regenerate_id(true);
            $_SESSION['user_id']  = (int) $user['id'];
            $_SESSION['username'] = $user['username'];

            header('Location: index.php');
            exit;
        }

        // Même message dans les deux cas pour ne pas révéler si le compte existe
        $errors[] = 'Identifiants incorrects.';
    }
}

$pageTitle   = 'Connexion';
$currentPage = 'connexion';
$scripts     = ['assets/js/auth.js'];
require __DIR__ . '/includes/header.php';
?>

<div class="auth-wrap">
    <div class="auth-bg"><span></span><span></span></div>

    <section class="auth-card">
        <div class="auth-badge"><?= icon('lock') ?></div>
        <h1>Connexion</h1>
        <p class="auth-sub">Content de te revoir sur GameBook</p>

        <?php if (isset($_GET['inscrit'])): ?>
            <p class="msg ok">Compte créé, tu peux te connecter.</p>
        <?php endif; ?>
        <?php foreach ($errors as $error): ?>
            <p class="msg err"><?= e($error) ?></p>
        <?php endforeach; ?>

        <form method="post" class="auth-form">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

            <label class="field">
                <span class="field-label">Pseudo ou email</span>
                <span class="field-box">
                    <?= icon('user') ?>
                    <input type="text" name="login" value="<?= e($login) ?>" autocomplete="username" required>
                </span>
            </label>

            <label class="field">
                <span class="field-label">Mot de passe</span>
                <span class="field-box has-toggle">
                    <?= icon('lock') ?>
                    <input type="password" name="password" autocomplete="current-password" required>
                    <button type="button" class="toggle-pw" data-toggle-pw aria-label="Afficher le mot de passe">
                        <?= icon('eye') ?><?= icon('eye-off') ?>
                    </button>
                </span>
            </label>

            <button type="submit" class="btn-main">Se connecter</button>
        </form>

        <p class="auth-switch">Pas encore de compte ? <a href="inscription.php">S'inscrire</a></p>
    </section>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
