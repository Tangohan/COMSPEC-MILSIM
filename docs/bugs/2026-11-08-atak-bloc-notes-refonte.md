# Bloc-notes : refonte de l'app

- **Contexte** : demande du propriétaire : app utile et simple.
- **Symptôme** : trois écrans pour une note (liste, lecture, édition) et deux boutons par ligne ; texte perdu si l'on quitte sans Enregistrer ; date fausse, calculée sur `diag_tickTime`, qui repart à zéro à chaque lancement d'Arma ; `receive` rendait toujours `true`, jamais le résumé attendu par `fn_btItems`.
- **Correctif** : liste à une ligne par note (épinglées d'abord, date réelle de l'horloge du PC, recherche à partir de 7 notes) ; un clic ouvre la note, directement modifiable ; enregistrement automatique en quittant, y compris vers une autre app ; `+ MA GRILLE`, `+ HEURE`, ÉPINGLER, MESSAGE, BLUETOOTH, SUPPRIMER avec ANNULER ; 60 notes au plus. Les notes existantes restent lisibles (date affichée vide jusqu'à leur prochaine modification).
- **Fichiers touchés** : `functions/pages/fn_pageNotes.sqf`, `functions/pages/fn_notesAction.sqf`, `functions/ui/fn_pageRender.sqf`, `i18n/en.txt`.
- **Vérification** : en jeu : anciennes notes visibles ; créer, quitter par le dock, revenir : texte gardé ; note vide abandonnée ; ajouter grille et heure : pas de redessin ; épingler : en tête ; supprimer puis ANNULER ; partage Bluetooth et message ; note reçue en Bluetooth : résumé dans la notification.
- **Statut** : corrigé à vérifier en jeu.
