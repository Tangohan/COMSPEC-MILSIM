# Protection contre la surcharge sur le VPS (nginx, PHP-FPM, hébergeur)

Ce document couvre ce que le site ne peut pas faire seul. Le dépôt ne déploie **pas** la configuration nginx : l'action
`deploy-vps.yml` met à jour le code et recharge PHP-FPM, rien de plus. Tout ce qui suit s'applique **à la main** sur le serveur
(`/etc/nginx/sites-available/athena.ttrd.fr`, `/etc/nginx/conf.d/`, `/etc/php/8.4/fpm/pool.d/www.conf`), puis `nginx -t && systemctl reload nginx`.
Le modèle de vhost versionné reste `docs/nginx.example.conf`.

## 1. Trois couches, chacune son rôle

| Couche | Elle arrête | Elle ne peut pas arrêter |
|---|---|---|
| Hébergeur / réseau | Inondation du lien (DDoS volumétrique), paquets malformés | Rien de ce qui arrive jusqu'à nginx par des connexions valides |
| nginx (`limit_req`, `limit_conn`) | Excès de requêtes ou de connexions d'une même source, avant PHP | Une attaque qui sature déjà le lien ; un volume réparti sur beaucoup d'adresses |
| Application (page **Charge et surcharge**) | Ralentit les formules les moins élevées quand PHP sature, dans l'ordre Gratuit, Standard, Pro (Pro+ jamais) | Tout ce qui est refusé ou perdu avant PHP : le site ne le voit pas |

La page `Administration > Exploitation > Charge et surcharge` ne mesure que les requêtes arrivées jusqu'à PHP et la charge du processeur.
Elle ne dit rien de l'état du réseau, de ce que nginx ou le pare-feu ont refusé, ni de l'attente dans la file de PHP-FPM.
Un site saturé en amont peut donc y sembler calme : recoupez avec les journaux nginx et l'état de PHP-FPM (section 3).

## 2. nginx : limites par IP et par route

### Joueurs derrière une même adresse

Une salle, un réseau d'école ou un opérateur mobile (CGNAT) font partager une adresse publique à plusieurs joueurs. Une unité de 40 joueurs sur un même réseau
envoie plusieurs centaines de requêtes par seconde depuis **une seule IP** : le mod sonde l'API plusieurs fois par seconde et par joueur.
Une limite par IP trop basse couperait donc une communauté entière. Règles retenues :

- les routes du jeu (`/api/`) ont un plafond par IP **très large** ; la limite fine se fait par jeton de joueur (en-tête `Authorization`), jamais par IP ;
- les limites serrées ne visent que les routes qu'un joueur n'appelle pas en boucle (connexion, inscription, exports, relief) ;
- la clé de communauté (`X-COMSPEC-KEY`) est **partagée par tous les joueurs d'une communauté** : ne pas la limiter à un faible débit ;
- derrière un mandataire ou un CDN, déclarer l'adresse réelle (section 4) ; sinon `$binary_remote_addr` est celle du mandataire et une limite touche tout le monde.

### Configuration proposée

À poser dans `/etc/nginx/conf.d/athena-limites.conf` (contexte `http`). Valeurs de départ prudentes : lancer d'abord en **observation** (`limit_req_dry_run on;`,
nginx 1.17.1 ou plus), lire les journaux une semaine, puis appliquer.

