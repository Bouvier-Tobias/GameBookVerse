# GameBook

Site de découverte de jeux vidéo en PHP natif (XAMPP, MariaDB). Les jeux viennent de l'API IGDB (Twitch)
et sont copiés dans la base locale : la recherche et les filtres sont ainsi instantanés.

## Installation (Linux Mint + XAMPP)

1. Copier le dossier `gamebook` dans `/opt/lampp/htdocs/` puis lui donner les droits :

       sudo chown -R $USER:$USER /opt/lampp/htdocs/gamebook

2. Démarrer XAMPP :

       sudo /opt/lampp/lampp start

3. Ouvrir `http://localhost/phpmyadmin`, onglet **Importer**, choisir `database.sql`, valider.
4. Ouvrir `config/igdb.php` et y mettre le Client ID et le Client Secret Twitch.
5. Importer les jeux depuis IGDB :

       cd /opt/lampp/htdocs/gamebook
       /opt/lampp/bin/php import/import_igdb.php --test     # 500 jeux pour vérifier
       /opt/lampp/bin/php import/import_igdb.php            # import complet
       /opt/lampp/bin/php import/import_details.php         # vidéos, liens, modes, similaires... (après le précédent)

6. Ouvrir `http://localhost/gamebook/`.

Si l'import dit que l'extension curl est désactivée : dans `/opt/lampp/etc/php.ini`, enlever le `;`
devant `extension=curl`, puis `sudo /opt/lampp/lampp restart`.

## Organisation

- `index.php`, `catalogue.php`, `jeu.php`, `favoris.php`, `profil.php`, `contact.php` : pages du site
- `connexion.php`, `inscription.php`, `deconnexion.php`, `favori_toggle.php` : comptes et favoris
- `api/games.php`, `api/game.php` : API JSON locale
- `includes/` : fonctions, en-tête, pied de page, carte de jeu
- `config/` : connexion base de données et identifiants IGDB
- `import/import_igdb.php` : copie des jeux IGDB dans la base
- `import/import_details.php` : complète les jeux (vidéos, liens boutiques, modes, séries...)
- `assets/` : CSS et JavaScript

Les logos des boutiques (Steam, GOG...) se déposent dans `assets/icons/` : voir `assets/icons/LISEZMOI.txt`.
