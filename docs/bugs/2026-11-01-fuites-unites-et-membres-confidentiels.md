# Fuites d’identité sur les unités et membres confidentiels

## Contexte
Harmonisation de l’affichage des unités masquées / anonymisées / restreintes et des membres anonymisés
(pastille commune `App\Support\ConfidentialDisplay`). En passant sur chaque écran, plusieurs endroits
affichaient encore des informations que les règles de confidentialité existantes devaient cacher.

## Symptôme
- Organigramme (ORBAT, tableau de bord, JNET) : dans une unité anonymisée ou restreinte, le nom du chef et des
  titulaires de poste restait visible (cartes, fiche unité, liste des postes) ; le portrait du chef s’affichait
  sur le tableau de bord pour une unité restreinte.
- Organigramme : un membre anonymisé gardait son vrai nom s’il tenait un poste ou commandait une unité normale.
- Unité anonymisée : code hiérarchique, identifiant administratif, devise et insigne restaient transmis.
- Page publique de la communauté : unités masquées listées, nom réel d’une unité anonymisée dans le roster public,
  chef anonymisé affiché sous son nom et avec son portrait.
- Page publique d’une unité : liste nominative complète, sans tenir compte de la confidentialité du membre ni de l’unité.
- Plan du site : adresse des pages d’unités masquées ou anonymisées (le nom est dans l’adresse).
- Annuaire : prénom et nom civils, adresse de fiche et radio d’un membre anonymisé encore affichés.
- Disponibilité des unités : unités masquées listées pour un lecteur ayant seulement la consultation de l’ORBAT.

## Cause
Le filtrage (`OrgVisibilityService`) s’appliquait aux membres et aux unités, mais les données ajoutées après
(postes et titulaires, chef dérivé des postes, codes d’unité) et les pages publiques / la disponibilité ne
passaient pas par ce filtrage.

## Correctif
- `OrbatRosterPayload::redactPeopleForViewer()` après l’ajout des postes ; `commanderUserId` remis à 0 sur unité
  restreinte ou aux noms abrégés ; `anonymizeUnitNode()` vide aussi codes, devise, insigne, chef et adjoint.
- `OrgVisibilityService::effectiveUnitLevels()`, `publicUnitRow()`, `rosterForViewer()` utilisés par
  `CommunityController` (vitrine, page unité, roster public) et `SeoController` (plan du site).
- `UnitReadinessService::applyVisibility()` sur la page Disponibilité.
- Annuaire : champs d’identité supplémentaires vidés pour un membre anonymisé.
Aucune règle (qui voit quoi) n’a changé : les lecteurs habilités voient toujours tout, avec une pastille de niveau.

## Fichiers touchés
`app/Support/OrbatRosterPayload.php`, `app/Services/Organization/OrgVisibilityService.php`,
`app/Support/DashboardOrbatTree.php`, `app/Controllers/Web/CommunityController.php`,
`app/Controllers/Web/SeoController.php`, `app/Controllers/Web/PersonnelController.php`,
`app/Repositories/UserRepository.php`, `app/Services/Personnel/UnitReadinessService.php`,
`app/Controllers/Admin/Organization/UnitReadinessController.php`.

## Vérification
`tests/Unit/ConfidentialDisplayTest.php` (titulaires de poste, chef anonymisé, page publique, roster, disponibilité),
`tests/Unit/OrgVisibilityServiceTest.php`.

## Statut
Corrigé.
