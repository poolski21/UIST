<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #1f2937; font-size: 12px; }
        h1, h2 { color: #692333; }
        h1 { margin-bottom: 3px; }
        .brand { border-bottom: 3px solid #1a93e8; padding-bottom: 12px; margin-bottom: 20px; }
        .meta { margin: 18px 0; line-height: 1.8; }
        table { width: 100%; border-collapse: collapse; margin-top: 14px; }
        th, td { padding: 9px; border: 1px solid #d1d5db; text-align: left; }
        th { background: #eaf5fd; }
        .result { margin-top: 22px; padding: 12px; background: #f4f6f8; font-size: 14px; }
    </style>
</head>
<body>
    <div class="brand"><h1>Université Internationale des Sciences et Technologie</h1><div>Cocody Riviera, Abidjan · Côte d’Ivoire</div><h2>Relevé de notes</h2></div>
    <div class="meta">
        <strong>Étudiant :</strong> {{ $resultat->etudiant->name }}<br>
        <strong>Matricule :</strong> {{ $resultat->etudiant->matricule }}<br>
        <strong>Filière :</strong> {{ $resultat->etudiant->filiere?->nom ?? '—' }}<br>
        <strong>Niveau :</strong> {{ $resultat->etudiant->niveau?->nom ?? '—' }}<br>
        <strong>Année académique :</strong> {{ $resultat->anneeAcademique->libelle }}<br>
        <strong>Date d’édition :</strong> {{ $dateEdition->format('d/m/Y') }}
    </div>
    <table>
        <thead><tr><th>Matière</th><th>Coefficient</th><th>Moyenne matière / 20</th><th>Statut</th></tr></thead>
        <tbody>
            @foreach ($notes as $note)
                <tr>
                    <td>{{ $note->matiere->nom }}</td>
                    <td>{{ number_format($note->matiere->coefficient, 2, ',', ' ') }}</td>
                    <td>{{ number_format($note->note, 2, ',', ' ') }}</td>
                    <td>{{ $note->note >= 10 ? 'Validée' : 'Non validée' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <div class="result">
        <strong>Moyenne générale :</strong> {{ number_format($resultat->moyenne_generale, 2, ',', ' ') }} / 20<br>
        <strong>Crédits obtenus :</strong> {{ $resultat->credits_obtenus }}<br>
        <strong>Décision finale :</strong> {{ strtoupper($resultat->decision_finale ?? $resultat->decision_auto) }}
    </div>
</body>
</html>
