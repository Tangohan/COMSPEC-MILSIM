# Version minimale des DLL du jeu (SEC-001)

Les DLL publiées jusqu'à COMSPEC Link 2.0.95 (ATAK natif 2.4.0) et la DLL native « 1.0.0-native » joignent les
secrets Athena (clé communauté, Bearer, jeton de session, SteamID) à tout hôte qu'un script leur demande. Le
correctif sort avec ATAK natif 2.4.1 / COMSPEC Link 2.0.96. Une fois cette version publiée sur le Workshop, Athena
refuse les DLL plus anciennes et demande au joueur de mettre à jour.

Le blocage est **inactif par défaut** : il ne s'allume qu'en réglant un plancher de version (réglage plateforme
`game_min_link_version`, plus `game_min_atak_version` pour le numéro cité au joueur). Les deux domaines
(athena-comspec.eu, athena.ttrd.fr) sont la même application : un seul réglage les couvre.

## Activer, après la publication Steam

Sur le VPS (root), une fois ATAK 2.4.1 / COMSPEC Link 2.0.96 publié sur le Workshop :

```sh
cd /var/www/athena.ttrd.fr && php8.4 tools/game-client-gate.php set 2.0.96 --atak 2.4.1
```

Effet en 30 s au plus. Vérifier, sans rien envoyer à personne :

```sh
php8.4 tools/game-client-gate.php status
php8.4 tools/game-client-gate.php check "COMSPECExtension/2.0.95"                                        # REFUSÉ, HTTP 403
php8.4 tools/game-client-gate.php check "COMSPECExtension/2.0.95" --path /api/game/v1/session/restore    # REFUSÉ, HTTP 426
php8.4 tools/game-client-gate.php check "COMSPECExtension/2.0.96"                                        # accepté
```

Chaque changement (CLI ou administration) est relu en base et inscrit au journal d'audit
(`platform.game_client_floor_updated`).

## Retour arrière

- CLI : `php8.4 tools/game-client-gate.php clear` (plus aucune DLL refusée), ou `set <version plus basse>`.
- Site : Administration plateforme › Paramètres système › « Version minimale des DLL du jeu » : bouton
  « Désactiver le blocage » (un clic), ou modifier le numéro (réservé à l'administration du site).

## La règle

- Seules les requêtes dont le User-Agent annonce une DLL COMSPEC sont concernées : `COMSPECExtension/<version>`
  (COMSPEC Link) et `COMSPECATAKNativeExtension/<version>` (DLL native). Navigateurs, outils, robots, intégrations et
  requêtes sans User-Agent ne sont jamais refusés.
- La version est celle du User-Agent, suffixes ignorés (`1.0.0-native` = 1.0.0, `2.0.96+sha` = 2.0.96). Absente ou
  illisible : champ `extension_version` de la requête ; toujours illisible : refus.
- Les deux DLL sont comparées au même plancher (elles partagent le même code, `Extension.cs`).
- `/api/atak/version` reste ouvert : les anciens mods y apprennent qu'une mise à jour existe.
- Contrôle fait dans `public/index.php` (`App\Support\GameClientVersionGate`), avant la maintenance, la session et la
  base : un refus ne lit ni n'écrit rien en base, n'ouvre pas de session et n'écrit aucun journal applicatif. Le
  plancher est lu dans un petit fichier de cache (`storage/cache/atak-gate/client-version-floor.json`, relu en base
  toutes les 30 s au plus).

## Ce que la DLL corrigée doit annoncer

- COMSPEC Link : `User-Agent: COMSPECExtension/2.0.96` (InformationalVersion du csproj, déjà repris par la DLL).
- DLL native : `User-Agent: COMSPECATAKNativeExtension/2.0.96-native`. Le csproj natif annonçait `1.0.0-native`,
  ce qui aurait fait refuser la DLL native **corrigée** ; il est passé à `2.0.96-native` dans la PR du correctif
  2.4.1 (`mod/COMSPEC_ATAK_Native/COMSPECATAKNativeExtension/COMSPECATAKNativeExtension.csproj`). Toute DLL
  native reconstruite doit annoncer un numéro qui **commence** par 2.0.96 ou plus.
- Le numéro doit commencer par des chiffres (`native-2.0.96` est illisible, donc refusé).

## Ce que voit le joueur

Réponses : 426 `MOD_OUTDATED` à l'ouverture de session (Game Auth `/api/game/v1/`, liaison par code `/api/atak/pair/`,
`/api/atak/recovery/`, `/api/atak/game-link/`, `/api/atak/session-restore`) ; 403 `{"error":"unauthorized",
"code":"MOD_OUTDATED"}` + `Retry-After: 600` partout ailleurs. Corps : `message` (anglais) et `message_fr`, par
exemple « COMSPEC ATAK is out of date: update it from the Steam Workshop (2.4.1 or later). », puis
`min_mod_version` / `detected_mod_version` (numéros de DLL, 2.0.96 / 2.0.95 : ce sont eux que COMSPEC Link affiche),
`min_link_version`, `detected_link_version`, `min_atak_version` (2.4.1) et `client_product`.

| Mod installé | Effet attendu (lecture du code, à tester en jeu) |
|---|---|
| ATAK 2.4.1 / Link 2.0.96 et plus | aucun |
| ATAK 2.4.0 / Link 2.0.95, au lancement | reprise de session refusée en 426 : « Cette version du pack n'est plus acceptée. Pack actuel : 2.0.95 — version exigée : 2.0.96 » (COMSPEC Link) ou « Votre mod est trop ancien pour Athena. Mettez le mod à jour sur Steam » (téléphone) ; liaison par code : « Votre mod est plus ancien que la version minimale… Mettez le mod à jour sur Steam » ; « mise à jour disponible » si le site déployé annonce 2.4.1 |
| ATAK 2.4.0 en partie au moment de l'activation | premier sondage refusé (403 « unauthorized ») : la DLL arrête sondages et envois (verrou de refus), Athena passe hors ligne ; à la reconnexion, mêmes messages que ci-dessus |
| Link 2.0.94 et avant (sans verrou de refus) | mêmes messages à la connexion ; en partie, chaque appel est refusé avant la base, les envois attendent jusqu'à 10 min (Retry-After) |
| ATAK natif seul, DLL native d'avant le correctif (`1.0.0-native`) | refusé comme Link 2.0.95 |
| ATAK natif seul, DLL native 2.4.1 (`2.0.96-native`) | aucun |

Les serveurs dédiés Windows qui chargent une ancienne DLL sont refusés de la même façon jusqu'à leur mise à jour.
