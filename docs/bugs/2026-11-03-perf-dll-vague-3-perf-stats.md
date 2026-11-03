# Optimisation de la DLL COMSPEC Link (vague 3) et mesure Perf.Stats — ATAK 3.0.5

## Contexte

Troisième vague d'optimisation après 2.9.37 (SQF, redessins) et 3.0.1 (carte, zones, routes). Cette fois la DLL C#
(`mod/UptoDate/COMSPECExtension/Extension.cs`, compilée aussi en DLL native) est auditée pour la première fois :
connexions HTTP, travail sur le fil d'appel d'Arma, allocations, journal, verrous, caches, chargement, mesure.

## Symptôme

- Aucun chiffre sur le temps passé dans la DLL : impossible de dire si une saccade vient d'un `callExtension`.
- `GetWantedNotices` appelée en direct par le SSE (`fn_bioWantedCache`) : requête HTTP sur le fil du jeu, jusqu'à 8 s.
- Journal fichier : une ouverture / fermeture du fichier par ligne (fil de fond, mais des dizaines de lignes par seconde en
  débogage) ; chaque réponse au jeu allouait un `byte[]` ; découpage d'une tenue volumineuse reparcouru à chaque morceau ;
  `GetWantedNotices` recopiait sa sortie à chaque avis pour compter les octets ; `LatestVersions` refaisait sa requête à
  chaque contrôle d'intégrité ; le csproj de la DLL native annonçait encore 2.0.102 (RVExtensionVersion).

## Cause

Points restants d'une DLL par ailleurs déjà conforme (client HTTP unique avec pool et keep-alive, gzip et ETag, aucun
travail au chargement, porte Async à 6, lots de télémétrie, paliers de repli, sondages servis en mémoire) : aucune
mesure interne, un chemin synchrone resté ouvert aux appels directs, et quelques allocations ou relectures par appel.

## Correctif

- `Perf.Stats` : chronomètre aux deux points d'entrée, par commande (appels, moyenne, p95 par paliers, pire), remise à
  zéro ; lu par `fn_perfDll` dans l'onglet Debug › PERFS (sections DLL native / COMSPEC Link, bouton RAZ DLL).
- `GetWantedNotices` hors Async : `ServePollGet` avec rafraîchissement de 5 min ; dans Async (Actualiser) : lecture fraîche.
- Journal écrit par paquets ; `Output` sur tampon loué ; `GetBuffer` au lieu de `ToArray` (7 réécritures JSON) ;
  découpage des tenues calculé une fois ; `LatestVersions` gardée 5 min ; octets comptés au fil de l'eau.
- SQF : `fn_notificationsRender` sort tout de suite sans file ni toast affiché.
- Versions : ATAK 3.0.5, COMSPEC Link 2.0.119 (DLL native 2.0.119-native), connect 1.6.34.

## Fichiers touchés

`mod/UptoDate/COMSPECExtension/Extension.cs`, `COMSPECExtension.csproj`,
`mod/COMSPEC_ATAK_Native/COMSPECATAKNativeExtension/COMSPECATAKNativeExtension.csproj`,
`mod/COMSPEC_ATAK_Native/Sources/addons/main/` : `script_component.hpp`, `config.cpp`, `i18n/en.txt`,
`functions/core/fn_perfDll.sqf` (nouveau), `functions/pages/fn_pageDebug.sqf`, `functions/ui/fn_notificationsRender.sqf` ;
`mod/UptoDate/Sources/comspec-overwatch-addons/connect/` : `config.cpp`, `functions/fn_extFast.sqf`.

## Vérification

- `dotnet build` des deux projets : OK (avertissements préexistants). `hemtt check` : 2 erreurs connues, inchangées.
- En jeu (à faire) : Debug › PERFS après quelques instants (deux sections DLL, p95, RAZ DLL, anglais : RESET DLL) ;
  app Recherchés « Actualiser » et SEEK / biométrie du SSE ; journal `COMSPEC_*.log` ; tenue volumineuse ; toast
  reçu puis expiré ; contrôle d'intégrité des versions.

## Statut

Corrigé en sources, DLL et PBO reconstruits (3.0.5), à vérifier en jeu.
