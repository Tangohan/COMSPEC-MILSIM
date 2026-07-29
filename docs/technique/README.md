# Documentation technique — Athena / COMSPEC-MILSIM

Ce sommaire s’adresse aux **développeurs** et à l’**exploitation** : architecture, dépôt, configuration, sécurité et intégrations.

## Mod Overwatch (documentation portail)

Fiches destinées aux moddeurs / intégrateurs, exposées sur `/documentation/references` (sans surface réseau du portail) :

| Document | Contenu |
|----------|---------|
| [Index mod](overwatch-mod/index.md) | Vue d’ensemble pack @COMSPECOverwatch |
| [Architecture & addons](overwatch-mod/architecture.md) | PBO, extension, conventions SQF |
| [Bibliothèques & dépendances](overwatch-mod/bibliotheques-et-dependances.md) | CBA, ACE, cTab, BCE, KAT, Mavic, radios… |
| [Compilation & publication](overwatch-mod/compilation.md) | Build, Workshop |

Sources Markdown du dépôt mod : `mod/UptoDate/docs/`.

## Pages

| Document | Contenu |
|----------|---------|
| [Architecture applicative](architecture.md) | Point d’entrée HTTP, routage, middlewares, multi-tenant |
| [Structure du dépôt](structure-du-depot.md) | Arborescence et rôles des répertoires |
| [Configuration et déploiement](configuration-et-deploiement.md) | Variables d’environnement, migrations, production |
| [Pilotage mensuel & fiabilisation déploiements](pilotage-mensuel-fiabilisation-deploiements.md) | KPI mensuels, workflow idempotent pré-prod → prod, smoke tests et rollback minimal |
| [Modules fonctionnels](modules-fonctionnels.md) | Cartographie fonctionnalités ↔ zones du code |
| [Sécurité et permissions](securite-et-permissions.md) | Auth, RBAC, API tactiques, en-têtes |
| [Intégrations externes](integrations.md) | Courriel, Stripe, clients tactiques |
| [Blueprint LMS compétences](lms-competency-system-blueprint.md) | Schéma multi-tenant compétences/modules et prompt enrichi |
| [Plan amélioration administration site](plan-amelioration-administration-site-mod-admin-support.md) | Diagnostic et axes d'amélioration modération/support/admin |
| [Plan exécution administration site](plan-execution-administration-site-mod-admin-support.md) | Roadmap exécutable par lots, RACI, DoD, KPI et run |
| [Plan amélioration interactif/UI-UX/features](plan-amelioration-interactif-ui-ux-feature.md) | Analyse et plan exécutable pour interactions, UX/UI et fonctionnalités transverses |
| [Analyse projet & propositions (avril 2026)](analyse-ajouts-ameliorations-2026-04.md) | Diagnostic global et feuille de route d'amélioration priorisée |
| [Plan P2 doctrine/XP/wargaming/AAR IA](plan-p2-doctrine-xp-wargaming-aar.md) | Spécification exécutable des chantiers P2.1 à P2.4 et détails UI/UX immersion |

## Liens utiles

- [Guide utilisateur](../utilisateur/README.md) — documentation fonctionnelle sans jargon technique interne.

---

*Produit : portail **Athena** pour communautés MILSIM et gestion opérationnelle.*
