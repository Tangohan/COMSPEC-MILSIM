# ATHENA Companion

Bot officiel multi-serveurs / Official multi-server bot. Python 3.12+, `discord.py`.

## Mise en service

1. Appliquer les migrations du site. Nommer l’application Discord « ATHENA Companion » (la même que pour les rôles liés), activer le bot et copier son jeton uniquement dans le service Companion. Aucun intent privilégié (Message Content / Server Members) n’est nécessaire.
2. Dans Administration → Intégrations de la plateforme → ATHENA Companion, enregistrer une clé aléatoire de 32 caractères minimum. Définir la même valeur dans `ATHENA_COMPANION_API_KEY` du service. Cette clé est distincte du jeton Discord et des clés API des communautés.
3. Créer un environnement virtuel Python, installer `requirements.txt`, puis configurer les variables de `.env.example` via un fichier d’environnement protégé. `ATHENA_SITE_URL` doit inclure `/public` si le site utilise ce chemin. Le service ne charge pas automatiquement un fichier `.env`.
4. Lancer `python main.py`. Un exemple systemd est fourni ; adapter les chemins, créer le compte système et protéger `/etc/athena-companion.env` (lecture réservée au compte du service).
5. Une communauté invite le bot depuis Événements → Configuration Discord, crée son code temporaire puis utilise `/athena connect code:… channel:…` sur son serveur. Cette commande nécessite Gérer le serveur et répond en privé. Une communauté correspond à un serveur Discord ; les changements exigent de délier l’association dans le site.
6. Lors de la création d’un événement, cocher « Publier sur Discord » et choisir ATHENA Companion. Le bot publie les événements des trente prochains jours, vérifie la file toutes les 60 secondes et met à jour les annonces après modification ou annulation.

Les réactions ✅ présent, ❌ absent et ❓ peut-être sont synchronisées par le Gateway, avec une réconciliation périodique de cinq minutes. Les comptes doivent être liés à Discord par OAuth dans Athena. Les conflits d’emojis et les corrections du site suivent les mêmes règles que les événements existants ; le pointage effectif est conservé.

Les noms des salons sont synchronisés toutes les dix minutes et lors d’un renommage. Le carnet de webhooks peut utiliser ces noms sans stocker le jeton du bot dans les communautés. Les webhooks existants et les bots spécifiques restent disponibles.

Les autorisations demandées sont Voir le salon, Envoyer des messages, Intégrer des liens, Voir les anciens messages et Ajouter des réactions. Les messages Discord sont sans mentions. Le bot ne crée ni rôles ni salons et ne modère pas les membres.

Conserver le fichier SQLite du service : il garde les accusés de publication et les correspondances des messages pour les réactions après redémarrage. En cas de perte d’un accusé, le bot cherche aussi les 100 derniers messages pour retrouver son annonce. Une seule instance de publication doit être lancée. La file est bornée à 200 événements proches par interrogation ; prévoir une pagination avant des déploiements dépassant ce volume. Les messages supprimés manuellement sont signalés en échec et ne sont pas republiés automatiquement.

## English setup

Apply site migrations and use the same Discord application as linked roles. Keep `DISCORD_TOKEN` exclusively in the Companion service. No privileged intents are required. Save a separate random API key in the platform integration settings and put the same value in `ATHENA_COMPANION_API_KEY`.

Create a Python virtual environment, install `requirements.txt`, configure the environment and run `python main.py` (systemd example included). Use the exact public `ATHENA_SITE_URL`, including `/public` where needed. `.env` files are not loaded automatically.

Invite the bot from Event Discord settings. Generate a ten-minute pairing code, then run `/athena connect code:… channel:…` as a Discord server manager. Choose ATHENA Companion as the event publication destination. Account linking uses the existing OAuth flow; the redirect URL still needs to match Discord Developer Portal → OAuth2 → Redirects exactly.

Publications are checked every minute. Emoji RSVPs update through the Gateway with five-minute reconciliation. Channel names refresh every ten minutes and on rename. Actual attendance is preserved. Keep the SQLite state file and run only one publishing instance. The first version handles up to 200 nearby events per poll; larger deployments require pagination. Manually deleted announcements are reported rather than automatically reposted.

## Tests

`python -m unittest discover -s bots/athena-companion -p 'test_*.py'` (dependencies required). Tests do not log in to Discord or send messages.
