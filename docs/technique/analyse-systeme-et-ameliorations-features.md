# Analyse système et améliorations de features existantes

## 1) Objectif

Ce document propose une lecture **système** de COMSPEC-MILSIM et un plan d’amélioration des fonctionnalités déjà en place, afin de :

- réduire la fragmentation entre modules,
- augmenter l’adoption réelle des parcours clés,
- améliorer la fiabilité opérationnelle,
- faciliter le pilotage produit et la montée en charge.

## 2) Analyse système (vue transverse)

## 2.1 Forces structurelles observées

1. **Couverture fonctionnelle large** : auth, communautés, personnel/ORBAT, LMS, forum, courrier, événements, ATAK/API tactiques.
2. **Approche modulaire claire** via `Controllers`, `Services`, `Repositories`.
3. **Orientation multi-tenant mature** (organisation + système), favorable à la scalabilité produit.
4. **Documentation technique déjà structurée** (architecture, modules, sécurité, plans d’amélioration sectoriels).

## 2.2 Limites systémiques à traiter

1. **Expérience utilisateur en silos** : les modules existent, mais l’utilisateur n’a pas toujours un “flux de mission” unique.
2. **Hétérogénéité d’UX** : états vides, wording CTA et feedback action parfois non homogènes d’un module à l’autre.
3. **Pilotage produit incomplet** : beaucoup de données transactionnelles, mais peu d’indicateurs consolidés usage/valeur.
4. **Automatisation insuffisante entre modules** : peu de déclencheurs transverses (ex. enrôlement → onboarding → formation → événement).
5. **Dette opérationnelle discrète** : certaines zones critiques (uploads, anti-abus, relances, consentements, alerting) méritent un cadrage systémique.

## 3) Principes directeurs pour améliorer l’existant

- **Principe P1 — “Ne pas ajouter, relier”** : privilégier l’orchestration de features déjà présentes.
- **Principe P2 — “Parcours avant écran”** : optimiser les séquences d’usage complètes plutôt que des pages isolées.
- **Principe P3 — “Instrumenter d’abord”** : chaque amélioration doit être mesurable (activation, conversion, rétention, temps de traitement).
- **Principe P4 — “Dégradation élégante”** : garder des comportements robustes en cas d’échec partiel (réseau/API/services).
- **Principe P5 — “Tenant first”** : exposer de la personnalisation locale sans casser la cohérence plateforme.

## 4) Propositions d’amélioration par feature existante

## 4.1 Communautés (découverte, entrée, conversion)

### Problème
Le parcours visiteur → candidat → membre reste trop dépendant d’actions manuelles.

### Améliorations
- Unifier les CTA publics (`Rejoindre`, `Candidater`, `Contacter`) avec règles de priorité tenant.
- Ajouter un entonnoir de conversion dans l’admin org : vue `visites -> clic CTA -> candidature -> acceptation`.
- Introduire un score de complétude de la vitrine publique (profil, visuel, liens, événements).
- Ajouter des suggestions automatiques de “prochaine action admin” si la conversion baisse sur 7 jours.

### KPI
- Taux visite → action CTA.
- Taux candidature → acceptation.
- Délai médian visite → premier contact.

## 4.2 Enrôlement / Recrutement

### Problème
Le traitement candidature est souvent linéaire et peu assisté.

### Améliorations
- Préqualification légère (questions dynamiques selon communauté).
- Modèles de réponses contextuels (accepté, en attente, refus argumenté, redirection).
- SLA interne configurable (alerte si dossier sans action > X heures).
- Passerelle directe vers onboarding membre (création de tâches post-acceptation).

### Détail de mise en œuvre recommandé
1. **Préqualification dynamique (avant dépôt final)**
   - Déclencher un bloc de questions conditionnelles selon le type de communauté, le rôle visé et la disponibilité déclarée.
   - Calculer un score de complétude + drapeaux de vigilance (ex. disponibilité incompatible, prérequis manquants).
   - Rendre visible au staff une synthèse courte de préqualification en tête de dossier.

2. **Aide à la décision staff (pendant instruction)**
   - Proposer des modèles de réponse contextualisés par statut (`accepté`, `en attente`, `refus argumenté`, `redirection`) avec variables automatiques (nom, unité, prochaine étape).
   - Imposer un motif structuré en cas de refus pour alimenter l’analyse qualité du funnel.
   - Journaliser les changements d’état (qui, quand, commentaire) pour audit interne.

