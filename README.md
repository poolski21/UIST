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

- Administration : `admin@uist.ci` / `AdminUIST2its!`
- Enseignant : `enseignant1@uist.ci` / `EnseignantUIST!1`
- Enseignant : `enseignant2@uist.ci` / `EnseignantUIST!2`
- Étudiants : matricules `UIST-2026-0001` à `UIST-2026-0010`, sans mot de passe.

Ces identifiants sont exclusivement destinés au développement local et doivent être remplacés avant tout déploiement.

## Connexion et sécurité

Les étudiants s’authentifient avec leur matricule permanent. Le paramètre `UIST_STUDENT_SECOND_FACTOR=true` active la vérification complémentaire de la date de naissance (renseignée par l’administration). Les tentatives par matricule sont limitées par adresse IP et matricule ; chaque succès et échec est conservé dans `connexions_log`.

Les enseignants utilisent leur adresse e-mail et leur mot de passe. Les policies Laravel et le middleware `role` cloisonnent l’administration, la saisie des matières attribuées, les notes personnelles et les décisions publiées. Les pièces jointes sont stockées sur le disque public ; lancer `php artisan storage:link` pour les servir.

## Règle annuelle

La moyenne est pondérée par les coefficients des matières dont les notes sont publiées. Une matière validée (`note >= 10`) apporte ses crédits. La décision automatique est « admis » si la moyenne atteint `UIST_ADMISSION_AVERAGE` (10 par défaut) et si les crédits requis du niveau sont obtenus ; sinon elle est « ajourné ». Une décision finale différente de la proposition automatique exige un motif et est inscrite dans `journal_modifications`.

## Tests

```bash
php artisan test
```

Les tests utilisent SQLite en mémoire et vérifient la connexion par matricule, les permissions et le calcul pondéré de la décision annuelle.
