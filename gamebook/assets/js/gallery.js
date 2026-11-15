// Galerie d'images de la fiche d'un jeu : grande image + miniatures + flèches
document.querySelectorAll('[data-gallery]').forEach((gallery) => {
    const main = gallery.querySelector('[data-main]');
    const thumbsBox = gallery.querySelector('[data-thumbs]');
    const thumbs = [...gallery.querySelectorAll('.thumb')];
    if (!main || thumbs.length < 2) {
        return;
    }

    let current = 0;

    function show(index) {
        current = (index + thumbs.length) % thumbs.length;
        thumbs.forEach((t, i) => t.classList.toggle('active', i === current));

        // On charge la nouvelle image en arrière-plan pour un fondu sans clignotement
        main.classList.add('switching');
        const next = new Image();
        next.onload = () => {
            main.src = next.src;
            main.classList.remove('switching');
        };
        next.onerror = () => main.classList.remove('switching');
        next.src = thumbs[current].dataset.full;

        // Fait défiler la rangée de miniatures pour garder la miniature active visible
        const thumb = thumbs[current];
        thumbsBox.scrollTo({
            left: thumb.offsetLeft - thumbsBox.clientWidth / 2 + thumb.clientWidth / 2,
            behavior: 'smooth',
        });
    }

    thumbs.forEach((thumb, i) => thumb.addEventListener('click', () => show(i)));
    gallery.querySelector('[data-prev]').addEventListener('click', () => show(current - 1));
    gallery.querySelector('[data-next]').addEventListener('click', () => show(current + 1));
    main.addEventListener('click', () => show(current + 1));
});
