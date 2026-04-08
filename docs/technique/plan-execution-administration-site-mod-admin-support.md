# Plan d’exécution — Administration du site par rôle (Modérateur, Admin, Support)

## 1) Finalité

Transformer les axes d’amélioration en plan de delivery **exécutable** avec responsabilités claires par rôle, séquencement, dépendances techniques et critères d’acceptation mesurables.

## 2) Cibles opérationnelles par rôle

### 2.1 Modérateur
- Réduire le backlog de signalements.
- Harmoniser les décisions de sanction.
- Accélérer le traitement des cas à fort impact communautaire.

### 2.2 Support
- Réduire le délai de première réponse.
- Améliorer le taux de résolution au premier contact.
- Fiabiliser l’escalade vers admin/modération.

### 2.3 Admin système
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

## 5) Lots de delivery, jalons et “Definition of Done”

### Lot 1 (0–6 semaines)
**Objectif:** opérationnaliser les quick wins transverses et standardiser le langage métier.

#### Livrables
- Widget “mes actions en attente” visible en moins de 2 clics pour les trois rôles.
- Liens profonds entre cas support, lookup utilisateur et événements d’audit.
- Bibliothèque de templates (incident, maintenance, abuse report, clôture).
- Dictionnaire de statuts unifié (incident/modération/maintenance).

#### Definition of Done (DoD)
- 100% des écrans ciblés exposent le statut via le dictionnaire commun.
- Au moins 1 scénario de bout en bout validé par rôle (modérateur/support/admin).
- Trace d’audit enregistrée pour chaque action de transition de statut.

### Lot 2 (6–12 semaines)
**Objectif:** centraliser les opérations quotidiennes et fiabiliser l’escalade.

#### Livrables
- Ops Center V1 couvrant ≥ 80% des actions quotidiennes.
- Dossier de cas support unifié (profil, historique, décisions, escalades).
- Priorisation explicite de la queue de modération (score + raisons).
- Reporting hebdomadaire standardisé pour revue ops.

#### Definition of Done (DoD)
- Chaque escalade support est journalisée avec motif, destinataire, résultat.
- L’Ops Center permet un accès direct aux trois workflows principaux.
- Les revues hebdomadaires peuvent être générées sans retraitement manuel.

### Lot 3 (12–20 semaines)
**Objectif:** verrouiller la gouvernance des accès et automatiser le pilotage SLA.

#### Livrables
- Revue périodique des accès rôles site (attestations tracées/auditées).
- Procédure break-glass testée, documentée, horodatée.
- Mécanismes SLA automation (rappels, relances, escalades).
- Dashboard KPI continu (MTTR, backlog modération, SLA support).

#### Definition of Done (DoD)
- 100% des accès sensibles ont une attestation dans la période courante.
- Exercice break-glass réalisé au moins une fois avec journal complet.
- KPI disponibles en self-service et historisés.

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

### Cibles initiales recommandées (à ajuster après baseline)
- FRT support: **-30%** à 12 semaines.
- Backlog signalements > 72h: **-40%** à 12 semaines.
- Incidents config critiques: **-25%** à 20 semaines.

## 7) Décisions techniques structurantes

1. Introduire un identifiant unique de cas transverse (support/modération/admin).
2. Normaliser les statuts métier avec dictionnaire commun.
3. Ajouter des événements d’audit orientés workflow (handoff, escalation, closure).
4. Créer un socle de composants UI partagé pour écrans d’exploitation.

## 8) Ordonnancement technique (séquence d’implémentation)

1. **Fondations données (S1–S3)**
   - ID de cas global + mapping legacy.
   - Dictionnaire de statuts versionné.
   - Événements d’audit normalisés.
2. **Fondations UI (S2–S5)**
   - Composants partagés: statut, priorité, timeline, assignation.
   - Vue “mes actions en attente” par rôle.
3. **Workflows (S4–S10)**
   - Dossier support + passerelles d’escalade.
   - Queue modération priorisée.
4. **Gouvernance & automatisation (S10–S20)**
   - Attestations accès, break-glass, SLA automation.

## 9) Plan de run & gouvernance projet

### Rituels
- Daily ops (15 min) : incidents et blocages inter-rôles.
- Revue hebdo ops (45 min) : KPI, qualité escalades, décisions.
- Steering bimensuel : arbitrages de périmètre, dépendances, risques.

### Artefacts obligatoires
- Journal des décisions (ADR léger) pour choix impactant rôles/statuts.
- Changelog opérations (nouveaux workflows, nouveaux templates).
- Compte-rendu de post-incident avec actions correctives tracées.

## 10) Risques d’exécution et réponses

- **Risque**: surcharge de chantier multi-rôles.
  **Réponse**: livrer par incréments métier à forte valeur (P0 puis P1).
- **Risque**: résistance au changement des équipes ops.
  **Réponse**: co-conception avec modérateurs/support/admin + formation courte.
- **Risque**: complexité de données historiques hétérogènes.
  **Réponse**: migration progressive, compatibilité ascendante, fallback manuel.
- **Risque**: bruit de priorisation automatique (modération).
  **Réponse**: score explicable + override humain + contrôle qualité hebdo.

## 11) Checklist de mise en production par lot

### Go-live Lot 1
- [ ] Dictionnaire de statuts publié.
- [ ] Templates communication validés par support + admin.
- [ ] Liens transverses testés sur cas réel.

### Go-live Lot 2
- [ ] Ops Center utilisé par au moins 1 équipe pilote.
- [ ] Escalade avec traçabilité complète activée.
- [ ] Revue hebdo basée sur le reporting standard.

### Go-live Lot 3
- [ ] Campagne d’attestation des accès clôturée.
- [ ] Exercice break-glass conforme.
- [ ] Alerting SLA actif sur périmètre critique.
