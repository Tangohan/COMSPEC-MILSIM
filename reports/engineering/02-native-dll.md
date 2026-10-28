# Audit DLL / natif — branche release/2.7.0

Périmètre lu, aucun correctif appliqué :

- `mod/UptoDate/COMSPECExtension` (COMSPEC Link), version annoncée **2.0.99**
- `mod/COMSPEC_ATAK_Native/COMSPECATAKNativeExtension`, version annoncée **2.0.99-native**

Les deux projets sont des bibliothèques **C# / .NET 8**, `PublishAot=true` à la publication. La DLL native compile les mêmes sources que Link (`Extension.cs` et fichiers partiels) avec la constante `COMSPEC_ATAK_NATIVE`, plus quatre fichiers locaux : `Clipboard.cs`, `LocalConfig.cs`, `BtTransfer.cs`, `MusicPlayer.cs`.

Il n’existe **pas de projet C++ natif** (pas de `.vcxproj`, pas de CMake, pas de source `.cpp` d’extension). Les `config.cpp` du dépôt sont des configurations de module Arma, pas une DLL.

**ASan / UBSan : non applicables.** Le code audité est du C#. Le runtime managé et le Native AOT .NET ne se compilent pas avec AddressSanitizer ni UndefinedBehaviorSanitizer. Aucun résultat de sanitizer n’est inventé ici. Aucune publication Native AOT n’a été lancée (elle produit une DLL non chargeable par le banc, et le temps de compilation native n’apporte pas ces sanitizers).

Exports présents, attribut `UnmanagedCallersOnly` :

| Export | Site |
| --- | --- |
| `RVExtensionRegisterCallback` | `Extension.cs:429` |
| `RVExtensionVersion` | `Extension.cs:2816` |
| `RVExtension` | `Extension.cs:2855` |
| `RVExtensionArgs` | `Extension.cs:2956` |

`RVExtensionVersion` n’écrit que la version (`CurrentExtensionVersion`, attribut d’assemblage, repli `2.0.99`). `2.0.99-native` tient dans le tampon de 32 octets du moteur. La copie de réponse (`Output`, `Extension.cs:2824`) est bornée à 8 000 octets UTF-8 et termine par un NUL.

## SEC-001 — pas de contournement rouvert

`IsTrustedPortalHost` (`Extension.cs:3208`) n’accepte que `athena.ttrd.fr` et `athena-comspec.eu`, plus `localhost` / `127.0.0.1` / `::1` si `COMSPEC_DEV_TRUST_LOOPBACK=1` (absent en production). `IsSecretSafeUri` (`Extension.cs:3221`) exige une URL absolue, sans identifiants, hôte de cette liste, et `https` (ou `http` seulement vers la boucle locale de développement).

Contrôles revus dans le code actuel :

- `_baseUrl` n’est écrit que par `TrySetSqfBaseUrl` (`Extension.cs:3235`) et par une re-normalisation de la valeur déjà posée (`Extension.cs:4771`).
- `AttachApiKeyHeader` (`Extension.cs:3244`) sort avant de poser clé, Bearer, session ou Steam si l’URI n’est pas sûre.
- `GameGetJson` / `GamePostJson` (`GameAuth.cs:1069` et `1029`) refont le même test avant d’envoyer le Bearer du compte.
- `AllowAutoRedirect` est faux sur le client principal (`Extension.cs:139`). `SendImageGetFollowingRedirects` (`Extension.cs:2498`) réattache les en-têtes à chaque saut, refuse `https` vers `http`, et un hôte hors liste ne reçoit donc pas les secrets.
- `TrySendQueuedPost` (`Extension.cs:1638`) abandonne une URL de file non sûre **avant** `AddQueueSecrets`.
- `StampOfficialClient` (`OfficialClientProof.cs:17`) ne pose la preuve du module que si l’URI est sûre.
- Les clients sans secret (tuiles `MapPhotoHttp`, Google Slides, lecteur musique) sont des `HttpClient` distincts.

Le banc local (sortie réelle plus bas) ne montre pas de fuite vers `127.0.0.2`. L’unique échec du banc est un décalage du harnais, pas un contournement : voir la section Validation.

## Validation exécutée

`dotnet` 10.0.302. Builds **managés** (`-p:PublishAot=false`), 0 erreur.

COMSPEC Link, sortie réelle (extraits) :

```text
COMSPECExtension -> ...\mod\UptoDate\COMSPECExtension\bin\Release\COMSPECExtension_x64.dll
La génération a réussi.
6 Avertissement(s)
0 Erreur(s)
```

Avertissements : nullabilité `Extension.cs:3740`, `:3850`, `:5044` ; champs jamais lus `_apiKeyValidatedByClientInit` (`:36`) et `_rateLimitBackoffSec` (`:85`).

DLL native, sortie réelle :

```text
COMSPECATAKNativeExtension -> %TEMP%\comspec-dll-audit\native\bin\...\COMSPECATAKNativeExtension_x64.dll
La génération a réussi.
7 Avertissement(s)
0 Erreur(s)
```

Le septième est `CA1416` sur `Thread.SetApartmentState` (`MusicPlayer.cs:188`) : l’appel n’est garanti que sous Windows, ce qui est la cible réelle de la DLL.

