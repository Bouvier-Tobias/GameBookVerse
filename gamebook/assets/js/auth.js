// Afficher / masquer le mot de passe
document.querySelectorAll('[data-toggle-pw]').forEach((btn) => {
    btn.addEventListener('click', () => {
        const input = btn.closest('.field-box').querySelector('input');
        const show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        btn.classList.toggle('shown', show);
        btn.setAttribute('aria-label', show ? 'Masquer le mot de passe' : 'Afficher le mot de passe');
    });
});

// Petit cercle qui tourne dans le bouton pendant l'envoi
document.querySelectorAll('.auth-form').forEach((form) => {
    form.addEventListener('submit', () => {
        form.querySelector('button[type="submit"]').classList.add('loading');
    });
});

// Si on revient en arrière avec le bouton du navigateur, on enlève l'animation
window.addEventListener('pageshow', () => {
    document.querySelectorAll('.btn-main.loading').forEach((b) => b.classList.remove('loading'));
});
