# Certificat : Let’s Encrypt refuse athena.ttrd.fr à cause de l’IPv6

## Contexte

Migration du portail vers `athena-comspec.eu`, extension du certificat existant `athena.ttrd.fr` (même VPS).

## Symptôme

`certbot --nginx --cert-name athena.ttrd.fr --expand` échoue :

`2a02:4780:28:58bc::1: Invalid response from http://athena.ttrd.fr/.well-known/acme-challenge/… : 404`

Le fichier `/etc/nginx/sites-available/athena.ttrd.fr` n’existe pas. `nginx -t` reste valide, avec un avertissement de `server_name` « tt.ttrd.fr » en double (sans lien avec cet échec).

## Cause

`athena.ttrd.fr` a un A (`72.62.22.55`) et un AAAA (`2a02:4780:28:58bc::1`). Le vhost Athena n’écoute qu’en IPv4. En IPv6, le port 80 sert la page « Welcome to nginx » et le port 443 sert un autre site du VPS. Let’s Encrypt valide toutes les adresses : l’IPv6 répond 404, le certificat entier est refusé. Les nouveaux noms n’ont pas d’AAAA ; ils ne sont pas la cause.

## Correctif

Ajouter `listen [::]:80` et `listen [::]:443 ssl` (mêmes options que l’IPv4, sans `default_server`) sur les blocs du vhost Athena, puis relancer certbot. Alternative : retirer l’AAAA de `athena.ttrd.fr` le temps de l’émission.

## Fichiers touchés

- `docs/migration-domaine-athena-comspec.md` (la procédure ne doit plus supposer le nom de fichier, et doit traiter l’IPv6 avant certbot)
- VPS : `/etc/nginx/sites-available/athena` (lien `sites-enabled/athena`). Ne pas éditer `athena.bak.20261013-170926`.

## Vérification

Depuis l’extérieur : `curl -6 -sI http://athena.ttrd.fr/` doit rediriger vers le portail, plus afficher « Welcome to nginx ». Ensuite certbot `--expand` doit aboutir.

## Statut

Identifié. Correctif nginx à appliquer sur le VPS.