Banc `tools/sec001-bench`, DLL Link managée, secrets factices, hôtes `127.0.0.1` et `127.0.0.2`. Pas d’espace réseau isolé : Windows n’a pas l’équivalent de `unshare -n` décrit dans le README du banc. `queue.jsonl` (0 octet) a été recopié avant le banc et restauré après ; `position.json` n’existait pas et a été retiré s’il avait été créé. `medical_roster.json` et `wardrobes.json` ne sont pas touchés par le banc.

Sortie réelle, mode développement (`COMSPEC_DEV_TRUST_LOOPBACK` posé par le banc) :

```text
=== Banc SEC-001 (DLL = CORRIGÉE) ===
    E:\Developpement\compsec.ttrd.fr\COMSPEC-MILSIM\mod\UptoDate\COMSPECExtension\bin\Release\COMSPECExtension_x64.dll

[A] AttachApiKeyHeader : en-têtes selon la destination
  [PASS] hôte non fiable (127.0.0.2) → aucun secret — en-têtes posés : User-Agent
  [PASS] athena.ttrd.fr (https) → secrets présents (pas de régression)
  [PASS] athena-comspec.eu (https) → secrets présents (portail du mod natif)
  [PASS] 127.0.0.1 → secrets présents (banc de développement)
  [PASS] http:// en clair vers athena.ttrd.fr → aucun secret — en-têtes posés : User-Agent
  [PASS] URL avec identifiants (x@athena.ttrd.fr) → aucun secret — en-têtes posés : User-Agent
  [PASS] sous-domaine atak.athena-comspec.eu → aucun secret (hors liste) — en-têtes posés : User-Agent

[B] Connect(url hostile) : _baseUrl, en-têtes et CORPS client-init
      réponse Connect = ERR|untrusted_host | _baseUrl après = ""
  [PASS] _baseUrl non pointé vers l'hôte hostile — refusé/ignoré
  [PASS] aucun secret (en-tête ni corps) reçu par l'hôte hostile — 0 requête(s), aucun secret
  [PASS] Connect renvoie ERR|untrusted_host — ERR|untrusted_host

[C] Toutes les commandes qui posent _baseUrl depuis SQF (URL hostile)
  [PASS] Connect            _baseUrl non hostile — ok (ERR|untrusted_host)
  [PASS] PairStart          _baseUrl non hostile — ok (ERR|untrusted_host)
  [PASS] RedeemRecoveryCode _baseUrl non hostile — ok (ERR|untrusted_host)
  [PASS] RedeemGameLink     _baseUrl non hostile — ok (ERR|untrusted_host)
  [PASS] LinkBySteam        _baseUrl non hostile — ok (ERR|untrusted_host)
  [PASS] RegisterBeta       _baseUrl non hostile — ok (ERR|untrusted_host)
  [PASS] ReportDiag         _baseUrl non hostile — ok (ERR|untrusted_host)
  [PASS] Init               _baseUrl non hostile — ok (OK|CONTACTING_ATHENA)
  [PASS] RestoreSession     _baseUrl non hostile — ok (OK|READY)
  [PASS] AuthPassword       _baseUrl non hostile — ok (ERR|invalid)
  [PASS] RequestOtp         _baseUrl non hostile — ok (ERR|invalid)
  [PASS] VerifyOtp          _baseUrl non hostile — ok (ERR|invalid)
  [PASS] AuthSteam          _baseUrl non hostile — ok (ERR|invalid)
  [PASS] Connect (chemin async RvExtensionArgsImpl) _baseUrl non hostile
  [PASS] aucun secret reçu par l'hôte hostile sur tout le balayage — 0 requête(s)

[Q] File d'envoi rechargée du disque avec une adresse non-Athena (fichier modifié)
  [PASS] file disque : aucun secret (corps compris) vers l'hôte hostile — hôte hostile non contacté
  [PASS] file disque : les deux abandons comptés (queue.jsonl et position.json) — 2 abandon(s)
  [PASS] file disque : un seul signalement, libellé fixe au 2ᵉ champ — 0|file-hors-athena|0|/api/atak/reports : hôte non-Athena, envoi abandonné (2)
  [PASS] file disque : signalement sans hôte ni secret — 0|file-hors-athena|0|/api/atak/reports : hôte non-Athena, envoi abandonné (2)

[E] Redirection 127.0.0.1 (confiance) → 127.0.0.2 (hostile)
  [PASS] l'hôte hostile ne reçoit aucun secret après redirection — non contacté (redirection non suivie)

[E2] DownloadBriefingSlideImage : image via redirection confiance → hostile
  [PASS] image : aucun secret vers l'hôte hostile (redirection comprise) — 1 requête(s), aucun secret
  [PASS] image : la redirection vers l'hôte hostile est bien suivie (CDN externe) — 1 requête(s) reçue(s) par la cible
  [PASS] image : GET direct vers hôte de confiance aboutit (contacté) — ERR|invalid_image / 1 requête(s)

[R] Tuiles /map-photo (client sans secret) : la redirection doit être suivie
  [PASS] tuile PHOTO : 301 suivie jusqu'au 200 — HTTP 200
  [PASS] tuile PHOTO : aucun secret posé sur ces requêtes — 2 requête(s)

[S] StartupCheck / LatestVersions / Server.PublicIp vers un hôte arbitraire
      StartupCheck    : ERR|untrusted_host
      LatestVersions  : ERR|untrusted_host
      Server.PublicIp : ERR|untrusted_host
  [PASS] aucune requête vers l'hôte arbitraire — 0 requête(s)
  [PASS] aucun secret vers l'hôte arbitraire — 0 requête(s)

[G] Bearer du compte (GameAuth) avec un _baseUrl hostile forcé par réflexion
      GameGetJson  : ERR|untrusted_host
      GamePostJson : EXC:methode GamePostJson
  [FAIL] GameGetJson / GamePostJson refusent l'hôte non sûr — ERR|untrusted_host / EXC:methode GamePostJson
  [PASS] aucune requête GameAuth vers l'hôte hostile — 0 requête(s)
  [PASS] aucun Bearer du compte vers l'hôte hostile — 0 requête(s)

[P] ... 14 accepté(s) sur 43, tous attendus ; 0 cible acceptée non sûre
=== BILAN : 1 ÉCHEC(S) ===
```

