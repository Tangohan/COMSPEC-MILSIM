# Cohérence des spécialités dans les dossiers et les objectifs pédagogiques

- **Contexte** : intégration du nouveau référentiel national de spécialités RH/LMS.
- **Symptôme** : deux ajouts d’objectifs pouvaient s’écraser ; un niveau archivé pouvait
  disparaître des choix d’un dossier ; une purge historique pouvait transférer une
  spécialité personnelle au compte technique « Ancien membre ».
- **Cause** : lecture des objectifs avant transaction, options limitées aux niveaux
  actifs, nouvelle table absente des listes de nettoyage des données personnelles.
- **Correctif** : mutation d’un objectif sous verrou transactionnel ; chargement des
  niveaux historiques avec option courante sélectionnée ; suppression des spécialités
  personnelles dans les circuits d’anonymisation et de purge.
- **Fichiers touchés** : `MilitarySpecialtyController`, `MilitarySpecialtyService`, vue
  `specialties.php`, `AccountDeletionService`, `AccountPurgeService` et tests associés.
- **Vérification** : tests des objectifs conservés, du niveau archivé sélectionné,
  de la purge de deux membres partageant un même code et du cloisonnement par communauté.
- **Statut** : corrigé et testé en développement ; migration non exécutée en production.
