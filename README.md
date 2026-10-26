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

1. Créer une base PostgreSQL 16 sur Render. Copier son **Internal Database URL** ; ne pas le placer dans Git.
2. Créer un Web Service depuis le dépôt, choisir l’environnement **Docker** et le Dockerfile à la racine. L’image utilise Apache avec `public/` comme document root ; elle construit les ressources avec `npm ci && npm run build` et écoute sur le `PORT` fourni par Render (10000 par défaut hors Render).
3. Définir dans l’environnement du Web Service les variables de `.env.production.example`, au minimum `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL`, `APP_KEY`, `DB_CONNECTION=pgsql`, `DB_URL`, les options de session, `QUEUE_CONNECTION` et les paramètres mail si les courriels sont souhaités.
4. Générer une clé avec `php artisan key:generate --show` dans un environnement de confiance, puis enregistrer le résultat comme `APP_KEY` dans Render. Remplacer les valeurs `change-moi`/`change-me` ; elles sont uniquement des exemples.
5. Au démarrage, le conteneur exécute `php artisan migrate --force`, puis lance Apache. Aucune route cache n’est créée, car la route `/espace` est déclarée avec une closure.
6. Depuis le Shell Render du Web Service, créer le premier compte administrateur avec la commande ci-dessous. En production, le seeder crée ce compte puis ignore les comptes et données de démonstration. Sans `ADMIN_INITIAL_PASSWORD`, il affiche un secret aléatoire une seule fois et impose son remplacement à la première connexion :

   ```bash
   php artisan db:seed --force
   ```

   Si `admin@uist.ci` existe déjà, le seeder ne change pas son mot de passe. N’exécuter jamais en production un ancien seeder ou une version du code antérieure à la protection production.
7. Vérifier le service via `https://<nom-du-service>.onrender.com/up`.
8. Configurer une destination persistante (Render Disk ou stockage objet) avant de conserver des pièces jointes : le système de fichiers local d’un conteneur Render peut être éphémère. Prévoir également un worker séparé si le pilote de queue est ensuite changé en `database`.

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

Pour créer le premier compte administrateur sur une installation neuve, renseignez éventuellement `ADMIN_INITIAL_PASSWORD` dans `.env` avant le seeding ; cette valeur doit contenir au moins 16 caractères. Si elle est vide, le seeder génère un secret aléatoire, l’affiche une seule fois dans la console et impose son remplacement à la première connexion. Cette variable ne réinitialise pas les comptes déjà créés.

## Connexion et sécurité

Les étudiants s’authentifient avec leur matricule permanent et leur date de naissance, obligatoire. Les tentatives sont limitées par adresse IP et matricule ; un matricule ou une date de naissance incorrects produisent le même message d’erreur et chaque succès ou échec est conservé dans `connexions_log`.

Les enseignants utilisent leur adresse e-mail et leur mot de passe. Les policies Laravel et le middleware `role` cloisonnent l’administration, la saisie des matières attribuées, les notes personnelles et les décisions publiées. Les pièces jointes sont stockées sur le disque public ; lancer `php artisan storage:link` pour les servir.

Un étudiant ne reçoit que les emplois du temps qui correspondent exactement à sa filière et à son niveau. Les listes de classe sont réservées à l’administration et établies à partir des inscriptions de l’année universitaire sélectionnée ; l’année active est sélectionnée par défaut.

## Règle annuelle

La moyenne est pondérée par les coefficients des matières dont les notes sont publiées. Une matière validée (`note >= 10`) apporte ses crédits. La décision automatique est « admis » si la moyenne atteint `UIST_ADMISSION_AVERAGE` (10 par défaut) et si les crédits requis du niveau sont obtenus ; sinon elle est « ajourné ». Une décision finale différente de la proposition automatique exige un motif et est inscrite dans `journal_modifications`.
