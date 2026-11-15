<?php
require __DIR__ . '/config/db.php';
require __DIR__ . '/includes/functions.php';
require_login();

$stmt = $pdo->prepare('SELECT username, email, created_at, password_hash FROM users WHERE id = ?');
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Le compte n'existe plus : on déconnecte
if (!$user) {
    header('Location: deconnexion.php');
    exit;
}

$errors  = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf'] ?? null)) {
        $errors[] = 'Formulaire invalide, réessaie.';
    }

    $current = $_POST['current'] ?? '';
    $new     = $_POST['new'] ?? '';
    $confirm = $_POST['confirm'] ?? '';

    if (!password_verify($current, $user['password_hash'])) {
        $errors[] = 'Le mot de passe actuel est incorrect.';
    }
    if (strlen($new) < 8) {
        $errors[] = 'Le nouveau mot de passe doit faire au moins 8 caractères.';
    }
    if ($new !== $confirm) {
        $errors[] = 'Les nouveaux mots de passe ne correspondent pas.';
    }

    if (!$errors) {
        $stmt = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
        $stmt->execute([password_hash($new, PASSWORD_DEFAULT), $_SESSION['user_id']]);
        session_regenerate_id(true);
        $success = true;
    }
}

$pageTitle   = 'Mon profil';
$currentPage = 'profil';
require __DIR__ . '/includes/header.php';
?>

<h1>Mon profil</h1>

<section class="panel">
    <h2>Mes informations</h2>
    <dl class="info">
        <dt>Pseudo</dt>
        <dd><?= e($user['username']) ?></dd>
        <dt>Email</dt>
        <dd><?= e($user['email']) ?></dd>
        <dt>Membre depuis</dt>
        <dd><?= e((new DateTime($user['created_at']))->format('d/m/Y')) ?></dd>
    </dl>
</section>

<section class="panel">
    <h2>Changer mon mot de passe</h2>

    <?php if ($success): ?>
        <p class="msg ok">Mot de passe modifié.</p>
    <?php endif; ?>
    <?php foreach ($errors as $error): ?>
        <p class="msg err"><?= e($error) ?></p>
    <?php endforeach; ?>

    <form method="post" class="form">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

        <label>Mot de passe actuel
            <input type="password" name="current" required>
        </label>
        <label>Nouveau mot de passe
            <input type="password" name="new" required>
        </label>
        <label>Confirmer le nouveau mot de passe
            <input type="password" name="confirm" required>
        </label>

        <button type="submit">Modifier</button>
    </form>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
