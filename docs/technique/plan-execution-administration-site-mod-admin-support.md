# Plan d’exécution — Administration du site par rôle (Modérateur, Admin, Support)

## 1) Finalité

Transformer les axes d’amélioration en plan de delivery exécutable avec responsabilités claires par rôle, séquencement et critères d’acceptation.

## 2) Cibles opérationnelles par rôle

## 2.1 Modérateur
- Réduire le backlog de signalements.
- Harmoniser les décisions de sanction.
- Accélérer le traitement des cas à fort impact communautaire.

## 2.2 Support
- Réduire le délai de première réponse.
- Améliorer le taux de résolution au premier contact.
- Fiabiliser l’escalade vers admin/modération.

## 2.3 Admin système
- Diminuer les incidents liés à la configuration.
- Mieux piloter maintenance/alertes/audit.
- Sécuriser la gouvernance des rôles site.

## 3) Backlog priorisé (MVP → avancé)

### P0 (MVP exploitation)
1. Vue “mes actions en attente” par rôle.
2. Liens transverses lookup utilisateur ↔ audit ↔ modération.
3. Templates de communication support + alertes incident.
4. Statuts unifiés incident/modération/maintenance.

### P1 (pilotage unifié)
1. Ops Center V1 (tableau consolidé).
2. Dossier de cas support unifié.
3. Priorisation automatique de la queue modération.
4. Reporting hebdomadaire standardisé (ops review).

### P2 (gouvernance avancée)
1. Matrice de rôles explicable et attestations périodiques.
2. Break-glass sécurisé et journalisé.
3. SLA automation (rappels, relances, escalades automatiques).
4. Observabilité décisionnelle (tendances, points de friction, prévision charge).

## 4) Modèle de responsabilité (RACI simplifié)

| Action | Modérateur | Support | Admin système |
|---|---|---|---|
| Qualification signalement | R | C | I |
| Sanction / levée | R | I | C |
| Triage incident utilisateur | C | R | I |
| Escalade technique | I | R | A |
| Publication alerte plateforme | C | C | A/R |
| Planification maintenance | I | C | A/R |
| Attribution rôles site | I | I | A/R |
| Revue post-incident | C | R | A |

Légende: **R** = Responsable opérationnel, **A** = Autorité décisionnelle, **C** = Consulté, **I** = Informé.

## 5) Critères d’acceptation par lot

## Lot 1 (0–6 semaines)
- Chaque rôle voit ses actions en attente en moins de 2 clics.
- Les cas support incluent lien direct vers utilisateur et audit.
- Les messages incident suivent un template unique validé.

## Lot 2 (6–12 semaines)
- Ops Center couvre au moins 80% des actions quotidiennes.
- Les escalades support sont journalisées avec motif et résultat.
- La queue modération affiche une priorité explicite.

## Lot 3 (12–20 semaines)
- Revue d’accès rôles site tracée et auditée.
- Procédure break-glass testée et documentée.
- KPI MTTR, backlog modération, SLA support disponibles en continu.

## 6) Instrumentation & KPIs

### KPIs Support
- Temps de première réponse (FRT).
- Temps moyen de résolution (MTTR support).
- Taux de résolution au premier contact.

### KPIs Modération
- Délai moyen de traitement des signalements.
- Taux de récidive après sanction.
- Ratio faux positifs / appels contestés.

### KPIs Admin
- Nombre d’incidents liés à configuration.
- Taux de conformité des revues d’accès.
- Disponibilité perçue pendant maintenance.

## 7) Décisions techniques structurantes

1. Introduire un identifiant unique de cas transverse (support/modération/admin).
2. Normaliser les statuts métier avec dictionnaire commun.
3. Ajouter des événements d’audit orientés workflow (handoff, escalation, closure).
4. Créer un socle de composants UI partagé pour écrans d’exploitation.

## 8) Risques d’exécution

- **Risque**: surcharge de chantier multi-rôles.
  **Réponse**: livrer par incréments métier à forte valeur (P0 puis P1).
- **Risque**: résistance au changement des équipes ops.
  **Réponse**: co-conception avec modérateurs/support/admin + formation courte.
- **Risque**: complexité de données historiques hétérogènes.
  **Réponse**: migration progressive, compatibilité ascendante, fallback manuel.
