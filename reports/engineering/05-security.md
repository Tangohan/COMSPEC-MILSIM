# Audit sécurité — frontières SQF, DLL, navigateur du téléphone et API

- Dépôt : `release/2.7.0`, commit `e0beac95b`
- Date : 2026-10-27
- Périmètre lu : `mod/UptoDate/COMSPECExtension` (dont `GameAuth.cs`, `OfficialClientProof.cs`, `GoogleSlidesDeck.cs`, `Extension_MapPhoto.cs`, `MusicPlayer.cs` de la DLL native), `mod/COMSPEC_ATAK_Native` (transfert, musique, briefing, FENRIR, conditions réseau), garde PHP `GameClientVersionGate`, `ComspecApiKeyAuth`, `OfficialClientAttestation`, et les routes jeu de `AtakApiController` qui reçoivent ces commandes.
- Hors périmètre : `mergewt/`, ateliers `mod/Workshop/`, binaires. Aucun essai vers `athena.ttrd.fr` ni `athena-comspec.eu`. Aucun correctif appliqué.

Les numéros commencent à SEC-041. SEC-001, SEC-008, SEC-009, SEC-018 et SEC-032 sont déjà dans l’historique ou le journal de version.

## Déjà traité, non rouvert

**SEC-001 (clos, en production).** Les secrets (clé de communauté, jeton de session, Bearer, Steam) ne partent que vers `athena.ttrd.fr` et `athena-comspec.eu`, en HTTPS, sans identifiant dans l’adresse. Le plancher 2.0.96 est annoncé actif (403 `MOD_OUTDATED` pour 2.0.95 et pour `1.0.0-native`). Ce rapport ne le recompte pas comme ouvert.

Contournement d’hôte recherché sur la même règle que `IsSecretSafeUri` (`Extension.cs` vers 3208–3226), avec le parseur d’adresses de .NET 8, sur 24 adresses contrôlées : sous-domaine, suffixe, utilisateur dans l’adresse, `http`, boucle locale, homographe, pourcentages (`%00`, `%2e`, `%5c`, `%23`, `%2f`, `%40`, double encodage), antislash. Résultat local : `NO_HOST_BYPASS`. Les seules adresses acceptées sont les deux portails en HTTPS (le point final du nom est le même hôte DNS). Les redirections des clients qui portent des secrets restent filtrées à chaque saut (`SendImageGetFollowingRedirects`, vers 2492–2521 ; `AttachApiKeyHeader`, vers 3244–3253). `GoogleSlidesDeck` n’accepte que `https://docs.google.com/presentation/d/…` et n’attache pas les secrets Athena. La musique refuse les adresses privées avant connexion. Le transfert Bluetooth reste sous les dossiers de captures ou de musique.

Résidu du plancher, **facultatif**, pas une réouverture de SEC-001 : `GameClientVersionGate::check` (`GameClientVersionGate.php` vers 84–87 et 140–160) ne lit le numéro que si le User-Agent annonce `COMSPECExtension/` ou `COMSPECATAKNativeExtension/`. Le client officiel 2.0.95 annonce ce nom et reste refusé. Un binaire reconstruit qui n’annonce pas ce nom n’est pas examiné. Ce binaire peut aussi retirer le filtre d’hôte : le plancher ne le couvre pas, et ce n’est pas le défaut d’origine.

**SEC-009**, toujours connu, lot séparé, non renuméroté : la clé de communauté reste dans le profil Arma, lisible par un script de mission. Les routes ci-dessous exigent en plus la preuve du client officiel quand elle est armée (`OfficialClientAttestation`) : la clé seule ne suffit pas. Le téléphone officiel pose cette preuve tout seul sur les commandes qu’il expose.

**SEC-008, SEC-018, SEC-032** : cloisonnement déjà livré (relecture, appui-feu, AAR, POI, MEDEVAC, QRF, service véhicule). Non réexaminés comme des trous ouverts.

