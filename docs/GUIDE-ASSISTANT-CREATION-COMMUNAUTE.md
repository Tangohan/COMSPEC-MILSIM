# Guide — Assistant « Créer une communauté »

Ce document décrit le parcours `/communities/create` : objectif de chaque étape, champs importants, aides intégrées et personnalisation possible avant et après création.

Version utilisateur (publique, sans compte) : **`/communities/create/guide`** — tableau « champ / à quoi il sert / conseil / où le modifier ensuite » pour chaque étape, structures types, modèles officiels et FAQ (`views/community/create_guide.php`).

## Accès

- Assistant : utilisateur connecté uniquement.
- Guide : public (lien « Guide complet des champs » dans l’en-tête de l’assistant et dans le tutoriel).
- Après création réussie, le fondateur dispose d’un compte dans la nouvelle communauté avec le rôle « Fondateur ».

## Aides intégrées à l’assistant

| Aide | Où | Comportement |
|------|----|--------------|
| Tutoriel en 6 diapositives | Bouton « Tutoriel en 2 minutes » (en-tête) | Ouvert automatiquement à la première visite (mémorisé dans `localStorage`, clé `athena.ccTutorialSeen`), jamais si un brouillon est repris. Flèches ← → et Échap. |
| Encart « À retenir » | Haut de chaque étape (`.cc-tip`) | Trois points clés de l’étape. |
| Explications de champ | `<details class="cc-help">` sous les champs sensibles | Repliées par défaut, sans JavaScript. |
| Validation | Étape 1 | « Suivant », les onglets et l’envoi exigent un nom ; adresse personnalisée vérifiée. |
| Aperçu de l’adresse | Sous « Nom affiché » | Calcul en direct de `/c/…`, avertit si l’adresse est réservée (`TenantSlugService::RESERVED`). |
| Progression | Bandeau d’étapes | Coche ✓ sur les étapes validées. |

## Vue d’ensemble des étapes

1. **Identité** — nom, adresse web, langue, fuseau, unité représentée (réelle via le référentiel militaire, ou fictive), vitrine facultative.
2. **Type** — Complet, Bureau des effectifs ou Carte ATAK (`TenantTypeConfig`), avec la conséquence de chaque choix affichée sur la carte.
3. **Organisation** — modèle de rôles, rôles supplémentaires, modèle officiel du catalogue (filtrable), structure type et arborescence ORBAT.
4. **Grades** — référentiel français ou américain, grade du fondateur.
5. **Accès & offre** — mode d’inscription, accueil, verrouillage, formulaire MilSim, créneaux, visibilité ORBAT, formule, ancienneté, aperçu, récapitulatif.

## Étape 3 — structures types (presets ORBAT)

Source : `App\Services\Community\WizardOrbatPresets` (testée par `tests/Unit/WizardOrbatPresetsTest.php`).

| Identifiant | Libellé | Unités |
|-------------|---------|--------|
| `minimal` | Structure minimale (valeur par défaut) | 3 |
| `squad` | Escouade | 4 |
| `section_fr` | Section d’infanterie (FR) | 11 |
| `platoon_us` | Peloton US (platoon) | 12 |
| `company_fr` | Compagnie (3 sections) | 15 |
| `sof_detachment` | Détachement forces spéciales | 5 |
| `combined_arms` | Groupement interarmes | 18 |
| `gaming` | Communauté de joueurs | 6 |

Un clic sur une carte remplace l’arborescence (confirmation si elle n’est pas vide). Contraintes respectées par chaque preset : une seule racine, types `group|section|squad|team`, adresses courtes uniques et conformes à `^[a-z0-9]([a-z0-9-]{0,48}[a-z0-9])?$`.

Côté serveur, `wizard_units_json` fait foi ; la structure par défaut ne sert que si rien n’est envoyé (navigateur sans JavaScript).

## Étape 3 — modèles officiels du catalogue

Source : `App\Services\OrganizationCatalog\OrganizationKitDefinitions` (synchronisés en base par `OrganizationCatalogService::ensureOfficialSeeded()`).

| Code | Titre | Grades |
|------|-------|--------|
| `official.french_army` | Armée française (catalogue d’emplois) | FR |
| `official.us_sof` | SOF américain (catalogue d’emplois) | US |
| `official.infantry_light` | Compagnie d’infanterie légère (v2 : appui, soutien, rôle Instruction) | FR |
| `official.us_army_infantry` | Compagnie d’infanterie US Army | US |
| `official.sof_task_force` | Task force d’opérations spéciales | US |
| `official.french_armor` | Escadron blindé | FR |
| `official.airmobile` | Groupement aéromobile | FR |
| `official.private_military` | Société militaire privée | FR |
| `official.gaming_community` | Communauté gaming (v2 : modération, missions, serveurs) | FR |

Le filtre « Filtrer les modèles » cherche dans le titre, le résumé et les noms de fonctions.

## Étape 1 — unités réelles

Le sélecteur s’appuie sur le référentiel `military_units` (seed `bootstrap/military_referential_seed.php`, 12 pays, sources et corrections dans [REFERENTIEL-UNITES-REELLES-SOURCES.md](REFERENTIEL-UNITES-REELLES-SOURCES.md)). La recherche ignore désormais accents, apostrophes et ordre des mots, et inclut les alias (sigles, surnoms, anciens noms).

## Après la création

- Paramétrage fin : back-office (présentation, rôles, utilisateurs, forum, etc.).
- La recherche du back-office (Ctrl/⌘+K) renvoie aussi des **guides** (`App\Services\Portal\BackOfficeHelpCatalog`) : sections du guide du portail et du guide de création.
- Communautés anciennes sans assistant complet : page **Aide après inscription** (`/back-office/onboarding-recovery`) et [QA_COMMUNITY_ONBOARDING.md](QA_COMMUNITY_ONBOARDING.md).

## Paiement Stripe

Si vous choisissez une formule payante, la configuration est stockée jusqu’à confirmation du paiement ; la communauté est créée lors du traitement du webhook Stripe avec la même configuration normalisée que pour une création gratuite.
