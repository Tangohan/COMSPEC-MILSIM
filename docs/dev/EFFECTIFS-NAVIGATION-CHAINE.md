# Effectifs : navigation « Chaîne de commandement »

Audit du 2026-11-16 : les pages du domaine « chaîne de commandement, organisation, doctrine, référentiels », leur accès
avant cette refonte, et ce qui a été ajouté. Toutes ces pages existaient déjà ; aucune page n’a été créée.

| Page | Adresse | Onglet Effectifs (avant) | Menu latéral (avant) | Ajouté |
| --- | --- | --- | --- | --- |
| Chaîne de commandement | `effectifs/chaine` | oui | oui (Effectifs) | sous-menu « Chaîne de commandement » › Vue de la chaîne ; pastille « postes de chef à pourvoir » |
| Adéquation de l’organisation | `effectifs/chaine/adequation` | **non** (onglet « Chaîne » allumé) | **non** | onglet « Plus › Organisation », sous-navigation, menu latéral |
| Échelons attendus (référentiel des échelons) | `effectifs/chaine/adequation?onglet=echelons` | **non** | **non** | sous-navigation, menu latéral |
| Règles de l’adéquation | `effectifs/chaine/adequation?onglet=regles` | **non** | **non** | lien « Ouvrir les règles » du résumé (contrôle inactif) |
| Doctrine des fonctions (relations de commandement entre rôles) | `roles-functions` | non | **non** (ouverte depuis Fonctions et emplois) | sous-menu « Chaîne de commandement », sous-navigation |
| Grades, règles d’avancement, catalogue OTAN | `referentiels/grades` (+ `?onglet=avancement`, `catalogue`) | non | oui (Référentiels) | sous-navigation (référentiels liés) |
| Qualifications (dont qualifications de commandement exigibles) | `referentiels/qualifications` | non | oui (Référentiels) | sous-navigation (référentiels liés) |
| Référentiel doctrinal (choix FR / US qui filtre les échelons) | `doctrine/referentiel` | non | oui (Doctrine et documents) | sous-navigation (référentiels liés) |
| Organigramme (ORBAT) | `orbat` | raccourci « Ouvrir l’ORBAT » | oui | — |
| Alertes RH liées (écarts d’adéquation) | `effectifs/a-traiter` | oui | oui (Dossiers RH) | — (lien `#eff-adequacy` conservé) |
| Effectif hors plage, sous-unités, surqualification | onglet « Vue d’ensemble » de l’adéquation | — | — | résumé par type sur la chaîne |

Pas de page dédiée aux « qualifications de commandement » : elles se choisissent par échelon dans « Échelons attendus ».

## Où c’est déclaré

- Sous-navigation (shell Effectifs, pages `chaine` et `adequation`) : `App\Support\CommandChainNav` ; chaque lien est filtré
  par les règles du menu latéral (type de communauté, Gate).
- Menu latéral : `views/partials/ath_sidebar_nav.php`, sous-menu « Chaîne de commandement » de « Effectifs ».
- Pastille du menu : `config/back_office_menu.php`, clé `chaine_chefs` (`CommandChainVacanciesBadge`, même compte que
  l’onglet). Les écarts d’adéquation ne sont pas une pastille du menu (calcul trop lourd à chaque page) : ils sont comptés
  sur les deux pages qui les évaluent déjà.
- Onglet « Adéquation de l’organisation » : `views/admin/effectifs_workspace/shell.php` (groupe « Organisation »).
