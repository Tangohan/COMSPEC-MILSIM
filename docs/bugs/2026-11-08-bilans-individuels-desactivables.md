# Bilans individuels impossibles à désactiver

- **Contexte** : onglet « Bilans » de la fiche d'un membre (personnel et effectifs).
- **Symptôme** : une communauté qui ne veut pas de bilans individuels ne pouvait que couper les rappels ; l'onglet et la saisie restaient.
- **Cause** : le réglage « Suivre les bilans périodiques » ne pilote que le calendrier et les rappels.
- **Correctif** : nouveau réglage communauté `individual_bilans` (case « Proposer les bilans individuels », suivi d'immersion, actif par défaut). Décoché : onglet masqué, saisie refusée côté serveur, liste non chargée dans les effectifs. Les bilans existants sont conservés. Pas de migration (JSON des réglages).
- **Fichiers touchés** : `RoleplayFollowupSettings.php`, `PersonnelController.php`, `EffectifsWorkspaceController.php`, `roleplay_immersion_settings.php`, `lang/en`.
- **Vérification** : `RoleplayFollowupSettingsTest`.
- **Statut** : corrigé, à vérifier en production.