Résidu déjà écrit dans le journal 2.4.1, non rouvert : une mission peut encore faire télécharger une image depuis l’hôte de son choix. Aucun secret n’est joint. L’adresse IP du joueur est visible par cet hôte.

## SEC-041 — L’identité du compte Athena est rendue à n’importe quel script

- **Statut :** confirmé
- **Gravité :** élevée
- **Fichiers :** `mod/UptoDate/COMSPECExtension/GameAuth.cs:62` (`GetAuthState`), `GameAuth.cs:183-214` (réponse), `GameAuth.cs:847` (e-mail), `GameAuth.cs:941-943` (numéro, IMEI, adresse matérielle)

**Symptôme.** Après une liaison, la commande `GetAuthState` renvoie au script une ligne à tabulations qui contient l’e-mail du compte, le prénom, le nom, l’identifiant de compte, le numéro de téléphone, l’IMEI et l’adresse matérielle enregistrés sur la fiche.

**Cause.** `FormatGameAuthState` recopie ces champs sans les masquer. `GetAuthState` n’a pas d’appelant privilégié : toute mission ou tout autre mod chargé sur le poste peut l’invoquer. Le jeton Bearer, lui, n’est pas dans cette ligne.

**Preuve locale.** Lecture du formatage. Les cellules 26 à 29 sont documentées comme numéro, IMEI, adresse matérielle et type de numéro dans `fn_phoneDeviceSync.sqf`. Aucune de ces valeurs n’est remplacée par un tiret, contrairement à l’indicatif quand il ressemble à un titre de communauté (`CallsignCell`).

**Impact multijoueur.** Le script de mission s’exécute sur chaque poste qui a chargé le mod. Il peut donc lire l’identité réelle de chaque joueur relié et la renvoyer au serveur de la partie. Ce sont des données que le script de mission n’a pas par ailleurs.

**Correction proposée.** Ne renvoyer au script que l’état de liaison, l’indicatif et la communauté. Garder e-mail, nom civil, téléphone, IMEI et adresse matérielle dans la DLL. Si l’écran du téléphone en a besoin, les demander par une commande séparée qui ne les place pas dans une variable de mission.

**Test de régression.** Fixture locale : une réponse de compte avec l’adresse `ada@example.test`, le numéro `+33600000000` et un IMEI de test. La chaîne renvoyée par `GetAuthState` ne contient aucune de ces trois valeurs. L’état `READY` et l’indicatif restent présents.

## SEC-042 — Le temps de jeu et les distances de toute la communauté partent avec la session d’un joueur

- **Statut :** confirmé
- **Gravité :** élevée
- **Fichiers :** `mod/UptoDate/COMSPECExtension/Extension.cs:3578-3587` et `:3600-3609` ; `app/Controllers/Api/AtakApiController.php:7223-7257` (`screenTimeBatch`) et `:7267-7304` (`distanceBatch`)

**Symptôme.** Un poste joueur déjà relié peut envoyer le temps de jeu, les rôles et les distances d’autres membres de la même communauté. Ces chiffres sont enregistrés comme s’ils venaient du serveur de la partie.

**Cause.** Les deux commandes sont prévues pour le serveur Arma. L’API les accepte dès que `authArma()` réussit, et la session de jeu d’un joueur suffit (`ComspecApiKeyAuth.php` vers 349-352 et 379-387). `screenTimeBatch` marque chaque ligne `SOURCE_SERVER` et n’a pas de branche « ce joueur seulement ». `distanceBatch` ne réduit la liste au joueur appelant que si le corps contient exactement la source `client`. Une source absente ou autre laisse toute la liste.

**Preuve locale.** Script PHP sur des identifiants fictifs `76561198000000001` et `76561198000000002`, sans réseau. Liste vide de Steam : la clause de filtre n’est pas ajoutée. Source omise : les deux fiches restent. Source `client` : une seule reste. Le contrôle statique du contrôleur confirme l’absence de branche `client` dans `screenTimeBatch` et sa présence dans `distanceBatch`. Résultat : `LOGIC_CONFIRMED`.

