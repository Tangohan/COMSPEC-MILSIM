# Migration du domaine : athena.ttrd.fr → athena-comspec.eu

Même VPS (72.62.22.55), même dossier (`/var/www/athena.ttrd.fr`, simple nom de dossier, on ne le renomme pas),
même base de données. On ajoute un nom de domaine, on ne déménage rien.

## Principe

- **athena-comspec.eu** devient l'adresse officielle (`APP_URL`) : liens des e-mails, canonical SEO, OAuth Discord,
  Steam OpenID, adresses affichées aux joueurs (/lier, /connect).
- **athena.ttrd.fr reste en service, sans limite de durée.** Les mods déjà installés chez les joueurs (ATAK natif
  ≤ 2.3.10, SSE, ancien Overwatch figé, DLL) appellent `https://athena.ttrd.fr/public/api/…` en dur. Une redirection
  casserait les POST du jeu : l'API reste donc servie telle quelle sur l'ancien domaine.
- Le code du site lit le domaine dans `APP_URL` (`App\Support\PortalHost`) : rien d'autre à changer côté site.
- Le mod natif passera au nouveau domaine dans le prochain bundle ATAK, **seulement une fois le nouveau domaine
  en HTTPS** (sinon le téléphone ne joindrait plus le portail).

## Étape 1 — DNS chez le registrar (TTRD)

Dans la zone DNS de `athena-comspec.eu` :

| Type | Nom | Valeur | TTL |
|------|-----|--------|-----|
| A | `@` (racine) | `72.62.22.55` | 3600 |
| A | `www` | `72.62.22.55` | 3600 |
| A | `atak` | `72.62.22.55` | 3600 |
| A | `sse` | `72.62.22.55` | 3600 |

- Supprimer les enregistrements A / AAAA / CNAME « parking » du registrar sur `@` et `www`.
- `atak.` et `sse.` ne sont que des raccourcis : ils renvoient vers des pages du site principal (une seule
  session, un seul site à maintenir).
- Pas d'AAAA tant que l'IPv6 du VPS n'est pas configurée dans Nginx.
- Propagation : de quelques minutes à quelques heures. Vérifier depuis n'importe où :
  `nslookup athena-comspec.eu` → doit répondre `72.62.22.55`.

## Étape 2 — Nginx + certificat HTTPS (VPS, en SSH)

```bash
# 1) Ajouter les deux noms au vhost existant (toutes les lignes server_name)
sudo cp /etc/nginx/sites-available/athena.ttrd.fr /root/athena.ttrd.fr.nginx.bak
sudo sed -i 's/server_name athena\.ttrd\.fr;/server_name athena.ttrd.fr athena-comspec.eu www.athena-comspec.eu;/' \
  /etc/nginx/sites-available/athena.ttrd.fr
grep -n server_name /etc/nginx/sites-available/athena.ttrd.fr
sudo nginx -t && sudo systemctl reload nginx

# 2) Raccourcis atak. (carte ATAK web) et sse. (portail SSE) : simple renvoi vers le site principal
sudo tee /etc/nginx/sites-available/athena-comspec-raccourcis >/dev/null <<'NGINX'
server {
    listen 80;
    listen [::]:80;
    server_name atak.athena-comspec.eu;
    return 301 https://athena-comspec.eu/atak;
}
server {
    listen 80;
    listen [::]:80;
    server_name sse.athena-comspec.eu;
    return 301 https://athena-comspec.eu/atak/sse;
}
NGINX
sudo ln -sf /etc/nginx/sites-available/athena-comspec-raccourcis /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx

# 3) Étendre le certificat Let's Encrypt existant à tous les nouveaux noms
sudo certbot --nginx --cert-name athena.ttrd.fr --expand \
  -d athena.ttrd.fr -d athena-comspec.eu -d www.athena-comspec.eu \
  -d atak.athena-comspec.eu -d sse.athena-comspec.eu
sudo nginx -t && sudo systemctl reload nginx

# 4) Tests (les deux domaines doivent répondre pareil)
curl -sS https://athena-comspec.eu/api/atak/ping
curl -sS https://athena-comspec.eu/public/api/atak/ping
curl -sS https://athena.ttrd.fr/public/api/atak/ping
curl -sI https://www.athena-comspec.eu/ | head -n 3
curl -sI https://atak.athena-comspec.eu/ | grep -i ^location   # → https://athena-comspec.eu/atak
curl -sI https://sse.athena-comspec.eu/ | grep -i ^location    # → https://athena-comspec.eu/atak/sse
```

