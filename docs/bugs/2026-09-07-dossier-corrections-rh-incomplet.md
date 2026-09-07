# Corrections RH — dossier incomplet et emploi affiché en numéro

## Contexte

Les responsables éditent un membre depuis **Corrections RH** (`/back-office/personnel/corrections?membre=…`). L’édition classique du dossier les renvoie vers Effectifs. Cette page était donc devenue le lieu réel de correction.

## Symptôme

- Le formulaire ne couvrait qu’une partie de la fiche (physique, une affectation, un emploi, équipement).
- L’emploi principal pouvait s’afficher comme un numéro.
- Notes de commandement, matricule, habilitation, immersion, décorations, plusieurs équipes et le portrait n’étaient pas accessibles ici.

## Cause

Le catalogue membre (`CORRECTABLE_FIELDS`) était aussi servi aux responsables. La liste d’emplois était filtrée (catalogue membre), donc un emploi hors filtre n’avait plus de libellé. Une seule ligne d’unité et d’emploi était proposée.

## Correctif

- Catalogue étendu pour un responsable : personnage, plusieurs affectations et emplois, immersion, équipement, identifiants, notes, portrait.
- Interface en onglets, en-tête du membre, jusqu’à quatre équipes et quatre emplois.
- Liste d’emplois complète pour un responsable, avec le nom de la fonction.
- Un membre continue de ne proposer que les champs de sa fiche, sans notes de commandement ni matricule.

## Fichiers touchés

- `app/Services/Personnel/PersonnelCorrectionRequestService.php`
- `app/Controllers/Web/PersonnelCorrectionController.php`
- `app/Controllers/Web/PersonnelController.php`
- `views/personnel/correction_form.php`
- `views/personnel/corrections_queue.php`
- `public/assets/css/personnel-dossier.css`
- `public/assets/css/back-office-corrections.css`

## Vérification

- Contrôle syntaxe PHP des fichiers modifiés.
- Tests d’assets formulaire / file d’attente et UPDATE #475.

## Statut

corrigé