**Impact multijoueur.** Un script sur un poste relié appelle `ScreenTime.Batch` ou `Distance.Batch`. Les heures, les rôles et les kilomètres des autres opérateurs de la communauté sont alors ceux que ce script a choisis. Le téléphone officiel, quand le serveur n’est pas relié, est censé n’envoyer que son propre joueur : l’API ne l’impose pas.

**Correction proposée.** Si l’appelant est une session de joueur (`matchedUserId` renseigné), n’enregistrer que le Steam de cette session, comme le fait déjà la branche `client` des distances. Réserver l’écriture des autres fiches à la clé de la communauté sans session de joueur (le serveur dédié).

**Test de régression.** Deux fiches de test dans la même communauté. L’appel avec la session de A et une liste A+B, source absente, n’écrit que A, pour le temps d’écran et pour les distances. Le même corps avec la clé de communauté sans session de joueur écrit A et B.

## SEC-043 — Un joueur peut remplacer le plan de camp affiché au poste

- **Statut :** confirmé
- **Gravité :** moyenne
- **Fichiers :** `app/Controllers/Api/AtakApiController.php:7070-7104` ; `app/Repositories/AtakBriefingPlanRepository.php:137-157` et `:181` ; commande `BriefingPlan.Push` dans `Extension.cs` vers 3626-3636

**Symptôme.** Le plan partagé d’un camp (propriétaire vide) peut être remplacé par le contenu envoyé depuis un téléphone de joueur.

**Cause.** `owner` est pris dans le corps. Vide, c’est le plan commun du camp (`personal` faux dans `recent`). L’écriture refuse seulement une révision plus petite que celle déjà en base. Une révision plus grande remplace le plan. Rien ne distingue le serveur de la partie d’un joueur.

**Preuve locale.** Le contrôleur passe `owner` tel que nettoyé depuis le corps, y compris la chaîne vide. Le dépôt fait un upsert sur `(communauté, carte, camp, propriétaire)`.

**Impact multijoueur.** Le calque du camp au poste de commandement affiche alors les points envoyés par ce joueur, pour toute la communauté, jusqu’à une révision encore plus grande.

**Correction proposée.** Session de joueur : forcer le propriétaire à ce joueur et refuser le propriétaire vide. Plan partagé : clé de communauté du serveur, sans session de joueur.

**Test de régression.** Session de A, propriétaire vide, révision 2 alors que le plan partagé est en révision 1 : aucun changement du plan partagé. Le brouillon personnel de A peut être écrit. La clé de communauté sans session de joueur écrit le plan partagé.

## SEC-044 — Les avis d’administration « serveur » ne sont pas limités au joueur concerné

- **Statut :** confirmé (le contrôle manque). Portée actuelle limitée : le téléphone n’appelle pas ces deux adresses.
- **Gravité :** moyenne
- **Fichiers :** `app/Controllers/Api/AtakApiController.php:7315-7360` et `:7364-7378` ; `app/Repositories/AtakAdminControlRepository.php:1076-1097` et `:1105-1120`. Le jumeau sûr est `pendingForPlayer` / `markMessagesForPlayer` (vers 939 et 963), utilisé par `/mine` et `/mine/ack`.

**Symptôme.** `POST /api/atak/admin-control/pending` avec une liste de joueurs vide lit jusqu’à 200 avis non validés de toute la communauté (Steam, titre, texte). `POST /api/atak/admin-control/ack` marque n’importe quel identifiant de cette communauté comme affiché ou validé.

**Cause.** Les deux actions documentent un appel du serveur de jeu, puis utilisent le même `authArma()` qu’un joueur. `pendingForTenant` n’ajoute le filtre Steam que si la liste n’est pas vide. `markMessages` filtre la communauté et l’identifiant, pas le joueur. Le téléphone, lui, passe par `AdminControl.Poll` et `AdminControl.Ack`, qui visent `/mine` et `/mine/ack` (`Extension.cs:3697` et `:3746`). Aucun appelant de `/pending` ni de `/ack` n’est présent dans le mod lu.