Code de sortie : **1**.

Sortie réelle, mode production du banc (`BENCH_PROD=1`, variable de boucle locale absente) :

```text
=== Banc SEC-001 (DLL = CORRIGÉE, mode PRODUCTION) ===
[PROD] Sans COMSPEC_DEV_TRUST_LOOPBACK : la boucle locale ne doit rien recevoir
  [PASS] 127.0.0.1 → aucun secret (réglage de développement absent) — en-têtes posés : User-Agent
  [PASS] Connect vers la boucle locale refusé — ERR|untrusted_host
  [PASS] aucune requête vers la boucle locale — 0 requête(s)
  [PASS] athena.ttrd.fr → secrets présents (production inchangée)
=== BILAN : aucun échec ===
```

Code de sortie : **0**. Le cas `[A]` « secrets présents » ne fait qu’appeler `AttachApiKeyHeader` : aucune requête n’est envoyée vers `athena.ttrd.fr` par ce cas.

### Écart du banc, cas [G]

`GamePostJson` est déclaré `GamePostJson(string, string, bool, string? bearerToken = null)` (`GameAuth.cs:1021`). Le banc cherche la surcharge à trois arguments (`Program.cs:339`) et lève `methode GamePostJson`. `GameGetJson` a bien répondu `ERR|untrusted_host`. Le PASS « 0 requête » du cas [G] ne prouve donc pas le refus de `GamePostJson` : la méthode n’a pas été appelée. Le garde `IsSecretSafeUri` est présent dans la source à `GameAuth.cs:1029`, avant `SendAsync`. Ce n’est pas un contournement SEC-001. Le harnais est en retard sur la signature.

### Effet de bord du banc sous Windows — à lire

Le README du banc suppose un `HOME` isolé et `unshare -n`. Sous Windows, `RestoreSession` lit le magasin DPAPI réel (`GameAuth.cs:1125`, `%LOCALAPPDATA%\Arma 3\COMSPEC\version-beta\secure\session.bin`).

Le banc a reçu `OK|READY` pour `RestoreSession` vers une URL hostile. Ce libellé vient de `FinishGameAuthReady` (`GameAuth.cs:796` et `:920`) après un JSON de session accepté. Le chemin « pas de jeton » renvoie `ERR|SESSION_EXPIRED` (`GameAuth.cs:374`). `TryResumeCommunityKeySession` (`GameAuth.cs:340`) sort tout de suite si `_baseUrl` est vide, ce qui était le cas (le banc le remet à `""` avant l’appel). L’URL hostile est ignorée (`GameAuth.cs:87`), puis `store.BaseUrl` est reposé s’il est de confiance (`GameAuth.cs:378`) et `RefreshGameSession` envoie le jeton de rafraîchissement (`GameAuth.cs:382`).

**Un rafraîchissement vers le portail enregistré dans ce magasin a donc pu avoir lieu, et le jeton local a pu être tourné** (`persistTokens` vaut vrai dans `ApplyGameAuthResponse`). Si Arma tournait avec l’ancien jeton en mémoire, la session en jeu peut être devenue incohérente. Le banc n’a pas été relancé. `Init` sur une URL refusée pose seulement `https://athena.ttrd.fr/public` en mémoire (`GameAuth.cs:53`) et ne fait pas d’HTTP lui-même.

## Constats

### DLL-001 — Lecture et envoi de n’importe quel fichier local