```nginx
# --- Clés : une clé vide n'est pas comptée par nginx, ce qui permet de cibler des routes depuis un seul bloc ---
map $request_uri $athena_is_api  { default 0; ~^/(public/)?api/ 1; }
map $athena_is_api $athena_k_page { 0 $binary_remote_addr; default ""; }
map $athena_is_api $athena_k_api  { 1 $binary_remote_addr; default ""; }

# Connexion, inscription, récupération de mot de passe, ouverture de session du jeu.
map $request_uri $athena_k_auth {
    default "";
    ~^/(public/)?(login|register|forgot-password|reset-password|resend-verification|connect/code|atak/connect/code)(\?|/|$) $binary_remote_addr;
    ~^/(public/)?api/(game/v1/auth|atak/game-link|atak/pair|atak/recovery)                                                     $binary_remote_addr;
}

# Routes coûteuses qu'un joueur n'appelle pas en boucle : relief, exports.
map $request_uri $athena_k_heavy {
    default "";
    ~^/(public/)?api/atak/terrain/(hillshade|slope|contours) $binary_remote_addr;
    ~\.(pdf|csv|xlsx)(\?|$)                                  $binary_remote_addr;
}

# Jeton de session du joueur (Bearer) : un compteur par joueur, même si 40 joueurs partagent une IP.
map "$athena_is_api:$http_authorization" $athena_k_bearer {
    default "";
    "~^1:Bearer .+" $http_authorization;
}

limit_req_zone $athena_k_page   zone=athena_page:10m    rate=20r/s;
limit_req_zone $athena_k_auth   zone=athena_auth:10m    rate=30r/m;
limit_req_zone $athena_k_heavy  zone=athena_heavy:10m   rate=2r/s;
limit_req_zone $athena_k_api    zone=athena_api_ip:10m  rate=300r/s;   # plafond large : réseau partagé
limit_req_zone $athena_k_bearer zone=athena_bearer:20m  rate=40r/s;    # environ dix fois l'usage normal d'un joueur

limit_conn_zone $athena_k_page zone=athena_conn_page:10m;
limit_conn_zone $athena_k_api  zone=athena_conn_api:10m;

# 503 et non 429 : le mod traite un 429 comme une pause de toute la liaison (ordres compris), un 503 ne met en pause que la route.
limit_req_status  503;
limit_conn_status 503;
limit_req_log_level warn;
# limit_req_dry_run on;   # phase d'observation : compte et journalise sans refuser
```

Dans `location ~ \.php$` du vhost (là où passent toutes les pages et l'API), à la suite de `fastcgi_pass` :

```nginx
        limit_req  zone=athena_page   burst=60   nodelay;
        limit_req  zone=athena_auth   burst=10   nodelay;
        limit_req  zone=athena_heavy  burst=10   nodelay;
        limit_req  zone=athena_api_ip burst=600  nodelay;
        limit_req  zone=athena_bearer burst=120  nodelay;
        limit_conn athena_conn_page 40;
        limit_conn athena_conn_api  400;
```

Puis, dans le bloc `server`, une réponse qui dit au mod et au navigateur d'attendre (le corps ne doit **pas** contenir le mot « maintenance » :
la DLL du mod y lit une pause de toute la liaison) :

```nginx
    error_page 503 @athena_busy;
    location @athena_busy {
        add_header Retry-After 30 always;
        add_header Cache-Control "no-store" always;
        default_type application/json;
        return 503 '{"error":"server_busy","message":"Athena traite beaucoup de demandes. Reessayez dans quelques instants.","retry_after":30}';
    }
```

À savoir :

- les fichiers statiques (`/assets/`, `/uploads/`) sont servis par nginx avant PHP et ne passent pas par ces limites ;
- les webhooks Stripe et PayPal (`/api/stripe/webhook`, `/api/paypal/webhook`) arrivent par `/api/` : le plafond par IP (300 r/s) est très au-dessus de leur débit, mais **ne les mettez jamais** dans une zone plus serrée ;
- ne limitez pas `/admin` plus que le reste : un administrateur doit pouvoir agir pendant un incident ;
- `limit_req` ne suffit pas pour les connexions lentes : garder `client_header_timeout`, `client_body_timeout` et `send_timeout` courts (10 à 20 s) et `keepalive_timeout` modéré ;
- après un `reload`, relire `error.log` : les refus apparaissent avec « limiting requests », zone et adresse.

### Pour mesurer avant de régler

Journal avec la durée et le temps passé dans PHP, à ajouter dans `http {}` puis à utiliser dans `access_log` :

```nginx
log_format athena_timing '$remote_addr [$time_local] "$request" $status $body_bytes_sent '
                         'rt=$request_time urt=$upstream_response_time';
```

`rt` bien supérieur à `urt` signale une attente avant PHP (file de PHP-FPM, réseau lent) : c'est précisément ce que la page Charge et surcharge ne voit pas.

## 3. PHP-FPM : état du pool

À activer dans le pool (`/etc/php/8.4/fpm/pool.d/www.conf`), puis `systemctl reload php8.4-fpm` :

```ini
pm.status_path = /fpm-status
ping.path = /fpm-ping
; Une requête qui dépasse est tuée au lieu de bloquer un worker : à régler d'après la requête légitime la plus longue.
request_terminate_timeout = 60s
request_slowlog_timeout = 8s
slowlog = /var/log/php8.4-fpm.slow.log
```