**Preuve locale.** Même script que SEC-042 : liste de Steam vide, la requête reconstituée n’a pas de filtre `IN`. Le contrôleur pending appelle `pendingForTenant`. Le contrôleur ack appelle `markMessages`, pas `markMessagesForPlayer`.

**Impact multijoueur.** Aujourd’hui le téléphone ne peut pas choisir cette adresse : la DLL ne publie pas de commande dont le chemin vient du script. Le trou est sur la route, pour tout appelant déjà accepté par la garde tactique (session de jeu avec la preuve du client, ou clé de communauté avec cette preuve). Un tel appelant lit les messages imposés des autres opérateurs, y compris un avis de compromission, et peut les valider à leur place. Ils ne s’affichent alors plus.

**Correction proposée.** Réserver `/pending` et `/ack` à la clé de communauté sans session de joueur. Un joueur reste sur `/mine` et `/mine/ack`.

**Test de régression.** Deux avis, un pour A et un pour B. La session de A sur `/pending` avec une liste vide ne reçoit que l’avis de A. Sa validation de l’identifiant de B laisse l’avis de B non validé. La clé de communauté sans session de joueur voit les deux.

## SEC-045 — Le relevé de conditions réseau de la communauté est écrivable par un joueur

- **Statut :** confirmé
- **Gravité :** moyenne
- **Fichiers :** `mod/COMSPEC_ATAK_Native/Sources/addons/main/functions/network/fn_netConfig.sqf:64-82` ; `Extension.cs:3614-3621` ; `app/Controllers/Api/AtakApiController.php:8455-8475` ; `app/Services/Tactical/AtakExperienceService.php:391-424`

**Symptôme.** Le bloc « conditions appliquées en jeu » de la communauté peut être réécrit depuis un poste qui n’est ni Zeus, ni administrateur du serveur.

**Cause.** L’écran du menu Échap vérifie le droit avant d’appeler la DLL (`canEdit` : administrateur, hôte ou Zeus). L’API `netConditions` ne refait pas ce contrôle : toute session de jeu acceptée enregistre `net_live` dans la configuration d’expérience. Les valeurs sont bornées au catalogue, le nom affiché « appliqué par » est un texte libre court. La simulation réellement diffusée en partie, elle, repasse par le serveur et refuse un client sans droit (`fn_netConfig.sqf` vers 86-95).

**Preuve.** La commande `NetConditions.Report` est une commande publique de la DLL. Le corps est celui que le script fournit. `recordLiveNetwork` enregistre puis sauve la configuration.

**Impact multijoueur.** La page de contrôle du serveur montre un relevé faux (qui a appliqué quoi). Si un gestionnaire reprend ensuite ce relevé comme réglage de la communauté, les valeurs choisies par le joueur deviennent le réglage. La partie en cours n’est pas modifiée par ce seul appel.

**Correction proposée.** N’accepter l’écriture que si l’appelant est la clé de communauté du serveur, ou un compte dont le rôle est déjà celui du parc ATAK (gestionnaire). Ignorer l’appel d’une session de joueur ordinaire.

**Test de régression.** Session d’un membre sans rôle de gestion : le relevé précédent est inchangé. Clé de communauté sans session de joueur : le relevé est mis à jour, et les autres clés de la configuration d’expérience restent.

## SEC-046 — Le lien ouvert dans le navigateur du système accepte le HTTP

- **Statut :** confirmé
- **Gravité :** moyenne
- **Fichier :** `mod/UptoDate/COMSPECExtension/Extension.cs:3926-3938`

**Symptôme.** `OpenAthenaLink` ouvre dans le navigateur du joueur une adresse dont l’hôte est celui du portail déjà retenu, y compris en `http://`.

**Cause.** Le contrôle compare les hôtes, pas le schéma. Les schémas permis sont `http` et `https`. Le portail retenu en production est en HTTPS (`TrySetSqfBaseUrl`), donc l’hôte est bien Athena, mais la page demandée peut être en clair. L’ouverture passe par le shell (`UseShellExecute`). Le site envoie HSTS une fois une page HTTPS vue ; la première demande en clair, elle, part avant cette bascule.

