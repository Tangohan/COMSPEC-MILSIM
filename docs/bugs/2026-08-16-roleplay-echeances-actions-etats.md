# Échéances roleplay — actions génériques « Gérer »

## Contexte

Écran `/back-office/roleplay-followup/echeances` (Entretien / Médical / Rotation).

## Symptôme

Les trois colonnes affichaient la même date et le même bouton « Gérer », sans état lisible ni action métier.

## Correctif

- États : Non programmé / Programmé / Bientôt (≤ 14 j) / En retard, avec pastille colorée.
- Actions propres : Programmer / Planifier la visite / Planifier la rotation ; Reporter… ; Entretien fait / Visite faite / Rotation faite.
- Dialogue adapté au type (libellés, date, boutons).

## Fichiers touchés

- `views/admin/organization/roleplay_deadlines.php`

## Vérification

Ouvrir Échéances : chaque colonne montre un état distinct et des boutons différents selon la situation.

## Statut

corrigé