La page d'état ne doit **jamais** être publique. Soit on l'interroge depuis le serveur, sans nginx :

```bash
# paquet libfcgi-bin
SCRIPT_NAME=/fpm-status SCRIPT_FILENAME=/fpm-status REQUEST_METHOD=GET QUERY_STRING=json \
  cgi-fcgi -bind -connect /run/php/php-fpm.sock
```

soit on l'expose en local seulement :

```nginx
    location = /fpm-status {
        allow 127.0.0.1;
        allow ::1;
        deny all;
        access_log off;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $fastcgi_script_name;
        fastcgi_pass unix:/run/php/php-fpm.sock;
    }
```

Les champs qui comptent :

| Champ | Lecture |
|---|---|
| `active processes` proche de `max children reached` | le pool est plein |
| `listen queue` > 0 | des requêtes attendent un worker : saturation réelle, invisible dans les mesures de l'application |
| `max listen queue` | pic depuis le démarrage |
| `max children reached` > 0 | le pool a déjà atteint `pm.max_children` : augmenter seulement si la mémoire le permet |
| `slow requests` | requêtes plus longues que `request_slowlog_timeout` |

Dimensionner `pm.max_children` : mémoire disponible pour PHP divisée par la mémoire moyenne d'un worker (`ps -o rss= -C php-fpm8.4`). Au-delà, le serveur swappe et tout ralentit.
Un `reload` de PHP-FPM tue les workers occupés (déjà noté dans `deploy-vps.yml`) : éviter d'en déclencher en pleine opération.

## 4. Un vrai DDoS réseau : ce que le site ne peut pas arrêter

Quand le lien du serveur est saturé, la requête n'arrive jamais à nginx : ni les limites de la section 2, ni le ralentissement de l'application ne servent. Il faut agir en amont.

1. **Confirmer** : `error.log` nginx presque vide alors que le site est injoignable, charge processeur basse, trafic réseau entrant très élevé chez l'hébergeur (`vnstat`, `nload`, ou le tableau de bord de l'hébergeur). Les mesures de l'application ne montrent rien : c'est le signe.
2. **Prévenir l'hébergeur tout de suite** (ticket d'urgence ou chat) avec : l'heure de début (UTC), l'adresse IP du VPS attaquée, le volume observé et le sens, un extrait de journaux. Demander s'il filtre déjà en amont (protection anti-DDoS du réseau, nettoyage du trafic) et ce qui est inclus dans l'offre du VPS. Il peut bloquer temporairement une adresse ou une plage en amont, ce que le serveur ne peut pas faire seul.
3. **Mettre un service de protection devant le site** si les attaques reviennent : un service qui reçoit le trafic à la place du serveur (DNS en mode mandataire) et absorbe l'excès. Points d'attention propres à Athena :
   - les mods déjà installés ont les domaines en dur : **`athena-comspec.eu` et l'ancien `athena.ttrd.fr` doivent passer tous les deux** par le service, sinon l'ancien domaine reste un contournement ;
   - déclarer le mandataire pour que PHP lise la vraie adresse du visiteur : `TRUSTED_PROXIES` dans `.env` (plages du service, CIDR accepté) ; sans cela, les limites par adresse de l'application voient toutes les requêtes venir d'une seule source (`App\Support\ClientIp`). Le réglage `TRUST_CF_CONNECTING_IP` existe pour Cloudflare ;
   - côté nginx, `set_real_ip_from` (plages du service) et `real_ip_header` pour que `$binary_remote_addr` des limites de la section 2 soit la vraie adresse ;
   - **fermer l'accès direct** à l'adresse du serveur : pare-feu qui n'autorise le port 80/443 que depuis les plages du service. Sans cela, une attaque contourne le service en visant directement l'adresse du VPS ;
   - le service ne doit pas mettre en cache les réponses `/api/` ni `/login`.
4. **Pendant l'incident** : garder un accès SSH par une autre voie (console de l'hébergeur), ne pas relancer PHP-FPM inutilement, noter l'heure des changements. Si seul PHP sature (pas le lien), activer le ralentissement sous charge depuis l'administration (réglage désactivé par défaut) ; la variable `LOAD_SHED=off` dans `.env` le coupe sans passer par l'administration, pris en compte en moins d'une minute.
5. **Après** : relire les journaux nginx, ajuster les zones de la section 2 d'après ce qui a été vu, consigner l'incident.

