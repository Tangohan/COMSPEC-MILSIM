# COMSPEC ATAK Native — tests

## Vérifications statiques avant build

1. Vérifier les accolades/configs et l'existence de toutes les fonctions déclarées dans `CfgFunctions`.
2. Rechercher dans `atak_native` les chaînes interdites (`html`, `javascript`, `leaflet`, `webview`, `cef`, `JSDialog`) ; seules les mentions documentaires éventuelles sont permises, jamais du code.
3. Construire avec `mod/UptoDate/build_mod.bat` sous Windows/Arma Tools. `atak_native.pbo` est obligatoire.
4. Publier en staging avec `workshop-pack.ps1`; l'absence du PBO natif doit interrompre le script.

## Matrice en jeu

| Test | Procédure | Résultat attendu |
|---|---|---|
| Offline | bloquer Athena/DNS, lancer mission, ouvrir via Ctrl+U | display, carte, joueur/groupe, sélection et marqueurs locaux disponibles ; `ATHENA OFFLINE` |
| Session valide | démarrer avec DLL et session restaurable | profil global préservé, `CONNECTED`, BFT, marqueurs, chat et tasks enrichis |
| Coupure | couper le réseau display ouvert | passage DEGRADED/OFFLINE sans fermeture ni overlay bloquant |
| Retour | restaurer le réseau | callback Connected, notification courte, resync sans doublon |
| 20 cycles | ouvrir/fermer vingt fois puis `debugDump` | un seul PFH pendant ouverture, `-1` fermé, aucun control/EH dupliqué |
| Mission suivante | terminer/recharger une mission | state/store réinitialisés au preInit, préférences profile conservées |
| Dedicated MP | serveur dédié + plusieurs clients | aucun display/PFH UI serveur, données locales propres à chaque client |
| Sans mods optionnels | retirer ACE/ACRE/BCE/cTab | aucun popup requiredAddon, terminal complet |
| Avec mods optionnels | charger ces mods | connecteur peut enrichir radio/médical, aucune UI tierce superposée |
| RPT | filtrer `COMSPEC ATAK NATIVE` | canary version exacte, suppression legacy, display/map puis cleanup ; aucun spam |

## Validation visuelle

Tester 16:9, 16:10 et ultrawide, trois tailles d'interface Arma, carte à plusieurs zooms, rail complet, inspecteur et toasts (maximum trois). Vérifier le clavier/souris : sélection, clic droit informatif, Ctrl-ping, Shift-marqueur et navigation native de la carte.

## Limites de CI

Le moteur Arma 3, Arma Tools/AddonBuilder et `COMSPECExtension_x64.dll` Windows sont nécessaires pour les scénarios runtime. Sur un hôte Linux, les contrôles statiques valident la structure mais ne remplacent pas les dix essais en jeu ni l'inspection du RPT réel.
