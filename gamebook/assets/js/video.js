// Vidéos de la fiche d'un jeu : la vidéo YouTube ne se charge qu'au clic (plus rapide, plus discret)
document.querySelectorAll('[data-video]').forEach((box) => {
    const button = box.querySelector('.video-play');
    if (!button) {
        return;
    }

    button.addEventListener('click', () => {
        const frame = document.createElement('iframe');
        frame.src = 'https://www.youtube-nocookie.com/embed/' + encodeURIComponent(box.dataset.video) + '?autoplay=1&rel=0';
        frame.title = box.dataset.title || 'Vidéo du jeu';
        frame.allow = 'autoplay; encrypted-media; picture-in-picture; fullscreen';
        frame.allowFullscreen = true;
        box.replaceChildren(frame);
    });
});
