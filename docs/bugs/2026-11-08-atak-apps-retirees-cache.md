# Désactivation d'une app par communauté : plusieurs trous

## Contexte
Retour Discord : « Is that with the CBA settings? ». Vérification du mécanisme pour toutes les apps.

## Symptôme
- « Apps retirées » (CBA) sans effet après une première mission dans la même session de jeu, et sans effet en cours de partie.
- Règle Athena (Contrôle serveur) appliquée seulement à la navigation suivante ; l'app ouverte restait ouverte.
- Route, Zones, Debrief, Tenant, Academy, Calc et Notes absentes de la liste du site : impossibles à interdire depuis Athena.
- `Settings` ou `Store` dans « Apps retirées » : plus aucun moyen de régler ou de réinstaller.
- Poste TOC interdit ou apps civiles coupées : encore ouvrables par un raccourci.

## Cause
- Cache `COMSPEC_ATAK_AppsCache` dans uiNamespace (survit à la mission), jamais relié à la valeur du réglage.
- Boucle des réglages de communauté sans appel à `fn_appInstall` « applied ».
- `AtakExperienceService::phoneApps` en retard sur `COMSPEC_ATAK_Apps`.

## Correctif
Cache lié à la valeur du réglage, « applied » sur chaque changement (CBA et Athena), liste du site complétée, Réglages et App Store protégés, contrôle dans `fn_navigate`.

## Fichiers touchés
`fn_appList`, `fn_appInstall`, `fn_navigate`, `XEH_postInitClient`, `AtakExperienceService`.

## Vérification
Tests PHP (`AtakAppStorePolicyTest`, `AtakPilotSpacesTest`), `hemtt check`. Pas de test en jeu possible ici.

## Statut
corrigé à vérifier en jeu
