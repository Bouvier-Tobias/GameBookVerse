<?php
require __DIR__ . '/config/db.php';
require __DIR__ . '/includes/functions.php';

$errors  = [];
$name    = '';
$email   = '';
$message = '';

// Préremplissage pour un utilisateur connecté
if (is_logged_in()) {
    $stmt = $pdo->prepare('SELECT username, email FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    if ($u = $stmt->fetch()) {
        $name  = $u['username'];
        $email = $u['email'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf'] ?? null)) {
        $errors[] = 'Formulaire invalide, réessaie.';
    }

    $name    = trim($_POST['name'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
        $errors[] = 'Le nom doit faire entre 2 et 100 caractères.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 255) {
        $errors[] = 'Adresse email invalide.';
    }
    if (mb_strlen($message) < 10 || mb_strlen($message) > 2000) {
        $errors[] = 'Le message doit faire entre 10 et 2000 caractères.';
    }

    if (!$errors) {
        $stmt = $pdo->prepare('INSERT INTO contact_messages (user_id, name, email, message) VALUES (?, ?, ?, ?)');
        $stmt->execute([
            is_logged_in() ? (int) $_SESSION['user_id'] : null,
            $name,
            $email,
            $message,
        ]);

        // Redirection pour éviter le renvoi du formulaire en actualisant la page
        header('Location: contact.php?envoye=1');
        exit;
    }
}

$sent = isset($_GET['envoye']);

$pageTitle   = 'Contact';
$currentPage = 'contact';
$scripts     = ['assets/js/auth.js'];
require __DIR__ . '/includes/header.php';
?>

<div class="auth-wrap">
    <div class="auth-bg"><span></span><span></span></div>

    <section class="auth-card wide">
        <div class="auth-badge"><?= icon('mail') ?></div>
        <h1>Contact</h1>
        <p class="auth-sub">Une question, une suggestion ou un bug ? Écris-nous.</p>

        <?php if ($sent): ?>
            <p class="msg ok">Message envoyé, merci !</p>
        <?php endif; ?>
        <?php foreach ($errors as $error): ?>
            <p class="msg err"><?= e($error) ?></p>
        <?php endforeach; ?>

        <form method="post" class="auth-form">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

            <label class="field">
                <span class="field-label">Nom</span>
                <span class="field-box">
                    <?= icon('user') ?>
                    <input type="text" name="name" value="<?= e($name) ?>" maxlength="100" required>
                </span>
            </label>

            <label class="field">
                <span class="field-label">Email</span>
                <span class="field-box">
                    <?= icon('mail') ?>
                    <input type="email" name="email" value="<?= e($email) ?>" maxlength="255" required>
                </span>
            </label>

            <label class="field">
                <span class="field-label">Message</span>
                <textarea name="message" maxlength="2000" required><?= e($message) ?></textarea>
            </label>

            <button type="submit" class="btn-main">Envoyer</button>
        </form>
    </section>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
