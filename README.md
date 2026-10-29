# UIST2its

Portail web de l’Université Internationale des Sciences et Technologie (Cocody Riviera, Abidjan), réalisé avec Laravel 13, Inertia.js, React, Tailwind CSS et PostgreSQL 16 en production.

## Prérequis

- Docker Desktop ou Docker Engine pour exécuter l’image de production
- PostgreSQL 16 pour la production ; SQLite en mémoire pour la suite de tests par défaut
- PHP 8.3+, Composer, Node.js 22.12+ et npm pour le développement local

## Installation locale

```bash
composer install
copy .env.example .env
php artisan key:generate
```

Pour le développement, créer une base PostgreSQL locale et configurer `DB_CONNECTION=pgsql`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME` et `DB_PASSWORD` dans `.env`. SQLite reste pris en charge pour les tests automatisés. Configurer `MAIL_*` si les courriels doivent être envoyés.

```bash
php artisan migrate
php artisan db:seed
npm install
npm run build
php artisan storage:link
```

Lancer `php artisan serve` et `npm run dev` dans deux terminaux. Les notifications implémentent `ShouldQueue`. En local, le pilote `database` nécessite aussi `php artisan queue:work`; en production l’exemple utilise `QUEUE_CONNECTION=sync` pour fonctionner sans worker.

## Tests

Par défaut, `php artisan test` utilise SQLite en mémoire. Pour exécuter explicitement les tests sur une base PostgreSQL **jetable et isolée** (jamais la production), fournir ses paramètres dans l’environnement avant de lancer le runner :

```powershell
$env:DB_CONNECTION = 'pgsql'
$env:DB_URL = ''
$env:DB_HOST = '127.0.0.1'
$env:DB_PORT = '5432'
$env:DB_DATABASE = 'uist2its_test'
$env:DB_USERNAME = 'postgres'
$env:DB_PASSWORD = 'change-moi'
php artisan test
```

Vérifier la base ciblée avant l’exécution ; le trait `RefreshDatabase` réinitialise le schéma de test.

## Déploiement sur Render avec Docker

1. Utiliser le Blueprint `render.yaml` à la racine pour créer le Web Service Docker, la base PostgreSQL et le disque des fichiers publics. Les services sont placés dans la région Frankfurt.
2. L’image PHP 8.3 construit les ressources avec `npm ci && npm run build`, sert `public/` avec Apache et écoute sur le `PORT` fourni par Render (10000 par défaut hors Render).
3. Renseigner les variables marquées `sync: false` dans Render : `APP_KEY`, `APP_URL` (URL HTTPS du Web Service), et `ADMIN_INITIAL_PASSWORD` (au moins 16 caractères). Les paramètres SMTP sont requis si les courriels sont souhaités. `DB_URL` est reliée automatiquement à la base du Blueprint.
4. Générer une clé avec `php artisan key:generate --show` dans un environnement de confiance, puis l’enregistrer comme `APP_KEY` dans Render. Ne pas placer cette valeur dans Git.
5. Au démarrage, le conteneur exécute `php artisan migrate --force`, puis le seeder initial, les caches de configuration/routes/vues et Apache. Le premier compte admin est créé uniquement si `users` est vide et si `ADMIN_INITIAL_PASSWORD` est défini. Dans les autres cas, aucun compte n’est créé ; un mot de passe absent sur une base vide fait échouer le démarrage sans afficher de secret. Les comptes existants ne sont pas modifiés.
6. Vérifier le service via `https://<nom-du-service>.onrender.com/up`. Les pièces jointes et images publiques sont conservées sur le disque Render du Blueprint ; ce disque convient à une instance unique, et un stockage objet sera préférable pour un déploiement multi-instance. Prévoir également un worker séparé si le pilote de queue est ensuite changé en `database`.

Avant chaque migration risquée, sauvegarder la base PostgreSQL. Exemple de sauvegarde depuis un environnement autorisé possédant `pg_dump` :

```bash
pg_dump --format=custom --no-owner "$DB_URL" --file="uist2its-$(date +%Y%m%d-%H%M%S).dump"
```

Conserver les sauvegardes en dehors du conteneur et tester périodiquement leur restauration.

## Comptes de démonstration

Les enseignants `enseignant1@uist.ci` / `EnseignantUIST!1` et `enseignant2@uist.ci` / `EnseignantUIST!2`, ainsi que les étudiants `UIST-2026-0001` à `UIST-2026-0010`, sont exclusivement des données de développement. Le `DatabaseSeeder` refuse de créer ces comptes/données lorsque `APP_ENV=production`.

## Rotation du mot de passe administrateur

Le mot de passe administrateur historique est compromis et ne doit plus être utilisé. Après déploiement du code, appliquez la migration puis réinitialisez uniquement le compte concerné depuis un terminal autorisé :

```bash
php artisan migrate --force
php artisan admin:reset-password admin@uist.ci
```

La commande génère un secret aléatoire fort, met à jour uniquement l’adresse e-mail fournie si elle appartient à un administrateur, et affiche le secret une seule fois dans la console. Transmettez-le par un canal sûr. À sa prochaine connexion, l’administrateur devra le remplacer par un mot de passe personnel d’au moins 16 caractères, avec majuscules, minuscules, chiffres et symbole. Les réinitialisations et changements sont enregistrés dans `admin_password_changes`, sans jamais conserver le mot de passe.

Pour créer le premier compte administrateur en production, `ADMIN_INITIAL_PASSWORD` est obligatoire et doit contenir au moins 16 caractères. Le seeder ne génère et n’affiche jamais de mot de passe en production. Il ne modifie aucun compte existant ; un redémarrage ne réinitialise donc pas le mot de passe admin.

## Connexion et sécurité

Les étudiants s’authentifient avec leur matricule permanent et leur date de naissance, obligatoire. Les tentatives sont limitées par adresse IP et matricule ; un matricule ou une date de naissance incorrects produisent le même message d’erreur et chaque succès ou échec est conservé dans `connexions_log`.

Les enseignants utilisent leur adresse e-mail et leur mot de passe. Les policies Laravel et le middleware `role` cloisonnent l’administration, la saisie des matières attribuées, les notes personnelles et les décisions publiées. Les pièces jointes sont stockées sur le disque public ; l’image Docker crée le lien `public/storage` lors du build (en local, lancer `php artisan storage:link`).

Un étudiant ne reçoit que les emplois du temps qui correspondent exactement à sa filière et à son niveau. Les listes de classe sont réservées à l’administration et établies à partir des inscriptions de l’année universitaire sélectionnée ; l’année active est sélectionnée par défaut.

## Règle annuelle

La moyenne est pondérée par les coefficients des matières dont les notes sont publiées. Une matière validée (`note >= 10`) apporte ses crédits. La décision automatique est « admis » si la moyenne atteint `UIST_ADMISSION_AVERAGE` (10 par défaut) et si les crédits requis du niveau sont obtenus ; sinon elle est « ajourné ». Une décision finale différente de la proposition automatique exige un motif et est inscrite dans `journal_modifications`.
