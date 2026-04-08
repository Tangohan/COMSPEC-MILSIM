# Plan d’amélioration majeur — Back-office TENANT

## Implémentation initiale (lot livré)

Cette livraison met en place la **fondation du cockpit "Centre des opérations"** et prépare l’exécution progressive du plan majeur demandé.

### Livré dans ce lot
- Nouvelle route: `/back-office/centre-operations`.
- Nouveau contrôleur d’agrégation opérationnelle (incidents modération, candidatures en attente, événements J+1/J+7, alertes locales, anomalies onboarding/configuration).
- Nouvelle vue unifiée "Control Tower" avec:
  - filtres par profil (`commandement`, `rh`, `moderation`, `formation`),
  - actions contextuelles (`Traiter`, `Instruire`, `Préparer`, `Planifier`, `Escalader`),
  - listes prioritaires transverses.
- Lien rapide ajouté depuis le dashboard back-office principal.

## Plan cible (rappel)

### Axe A — Expérience back-office “Control Tower”
Créer un cockpit opérationnel unique.

### Axe B — Gouvernance RBAC explicable
Matrice lisible, simulation “voir comme”, détection de dérive.

### Axe C — Workflow transverse Modération / RH / Recrutement
Dossier unifié membre et journal décisionnel multi-étapes.

### Axe D — Conformité continue et preuves
Score, checklist dynamique, manifeste d’intégrité à l’export.

### Axe E — Performance admin & qualité de données
Actions bulk et contrôles d’intégrité inter-modules.

## Roadmap de mise en œuvre recommandée

### Lot 1 (0–6 semaines)
- Quick wins UX et liens transverses.
- Score santé tenant minimal.
- Matrice de permissions lisible V1.

### Lot 2 (6–12 semaines)
- Centre des opérations V1 (ce lot initial + enrichissements UX/data).
- Workflow modération/RH unifié V1.
- Actions bulk principales utilisateurs/invitations.

### Lot 3 (12–20 semaines)
- Simulateur RBAC “voir comme”.
- Conformité continue renforcée.
- Instrumentation analytique de performance admin.
