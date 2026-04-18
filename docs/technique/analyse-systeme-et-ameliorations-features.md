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

### KPI
- Temps médian de traitement candidature.
- Taux de dossiers “bloqués” > SLA.
- Taux de conversion candidature → membre actif à J30.

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

### Détail d’implémentation recommandé

1. **Alignement formation ↔ rôle/grade/ORBAT**
   - Ajouter une matrice de compétences minimales par `rôle métier`, `grade` et `poste ORBAT`.
   - Tagger chaque module/lesson avec des objectifs opérationnels explicites (`commandement`, `transmissions`, `appui feu`, etc.).
   - Exposer une vue “écarts de compétences” côté staff pour identifier ce qui manque avant affectation opérationnelle.

2. **Parcours recommandés dynamiques**
   - Construire un score de recommandation combinant : rôle actuel, ancienneté, modules déjà validés, récurrence aux événements, et objectifs de l’unité.
   - Définir 3 états de parcours : `Essentiel`, `Renforcement`, `Spécialisation`.
   - Rafraîchir automatiquement les recommandations après chaque complétion de module et après chaque participation à un événement.

3. **Feedback post-leçon standardisé**
   - Afficher un mini-formulaire en fin de leçon (3 questions fixes : difficulté perçue, clarté pédagogique, utilité terrain).
   - Ajouter un champ commentaire libre optionnel pour signaux faibles.
   - Produire une note agrégée par leçon pour prioriser les révisions pédagogiques.

4. **Pont automatique vers les événements**
   - Lorsqu’un module “clé” est complété, déclencher une recommandation d’événement contextualisée (type d’entraînement, niveau requis, créneau conseillé).
   - Envoyer cette recommandation via notification in-app + rappel différé si aucune inscription sous 72h.
   - Permettre au staff de surcharger manuellement la recommandation si contrainte opérationnelle locale.

### Instrumentation (événements analytics minimaux)

- `training_recommendation_shown`
- `training_recommendation_opened`
- `training_path_enrolled`
- `training_lesson_feedback_submitted`
- `training_key_module_completed`
- `training_event_recommendation_pushed`
- `training_event_recommendation_accepted`

### KPI
- Taux de complétion des parcours recommandés.
- Délai entre inscription et première formation complétée.
- Corrélation formation complétée ↔ présence événementielle.

### Définition opérationnelle des KPI

- **Taux de complétion des parcours recommandés** = `# parcours recommandés complétés / # parcours recommandés démarrés` (fenêtre glissante 30 jours).
- **Délai inscription → 1ère formation complétée** = médiane en heures entre `date_inscription` et `date_première_completion`.
- **Corrélation formation ↔ présence événementielle** = comparaison, par cohorte mensuelle, du taux de présence entre membres ayant complété au moins 1 module clé et membres sans module clé complété.

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
Les modules sont puissants, mais la chaîne de décision reste fragmentée entre versions, validations et impacts RH, ce qui nuit à la lisibilité opérationnelle.

### Améliorations
- Historique unifié **version + décision** sur une même frise (qui a validé, quand, pourquoi, et sous quelle délégation).
- Moteur de modèles avec **smart defaults contextuels** (type de courrier, unité émettrice, destinataires, niveau de confidentialité).
- Checklists de conformité pré-envoi (signature requise, métadonnées minimales, permissions de diffusion, statut des pièces jointes).
- Lien bidirectionnel **courrier ↔ dossier personnel** lorsqu’un document impacte un membre (consultable depuis les deux écrans).
- Journal des écarts et exceptions (dérogation de validation, diffusion urgente, correction post-publication) pour auditabilité.

### KPI
- Taux d’erreur documentaire détectée **avant diffusion**.
- Temps moyen de cycle **brouillon → validé**.
- Taux d’usage des modèles recommandés.
- Délai moyen d’alignement courrier ↔ dossier personnel après publication.

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
   - Contrat d’événements versionné (`domain.object.action.v1`) avec propriétaires métier/techniques.
   - Revue mensuelle “signal vs bruit” pour supprimer les métriques non actionnables.

2. **Gouvernance des permissions**
   - Audit automatique des droits sensibles.
   - Recertification périodique des rôles.
   - Inventaire centralisé des permissions critiques (RBAC + exemptions “break glass”).
   - Rapport d’écarts horodaté (création, extension, révocation) avec validation nominative.

