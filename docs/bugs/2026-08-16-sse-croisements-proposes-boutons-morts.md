# Croisements proposés : les trois boutons ne faisaient rien

## Contexte

Portail SSE, page d'un dossier d'intérêt (`/atak/sse/interet/{id}`), panneau
« P.03 — Croisements proposés ».

## Symptôme

Les boutons « Confirmer », « Maintenir séparé » et « Analyse complémentaire »
étaient inertes : aucun clic n'avait d'effet, aucune décision n'était conservée.
La page annonçait pourtant que « les décisions de rapprochement seront journalisées ».

## Cause

Les trois boutons étaient des `<span class="btn">` décoratifs avec un attribut
`title="Bientôt disponible"` : ni formulaire, ni route, ni stockage. De plus, les
propositions envoyées à la vue ne portaient que du texte (`title`, `detail`, `score`),
sans l'identifiant de l'identité ni celui de l'entrée surveillée : il était donc
impossible de désigner ce sur quoi portait la décision.

## Correctif

- Nouvelle table `sse_cross_decisions` (une décision par couple identité / entrée
  surveillée et par dossier), avec auteur, horodatage, score retenu et justification.
- Nouveau dépôt `SseCrossDecisionRepository` (enregistrement idempotent via
  `ON DUPLICATE KEY UPDATE`, retrait de décision, lecture indexée pour la vue).
- `SsePortalController::crossProposals()` recalcule les rapprochements et y attache
  la décision déjà prise ; les rapprochements non tranchés remontent en tête de liste.
- Nouvelle action `interestCrossDecide` (route `POST /atak/sse/interet/{id}/croisements`) :
  contrôle d'habilitation, jeton anti-rejeu, enregistrement, journalisation
  `SSE_CROSS` dans le journal d'activité, message de confirmation.
- Vue : vrais boutons de formulaire, champ de justification, rappel de la décision
  prise (qui, quand, pourquoi) et bouton « Revenir sur la décision ».
- Le panneau affiche désormais un vrai état vide au lieu d'une fausse proposition
  « Aucun rapprochement automatique fort ».

## Fichiers touchés

- `bootstrap/atak_sse_cross_decisions_migration.php` (nouveau)
- `app/Repositories/SseCrossDecisionRepository.php` (nouveau)
- `app/Controllers/Web/SsePortalController.php`
- `routes/web.php`
- `run-migrations.php`
- `views/atak/sse/interest_case_show.php`
- `views/atak/sse/_layout.php` (version de la feuille de style)
- `public/assets/css/sse_portal.css`

## Vérification

- `php -l` sur tous les fichiers modifiés.
- Rendu vérifié au navigateur sur trois états : rapprochement à trancher,
  rapprochement maintenu séparé, rapprochement confirmé, plus l'état vide et le
  mode lecture seule (aucun bouton, mention de l'habilitation requise).

## Statut

Corrigé.
