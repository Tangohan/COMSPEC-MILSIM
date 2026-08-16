# On ne comprend pas comment se constitue un dossier

## Contexte

Portail SSE. La barre de navigation propose 23 entrées classées par nature d'objet
(Pilotage / Objets / Analyse / Exploitation), et deux natures de dossiers coexistent :
les dossiers d'intérêt (`DI-…`) et les dossiers (`SSE-…`).

## Symptôme

Un utilisateur ouvrant le portail ne sait pas par où commencer ni comment un dossier
se constitue. Rien n'indique l'ordre de travail, ni ce qu'il manque à un dossier
donné pour être exploitable.

## Cause

Deux causes distinctes, dont une structurelle.

1. **Le maillon central n'existait pas.** Aucun chemin ne reliait un dossier d'intérêt
   à un dossier : ni action, ni colonne en base, ni route. Un dossier d'intérêt pouvait
   être instruit à fond, il ne devenait jamais un dossier. « Constituer un dossier
   complet » n'avait donc littéralement pas de trajet dans l'application.
2. **La navigation est un inventaire d'outils, pas un parcours.** La numérotation
   01 → 23 laisse croire à un ordre qui n'existe pas (« 17 Collecte terrain » après
   « 23 Atelier de préparation »). Et un dossier n'a que quatre états (Ouvert,
   En cours, Clos, Archivé), sans aucune notion de complétude : rien ne dit ce qui
   lui manque.

## Correctif

- Colonne `sse_cases.interest_case_id` (NULL assumé pour les dossiers existants :
  leur origine est inconnue, l'inventer serait un faux). Migration idempotente,
  appliquée aussi à la volée par `SseCaseRepository`.
- Action « Constituer le dossier » sur un dossier d'intérêt
  (`POST /atak/sse/interet/{id}/constituer`) : ouvre un dossier reprenant le motif
  d'ouverture, les observations et les faits déjà consignés, garde le lien d'origine,
  journalise l'opération et refuse une seconde constitution.
- Origine affichée des deux côtés : le dossier d'intérêt pointe vers le dossier
  constitué, le dossier rappelle le dossier d'intérêt dont il est issu.
- Nouveau panneau « Où en est ce dossier » (`views/atak/sse/partials/case_progress.php`) :
  cinq étapes dans l'ordre de travail (désigner qui est concerné, situer les faits,
  verser les pièces, relier les éléments, rédiger et conclure), chacune avec ce qui
  est déjà au dossier et le bouton qui mène à l'écran correspondant.
- Verdict de complétude : un dossier est exploitable dès lors qu'au moins une identité
  y est rattachée — sans elle, il ne désigne personne. Les autres étapes jalonnent
  le travail sans bloquer.

## Fichiers touchés

- `bootstrap/atak_sse_case_origin_migration.php` (nouveau)
- `views/atak/sse/partials/case_progress.php` (nouveau)
- `app/Repositories/SseCaseRepository.php`
- `app/Controllers/Web/SsePortalController.php`
- `routes/web.php`
- `run-migrations.php`
- `views/atak/sse/case_show.php`
- `views/atak/sse/interest_case_show.php`
- `public/assets/css/sse_portal.css`

## Vérification

- `php -l` sur tous les fichiers modifiés.
- Rendu vérifié au navigateur : dossier incomplet (aucune identité), dossier
  exploitable, dossier d'intérêt sans dossier constitué, dossier d'intérêt déjà
  converti, et mode lecture seule.

## Reste à faire

La navigation latérale reste un inventaire d'outils : sa réorganisation en parcours
(Ouvrir → Collecter → Analyser → Conclure) et la correction de la numérotation
trompeuse n'ont pas été traitées ici.

## Statut

Corrigé pour le chaînage et l'avancement ; navigation à revoir.
