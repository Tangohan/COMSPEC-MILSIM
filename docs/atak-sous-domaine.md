# Sous-domaine ATAK (atak.athena.ttrd.fr)

## Qui crée le sous-domaine ?

**Athena** est déjà un sous-domaine (ex. `athena.ttrd.fr`). Le sous-domaine **atak** (`atak.athena.ttrd.fr`) est un **sous-sous-domaine** : il doit être créé au niveau **DNS / hébergement**, pas par le code.

- **Qui le crée** : la personne qui gère le DNS du domaine (ex. `ttrd.fr` ou `athena.ttrd.fr`) — hébergeur, OVH, Cloudflare, etc.
- **Où** : dans le panneau DNS (zone `athena.ttrd.fr` ou équivalent), en ajoutant un enregistrement pour **atak** :
  - **Type** : A ou CNAME
  - **Nom** : `atak` (donc `atak.athena.ttrd.fr`)
  - **Cible** : même IP que le serveur qui héberge le site Athena, ou l’IP du serveur Node ATAK si elle est différente

## Pourquoi un sous-domaine dédié ?

Le nœud ATAK (serveur Node, port 3001) peut être :

1. **Sur le même serveur que le site** (PHP/Athena) : dans ce cas, un reverse proxy (Nginx/Apache) est configuré pour que `atak.athena.ttrd.fr` (port 443) redirige vers `localhost:3001`. Le sous-domaine permet d’exposer proprement le WebSocket/API sans mélanger avec le site principal.
2. **Sur une autre machine** : alors l’enregistrement DNS pointe vers l’IP de cette machine.

En résumé : **c’est l’admin hébergeur / DNS qui crée le sous-domaine** `atak` dans la zone correspondant à `athena.ttrd.fr`.
