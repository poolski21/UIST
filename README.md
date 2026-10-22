# UIST2its

Portail web de l’Université Internationale des Sciences et Technologie (Cocody Riviera, Abidjan), réalisé avec Laravel 13, Inertia.js, React, Tailwind CSS et MySQL.

## Prérequis

- PHP 8.3 ou supérieur et Composer
- Node.js 20.19+ / 22.12+ et npm
- MySQL 8.0+ (ou MariaDB compatible)

## Installation

```bash
composer install
copy .env.example .env
php artisan key:generate
```

Créer au préalable une base MySQL `uist2its`, puis configurer dans `.env` `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME` et `DB_PASSWORD`. Configurer également `MAIL_*` pour les notifications par courriel.

```bash
php artisan migrate --seed
npm install
npm run build
php artisan storage:link
```

Pour lancer l’application en développement, exécuter `php artisan serve` et `npm run dev` dans deux terminaux. Les notifications sont mises en file d’attente : démarrer également un worker avec `php artisan queue:work`.

## Comptes de démonstration

Après le seeding :

- Administration : `admin@uist.ci`. À la création initiale, le mot de passe est lu depuis `ADMIN_INITIAL_PASSWORD` ou généré aléatoirement et affiché une seule fois dans la console. Le changement est obligatoire à la première connexion.
- Enseignant : `enseignant1@uist.ci` / `EnseignantUIST!1`
- Enseignant : `enseignant2@uist.ci` / `EnseignantUIST!2`
- Étudiants : matricules `UIST-2026-0001` à `UIST-2026-0010`, sans mot de passe.

Les comptes et mots de passe enseignants ci-dessus sont exclusivement destinés au développement local et doivent être remplacés avant tout déploiement. Le seeder ne modifie jamais le mot de passe d’un compte administrateur déjà existant.

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

## Tests

```bash
php artisan test
```

Les tests utilisent SQLite en mémoire et vérifient la connexion par matricule, les permissions et le calcul pondéré de la décision annuelle.
