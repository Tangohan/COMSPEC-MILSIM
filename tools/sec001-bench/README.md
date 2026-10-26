# Banc de non-régression SEC-001 (fuite de secrets Athena)

Harnais local **non destructif** qui prouve que la DLL COMSPEC Link n'envoie aucun secret Athena
(clé communauté `X-COMSPEC-KEY`, `Authorization: Bearer`, `X-COMSPEC-SESSION`, `X-COMSPEC-STEAM`, et
`api_key` / `session_token` dans les corps) vers une destination **non-Athena**, tout en continuant de les
envoyer au portail Athena. Il charge la DLL **managée** par réflexion et appelle ses méthodes internes.

- Tout tourne sur la **boucle locale** : `127.0.0.1` = Athena simulé « dev », `127.0.0.2` = hôte
  « attaquant » (joignable sous Linux, 127/8, mais **absent** de la liste blanche). Secrets **factices**.
- **À lancer sous `unshare -n`** (espace réseau isolé) : c'est la seule garantie qu'aucune requête ne peut
  sortir, même en cas de bug du banc ou de minuteur resté actif dans la DLL. Au repos, `_baseUrl` vaut
  l'adresse morte `http://127.0.0.1:9` (jamais une adresse de production).
- La boucle locale n'est de confiance que si `COMSPEC_DEV_TRUST_LOOPBACK=1` : le banc pose lui-même cette
  variable avant de charger la DLL. Avec `BENCH_PROD=1` il ne la pose pas et vérifie le comportement de
  **production** (la boucle locale ne reçoit alors aucun secret).
- **Hors** de `mod/UptoDate/COMSPECExtension/` à dessein : ce csproj compile tous les `.cs` de son dossier.

## Ce qu'il vérifie

- `[A]` `AttachApiKeyHeader` : aucun secret vers `127.0.0.2`, vers `http://` en clair, vers une URL avec
  identifiants (`https://x@athena.ttrd.fr/`) ni vers un sous-domaine hors liste ; secrets présents vers
  `https://athena.ttrd.fr`, `https://athena-comspec.eu` et la boucle locale de développement.
- `[B]` `Connect(url hostile)` : `_baseUrl` non détourné, aucun secret (en-tête **ni corps** client-init)
  reçu par l'hôte hostile, réponse `ERR|untrusted_host`.
- `[C]` les 13 commandes qui posent `_baseUrl` depuis SQF (Connect, PairStart, RedeemRecoveryCode,
  RedeemGameLink, LinkBySteam, RegisterBeta, ReportDiag, GameAuth Init/RestoreSession/AuthPassword/
  RequestOtp/VerifyOtp/AuthSteam) + le Connect « async » : `_baseUrl` jamais hostile, aucun secret capté.
- `[Q]` **file d'envoi reprise du disque** : `queue.jsonl` / `position.json` du dossier de synchro réécrits
  avec une adresse hostile (ce qu'une version détournée aurait laissé) — l'envoi doit être abandonné, sans
  `api_key` ni `session_token` dans le corps, et l'abandon compté (il est signalé au journal de liaison du
  téléphone, positions comprises, que le journal d'envoi ne suit pas).
- `[G]` `GamePostJson` / `GameGetJson` (GameAuth), qui posent eux-mêmes le Bearer du compte hors
  `AttachApiKeyHeader` : avec un `_baseUrl` hostile forcé par réflexion (ce que ferait une régression), ils
  doivent répondre `ERR|untrusted_host` sans aucune requête.
- `[E]` redirection `127.0.0.1` (confiance) → `127.0.0.2` (hostile) : l'hôte hostile ne reçoit aucun
  secret (en-têtes personnalisés conservés par .NET lors d'une redirection).
- `[E2]` `DownloadBriefingSlideImage` : redirection confiance → hostile **suivie** mais sans secret (un CDN
  doit continuer de marcher), et image légitime vers un hôte de confiance toujours téléchargée.
- `[R]` tuiles `/map-photo` (client sans secret) : la redirection est bien **suivie** (sinon l'étape 6 de
  `docs/migration-domaine-athena-comspec.md` casserait le fond PHOTO en silence).
- `[S]` `StartupCheck`, `LatestVersions`, `Server.PublicIp` : adresse arbitraire refusée, aucune requête.
- `[P]` 41 candidats `_baseUrl` piégés (casse, point final, IDN homographes, `@` / `#` / `?` / `%40`,
  IPv4 décimale et octale, `::ffff:`, `nip.io`, `ftp://`, `file://`) : aucun accepté ne mène à une
  destination non sûre.

Secrets cherchés dans chaque requête captée : la clé, le jeton de session, le SteamID et le Bearer du compte
(valeurs factices), **et** tout en-tête porteur de secret quelle que soit sa valeur (`Authorization`,
`X-COMSPEC-KEY`, `X-COMSPEC-SESSION`, `X-COMSPEC-STEAM`, `X-ATAK-TOKEN`).

## Lancer (sur une copie HORS du worktree — dotnet écrit bin/obj à côté du csproj)

```bash
cp -r tools/sec001-bench /tmp/sec001-bench && cd /tmp/sec001-bench
dotnet build Sec001Bench.csproj -c Release
# Espace réseau isolé + un HOME à part (le banc écrit dans le dossier de synchro du cas [Q]) :
sudo unshare -n sh -c 'ip link set lo up;
  HOME=/tmp/bench-home BENCH_DLL=/chemin/.../COMSPECExtension_x64.dll \
  dotnet bin/Release/net8.0/Sec001Bench.dll'
# Comportement de production (boucle locale non fiable) :
#   BENCH_PROD=1 en plus
```

`BENCH_DLL` est **obligatoire** (aucun chemin par défaut). Code de sortie `0` si aucune fuite inattendue,
`1` sinon. Il faut une DLL **managée** (`dotnet build`), pas la `.so` Native AOT (`dotnet publish`), qui
n'est pas chargeable par réflexion.

Les scripts utilisés pour l'audit (`unshare -n` + proxy « puits » qui journalise toute tentative de sortie)
sont hors dépôt : `/home/user/audit-work/lot-sec001/scripts/`.
