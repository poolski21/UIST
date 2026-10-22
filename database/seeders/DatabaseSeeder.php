<?php

namespace Database\Seeders;

use App\Models\AnneeAcademique;
use App\Models\Annonce;
use App\Models\CategorieCours;
use App\Models\Cours;
use App\Models\EmploiDuTemps;
use App\Models\Filiere;
use App\Models\Inscription;
use App\Models\Matiere;
use App\Models\Niveau;
use App\Models\Note;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $filiereDefinitions = [
            'SCI' => 'Science Informatique',
            'DROIT' => 'Droit',
            'SEGDE' => "Science Economie et Gestion d'Entreprise",
            'STAT' => 'Statistique',
            'IDA' => 'IDA',
            'GEC' => 'GEC',
        ];

        $niveauDefinitions = [
            ['code' => 'L1', 'nom' => 'Licence 1', 'credits_requis' => 60],
            ['code' => 'L2', 'nom' => 'Licence 2', 'credits_requis' => 60],
            ['code' => 'L3', 'nom' => 'Licence 3', 'credits_requis' => 60],
            ['code' => 'M1', 'nom' => 'Master 1', 'credits_requis' => 60],
            ['code' => 'M2', 'nom' => 'Master 2', 'credits_requis' => 60],
            ['code' => 'BTS1', 'nom' => 'BTS 1', 'credits_requis' => 60],
            ['code' => 'BTS2', 'nom' => 'BTS 2', 'credits_requis' => 60],
        ];

        $filieres = [];
        foreach ($filiereDefinitions as $code => $nom) {
            $filieres[$code] = Filiere::updateOrCreate(
                ['code' => $code],
                ['nom' => $nom, 'description' => 'Formation proposée par l’Université Internationale des Sciences et Technologie.'],
            );

            foreach ($niveauDefinitions as $niveauData) {
                Niveau::updateOrCreate(
                    ['filiere_id' => $filieres[$code]->id, 'code' => $niveauData['code']],
                    ['nom' => $niveauData['nom'], 'credits_requis' => $niveauData['credits_requis']],
                );
            }
        }

        $filiere = $filieres['SCI'];
        $niveau = $filieres['SCI']->niveaux()->where('code', 'M1')->firstOrFail();

        $annee = AnneeAcademique::updateOrCreate(
            ['libelle' => '2026-2027'],
            ['date_debut' => '2026-09-01', 'date_fin' => '2027-07-31', 'active' => true],
        );
        AnneeAcademique::whereKeyNot($annee->id)->update(['active' => false]);

        $admin = User::query()->where('email', 'admin@uist.ci')->first();
        if ($admin && $admin->role !== 'admin') {
            throw new \RuntimeException('admin@uist.ci existe, mais ce compte n’est pas administrateur.');
        }
        if (! $admin) {
            $password = config('uist2its.admin_initial_password');
            $generatedPassword = ! is_string($password) || trim($password) === '';

            if ($generatedPassword) {
                $password = bin2hex(random_bytes(24));
            } elseif (mb_strlen($password) < 16) {
                throw new \InvalidArgumentException('ADMIN_INITIAL_PASSWORD doit contenir au moins 16 caractères.');
            }

            $admin = User::create([
                'email' => 'admin@uist.ci',
                'name' => 'Administration UIST',
                'role' => 'admin',
                'password' => $password,
            ]);
            $admin->forceFill(['password_change_required' => true])->save();

            if ($generatedPassword) {
                $this->command?->warn('Mot de passe initial admin (à conserver de façon sûre, affiché une seule fois) :');
                $this->command?->line($password);
            } else {
                $this->command?->info('Compte admin initial créé ; le changement du mot de passe sera requis à la première connexion.');
            }
        }
        $enseignant1 = User::updateOrCreate(
            ['email' => 'enseignant1@uist.ci'],
            ['name' => 'Dr. Awa Kouassi', 'role' => 'teacher', 'password' => 'EnseignantUIST!1'],
        );
        $enseignant2 = User::updateOrCreate(
            ['email' => 'enseignant2@uist.ci'],
            ['name' => 'M. Serge Yao', 'role' => 'teacher', 'password' => 'EnseignantUIST!2'],
        );

        $matiere1 = Matiere::updateOrCreate(
            ['code' => 'IPI-MATH-101'],
            ['nom' => 'Mathématiques appliquées', 'filiere_id' => $filiere->id, 'niveau_id' => $niveau->id, 'enseignant_id' => $enseignant1->id, 'credits' => 30, 'coefficient' => 2],
        );
        $matiere2 = Matiere::updateOrCreate(
            ['code' => 'IPI-INFO-102'],
            ['nom' => 'Algorithmique et programmation', 'filiere_id' => $filiere->id, 'niveau_id' => $niveau->id, 'enseignant_id' => $enseignant2->id, 'credits' => 30, 'coefficient' => 3],
        );

        $prenoms = ['Aya', 'Koffi', 'Mariam', 'Yann', 'Fatou', 'Serge', 'Aminata', 'Jean', 'Aïcha', 'Boris'];
        foreach ($prenoms as $index => $prenom) {
            $numero = $index + 1;
            $student = User::updateOrCreate(
                ['matricule' => sprintf('UIST-2026-%04d', $numero)],
                [
                    'name' => $prenom.' '.['Traoré', 'Kouamé', 'Diallo', 'N’Guessan', 'Koné'][$index % 5],
                    'email' => sprintf('etudiant%02d@exemple.ci', $numero),
                    'role' => 'student',
                    'filiere_id' => $filiere->id,
                    'niveau_id' => $niveau->id,
                    'date_naissance' => sprintf('%04d-%02d-%02d', 2000 + ($index % 5), 1 + ($index % 12), 5 + $index),
                    'password' => null,
                ],
            );
            Inscription::updateOrCreate(
                ['etudiant_id' => $student->id, 'annee_academique_id' => $annee->id],
                ['niveau_id' => $niveau->id],
            );

            Note::updateOrCreate(
                ['etudiant_id' => $student->id, 'matiere_id' => $matiere1->id, 'annee_academique_id' => $annee->id],
                ['note' => 9 + $index, 'statut' => 'publie', 'saisie_par' => $enseignant1->id, 'publie_le' => now()],
            );
            Note::updateOrCreate(
                ['etudiant_id' => $student->id, 'matiere_id' => $matiere2->id, 'annee_academique_id' => $annee->id],
                ['note' => 10 + ($index % 8), 'statut' => 'publie', 'saisie_par' => $enseignant2->id, 'publie_le' => now()],
            );
        }

        $categoryCounts = [
            'Divers' => 15,
            'MASTER EN ISE' => 10,
            'MASTER EN SAS' => 17,
            'MASTER EN FDA' => 10,
            'TRONC COMMUN IPI-ISE-IA' => 22,
            'MASTER EN IA' => 8,
            'MASTER EN ACG' => 16,
            'MASTER EN IPI' => 2,
            'ADMINISTRATION' => 1,
        ];
        $sampleTitles = ['CAPM', 'Principes de la cryptographie', 'Introduction à l’IA', 'Entreprise en difficulté'];
        foreach ($categoryCounts as $categoryName => $count) {
            $category = CategorieCours::updateOrCreate(
                ['slug' => Str::slug($categoryName)],
                ['nom' => $categoryName],
            );
            for ($i = 1; $i <= $count; $i++) {
                $title = $categoryName === 'Divers' && $i <= count($sampleTitles)
                    ? $sampleTitles[$i - 1]
                    : $categoryName.' · Cours '.str_pad((string) $i, 2, '0', STR_PAD_LEFT);
                Cours::updateOrCreate(
                    ['categorie_cours_id' => $category->id, 'titre' => $title],
                    ['description' => 'Ressource pédagogique proposée par l’Université Internationale des Sciences et Technologie.', 'actif' => true],
                );
            }
        }

        Annonce::updateOrCreate(
            ['titre' => 'Bienvenue sur UIST2its'],
            [
                'contenu' => 'Le portail étudiant UIST2its centralise les informations administratives, les emplois du temps, les notes publiées et les décisions annuelles.',
                'cible_type' => 'tous',
                'cible_id' => null,
                'cree_par' => $admin->id,
                'publiee_le' => now(),
            ],
        );
        EmploiDuTemps::updateOrCreate(
            ['titre' => 'Algorithmique et programmation', 'date_cours' => now()->startOfWeek()->toDateString()],
            [
                'filiere_id' => $filiere->id,
                'niveau_id' => $niveau->id,
                'description' => 'Cours magistral et travaux dirigés.',
                'heure_debut' => '08:00',
                'heure_fin' => '10:00',
                'salle' => 'Salle A1',
                'statut' => 'publie',
                'publie_par' => $admin->id,
                'publie_le' => now(),
            ],
        );
    }
}