- **Statut :** confirmé
- **Fichiers :** `Extension.cs:4156` (`PathExists`), `Extension.cs:11080` (`TryReadableImageFile`), `Extension.cs:10947` (appel), `Extension.cs:6559` et `:10747` (`UploadImage`), `Extension.cs:6545` et `:9945` (file photo)
- **Gravité :** haute
- **Symptôme :** une mission ou un script qui appelle la DLL peut savoir si un chemin existe, déclencher une connexion vers un partage réseau, et faire envoyer un fichier local vers Athena avec la session du joueur.
- **Cause :** `PathExists` teste `Directory.Exists` / `File.Exists` sur l’argument tel quel, avant le contrôle de session (`Extension.cs:4769`). `TryReadableImageFile` accepte tout fichier d’au moins 32 octets dont le dossier parent existe : pas de liste de dossiers, pas de contrôle d’extension (contrairement à `TryExistingImagePath`, `Extension.cs:11310`). `ResolveLocalImagePath` prend ce résultat. `UploadImage` ouvre ensuite un flux et l’envoie (`Extension.cs:10764`). `NotifyNewPhoto` met le chemin en file ; le worker résout et envoie de la même façon. Un chemin `\\serveur\partage\...` est enraciné : `Directory.Exists` sur le parent ouvre une session SMB (le lecteur musique, lui, refuse explicitement l’UNC dans `MusicPlayer.cs:352`).
- **Preuve :** les fonctions ci-dessus. `ImageDataChunk` en `local:` est, lui, limité à `comspec_cache` (`Extension.cs:3445`). `BtFileOpen` photo est limité aux dossiers de captures (`BtTransfer.cs:71`).
- **Impact :** sondage du disque du joueur depuis n’importe quelle mission qui charge le module ; coercition d’authentification vers un partage nommé par la mission ; copie d’un fichier local (document, export, capture hors dossier prévu) vers le poste Athena du compte déjà lié. `PathExists` n’exige aucune session.
- **Correction minimale :** pour `PathExists`, refuser l’UNC et ne répondre que pour les dossiers que la DLL connaît déjà (captures, musique, cache). Pour la résolution photo, n’accepter un chemin absolu que s’il est sous un de ces dossiers, avec une extension d’image, et refuser `\\` avant tout `Exists`.
- **Validation :** appel direct `PathExists` sur un fichier hors profil (réponse attendue : absent, sans toucher au partage) ; `UploadImage` sur un `.txt` hors captures (attendu : refus, aucun POST). Ne pas pointer un partage réel.
- **Reproduction minimale (à ne pas lancer contre un partage) :** `"COMSPECExtension" callExtension ["PathExists", ["C:\Windows\win.ini"]]` puis, session déjà ouverte, `callExtension ["UploadImage", ["C:\chemin\hors\captures\notes.txt", "test"]]`.

### DLL-002 — Le thread du jeu reste bloqué par des commandes synchrones

- **Statut :** confirmé
- **Fichiers :** `Extension.cs:4171` et `:4177` ; `Extension.cs:2717` et `:2767` ; `fn_showBetaAccessNote.sqf:60` ; `fn_extensionCall.sqf:4` ; `Clipboard.cs:41` ; `LocalConfig.cs:128` ; `MusicPlayer.cs:370` ; `BtTransfer.cs:172` ; HTTP direct dans `TryGetSyncResponse` (ex. `GetClientIp` `Extension.cs:4815`, `Server.PublicIp` `:3651`, `AdminControl.Poll` `:3701`, `GameAdmin.Fleet` `:3764`)
- **Gravité :** haute pour la boîte de dialogue et pour tout HTTP encore appelé sans `Async` ; moyenne pour le téléphone natif
- **Symptôme :** la simulation s’arrête jusqu’au clic, jusqu’à 8 s (`SyncTimeoutSeconds`, `Extension.cs:31`, plafond du `HttpClient`), ou le temps d’un accès disque.
- **Cause :** `RvExtensionArgs` appelle `TryGetSyncResponse` sur le thread qui a appelé `callExtension`. Arma n’a qu’un thread pour ça : un `spawn` ne libère pas le jeu (commentaire de `fn_extAwait.sqf:2`). `ShowBetaAccessNote` et `ShowAthenaLinkHelp` appellent `MessageBoxW` / `BetaNoticeWindow.ShowModal` sur ce thread. Le repli SQF de la note bêta utilise `callExtension` direct, pas `extAwait`. Le téléphone natif passe par `extensionCall`, qui est aussi un `callExtension` direct : `ClipOpen` fait jusqu’à dix `Thread.Sleep(15)` en tenant le presse-papiers ; `LocalCommit` parse et écrit jusqu’à 1 Mo ; `MusicList` énumère l’arbre du dossier musique ; `BtRecvOpen` préalloue le fichier temporaire (`SetLength`, jusqu’à 30 Mo).
- **Preuve :** les appels ci-dessus sont dans le chemin de retour de `TryGetSyncResponse`, donc avant que `RVExtensionArgs` rende la main. Les commandes de sondage (`ServePollGet`, `Extension.cs:2608`) et la file `EnqueueOrSend` évitent cet aller-retour ; une grande partie des commandes « réponse utile » ne le fait pas.
- **Impact :** gel du poste, du réseau de la partie et des autres `callExtension` pendant la boîte de dialogue ou le délai HTTP. Sur un serveur dédié Windows qui appellerait ces commandes, le même gel touche le serveur.
- **Correction minimale :** afficher la boîte depuis un thread STA dédié et répondre tout de suite `OK|busy` / un ticket, le résultat revenant par le rappel. Pour le natif, traiter `ClipCommit`, `LocalCommit`, `MusicList` et `BtRecvOpen` comme les commandes `Async` (file bornée, rappel). Laisser en synchrone seulement `Ping`, `GetExtensionVersion` et les lectures mémoire déjà instantanées.
- **Validation :** en menu principal, repli de la note bêta : la simulation et un `callExtension ["Ping",[]]` concurrent doivent répondre pendant que la fenêtre est ouverte. Mesurer `ClipCommit` avec le presse-papiers tenu par une autre application (aujourd’hui jusqu’à 150 ms de gel).

### DLL-003 — HTTP synchrone sur le pool de threads, verrou de file tenu pendant l’attente

