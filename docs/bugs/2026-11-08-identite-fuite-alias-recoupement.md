# COMSPEC IDENTITY : alias visibles sans habilitation, recherche de numéro, contrôles non recoupés

## Contexte
Audit de COMSPEC IDENTITY. Un infiltré ne doit pas être démasqué par la consultation d'une fiche.

## Symptôme
- Onglet « Identité & papiers » : un responsable sans habilitation Confidentiel voyait les alias, leurs papiers, et devinait une couverture à un kit vide ou à un nom présenté différent.
- `SseIdCheckService::store` : un numéro exact remonté derrière un numéro plus récent de même préfixe était traité comme inconnu.
- Fiche personne : les contrôles restaient sans personne ni identité malgré une clé biométrique déjà connue.

## Cause
- `MemberIdentityPapersService::SECRET` ne comptait pas `alias` ; le kit gardait sa ligne une fois ses papiers masqués ; la fiche personne listait les contrôles sans filtre d'habilitation.
- `list(..., 'q', 'limit' => 5)[0]` : seule la première ligne de la recherche partielle était comparée.
- Aucune fiche ne portait la clé `subject_bio_key`.

## Correctif
- `alias` entre dans la vérité de jeu : identités, papiers, kits, décomptes de la liste et contrôles masqués sans Confidentiel ; création d'une identité d'emprunt réservée à Confidentiel.
- Recherche de la correspondance exacte parmi les 5 résultats. Limite connue : au-delà de 5 numéros contenant le numéro cherché, l'exact peut sortir de la fenêtre.
- `SseIdSubjectMatchService` : lien automatique seulement sans ambiguïté, apprentissage sur un rattachement sûr (un papier volé n'enseigne rien), suggestions « possiblement la même personne » pour Confidentiel, jamais de fusion.

## Fichiers touchés
`app/Services/Sse/MemberIdentityPapersService.php`, `app/Services/Sse/SseIdCheckService.php`, `app/Services/Sse/SseIdSubjectMatchService.php`, `app/Services/Sse/SseIdCheckInsightService.php`, `app/Controllers/Web/SseIdentityRegistryController.php`, `views/admin/effectifs_workspace/partials/member_papers.php`, `views/atak/sse/papers/_person_block.php`, `bootstrap/atak_sse_identity_suite_migration.php`, `database/steps/4000_atak_sse_identity_suite.php`.

## Vérification
`tests/Unit/MemberIdentityPapersServiceTest.php`, `tests/Unit/SseIdSubjectMatchServiceTest.php`, `MigrationActiveQueueTest`.

## Statut
corrigé
