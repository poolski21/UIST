<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #1f2937; font-size: 12px; }
        h1, h2 { color: #692333; }
        h1 { margin-bottom: 4px; font-size: 18px; }
        h2 { margin-top: 8px; font-size: 15px; }
        .brand { border-bottom: 3px solid #1a93e8; padding-bottom: 12px; margin-bottom: 20px; }
        .meta { margin: 18px 0; line-height: 1.8; }
        table { width: 100%; border-collapse: collapse; margin-top: 14px; }
        th, td { padding: 9px; border: 1px solid #d1d5db; text-align: left; }
        th { background: #eaf5fd; }
    </style>
</head>
<body>
    <div class="brand">
        <h1>Université Internationale des Sciences et Technologie</h1>
        <div>Cocody Riviera, Abidjan · Côte d’Ivoire</div>
        <h2>Liste de classe</h2>
    </div>
    <div class="meta">
        <strong>Filière :</strong> {{ $filiere->nom }}<br>
        <strong>Niveau :</strong> {{ $niveau->nom }}<br>
        <strong>Année universitaire :</strong> {{ $annee->libelle }}<br>
        <strong>Date d’édition :</strong> {{ $dateEdition }}
    </div>
    <table>
        <thead>
            <tr><th>Matricule</th><th>Nom</th><th>Prénom</th></tr>
        </thead>
        <tbody>
            @foreach ($etudiants as $etudiant)
                <tr>
                    <td>{{ $etudiant['matricule'] }}</td>
                    <td>{{ $etudiant['nom'] }}</td>
                    <td>{{ $etudiant['prenom'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