- **Statut :** confirmé pour le blocage ; **hypothèse** pour un blocage définitif du pool
- **Fichiers :** `Extension.cs:2533` (`SendAsync` + `GetResult`), `Extension.cs:1665`, `Extension.cs:1750` (`QueueDrainLock` tenu autour de l’envoi), `Extension_Telemetry.cs:586`, `Extension.cs:2194` (`MapPackInstall`)
- **Gravité :** haute
- **Symptôme :** à-coups de plusieurs secondes, file d’envoi qui ne se vide plus, téléchargement de tuiles qui empile des travaux.
- **Cause :** `GetResult()` occupe un thread du pool pendant toute la durée du `HttpClient` (8 s, 60 s pour les envois photo). La continuation de `SendAsync` a aussi besoin du pool : si trop de threads sont déjà dans `GetResult`, l’injection de threads du runtime est lente et tout le monde attend. `DrainQueue` garde `QueueDrainLock` pendant ces attentes (trois envois, plus le lot de télémétrie). Un second passage du minuteur fait `TryEnter` et abandonne le tick. `MapPackInstall` lance un travail qui télécharge toutes les tuiles des niveaux 0 à 3 en séquence, sans drapeau « déjà en cours » : chaque appel ajoute un travail.
- **Preuve :** `SendTimed` ne propose pas de chemin vraiment asynchrone. `HandleMapPackInstall` (`Extension.cs:2194`) n’a pas de `Interlocked` d’exclusion, contrairement au warmup (`Extension.cs:2897`).
- **Impact :** sous ralentissement d’Athena, les sondages `Async` (porte limitée à 6, `Extension.cs:3417`), les journaux et la file se retardent ensemble. La position coalescée attend la fin du tick. Ce n’est pas un interblocage de deux verrous (l’ordre observé est `QueueDrainLock` puis `TelemetryDrainLock`, et `DiskQueueLock` puis `CoalescedPositionLock` ; l’ordre inverse n’a pas été trouvé).
- **Correction minimale :** dans le minuteur et les travaux de fond, `await SendAsync` sans `GetResult`, et ne tenir `QueueDrainLock` que pour retirer les éléments, pas pendant l’HTTP. Un seul `MapPackInstall` à la fois.
- **Validation :** test managé qui sature un `HttpClient` local (délai 8 s) avec plus de travaux que de threads du pool, et vérifie qu’un `Ping` via le même processus reste sous 100 ms. Ne pas viser le portail.

### DLL-004 — Rappels vers le jeu non sérialisés

- **Statut :** confirmé pour l’absence de verrou ; **hypothèse** pour le plantage moteur
- **Fichier :** `Extension.cs:436`
- **Gravité :** haute
- **Symptôme :** rappels perdus, ordre incohérent, ou plantage d’Arma quand plusieurs résultats partent ensemble (file, photo, `Async`, musique).
- **Cause :** `InvokeCallback` copie le délégué puis lance un `Task.Run` par rappel. Rien ne sérialise l’appel `cb(...)`. Le moteur documente un rappel à appeler hors du thread de `callExtension`, et les extensions stables n’y entrent qu’un thread à la fois. En plus, `RVExtensionRegisterCallback(0)` met `_callback` à null (`Extension.cs:432`) sans attendre les tâches déjà parties : elles appellent encore l’ancien pointeur.
- **Preuve :** aucun `lock` entre la copie de `cb` et l’appel. Les chaînes sont libérées après le retour de `cb` (`Extension.cs:456`), ce qui est correct si le moteur recopie pendant l’appel. Le défaut est la concurrence et la durée de vie du pointeur, pas le `FreeCoTaskMem`.
- **Impact :** le journal de liaison, les tickets `Async` et les photos peuvent se croiser. Un rappel après déchargement de mission peut toucher un pointeur périmé.
- **Correction minimale :** une file bornée et un seul thread de rappel ; à la désinscription, vider la file avant de lâcher le pointeur.
- **Validation :** banc managé qui enregistre un délégué comptant les chevauchements, envoie 100 `InvokeCallback` d’un coup, et attend un maximum de 1 appel simultané. Le plantage moteur, lui, ne se prouve que dans Arma.

### DLL-005 — Exception possible avant le filet des points d’entrée

- **Statut :** confirmé (le filet ne couvre pas le préambule) ; le déclenchement par Arma reste un cas limite
- **Fichiers :** `Extension.cs:2956` (`RVExtensionArgs`), `Extension.cs:2855` (`RVExtension`)
- **Gravité :** moyenne
- **Symptôme :** Arma se ferme net au lieu de recevoir `ERR|...`.
- **Cause :** le commentaire en `:2970` dit qu’une méthode `UnmanagedCallersOnly` ne doit pas laisser sortir d’exception (fail-fast du runtime). Le `try` commence seulement après `PtrToStringUTF8` et la boucle sur `argCount`. Un `argCount` négatif lève à l’allocation ; un pointeur illisible lève pendant la lecture. `RVExtension` a le même trou sur `PtrToStringUTF8` (`:2858`) avant son `try`.
- **Preuve :** le `try` de `RvExtensionArgs` est à la ligne 2975, après la boucle 2960-2968.
- **Impact :** un `callExtension` mal formé, ou un pointeur déjà libéré par le moteur, tue le processus au lieu de renvoyer une erreur.
- **Correction minimale :** entourer tout le corps des trois exports (arguments compris) du même `try`, et répondre un `ERR|` fixe si la lecture des arguments échoue. Borner `argCount` (par exemple 64) avant d’allouer.
- **Validation :** test managé qui appelle la méthode avec `argCount = -1` et vérifie qu’elle retourne au lieu de faire tomber le processus. Ne pas le brancher dans Arma tant que le filet n’est pas en place.

