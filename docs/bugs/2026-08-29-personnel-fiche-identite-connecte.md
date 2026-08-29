# Bug — fiche personnel d’un tiers affiche matricule / données du connecté

## Symptôme

Un membre connecté ouvre la fiche d’un autre opérateur. Le matricule, le grade,
le portrait ou d’autres champs du dossier affichés sont ceux **du connecté**,
pas ceux de la fiche consultée. En changeant de fiche, certains blocs semblent
identiques d’un membre à l’autre.

## Cause

Le bandeau Athena (`header_portal.php` → `athena_caverne_header.php`) assignait
`$personnelProfile`, `$personnelExtras` et `$grade` avec le dossier du **viewer**
dans le même scope PHP que la fiche (`personnel/file.php`), après l’`extract`
du contrôleur. La vue fiche consommait ensuite ces variables écrasées.

Secondaire : prénom/nom pouvaient être reconstitués depuis le `display_name` ou
une candidature, ce qui faisait « remplir » des champs manquants et renforçait
l’impression de doublon.

## Correctif

- Variables bandeau isolées (`$headerPersonnelProfile`, `$headerGrade`, …).
- Garde sur `user_id` du profil vs sujet de la fiche.
- Plus de substitution silencieuse : affichage « Donnée manquante ».
- Grade détail : libellé affiché à côté du code quand ils diffèrent.
- Back-office : alerte doublons configurable (matricule, callsign, nom…).

## Vérification

1. Se connecter en A, ouvrir `/personnel/{id_de_B}` : matricule/grade de B (ou « Donnée manquante »), jamais ceux de A.
2. Ouvrir deux fiches différentes : les champs dossier ne se recopient plus.
3. Bureau effectifs → Doublons : régler les champs et voir l’alerte sur le tableur.
