# Plan produit — Centre d’opérations admin

## 1) Problème

Le back-office est riche fonctionnellement, mais la priorisation quotidienne est coûteuse : les équipes admin doivent naviguer entre plusieurs vues pour identifier les actions critiques, ce qui augmente le délai de prise en charge et la variabilité de traitement.

## 2) Objectif

Créer un **Centre d’opérations admin** orienté exécution, qui concentre les actions prioritaires, guide la résolution d’incidents récurrents et donne une lecture KPI opérationnelle hebdomadaire.

## 3) Périmètre fonctionnel

### 3.1 File unique des alertes actionnables (triées par impact)

- Agréger dans une file unique : modération, permissions, incidents techniques, litiges, signaux RH/formation.
- Attribuer un score d’impact (criticité x périmètre x urgence x dette SLA).
- Afficher des actions immédiates (“Prendre en charge”, “Escalader”, “Clôturer avec justification”).
- Offrir des filtres rapides : type d’incident, équipe, niveau de risque, délai restant avant SLA.

### 3.2 Playbooks guidés pour incidents courants

Playbooks interactifs avec étapes, contrôles et preuves obligatoires pour :

1. **Spam**
   - Qualification (source, volume, récence).
   - Action standard (masquage, restriction, communication).
   - Vérification post-action (récidive sous 24h).

2. **Permissions**
   - Diagnostic rôle/groupe/périmètre.
   - Correction guidée avec validation de sécurité.
   - Journalisation automatique des changements d’accès.

3. **Panne module**
   - Checklist de triage technique (symptômes, portée, logs).
   - Procédure de mitigation temporaire.
   - Escalade standardisée vers équipe technique.

4. **Litige**
   - Qualification du dossier et pièces.
   - Circuit de décision et arbitrage.
   - Communication des décisions aux parties concernées.

### 3.3 Journal d’audit lisible par scénarios

Ajouter des vues d’audit orientées parcours, au lieu d’une simple chronologie brute :

- **Sécurité** : accès sensibles, élévations, break-glass, révocations.
- **RH** : affectations, statut personnel, habilitations.
- **Formation** : inscriptions, validations, blocages.
- **Contenu** : signalements, modération, restauration/suppression.

Chaque scénario expose : “qui, quoi, quand, pourquoi, impact, décision”.

### 3.4 Objectifs hebdomadaires pilotés par KPI

- Définir 3 à 5 objectifs opérationnels hebdomadaires.
- Visualiser l’état (`à risque`, `en cours`, `atteint`) et la variation vs semaine précédente.
- Lier chaque objectif aux incidents/actions qui influencent sa trajectoire.

## 4) KPIs de pilotage

### KPI principaux

- **MTTA (Mean Time to Acknowledge)** sur alertes admin.
- **MTTR (Mean Time to Resolve)** sur alertes admin.
- **Nombre d’incidents résolus via playbook** (et ratio vs traitement ad hoc).
- **Taux d’actions admin terminées dans le SLA**.

### KPI de qualité complémentaires (recommandés)

- Taux de réouverture d’incidents.
- Taux d’escalade hors playbook.
- Temps moyen par étape de playbook.

## 5) Parcours utilisateur cible (admin de garde)

1. Ouvre le Centre d’opérations admin.
2. Consulte la file unique triée par impact.
3. Prend l’alerte prioritaire et lance le playbook recommandé.
4. Suit les étapes, attache les preuves, exécute les actions.
5. Clôture l’incident avec décision et justification.
6. Vérifie l’évolution des objectifs hebdomadaires et de la variation KPI.

## 6) Critères d’acceptation (MVP)

- Une alerte actionnable est visible en ≤ 1 clic depuis le back-office.
- Toute alerte dispose d’un niveau d’impact et d’une échéance SLA.
- Les 4 playbooks ciblés sont exécutables de bout en bout.
- Le journal d’audit par scénario est consultable sur 30 jours glissants.
- Les 4 KPI principaux sont calculés automatiquement et historisés.

## 7) Découpage de livraison

### Lot A — Visibilité & priorisation

- File unique et scoring d’impact.
- Règles de tri et filtres critiques.
- MTTA/MTTR instrumentés.

### Lot B — Exécution guidée

- Playbooks spam/permissions/panne module/litige.
- Journalisation automatique des étapes et décisions.

### Lot C — Pilotage & amélioration continue

- Audit par scénarios.
- Objectifs hebdomadaires + variation KPI.
- Revue ops hebdomadaire standardisée.

## 8) Risques et garde-fous

- **Risque :** sur-priorisation automatique non pertinente.
  - **Garde-fou :** score explicable + override humain obligatoire.
- **Risque :** playbooks trop rigides.
  - **Garde-fou :** chemins alternatifs encadrés et motif obligatoire.
- **Risque :** surcharge documentaire.
  - **Garde-fou :** preuves minimales standardisées par type d’incident.