## 5. Ce que l'application fait de son côté

- **Mesure** : chaque requête qui arrive à PHP ajoute une ligne à un tampon fichier (`storage/cache/watch/`), regroupée en base toutes les 30 s environ (trafic web) et toutes les 5 min (tâche `platform_watch`). Conservation : 3 jours par minute et route, 8 jours par heure, communauté et route, 30 jours d'alertes.
- **État de charge** : calculé sur la dernière minute à partir de la durée p95 des requêtes dynamiques, de la part de réponses 5xx et de la charge du processeur par cœur (Linux). Trois seuils d'alerte réglables dans l'administration. Monter d'un palier demande deux mesures de suite, redescendre trois.
- **Priorité sous charge** (désactivée par défaut) : palier 1 = communautés Gratuit ; palier 2 = Gratuit et Standard ; palier 3 = Gratuit, Standard et Pro ; Pro+ jamais. La formule est la formule **effective** (`PlanGift::effectivePlanSlug`, formules offertes comprises). Réponse **503** avec `Retry-After` (30 à 75 s selon le palier, avec une gigue pour étaler les retours) sur les seules routes non critiques.
- **Jamais ralenti** : connexion et session, paiements et webhooks, alertes de commandement et sécurité des joueurs, administration de la plateforme, signalement d'erreurs du mod, sondes de vie, interface opérationnelle (liste exacte dans `App\Services\Watch\LoadShedPolicy::NEVER`, affichée dans la page d'administration).
- **Capteur en panne = personne n'est ralenti** : état absent, plus vieux de 2 minutes, trop peu de requêtes pour juger, base muette ou index des formules absent : aucune requête n'est touchée.
- **Sondes de l'application** : `storage/cache/watch/state.json` (état de charge), `plans.json` (rang des formules, relu toutes les minutes), `mod-state.json` (fenêtre des détecteurs du mod). Supprimer le dossier est sans danger : il se reconstruit.

## 6. Réaction du mod aux 429 et 503 (extension `COMSPECExtension`, `Extension.cs`)

| Réponse | Effet dans le mod |
|---|---|
| **429** | Pause **globale** de tous les envois (8 à 30 s, puis échelle 45, 75, 90 s, `Retry-After` respecté jusqu'à 600 s), callback `RateLimited` vers le jeu. Les ordres ne sont pas exemptés. |
| **503** sans « maintenance » dans le corps | Pause de **cette route seulement** (`TryNoteEndpointServerError` : 30, 60, 120 puis 300 s, ou `Retry-After` s'il est plus long, 300 s au plus). Les autres routes continuent. Corps remis en file. |
| **503** avec « maintenance » dans le corps | Pause globale, comme un 429. |

Pour cette raison, le ralentissement sous charge répond **503** et jamais 429, et son corps ne contient pas « maintenance » (test `LoadShedMiddlewareTest`).

Risques de boucle de réessais examinés :

- la file de réessai est bornée (500 envois ; la position est réduite à une seule entrée, les marqueurs à 1000), et le passage de vidage ignore une route en pause (`IsEndpointCoolingNow`) ; une route refusée n'est donc rappelée qu'une fois la pause écoulée ;
- l'échelle de pause d'une route monte à chaque refus consécutif (jusqu'à 300 s) et retombe au premier succès : une communauté ralentie à 100 % voit ses positions retardées jusqu'à 5 minutes, d'où la part de requêtes ralenties réglable (60 % par défaut) ;
- les DLL antérieures à la version qui sépare les erreurs par route (2.0.76) traitent tout 503 comme une saturation globale, avec pause de 45 à 90 s : encore plus rude pour elles, mais sans boucle ;
- le client web (`atak-socket.js`) met en pause toutes ses écritures après un 503 sur une écriture : c'est pourquoi le site **ne ralentit jamais** les écritures ni les appels `/api/` d'un navigateur, seulement les pages ouvertes en lecture et les envois du mod.

Aucun changement du mod n'est nécessaire. Évolutions possibles à regarder dans la PR #1395 (mod) si l'on veut aller plus loin : reconnaître `"error":"server_busy"` dans le corps pour afficher « Athena est très sollicité » au lieu d'une erreur générique dans le journal de liaison, et plafonner à 120 s la pause d'une route refusée pour un 503 de ce type.