Si certbot demande pour les raccourcis « redirect HTTP → HTTPS ? », répondre oui : il ajoute le bloc 443 qui
reprend le même `return 301`.

Si certbot répond « Could not find a matching certificate » : `sudo certbot certificates` donne le vrai nom du
certificat, à mettre après `--cert-name`.

## Étape 3 — Basculer APP_URL (VPS)

```bash
cd /var/www/athena.ttrd.fr
sudo cp .env /root/athena.env.bak
sudo sed -i 's#^APP_URL=.*#APP_URL=https://athena-comspec.eu#' .env
grep ^APP_URL .env
sudo systemctl reload php*-fpm
```

Retour arrière : remettre `APP_URL=https://athena.ttrd.fr` et recharger php-fpm.

Effet : les sessions sont liées au domaine. Chacun se reconnecte **une fois** sur athena-comspec.eu.

## Étape 4 — Services externes (TTRD)

| Service | Où | À faire |
|---------|----|---------|
| Discord (application OAuth / rôles liés) | discord.com/developers → votre application → OAuth2 → Redirects | Ajouter `https://athena-comspec.eu/discord/roles-lies/retour` (garder l'ancienne quelques semaines) |
| Discord (rôles liés) | même application → General Information → Linked Roles Verification URL | `https://athena-comspec.eu/discord/roles-lies` |
| Steam « Se connecter avec Steam » | rien | Le realm OpenID suit `APP_URL` automatiquement |
| Clé Steam Web API | steamcommunity.com/dev/apikey | Domaine déclaratif : peut être mis à jour, pas obligatoire |
| E-mails | Back-office → Intégrations → Plateforme | Si l'expéditeur passe en `@athena-comspec.eu` : authentifier le domaine chez Brevo (TXT SPF + DKIM + DMARC à ajouter dans la zone DNS). Sinon ne rien changer |
| Google Search Console / Bing | search.google.com/search-console | Ajouter la propriété `athena-comspec.eu`, envoyer `/sitemap.xml` |
| Pages Steam Workshop (ATAK, SSE) | description Steam | Les liens et images athena.ttrd.fr continuent de marcher ; à remplacer au prochain envoi Workshop |
| Discord du serveur COMSPEC | messages épinglés, salons | Mettre à jour les liens à la main (rien n'est publié automatiquement) |

Les webhooks Discord par communauté (back-office) sont des URL Discord : rien à changer.

## Étape 5 — Mod natif (bundle ATAK, plus tard)

Seulement **après** les étapes 1 à 3 : le prochain bundle remplace `https://athena.ttrd.fr/public` par
`https://athena-comspec.eu/public` dans le mod natif et le SSE (adresse fixe, textes du téléphone, i18n, mod.cpp).
Les joueurs qui n'ont pas mis à jour continuent d'utiliser l'ancien domaine, toujours servi.

## Étape 6 (facultative, dans quelques semaines) — rediriger les pages de l'ancien domaine

Quand plus personne n'utilise les vieux liens dans le navigateur, on peut renvoyer les **pages** (pas l'API, pas les
images) de athena.ttrd.fr vers athena-comspec.eu. Dans chaque bloc `server` en 443 du vhost, juste avant
`location / {` :

```nginx
    # Ancien domaine : l'API et les fichiers restent servis (mods installés, images Workshop).
    location ^~ /api/ {
        try_files $uri /index.php?$query_string;
    }
```

et en tête de `location / {` :

```nginx
        if ($host = athena.ttrd.fr) {
            return 308 https://athena-comspec.eu$request_uri;
        }
```

`/public/api/…` est réécrit en `/api/…` avant d'arriver là : il reste servi. `nginx -t && systemctl reload nginx`.
