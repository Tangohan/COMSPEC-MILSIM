# Data Inspector : « Sources non disponibles : terminaux »

- **Contexte** : `/back-office/atak/data-inspector`, lectures de `AthenaInspectorMetricsRepository`.
- **Symptôme** : le bandeau annonce « Sources non disponibles sur cette installation : terminaux » et les sections terminaux restent vides.
- **Cause** : non établie. Hypothèse : `atak_terminals` ou `atak_terminal_sync` absente, leurs étapes de migration étant archivées. Le site crée aussi `atak_terminals` à la demande (`AtakRealismRepository`) : si la table existe déjà en production, cette hypothèse tombe et l'erreur vient d'ailleurs. La lecture des requêtes n'a révélé aucune erreur.
- **Correctif** : filet seulement. L'étape active `4000_atak_aar_annexes_syntheses.php` crée les deux tables si elles manquent. Rien de plus tant que l'erreur réelle n'est pas connue.
- **Fichiers touchés** : `database/steps/4000_atak_aar_annexes_syntheses.php`, `database/steps/registry.php`.
- **Vérification** : aucune en base. Après migration, si le bandeau reste, lire dans le journal PHP la ligne `[data-inspector] terminaux : <erreur SQL>` : elle donne la vraie cause.
- **Statut** : ouvert, à confirmer avec le journal de production.
