# CR envoyé depuis /m/cr « Envoyé sur votre ATAK » mais jamais arrivé

## Contexte
Compte rendu rempli sur le vrai téléphone (QR code), confié à l'ATAK du joueur par un avis `mobile_report`.

## Symptôme
La page affiche « Envoyé par votre ATAK ✓ » et le compte rendu n'apparaît pas dans Athena.

## Cause
Deux causes côté site, une troisième à vérifier côté mod (autre enquête) :
1. `AtakAdminControlRepository::pendingForPlayer` rendait les 20 avis les plus anciens non validés (`ORDER BY id ASC LIMIT 20`).
   Un joueur avec vingt avis plus anciens jamais validés (durée de vie 14 jours) ne voyait jamais le nouvel avis `mobile_report`.
2. La réponse de relève n'était pas bornée : COMSPEC Link ne relit qu'une réponse courte, un avis tombé à la fin était coupé.
3. Le texte « Envoyé » était faux : l'ATAK valide l'avis dès qu'il le reçoit (`fn_adminNotices`), avant d'envoyer le
   compte rendu (`fn_reportAction "mobile"`). L'état `acked` veut dire « pris en charge », pas « enregistré sur Athena ».
   Si la fiche est incomplète pour l'ATAK, il la garde en brouillon sans rien renvoyer au site.

## Correctif
- Les avis `mobile_report` et `mobile_debrief` passent en premier dans la relève ; réponse bornée à 6000 octets (un avis
  n'est jamais coupé).
- Texte corrigé : « Pris en charge par votre ATAK » et lien vers l'historique `/m/cr/envoyes`, qui montre ce que le site a
  vraiment enregistré.

## Fichiers touchés
`app/Repositories/AtakAdminControlRepository.php`, `app/Controllers/Api/AtakApiController.php`, `public/assets/js/atak-m.js`,
`tests/Unit/AtakMobileHistoryTest.php`.

## Vérification
Test `testPhoneReportsComeFirstEvenBehindTwentyOlderPendingNotices`. Rien testé en jeu.

## Statut
corrigé à vérifier en jeu