### DLL-006 — Compteurs de ralentissement modifiés sans atomicité

- **Statut :** confirmé
- **Fichier :** `Extension.cs:1342` (`NoteSendFailure`), `:1426` (`NoteSendSuccess`)
- **Gravité :** moyenne
- **Symptôme :** l’échelle 45 s → 10 min monte ou redescend de façon incohérente ; deux succès ne suffisent plus, ou une rafale d’échecs est sous-comptée.
- **Cause :** `_sendFailStreak`, `_sendOkStreak` et `_sendBackoffStep` sont des `int` incrémentés par plusieurs threads (minuteur, photos, `Async`, télémétrie) sans `Interlocked` ni verrou. Les dates en `long` du même bloc utilisent `Interlocked`, pas ces compteurs.
- **Preuve :** `_sendFailStreak++` à `:1343` ; `_sendBackoffStep++` à `:1354`.
- **Impact :** en coupure, le poste peut annoncer un délai faux, ou reprendre les envois trop tôt et se reprendre un 429.
- **Correction minimale :** `Interlocked.Increment` / `Decrement`, ou un seul verrou déjà existant autour de tout le bloc « échec / succès ».
- **Validation :** deux tâches qui appellent `NoteSendFailure` 1000 fois ; le palier final doit être déterministe.

### DLL-007 — Envois « best effort » retirés de la file sans remise

- **Statut :** confirmé
- **Fichier :** `Extension.cs:1808`
- **Gravité :** moyenne
- **Symptôme :** météo ou flux vidéo émis pendant une pause réseau disparaissent. Le joueur ne les voit pas repartir à la reconnexion.
- **Cause :** l’élément est déjà retiré par `TryDequeue`. Si c’est un endpoint best effort et que le réseau est en pause, le code fait `continue` sans `EnqueueForRetry`. Le même abandon existe à l’entrée : `EnqueueOrSend` retourne sans mettre en file si le flux vidéo est en pause (`Extension.cs:2412`).
- **Preuve :** les positions, elles, repassent par `EnqueueForRetry` quelques lignes plus haut (`:1778`).
- **Impact :** trou de météo ou de caméra après un hoquet, jusqu’au prochain envoi SQF. Si le SQF ne réémet pas, la donnée est perdue.
- **Correction minimale :** remettre l’élément en file (une seule entrée coalescée, comme la position), ou ne pas le retirer tant que la pause dure.
- **Validation :** mettre un POST météo en file, forcer le backoff, appeler `DrainQueue`, vérifier que l’élément est encore là.

### DLL-008 — Réponse HTTP non libérée sur l’ancien client-init

- **Statut :** confirmé
- **Fichier :** `Extension.cs:864` (`StartClientInitAsync`), encore appelé depuis le Connect de repli `:9016`
- **Gravité :** basse
- **Symptôme :** connexions qui restent occupées jusqu’au ramasse-miettes après un Connect passé par `RvExtensionArgsImpl`.
- **Cause :** `SendAsync(...).ContinueWith` lit `t.Result` et ne dispose jamais le `HttpResponseMessage`. `EnqueueOrSend` (`:2454`) et le PATCH pilote (`:9599`) disposent la réponse. Celui-ci non.
- **Preuve :** pas de `resp.Dispose()` dans le `ContinueWith` des lignes 878-915. Le corps est lu, la réponse non.
- **Impact :** sous des Connect répétés (repli async), le pool de connexions du client 8 s se remplit plus vite que prévu.
- **Correction minimale :** `using var resp = t.Result` dans le rappel, y compris sur les branches d’erreur.
- **Validation :** 50 appels du chemin async contre un serveur local ; le nombre de sockets vers ce port doit redescendre après les réponses.

### DLL-009 — Transfert Bluetooth : intégrité et durée de vie

- **Statut :** confirmé
- **Fichier :** `BtTransfer.cs:171` et `:176`
- **Gravité :** moyenne
- **Symptôme :** un fichier reçu est annoncé complet alors qu’il manque un morceau ; des fichiers temporaires et des poignées s’accumulent.
- **Cause :** `BtRecvWrite` ajoute `data.Length` à `Got` à chaque écriture, sans mémoriser les index déjà reçus. Renvoyer deux fois le morceau 0 fait monter `Got` jusqu’à la taille. `BtRecvEnd` (`:195`) compare seulement `Got` et `Size`. `BtRecvOpen` remplace une réception du même id sans effacer l’ancien `.part`. `BtSends` / `BtRecvs` n’ont pas de plafond ni d’expiration : sans `BtFileClose` / `BtRecvAbort`, les entrées restent pour la vie du processus. Le nettoyage des `.part` de plus d’une heure (`:47`) ne retire pas les entrées du dictionnaire.
- **Preuve :** `lock (r) r.Got += data.Length` après un `Write` qui ne refuse pas un index déjà vu.
- **Impact :** photo ou morceau corrompu affiché comme reçu ; jusqu’à 30 Mo de `.part` par réception abandonnée, plus les entrées mémoire. Le chemin d’écriture, lui, est borné (`BtSafeName`, dossiers imposés).
- **Correction minimale :** un tableau de morceaux reçus, `Got` recalculé depuis ce tableau ; à l’ouverture, supprimer l’ancienne réception du même id ; plafond (par exemple 8 envois et 8 réceptions) et abandon au-delà.
- **Validation :** écrire deux fois le morceau 0 d’un fichier de deux morceaux, omettre le morceau 1, appeler `BtRecvEnd` : la réponse doit être un refus et le `.part` supprimé.