**Preuve.** Lecture du prédicat : schéma `http` ou `https`, puis égalité d’hôte avec `_baseUrl`. Aucune liste de chemins.

**Impact multijoueur.** Un script de mission peut faire ouvrir au joueur une page du portail, avec les cookies déjà présents dans son navigateur. Une adresse de validation de code en `http://` expose ce code sur le réseau du joueur. Le schéma `file` et un autre hôte sont refusés.

**Correction proposée.** Exiger `https`, le même hôte, pas d’identifiant dans l’adresse, et un chemin limité aux pages de liaison (`/lier` et la page de vérification de code). Refuser le HTTP même si l’hôte est Athena.

**Test de régression.** `_baseUrl` = `https://athena.ttrd.fr`. `http://athena.ttrd.fr/lier` et `https://autre.exemple/lier` sont refusés. `https://athena.ttrd.fr/lier` est accepté. Le test ne lance pas de processus : il ne vérifie que le prédicat.

## SEC-047 — N’importe quel chemin du disque peut être sondé

- **Statut :** confirmé
- **Gravité :** faible
- **Fichier :** `mod/UptoDate/COMSPECExtension/Extension.cs:4156-4167`

**Symptôme.** `PathExists` répond si un fichier ou un dossier existe, pour le chemin que le script donne.

**Cause.** Aucune racine autorisée, contrairement à `DeleteLocalScreenshot` (`IsSafeScreenshotDeletePath`, vers 11557) et au transfert Bluetooth.

**Preuve.** La commande appelle `Directory.Exists` / `File.Exists` sur la chaîne reçue et renvoie `OK|1` ou `OK|0`. Elle ne renvoie pas le contenu.

**Impact multijoueur.** Un script de mission peut cartographier la présence de dossiers du poste (profils, documents). Il ne lit pas les fichiers par cette commande.

**Correction proposée.** Répondre seulement pour les dossiers de captures, le journal de liaison et le cache du mod. Tout autre chemin : `OK|0`, sans distinguer « interdit » et « absent » si cette distinction renseigne encore.

**Test de régression.** Un chemin sous le dossier de captures de test existe : `OK|1`. Un chemin hors de ces racines : `OK|0`, que le fichier de test existe ou non sur le disque.

## Contrôles qui tiennent, sur ce qui a été lu

- Filtre d’hôte et redirections des secrets : voir le début de ce rapport.
- `chatPurge` refuse une clé de jeu (`AtakApiController.php` vers 9649-9654).
- Le parc ATAK en jeu et les fiches médicales exigent une session de joueur et un rôle (`gameAdminCaller`, `medicalRoster`).
- Le navigateur du téléphone échappe les textes de tchat et d’alerte avant `ExecJS` (`fn_webBrowserJsEscape.sqf`, `fn_tabletChatPush.sqf`, `fn_pushHtmlAlert.sqf`).
- Les identifiants FENRIR interpolés dans `compile` sont produits localement (`p1`, `p2`, … dans `fn_fenrir.sqf`), pas pris dans un texte réseau.
- `DeleteLocalScreenshot` et `BtFileOpen` restent sous des dossiers connus. `ImageDataChunk` en `local:` reste sous `comspec_cache`.

## Ordre suggéré

1. SEC-041 et SEC-042 (identité réelle, compteurs des autres joueurs).
2. SEC-043 et SEC-045 (ce que le poste et la page de contrôle affichent).
3. SEC-044, SEC-046, SEC-047.

---

## Append — A7 securite (27/10 soir)

A7 ([secu](b49fe044-24da-4c04-bbe4-c10df052f645)) : SEC-041/042/044-047 `fixed_in_source` confirmes (dont parite screenTimeBatch). SEC-043 **partial** volontaire (plan camp owner vide). `05-security.md` = photo pre-patch ; ne pas le reecrire.
