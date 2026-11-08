# Journal des paiements : auteur générique et communauté absente

- **Contexte** : `/admin/paiements?vue=paiements`, frise de chaque demande et fil d'activité.
- **Symptôme** : toutes les actions de l'administration apparaissaient sous « Gestionnaire Athéna @TTRD » (nom affiché commun à plusieurs comptes), sans dire quelle communauté était concernée par la formule.
- **Cause** : la requête des événements lisait seulement `users.display_name` et ne joignait pas la communauté bénéficiaire.
- **Correctif** : l'auteur est l'indicatif, sinon prénom et nom, sinon nom affiché, suivi de sa communauté ; chaque ligne indique la communauté bénéficiaire et la formule. `/soutenir-atak` liste aussi les communautés soutien avec leur formule (sans montant). `/traduire-atak` exige un compte et enregistre sur le serveur.
- **Fichiers touchés** : `PaymentLedgerRepository`, `DonorService`, `AtakSupportController`, `views/admin/system/plan_payments.php`, `views/atak/support.php`, `views/site/atak_translate.php`, `routes/web.php`.
- **Vérification** : `PaymentsLedgerTest`. La liste des communautés apparaît après le prochain recalcul du cache des donateurs.
- **Statut** : corrigé, à vérifier en production.