### DLL-010 — Files non bornées : journal disque et lecteur musique

- **Statut :** confirmé
- **Fichiers :** `Extension.cs:6816` (`PendingLogLines`), `MusicPlayer.cs:143` (`MusicQueue`), `MusicPlayer.cs:175` (thread)
- **Gravité :** moyenne
- **Symptôme :** la mémoire de la DLL monte si le disque est lent ou si le SQF envoie des lignes plus vite que l’écriture ; la file musique grossit si MCI reste bloqué.
- **Cause :** `EnqueueLogLine` enfile sans plafond. Les journaux appareil sont bornés à 800 (`Extension.cs:6868`) ; la file POST à 500 ; les photos à 64 ; la télémétrie P0 à 128 et P3 à 64. Le journal fichier et `BlockingCollection<Action>` musique n’ont pas de borne. Le thread MCI est `while (true)` sans arrêt (thread de fond : il ne retient pas le processus à la fermeture d’Arma, mais il ne lâche pas l’alias MCI si le module était déchargé, ce qu’Arma ne fait pas aujourd’hui).
- **Preuve :** `PendingLogLines.Enqueue` (`:6824`) sans test de `Count`. `MusicQueue.Add` (`:197`) sur une collection construite sans capacité.
- **Impact :** session longue avec journal verbeux, ou dossier musique sur un disque qui bloque : croissance mémoire dans le processus Arma. Le lecteur ne fige pas le thread du jeu (`MusicPost` n’attend pas), il accumule.
- **Correction minimale :** plafond (par exemple 2 000 lignes, 32 commandes musique) avec abandon des plus anciennes. À `MusicStop` / fin de mission, fermer l’alias et vider la file.
- **Validation :** enfiler 100 000 lignes avec un drain artificiellement lent ; la file doit rester au plafond.

### DLL-011 — Diapositives Google : jeton d’annulation libéré trop tôt

- **Statut :** confirmé pour la course ; **facultatif** pour la redirection
- **Fichier :** `GoogleSlidesDeck.cs:74` et `:36`
- **Gravité :** basse
- **Symptôme :** un second chargement annule le premier et le fait échouer avec un message « analyse impossible », ou une redirection Google est suivie vers un hôte quelconque.
- **Cause :** `StartLoad` appelle `Cancel` puis `Dispose` sur le `CancellationTokenSource` pendant que `LoadAsync` utilise encore ce jeton. Disposer un CTS encore en cours d’usage lève `ObjectDisposedException`, attrapé par le `catch (Exception)` qui remonte `parse_failed` (`:100`). Le client Google a `AllowAutoRedirect = true` et ne revérifie pas l’hôte après redirection. Il n’a pas les en-têtes Athena (User-Agent de navigateur seulement). L’URL de départ est bien limitée à `https://docs.google.com/presentation/d/` (`:138`).
- **Preuve :** `_cts?.Dispose()` sous `Gate` alors que la tâche précédente n’est pas attendue.
- **Impact :** navigation rapide entre présentations : erreur affichée à la place d’un simple abandon. La redirection automatique est un résidu SSRF vers la machine du joueur si `docs.google.com` renvoyait un 302 interne ; ce n’est pas une fuite de secret Athena.
- **Correction minimale :** `Cancel` sans `Dispose` immédiat (laisser le CTS au ramasse-miettes, ou le disposer dans un `finally` de la tâche). Suivre les redirections à la main comme `SendImageGetFollowingRedirects`, en restant sur des hôtes Google connus.
- **Validation :** deux `StartLoad` à 10 ms d’intervalle contre un serveur local lent ; le premier doit sortir en annulé, pas en exception de dispose.

### DLL-012 — Écriture du profil Arma pendant que le jeu tourne

- **Statut :** hypothèse (course avec le jeu) ; l’écriture, elle, est dans le code
- **Fichier :** `Extension.cs:11770` (`PatchArma3ProfilesScreenshotLimit`)
- **Gravité :** moyenne
- **Symptôme :** réglage de taille de captures modifié, ou profil `.Arma3Profile` abîmé si Arma l’écrit en même temps.
- **Cause :** la DLL lit tout le profil, remplace `maxScreenShotFolderSizeMB` par 4000, ou ajoute la ligne, puis `WriteAllText` / `AppendAllText`. Aucun verrou avec le jeu, pas de fichier temporaire renommé de façon atomique sur le même volume de façon exclusive. Appelé depuis le ménage des captures (`:11762`).
- **Preuve :** `File.WriteAllText(prof, patched)` à `:11801` et `AppendAllText` à `:11805`.
- **Impact :** perte d’un réglage que le joueur venait d’enregistrer, ou fichier de profil tronqué si les deux écritures se croisent.
- **Correction minimale :** ne plus réécrire le profil depuis la DLL. Si le plafond est indispensable, l’écrire une fois hors jeu, via un fichier temporaire et `File.Replace`, et seulement si Arma n’a pas le fichier ouvert.
- **Validation :** hors Arma, profil de test avec `maxScreenShotFolderSizeMB=100` : après l’appel, la valeur est 4000 et le reste du fichier est intact. Avec un verrou exclusif tenu sur le fichier, l’appel doit échouer sans le vider.

