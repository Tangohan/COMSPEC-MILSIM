# SSE — impossible de relier les éléments entre eux

## Contexte

Portail SSE, page des corrélations d'un dossier (`/atak/sse/dossiers/{id}/correlations`)
et toile d'investigation (`/atak/sse/toiles/{id}`). Signalé depuis le registre des
investigations, où toutes les toiles affichaient « 0 liens ».

## Symptôme

- Dans un dossier, on ne pouvait poser un lien qu'**entre deux personnes**. Les sites,
  pièces, saisies, pièces à conviction et documents s'affichaient dans le graphe mais
  n'étaient jamais proposés dans le formulaire.
- Les pièces à conviction et les documents rattachés au dossier n'apparaissaient pas du
  tout comme éléments du graphe, alors que la base porte déjà le rattachement
  (`sse_case_evidence.person_id`, `sse_documents.case_id`).
- Sur la toile, relier deux entités obligeait à ouvrir l'onglet « Construire » et à
  choisir deux entités dans des listes déroulantes ; rien ne permettait de tirer un lien
  sur le canevas, ce qui est le geste attendu sur un graphe.
- Le registre affichait « 1 entités » (pluriel forcé).

## Cause

- `SsePortalController::caseRelationStore` forçait `from_type` et `to_type` à `person`
  et lisait `from_id` / `to_id`, alors que `sse_relations` porte déjà un type libre des
  deux côtés.
- `SseCorrelationService::graphForCase` ne construisait de nœuds que pour les personnes,
  les sites, les saisies, et les pièces citées par une arête.
- `SseCaseRepository::listEvidence` n'exposait pas `person_id`, le rattachement stocké
  restait donc invisible.
- `public/assets/js/sse-mesh.js` n'avait aucun geste de pose de lien : le canevas ne
  gérait que la sélection et le déplacement.

## Correctif

- Le graphe du dossier expose désormais **six natures d'éléments** : personnes, sites,
  pièces, saisies, pièces à conviction, documents. Les pièces d'un site et les pièces à
  conviction rattachées à une personne génèrent leurs liens déduits.
- Le formulaire de pose de lien désigne les deux extrémités par `type:identifiant`, avec
  deux listes groupées par nature ; le contrôleur vérifie que les deux éléments
  appartiennent bien au graphe du dossier avant d'enregistrer.
- La toile propose un mode « Relier deux entités » (clic départ puis clic arrivée) et le
  raccourci **Maj + glisser** d'une entité vers une autre. Une fiche de lien s'ouvre sur
  le canevas pour la nature, la fiabilité et la justification, puis poste sur la route
  existante.
- Disposition du formulaire de corrélation corrigée : il était happé par la règle
  « formulaire de saisie en une colonne » du thème bureau et s'affichait en escalier.
- Pluriel corrigé sur les compteurs du registre des investigations.

## Fichiers touchés

- `app/Services/Sse/SseCorrelationService.php`
- `app/Services/Sse/SseMeshService.php`
- `app/Services/Sse/SseCasePdfService.php`
- `app/Repositories/SseCaseRepository.php`
- `app/Controllers/Web/SsePortalController.php`
- `views/atak/sse/case_correlations.php`
- `views/atak/sse/mesh_show.php`
- `views/atak/sse/meshes.php`
- `views/atak/sse/_layout.php`
- `public/assets/js/sse-mesh.js`
- `public/assets/css/sse_portal.css`

## Vérification

- `php -l` sur les fichiers PHP modifiés.
- Aperçu local des deux écrans : pose de lien sur le canevas (clic-clic et Maj + glisser)
  jusqu'à l'ouverture de la fiche de lien avec les bons identifiants et la bonne route ;
  formulaire de corrélation affichant les six natures d'éléments dans les deux listes.

## Statut

Corrigé.
