# Comptes rendus envoyés depuis le web (QR code) : rien en jeu

- **Contexte** : retour du propriétaire, page mobile `/m/cr` ouverte par le QR code de l'app Comptes rendus.
- **Symptôme** : la page annonce l'envoi par l'ATAK, mais les autres n'ont ni notification ni app à ouvrir, et rien dans l'app, ni reçu ni envoyé.
- **Cause prouvée par lecture** : `fn_reportAction` « mobile » compare `_arg select [0, 8]` (8 caractères : `["MCR1",`) à `"[""MCR1"""` (7 caractères : `["MCR1"`). La comparaison échoue toujours : la fiche est abandonnée sans message. Même erreur pour `MDB1` (CR fin d'op rempli sur le téléphone) et `DBF1` (fiche de CR fin d'op imposée par le site, `fn_debrief`). La page dit « envoyé » car `fn_adminNotices` valide l'avis (`NoticeAck`) dès la réception, avant le traitement. Corps du site (`AtakMobileReportCatalog::noticeBody`, `AtakDebriefSpec`) et transport DLL (`AdminControl.Poll`) relus : format correct.
- **Correctif** : préfixe comparé sur 7 caractères aux trois endroits. La fiche passe ensuite par « send », comme une fiche remplie en jeu : diffusion, notification, onglets ENVOYÉS et REÇUS.
- **Non traité** : une fiche jugée incomplète en jeu reste en brouillon alors que la page annonce « Envoyé par votre ATAK » (accusé donné à la réception). Le site et le jeu ont les mêmes champs requis ; cas rare.
- **Fichiers touchés** : `functions/reports/fn_reportAction.sqf`, `functions/debrief/fn_debrief.sqf`.
- **Vérification** : en jeu à deux joueurs : envoyer un CONTACT depuis le QR code : ENVOYÉS chez l'auteur, notification et REÇUS chez l'autre, enregistré sur Athena. Remplir un CR fin d'op sur le téléphone. Imposer un CR fin d'op depuis le site : la fiche s'ouvre dans l'app.
- **Statut** : corrigé à vérifier en jeu.