3. **SLA candidature et escalade**
   - Introduire un SLA configurable par communauté (ex. première prise en charge < 24 h, décision < 72 h).
   - Définir des seuils d’alerte progressifs (warning 70 %, critique 100 %, escalade 130 % du SLA).
   - Afficher un indicateur de vieillissement de dossier dans la liste de recrutement (couleur + compteur heures).

4. **Passerelle recrutement -> onboarding**
   - À l’acceptation, générer automatiquement une checklist onboarding initiale (profil, documents clés, formation d’entrée, prochain événement).
   - Affecter les tâches au binôme recruteur/référent d’unité avec dates cibles.
   - Émettre un événement analytique unique permettant de mesurer la conversion J7/J30.

### Flux cible (résumé)
`Soumission candidature -> préqualification -> file d’instruction staff -> décision outillée -> onboarding auto -> suivi conversion J30`

### KPI
- Temps médian de traitement candidature.
- Taux de dossiers “bloqués” > SLA.
- Taux de conversion candidature → membre actif à J30.

### Définition opérationnelle des KPI
- **Temps médian de traitement candidature** : médiane entre `submitted_at` et `decision_at`, segmentée par communauté.
- **Taux de dossiers bloqués > SLA** : `% dossiers ouverts dont âge de l’état courant > seuil SLA`.
- **Taux de conversion candidature -> membre actif à J30** : `% candidatures acceptées avec au moins 1 action qualifiante entre J0 et J30` (connexion, complétion onboarding, participation événement, module LMS validé).

## 4.3 Onboarding membre (cross-modules)

### Problème
Après inscription, la marche à suivre dépend trop de la connaissance implicite des responsables.

### Améliorations
- Checklist onboarding multi-modules : profil, forum, document essentiel, formation d’entrée, événement.
- Plan d’onboarding par rôle (membre, cadre, instructeur, recruteur).
- Nudges intelligents (inactivité, tâche critique manquante, relance contextualisée).
- Badge de progression visible côté membre + vue de suivi côté staff.

### KPI
- Taux de complétion onboarding à J7/J14.
- % de nouveaux membres ayant réalisé au moins 1 action dans 3 modules distincts.

## 4.4 LMS / Training Studio

### Problème
Le LMS est riche, mais le lien avec progression opérationnelle et événements peut être renforcé.

### Améliorations
- Relier objectifs de formation aux rôles métier/grade et aux besoins ORBAT.
- Ajouter des “parcours recommandés” dynamiques selon profil + activité récente.
- Introduire un feedback post-leçon standardisé (difficulté, clarté, utilité).
- Pousser automatiquement un événement d’entraînement recommandé après complétion d’un module clé.

### KPI
- Taux de complétion des parcours recommandés.
- Délai entre inscription et première formation complétée.
- Corrélation formation complétée ↔ présence événementielle.

## 4.5 Forum et communication communautaire

### Problème
Le forum existe, mais n’est pas toujours intégré aux flux de mission quotidiens.

### Améliorations
- Fil “priorité mission” (annonces critiques, doctrine, AAR) épinglé par rôle.
- Résumé hebdo automatique des threads importants (digest tenant).
- Relances de lecture ciblées pour contenus obligatoires (avec ack).
- Pont avec messagerie interne pour notifications à fort impact.

### KPI
- Taux de lecture des publications prioritaires.
- Temps médian publication → première réponse staff.
- Ratio sujets actifs / sujets créés à 7 jours.

## 4.6 Événements / Pointage

### Problème
La présence est suivie, mais l’exploitation de la donnée pour l’amélioration continue est limitée.

### Améliorations
- Score de régularité présence par membre/équipe (avec fenêtre glissante).
- Motifs d’absence normalisés + statistiques exploitables.
- Recommandations automatiques de créneaux à forte probabilité de participation.
- Lien avec onboarding/LMS : proposer l’événement “utile suivant”.

### KPI
- Taux de présence confirmée vs effective.
- Taux de no-show.
- Progression de participation des nouveaux membres.

## 4.7 Courrier officiel / Documents

### Problème
Les modules sont puissants, mais la traçabilité décisionnelle peut encore gagner en lisibilité.

