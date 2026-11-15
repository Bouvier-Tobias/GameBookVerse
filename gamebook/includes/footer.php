</main>

<script>
    const burger = document.querySelector('.burger');
    const menu = document.getElementById('menu');
    burger.addEventListener('click', () => {
        const open = menu.classList.toggle('open');
        burger.setAttribute('aria-expanded', open);
    });
</script>

<?php foreach (($scripts ?? []) as $src): ?>
    <script src="<?= e($src) ?>" defer></script>
<?php endforeach; ?>
</body>
</html>
