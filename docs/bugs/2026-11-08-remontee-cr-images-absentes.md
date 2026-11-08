# Remontée de compte rendu : images jointes absentes

- **Contexte** : fiche `/back-office/remontees/cr/{id}` (rapport envoyé depuis le terrain).
- **Symptôme** : aucune image n'apparaît dans « Pièces jointes », alors que le joueur a pris des photos avec son compte rendu.
- **Cause** : la fiche ne lisait que la table `atak_report_attachments`, que rien n'alimente. Le mod envoie dans le champ « photo » du rapport le nom du cliché sur le poste (`fn_reportAction.sqf`) ; la photo elle-même arrive dans `recon_images`, avec ce nom dans `source_name`. La fiche affichait ce nom en texte, sans jamais chercher l'image.
- **Correctif** : `IntelIntakeService::find()` retrouve la photo par `source_name` dans la communauté du rapport (celle de l'auteur d'abord, puis la plus proche de l'envoi). L'adresse vient de `ReconImageStorage::urlForRow()` : une photo confidentielle passe par sa route authentifiée. Une photo prise mais pas encore transmise n'apparaît qu'une fois reçue.
- **Fichiers touchés** : `app/Services/Intel/IntelIntakeService.php`, `tests/Unit/IntelIntakeTest.php`.
- **Vérification** : test du nom de fichier en PHPUnit, requête non exécutée sur une vraie base. À vérifier en production avec un rapport qui a une photo.
- **Statut** : corrigé, à vérifier en production.
