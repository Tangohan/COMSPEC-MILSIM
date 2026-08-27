# Bug — RP Morphide sous Noopy + affectations sans explication

## Contexte

Dashboard SOAR, tableau rapide Effectifs. Compte Athena rattaché à SOAR sous le nom Noopy.

## Symptôme

- Sous **Noopy**, libellé `RP · MORPHIDE` (et la fiche ouverte via l’URL Noopy semblait être Morphide).
- Colonne **Affectation** (`B SQN, A TRP HQ`, `24th STS…`) sans explication de l’unité dans l’organigramme.

## Cause

1. La fiche et le tableau utilisaient `personnel_profiles.character_name` comme identité principale. Si ce champ contenait « MORPHIDE » (souvent poussé silencieusement depuis un profil de candidature), le compte Noopy semblait être Morphide.
2. L’affectation n’affichait que le nom d’unité, sans chemin d’organigramme ni présentation.

## Correctif

- Identité principale = nom de compte ; personnage affiché seulement s’il diffère.
- Arrêt de l’autofill silencieux de `character_name` depuis les profils de candidature.
- Info-bulles d’affectation (chemin organigramme, code, présentation).

## Fichiers touchés

- `app/Support/PersonnelDirectoryHints.php`
- `app/Repositories/UserRepository.php`
- `app/Services/Profile/RecruitmentPresetPayloadService.php`
- `views/partials/dashboard_effectifs_table.php`
- `views/personnel/file.php`, `edit.php`, `directory.php`
- `tests/Unit/PersonnelDirectoryHintsTest.php`

## Vérification

`phpunit tests/Unit/PersonnelDirectoryHintsTest.php` ; contrôle manuel tableau effectifs (info-bulle + Personnage distinct).

## Statut

Corrigé (données déjà faussées : vider ou corriger le nom de personnage sur la fiche Noopy si besoin).