### Améliorations
- Historique unifié des versions + décisions (qui a validé quoi, quand, pourquoi).
- Moteur de modèles “smart defaults” basé sur contexte (type, unité, destinataires).
- Checklists conformité pré-envoi (signature, métadonnées, permissions).
- Lien bidirectionnel courrier <-> dossier personnel lorsqu’un document impacte un membre.

### KPI
- Taux d’erreur documentaire détectée avant diffusion.
- Temps moyen de cycle brouillon -> validé.
- Taux d’usage des modèles recommandés.

## 4.8 Administration org/système

### Problème
Le back-office est complet, mais la priorisation quotidienne reste difficile.

### Améliorations
- “Centre d’opérations admin” : file unique des alertes actionnables triées par impact.
- Playbooks guidés pour incidents courants (spam, permissions, panne module, litige).
- Journal d’audit lisible par scénarios (sécurité, RH, formation, contenu).
- Objectifs hebdomadaires pilotés par KPI (avec état et variation).

### KPI
- MTTA/MTTR sur alertes admin.
- Nombre d’incidents résolus via playbook.
- Taux d’actions admin terminées dans le SLA.

## 5) Chantiers techniques transverses

1. **Observabilité produit + technique**
   - Événements d’usage standardisés (nomenclature commune).
   - Tableaux de bord de santé (erreurs, latence, file jobs, emails, cron).

2. **Gouvernance des permissions**
   - Audit automatique des droits sensibles.
   - Recertification périodique des rôles.

3. **Fiabilité des workflows asynchrones**
   - File de tâches avec retries, idempotence et dead-letter queue.
   - Alerting en cas d’échecs répétés de relances/notifications.

4. **Qualité UX et design system léger**
   - Standardiser composants feedback (succès/erreur/vide/chargement).
   - Harmoniser la terminologie produit entre modules.

5. **Stratégie anti-abus et confiance**
   - Scores de risque (contact, forum, enrôlement) + throttling progressif.
   - Mode dégradé sécurisé quand un service externe est indisponible.

## 6) Priorisation recommandée (impact / effort)

## Phase 1 (0–6 semaines)
- Instrumentation KPI minimale (funnels conversion + onboarding + événements).
- Unification CTA et états vides sur communautés/enrôlement/forum.
- Checklist onboarding V1 + rappels basiques.
- Alertes SLA sur candidatures.

## Phase 2 (6–12 semaines)
- Orchestration cross-modules (onboarding -> LMS -> événement).
- Digest forum prioritaire + notifications ciblées.
- Centre d’opérations admin V1.
- Historique décisionnel renforcé courrier/documents.

## Phase 3 (12–20 semaines)
- Recommandations intelligentes (formation/événement/créneaux).
- Automatisations avancées multi-tenant.
- Score confiance/risque + playbooks de mitigation.
- Boucle d’amélioration continue pilotée par cohortes et rétention.

## 7) Backlog concret (10 tickets prioritaires)

1. **COMM-001** — Funnel conversion communautés (tableau + exports).
2. **REC-002** — SLA candidature + alertes inactivité.
3. **ONB-003** — Checklist onboarding multi-modules.
4. **ONB-004** — Nudges d’inactivité contextualisés.
5. **LMS-005** — Parcours recommandés selon rôle métier.
6. **EVT-006** — Score régularité présence + motifs d’absence.
7. **FOR-007** — Digest hebdo des contenus prioritaires.
8. **DOC-008** — Historique décisionnel unifié courrier/documents.
9. **ADM-009** — Centre d’opérations admin (actions triées par impact).
10. **PLAT-010** — Standard d’événements analytiques transverse.

## 8) Risques et mitigations

- **Risque :** surcharge de notifications.
  - **Mitigation :** centre de préférences + limites journalières par canal.

- **Risque :** complexité de paramétrage tenant.
  - **Mitigation :** presets de configuration + mode “recommandé”.

- **Risque :** dette technique cachée dans les intégrations.
  - **Mitigation :** phase d’instrumentation et tests de non-régression avant automatisation massive.

- **Risque :** faible adoption des nouveautés admin.
  - **Mitigation :** onboarding in-app des responsables + quick wins visibles en < 2 semaines.

## 9) Résultat cible

À horizon 3 lots, la plateforme passe d’un ensemble de modules riches à un **système orchestré orienté mission**, avec :

- des parcours utilisateur lisibles de bout en bout,
- une exploitation active des signaux d’usage,
- une meilleure rétention membre et communauté,
- une administration plus proactive et mesurable.