3. **Fiabilité des workflows asynchrones**
   - File de tâches avec retries, idempotence et dead-letter queue.
   - Alerting en cas d’échecs répétés de relances/notifications.
   - Politique de retries par type de job (backoff exponentiel + jitter).
   - Clés d’idempotence obligatoires pour toutes les relances utilisateurs (email, webhook, notification).

4. **Qualité UX et design system léger**
   - Standardiser composants feedback (succès/erreur/vide/chargement).
   - Harmoniser la terminologie produit entre modules.
   - Bibliothèque minimale de patterns (formulaires, tables, filtres, états) partagée avec snippets prêts à l’emploi.
   - Glossaire produit unique FR/EN aligné avec les libellés back-office et portail.

5. **Stratégie anti-abus et confiance**
   - Scores de risque (contact, forum, enrôlement) + throttling progressif.
   - Mode dégradé sécurisé quand un service externe est indisponible.
   - Matrice de réponses graduées (silent flag, challenge, blocage temporaire, revue manuelle).
   - Journal de décisions anti-abus explorable (raison, score, règle déclenchée, durée de mitigation).

### Cadence de livraison recommandée (transverse)

- **S1–S3** : cadrage des schémas d’événements, inventaire permissions sensibles, définition des SLO workflows.
- **S4–S8** : instrumentation dashboards santé + alerting, déploiement retries/idempotence/DLQ, kit UX feedback V1.
- **S9–S12** : recertification rôles automatisée, score de risque V1 sur contact/forum, premiers modes dégradés testés.

### Critères d’acceptation minimaux

- Observabilité : >90% des parcours critiques couverts par événements standardisés.
- Permissions : 100% des rôles sensibles recertifiés sur la période cible.
- Asynchrone : baisse mesurable des échecs finaux (DLQ) et du temps de reprise.
- UX : états success/error/loading/empty homogènes sur les 3 modules à plus fort trafic.
- Anti-abus : réduction des incidents répétés sans hausse significative des faux positifs.

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

| Ticket | Priorité | Résultat attendu | Livrable clé |
| --- | --- | --- | --- |
| **COMM-001** | P0 | Mesurer la conversion visiteur -> membre actif pour chaque communauté. | Tableau de funnel par étape + export CSV/PDF. |
| **REC-002** | P0 | Réduire les candidatures sans réponse et les abandons en file d’attente. | SLA paramétrable avec alertes d’inactivité par rôle. |
| **ONB-003** | P0 | Structurer un parcours d’intégration unique entre communautés, docs et LMS. | Checklist onboarding multi-modules avec progression persistante. |
| **ONB-004** | P1 | Réengager les utilisateurs inactifs sans sur-notifier. | Nudges contextualisés selon module, fréquence et profil. |
| **LMS-005** | P1 | Augmenter le taux de complétion des formations métier. | Recommandations de parcours basées sur rôle + historique d’activité. |
| **EVT-006** | P1 | Identifier les habitudes de présence et les causes d’absence récurrentes. | Score de régularité présence + taxonomie des motifs d’absence. |
| **FOR-007** | P1 | Accélérer la visibilité des sujets critiques dans le forum. | Digest hebdo personnalisé des contenus prioritaires. |
| **DOC-008** | P0 | Garantir la traçabilité complète des décisions sur courrier/documents. | Historique décisionnel unifié (timeline horodatée + acteurs). |
| **ADM-009** | P0 | Donner aux admins une vue actionnable des tâches à plus fort impact. | Centre d’opérations avec file d’actions priorisées par impact/urgence. |
| **PLAT-010** | P0 | Uniformiser les métriques inter-modules pour pilotage produit fiable. | Standard transverse d’événements analytiques + dictionnaire de schéma. |

### Dépendances minimales

- **PLAT-010** est un prérequis de mesure pour **COMM-001**, **ONB-003**, **ONB-004**, **LMS-005** et **EVT-006**.
- **ONB-003** doit être livré avant **ONB-004** pour éviter des nudges sans parcours de référence.
- **DOC-008** et **ADM-009** se renforcent mutuellement via une même couche d’audit et de priorisation.

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
