# Comptes rendus dans le JNET

La page du back-office `/back-office/atak/comptes-rendus` reste en place. Le JNET présente les mêmes données à
`/jnet/comptes-rendus`, filtrées par les mêmes droits. Un lien « Ouvrir dans le JNET » mène du back-office au JNET, et un
lien « Ouvrir dans le back-office » fait le chemin inverse.

## Données et services

| Donnée | Table | Dépôt / service |
|---|---|---|
| Comptes rendus post-op | `aar_reports` | `AarReportRepository` (lecture, `setStatus`, `saveActions`, `countToReview`) |
| Règles pures (statuts, transitions, filtres, tri, indicateurs, actions) | aucune | `AarReportAdminService` (`filter` gère aussi `type` et `unit` ; `sort` est nouveau) |
| Pièces jointes (BDA, CR du terrain, annexes rédigées) | `aar_report_annexes` (+ `atak_tactical_reports`) | `AarAnnexRepository::listForReport` |
| Circuit de relecture (journal, avis à l'auteur) | moteur de circuits | `WorkflowHooks::statusChanged`, appelé par `setStatus` |
| CR envoyés depuis l'ATAK | `atak_tactical_reports` | `AtakMobileHistoryService::sent` (`map_id` 0 = toutes les parties) |
| CR de fin d'opération imposés au joueur | `atak_debriefs`, `atak_debrief_recipients` | `AtakDebriefRepository::forUser` (nouveau, lit uniquement les lignes du joueur) |
| Remontées du terrain (onglet du back-office) | `atak_tactical_reports` + `intel_intake_*` | `IntelIntakeService::listItems(source cr)` |

Aucune migration n'a été ajoutée.

## Statuts

- Compte rendu post-op : `pending` (En attente), `in_review` (En relecture), `validated` (Validé), `missing` (Manquant).
  Les transitions autorisées sont dans `AarReportAdminService::TRANSITIONS`.
- CR envoyé depuis l'ATAK : Reçu par Athena, Lu par le poste de commandement, Traité, Archivé (`AtakMobileHistoryService::card`).
- CR de fin d'opération : Envoyé, Affiché, Ouvert, Remis, Lu, Expiré, Annulé (`AtakDebriefRepository::state`).

## Qui voit quoi

La règle est commune : `App\Services\Aar\AarReportAccess`. Le back-office (`canOpenReports`) l'utilise aussi.

- Lire les comptes rendus post-op : `admin.system`, `admin.organization` ou `admin.access`, une communauté réelle
  (autre que `default`) et le module ATAK en lecture. Ce sont les conditions des routes du back-office
  (`TenantResourceAdminMiddleware` + `guardAtak('view')`).
- Traiter (changer le statut, clore ou rouvrir une action) : en plus, le module ATAK en gestion. Les profils
  d'administration l'ont tous par repli : en pratique, tout lecteur peut aussi traiter.
- Membre, et chef d'unité sans droit d'administration : seulement « Mes comptes rendus ». C'est la liste de ses propres
  envois ATAK (son compte ou son Steam) et de ses CR de fin d'opération avec leur état. Son téléphone lui montre déjà ces
  informations. Il ne voit aucun compte rendu post-op, aucune pièce jointe et aucune remontée d'un autre joueur.
- Vue par unité : le back-office n'en a pas. Un compte rendu n'a pas d'unité propre. Le filtre « Unité » du JNET utilise
  l'unité de l'auteur, et seul un lecteur y a accès.
- Le profil de communauté est vérifié : `jnet/comptes-rendus` relève du module `atak` dans `TenantTypeConfig::moduleForUri`,
  comme la page du back-office.

## Routes JNET

| Route | Accès | Réponse |
|---|---|---|
| `GET /jnet/comptes-rendus` | tout membre connecté | 200. Onglets : mes, rapports, actions, terrain. Un non-lecteur ne reçoit que « mes », même s'il demande un autre onglet. |
| `GET /jnet/comptes-rendus/{id}` | lecteur | 200. Sinon 404, identique pour un compte rendu absent, d'une autre communauté ou interdit. |
| `POST /jnet/comptes-rendus/{id}/statut` | personne qui peut traiter | Appelle `AdminAarReportsController::status`, puis redirige vers la fiche JNET. 403 avant toute lecture si le droit manque, 404 si le compte rendu est absent. |
| `POST /jnet/comptes-rendus/{id}/actions/basculer` | personne qui peut traiter | Appelle `AdminAarReportsController::toggleAction`. Avec `retour=suivi`, redirige vers l'onglet des actions. |

Le jeton CSRF est vérifié par le contrôleur, puis une seconde fois par le back-office. `CsrfPostMiddleware` couvre aussi
`/jnet/comptes-rendus/`. Pour la journalisation, le circuit de relecture et l'audit des interventions passent par le même
chemin qu'en back-office.

## Pièces jointes

Les annexes s'affichent uniquement sur la fiche, donc pour un lecteur. Elles ne contiennent que du texte. Le rapport du
terrain lié s'ouvre par la route contrôlée `/back-office/remontees/cr/{id}`, et le PDF par
`/back-office/atak/comptes-rendus/{id}/pdf`. Le JNET ne sert aucun fichier lui-même.

## Messages JNET

« Répondre par message » ouvre `/jnet/courrier/nouveau?membre={auteur}&cr={id}`. La rédaction existante (même service,
mêmes droits d'envoi) préremplit l'objet « Compte rendu AAR-xxxx — opération » et ajoute un lien vers la fiche JNET, à
condition que l'utilisateur puisse lire le compte rendu. Aucun lien n'est stocké en base : le message garde le compte
rendu en référence dans son objet et son corps.

## Non repris

- La rédaction, la modification, la suppression, les modèles et les synthèses restent dans le back-office. Le lien
  « Ouvrir dans le back-office » y mène.
- « Accuser lecture » et « prendre en charge » n'existent pas pour un compte rendu post-op. Le JNET propose les
  transitions existantes : « Passer en relecture », « Valider », « Renvoyer à l'auteur », « Marquer manquant ». Pour les
  CR du terrain, l'accusé de lecture et la prise en charge se font dans les remontées.
- Point existant, non corrigé ici : `GET /api/atak/aar-reports/export` ne vérifie pas le droit ATAK.