### DLL-013 — Premier chargement de la file disque sur le thread appelant

- **Statut :** confirmé
- **Fichier :** `Extension.cs:930` (`EnsureDrainTimer` appelle `LoadQueueFromDisk`)
- **Gravité :** basse
- **Symptôme :** le premier envoi de position ou de marqueur après le chargement peut accrocher le jeu le temps de lire `queue.jsonl`.
- **Cause :** `LoadQueueFromDisk` ne part qu’une fois (`Interlocked.Exchange` sur `_diskQueueLoaded`, `:2027`), mais `EnsureDrainTimer` est appelé depuis la mise en file, souvent sur le thread du jeu. La lecture et le parse des lignes (jusqu’à 500) sont synchrones. Le warmup (`:2898`) refait ce chargement en tâche de fond, seulement si le SQF a appelé `Warmup` avant.
- **Preuve :** `EnsureDrainTimer` ligne 932, avant le `lock` du minuteur. `EnqueueForRetry` (`:1166`) appelle `EnsureDrainTimer` et documente justement que l’écriture disque ne doit plus être sur le thread du jeu.
- **Impact :** une seule accroche au premier envoi si `Warmup` n’est pas passé, proportionnelle à la taille de la file restaurée.
- **Correction minimale :** ne plus appeler `LoadQueueFromDisk` dans `EnsureDrainTimer` ; le laisser au warmup et, à défaut, à la première échéance du minuteur.
- **Validation :** fichier `queue.jsonl` de 500 lignes, premier `UpdatePosition` sans `Warmup` : le retour de `callExtension` doit rester court (la lecture étant hors de ce thread).

## Ce qui tient

- Files déjà bornées : POST 500, marqueurs 1 000, photos 64, journaux appareil 800, télémétrie P0/P3, cache d’empreintes 96 × 512 Ko, décompression gzip plafonnée à 32 Mo (`Extension_SyncStats.cs:45`).
- `HttpClient` principal : pas de redirection automatique, corps de réponse plafonné à 64 Mo, HTTP/1.1 forcé, en-têtes d’authentification sur la requête et non plus sur `DefaultRequestHeaders` (le retrait résiduel sous `HeaderLock`, `Extension.cs:3116`, reste un `Remove` pendant un envoi ; l’exception est attrapée).
- Lecteur musique : client séparé, redirections revérifiées, adresses privées et de boucle locale refusées, y compris après DNS (`MusicPlayer.cs:38` et `:112`). Le relais webradio n’écoute que sur `127.0.0.1` avec un jeton aléatoire.
- Presse-papiers : `SetClipboardData` réussi laisse la mémoire au système ; l’échec la libère (`Clipboard.cs:67`). Le texte est borné à 2 000 000 caractères.
- Noms `LocalCommit` : lettres, chiffres, `-` et `_` seulement (`LocalConfig.cs:40`).
- Watchers de captures : pas de sous-dossiers, tampon 64 Ko, dossiers filtrés (`Extension.cs:10228`). Pas de handler `Error` : un débordement de tampon perd des événements (facultatif, le watcher continue).
- Aucun destructeur natif ni export d’arrêt. Les objets statiques (clients HTTP, minuteur, watchers, thread musique) vivent avec le processus. Arma ne décharge pas l’extension avant la fin du processus ; un déchargement futur casserait le thread musique et les watchers. Pas de dead-lock d’ordre de verrous identifié.

## Ordre suggéré

1. DLL-001 (fichiers et partages) avant toute autre chose.
2. DLL-002 et DLL-003 (gels).
3. DLL-004 (rappels).
4. Aligner le banc sur la signature à quatre arguments de `GamePostJson`, et ne plus le lancer sous Windows sans un magasin de session vide et sans accès réseau hors boucle locale.

---

## Append — 27/10/2026 soir (reprise)

### DLL-005 — filet d'entree elargi
- **Statut :** correctif applique dans `Extension.cs` (RVExtension + RVExtensionArgs)
- **Preuve code :** try autour de PtrToStringUTF8 / allocation args ; plafond argCount 0..64 ; ERR|bad_args / ERR|bad_arg_count
- **Validation :** Arma non execute ; test managé argCount=-1 non ajoute dans cette session

### DLL-007 — best-effort pendant pause
- **Statut :** correctif applique : `EnqueueForRetry` au lieu de `continue` apres dequeue
- **Validation :** non mesuree (pas de banc HTTP local cette session)

---

## Append — follow-up agents

DLL-C01 / DLL-C02 restes ouverts (voir rapport A3). DLL-005/007 deja traites plus haut.

---

## Append — A3/A6 DLL + memoire (LOT-DLL/MEM)

DLL-C01 : `InAsyncWorker` + `ERR|use_async` pour AdminControl/GameAdmin/SelfTest/StartupCheck/Server.Presence ; GetClientIp / Server.PublicIp sync seulement dans Async, sinon ServePollGet.
DLL-C02 : `MapPack.Count` cache TTL 10 s.
LOT-MEM : purge `COMSPEC_IcemanMedicalPushed` ; plafond `PendingLogLines` 2000.
dotnet build Extension : OK.
