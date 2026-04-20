# Audit UI — LMS & Studio LMS (20 avril 2026)

## Périmètre analysé
- LMS apprenant : `views/training/index.php`.
- LMS administration : `views/admin/training/dashboard_body.php`.
- Studio LMS (index + édition) : `views/admin/training/studio_index.php`, `views/admin/training/studio_edit.php`, `views/layout/training_studio.php`.

---

## Constats clés

### 1) Points forts actuels
- Hiérarchie visuelle claire (hero, cartes, blocs KPI, sections par usage).
- Parcours Studio bien segmenté (`Fiche`, `Présentation`, `Modules/leçons/ressources`).
- Signalétique déjà présente pour les cas critiques (version obsolète, capacité atteinte, états de visibilité).
- Présence d’un lien d’évitement clavier (“Aller au contenu”) côté shell Studio.

### 2) Frictions UX observées
- **Trop forte densité de textes d’aide** dans le Studio (messages longs et redondants), ce qui ralentit la lecture et l’action.
- **Beaucoup de micro-libellés en capitales** et tracking large ; lisibilité dégradée sur petits écrans.
- **Parcours de création fragmenté** : la création de formation est pratique, mais la transition vers les prochaines actions (fiche/presentation/structure/publication) n’est pas guidée explicitement.
- **Navigation fonctionnelle mais non orientée tâche** : dans le dashboard LMS, les blocs sont clairs mais ne matérialisent pas un “workflow quotidien” (ex. “à traiter aujourd’hui”).
- **Catalogue apprenant très visuel** mais peu informatif dès la carte (pré-requis, niveau, effort réel, format pédagogique), ce qui peut augmenter les clics inutiles.

---

## Recommandations prioritaires

## A. LMS apprenant (catalogue et décision d’inscription)
1. **Ajouter une barre de filtres utilitaires** (niveau, durée, modalité, disponibilité) avec reset rapide.
2. **Afficher 2–3 métadonnées décisionnelles directement sur la carte** : niveau, charge estimée hebdo, format (quiz/vidéo/pratique).
3. **Introduire un état de progression par utilisateur** sur les cartes (non commencé / en cours / terminé).
4. **Améliorer accessibilité du contraste et des tailles miniatures** (éviter texte 7–9px pour les infos clés).
5. **Uniformiser le wording FR** (éviter mélange “All_Modules”, “Details +”, termes anglais non nécessaires).

## B. LMS admin (pilotage)
1. **Créer une section “Actions du jour” en tête** (inscriptions en attente, formations expirant sous X jours, feedback non traités).
2. **Transformer les KPIs en KPIs actionnables** (clic direct vers la vue filtrée correspondante).
3. **Rendre explicite la santé opérationnelle** : ajouter indicateurs synthétiques (taux complétion par parcours, parcours sans activité récente, backlog validation).
4. **Réduire la charge cognitive des cartes d’accès** : regrouper par mission (Piloter, Publier, Contrôler, Certifier).

## C. Studio LMS (création/édition)
1. **Passer à un “setup wizard” post-création** :
   - Étape 1: Fiche minimale
   - Étape 2: 1er module + 1ère leçon
   - Étape 3: Présentation apprenant
   - Étape 4: Vérification publication
2. **Remplacer les longs pavés d’aide par “help accordions” contextuels** (repliés par défaut).
3. **Ajouter une checklist de complétion de publication** (slug, objectifs, visibilité, ressources, quiz, etc.) avec score de prêt-à-publier.
4. **Clarifier les CTA secondaires** (`Vitrine`, `Aperçu caviardé`, `Aperçu public`) par hiérarchie primaire/secondaire plus nette.
5. **Affiner l’expérience mobile** : simplifier barres d’actions compactes, éviter l’empilement de boutons à importance similaire.

---

## Quick wins (1 à 2 sprints)
- Standardiser libellés FR et tailles typographiques minimales.
- Ajouter “Actions du jour” sur dashboard admin.
- Ajouter checklist de publication sur `studio_edit`.
- Réduire la redondance des messages d’aide Studio (versions courtes + “En savoir plus”).
- Mettre des badges de progression sur le catalogue apprenant.

## Chantiers structurants (3 à 6 sprints)
- Mise en place d’un **design token commun LMS/Studio** pour harmoniser composants, focus states, contrastes.
- Refonte du flux de création en **workflow guidé** (wizard + autosave + validation continue).
- Instrumentation UX (temps pour créer/publier, taux d’abandon par écran, clics d’erreur).

---

## KPIs de succès recommandés
- Temps médian de création d’un parcours publiable.
- Taux de publication des brouillons créés.
- Délai de traitement des inscriptions en attente.
- Taux de clic carte → inscription (catalogue apprenant).
- Taux de complétion des parcours à 30 jours.

---

## Proposition de roadmap
- **Phase 1 (2 semaines)** : quick wins lisibilité + wording + actions du jour.
- **Phase 2 (3–4 semaines)** : checklist de publication + CTA harmonisés + micro-améliorations mobile.
- **Phase 3 (4–6 semaines)** : wizard Studio + instrumentation UX + optimisation continue.
