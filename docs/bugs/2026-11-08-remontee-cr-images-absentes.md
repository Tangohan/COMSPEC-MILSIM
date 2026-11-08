# Remontée de compte rendu : images jointes absentes

- **Contexte** : fiche `/back-office/remontees/cr/{id}` (rapport envoyé depuis le terrain).
- **Symptôme** : aucune image n'apparaît dans « Pièces jointes », alors que le joueur a pris des photos avec son compte rendu.
- **Cause** : la fiche ne lisait que la table `atak_report_attachments`, que rien n'alimente. Les photos du terrain sont enregistrées dans `recon_images` (même session de jeu, même indicatif) et n'étaient jamais rattachées au rapport.
- **Correctif** : `IntelIntakeService::find()` ajoute les photos nommées dans le champ « photo » du rapport, et celles du même auteur sur la même session, prises de 2 h avant à 15 min après l'envoi, dans la communauté du rapport. L'adresse vient de `ReconImageStorage::urlForRow()` : une photo confidentielle passe par sa route authentifiée.
- **Fichiers touchés** : `app/Services/Intel/IntelIntakeService.php`.
- **Vérification** : lecture du code, `php -l`. À vérifier en production avec un rapport qui a des photos.
- **Statut** : corrigé, à vérifier en production.
