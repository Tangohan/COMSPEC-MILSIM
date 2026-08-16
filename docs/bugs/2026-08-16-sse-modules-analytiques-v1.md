# Modules analytiques SSE — appréciation, lacunes, décisions, mentions contextuelles

## Contexte
Le dossier SSE restait surtout documentaire (notes libres + mentions). Manquaient
la chaîne analytique structurée, le suivi des lacunes, un registre de décisions
append-only et un générateur de mentions piloté par l’état du dossier.

## Symptôme
Impossible de distinguer fait / source / appréciation / confiance / hypothèse ;
pas de journal « avant → après » ; suggestions de mentions limitées au regex client.

## Cause
Absence de tables et d’UI dédiées ; bibliothèque sans doctrine / fragments ;
pas de moteur de règles serveur.

## Correctif (V1)
1. **Appréciation analytique** — FAIT → SOURCE (origine + cotation A–F / 1–6) → RECOUPEMENT → APPRÉCIATION → CONFIANCE (justifiée) → H1/H2/H3 + temporalité / urgence / divergence
2. **Lacunes et besoins** — lacune / besoin / critère de confirmation, priorité, responsable, échéance
3. **Registre des décisions** — append-only (date, domaine, avant, après, motif, analyste)
4. **Générateur contextuel** — suggestions serveur selon personnes, appréciations, lacunes, liens, inactivité
5. Relations entre dossiers (parent, dérivé, connexe, source, doublon, fusion/dissociation)
6. Synthèse exécutive générée automatiquement
7. Bibliothèque enrichie : doctrines, fragments, temporalité, urgence, déconfliction, variables conditionnelles `{{si.axe.valeur:texte}}`

## Fichiers touchés
- `bootstrap/atak_sse_analytical_migration.php`, `run-migrations.php`
- `app/Support/SseAnalyticalCatalog.php`, `SseTextLibraryCatalog.php`
- `app/Repositories/SseAnalyticalRepository.php`, `SseTextTemplateRepository.php`
- `app/Services/Sse/SseContextualMentionService.php`
- `app/Controllers/Web/SsePortalController.php`, `routes/web.php`
- `views/atak/sse/partials/case_analytical.php`, `case_show.php`, `document_form.php`
- `public/assets/css/sse_portal.css`

## Vérification
1. Lancer les migrations
2. Ouvrir un dossier → panneaux 01.08–01.13
3. Créer une appréciation (justification obligatoire) → apparaît + ligne au registre
4. Ajouter une lacune prioritaire → suggestion « Demande de recherche »
5. Lier un dossier « doublon potentiel » → mention déconfliction
6. Rédaction : chips enrichies par suggestions serveur

## Statut
corrigé (V1 livrée)
