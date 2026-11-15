# Bot Discord Athena sur le VPS

Le bot Discord de la plateforme (ATHENA Companion, code dans `bots/athena-companion/`) tourne en service permanent sur le VPS, supervisé par systemd. Il accompagne les communautés dans leur bureau des effectifs, sur le serveur Discord que chaque communauté lui a rattaché depuis le back-office (Intégrations › Bot Discord Athena, ou `/back-office/discord-bot`).

Rien dans cette procédure n'est lancé par le déploiement du site : l'installation et chaque mise à jour du bot se font à la main, après revue, avec le script `deploy/discord-bot/install.sh`.

## Ce que fait le bot

| Fonction | Détail |
| --- | --- |
| Événements | Publie les événements des 30 prochains jours et recueille les réponses par emoji (déjà en service). |
| Commandes | `/effectifs`, `/membre nom`, `/candidatures`, `/evenements`, `/integration`. Réponses privées. Chaque commande exige que le compte Discord soit lié à un compte Athena de la communauté du serveur et que ce compte ait, sur le site, le droit correspondant. |
| Rappels | Nouvelles candidatures, intégrations à valider, bilans 30 jours, qualifications qui arrivent à échéance (message privé au membre lié), campagne de fiabilisation à J-14, J-3 et jour J (message privé aux membres liés qui n'ont pas confirmé). Un rappel acquitté n'est jamais renvoyé. |
| Rôles | Option, désactivée par défaut : copie le niveau d'accès du site vers deux rôles Discord désignés par la communauté (membre, responsable). Jamais l'inverse : Discord ne donne aucun droit sur le site. |
| Accueil | Option, désactivée par défaut : message d'accueil avec le lien pour lier son compte Athena. |

Les décisions (accepter ou refuser une candidature, valider une intégration) se prennent uniquement sur le site. Les boutons de `/candidatures` sont des liens vers le site.

## Ce qui est exigé du bot (sécurité et vie privée)

- Le jeton Discord vit uniquement dans `/etc/athena-discord-bot/bot.env` (hors dépôt, lisible par root et par le compte du service). Il n'est ni dans la base, ni dans les journaux, ni dans les réponses.
- Aucune réponse ne contient d'état civil, d'e-mail, de photo, de papier ni de donnée SSE. Les fiches donnent indicatif, grade, statut, ancienneté.
- La communauté est toujours déduite du serveur Discord lié, jamais d'une saisie. Un serveur pour une communauté.
- Chaque commande est journalisée sans contenu (table `discord_bot_audit` : serveur, identifiant Discord, commande, résultat) et plafonnée à 12 par minute et par personne, plus un délai de 3 secondes dans le bot.
- Aucun intent privilégié par défaut : le bot ne lit pas les messages (`MESSAGE_CONTENT` désactivé) et ne reçoit pas la liste des membres. Seul l'accueil des nouveaux arrivants demande l'intent « Server Members », à activer volontairement (voir plus bas).
- Le bot ne publie jamais dans le salon SUPPORT du Discord COMSPEC et n'y a aucun réglage. Ses annonces de versions vont uniquement dans le salon des releases, par la procédure de publication existante, pas par ce service.

## Avant de commencer : réglages côté Discord

Portail des développeurs : https://discord.com/developers/applications. Utiliser la même application que celle des rôles liés (identifiant et secret déjà saisis dans Administration › Intégrations de la plateforme).

1. **Bot** : cliquer « Reset Token » et copier le jeton (il ne s'affiche qu'une fois). Il servira à l'étape suivante, nulle part ailleurs.
2. **Bot › Public Bot** : activé, sinon les autres communautés ne pourront pas ajouter le bot. **Requires OAuth2 Code Grant** : désactivé.
3. **Bot › Privileged Gateway Intents** : tout désactivé (Presence, Server Members, Message Content). Exception facultative : activer *Server Members Intent* seulement si vous voulez l'accueil des nouveaux arrivants, et mettre `ATHENA_COMPANION_MEMBERS_INTENT=1` dans le fichier d'environnement. Au-delà de 100 serveurs, Discord demande une vérification pour cet intent.
4. **OAuth2 › Redirects** : ajouter exactement `https://<votre site>/back-office/discord-bot/retour` (avec `/public` si le site l'utilise), en plus des adresses des rôles liés et de la connexion Discord déjà présentes.
5. Ne rien cocher dans OAuth2 › URL Generator : le lien d'invitation est généré par Athena pour chaque communauté.

Permissions demandées par l'invitation : Voir les salons, Envoyer des messages, Intégrer des liens, Voir les anciens messages, Ajouter des réactions. Jamais Administrateur. *Gérer les rôles* n'est ajoutée que si la communauté choisit la synchronisation des rôles.

## Avant de commencer : réglages côté site

1. Lancer les migrations de la base (étape `discord_bot`, numéro 4680) par la procédure habituelle (`run-migrations.php`). Sans elle, la page du back-office signale que le bot n'est pas installé et les nouvelles commandes répondent « non configuré ». Retour arrière : `migrations/rollback/discord_bot_down.sql`.
2. Administration › Intégrations de la plateforme › ATHENA Companion : enregistrer une clé aléatoire d'au moins 32 caractères (`openssl rand -hex 32`). Cette même valeur ira dans le fichier d'environnement du bot.

## Installation sur le VPS

Sur le VPS, depuis la copie du dépôt déjà déployée (`/var/www/athena.ttrd.fr`) :

```bash
cd /var/www/athena.ttrd.fr
sudo deploy/discord-bot/install.sh
```

Le script est idempotent. Il crée le compte système `athena-bot` (sans connexion, sans shell), copie le code dans `/opt/athena-discord-bot/app`, crée l'environnement Python et installe les dépendances épinglées, installe l'unité systemd et la rotation des journaux, active le service au démarrage, puis affiche l'état.

Au premier passage, il crée `/etc/athena-discord-bot/bot.env` depuis le modèle `deploy/discord-bot/bot.env.example` et **ne démarre pas** le service. Remplir ce fichier :

```bash
sudo nano /etc/athena-discord-bot/bot.env     # DISCORD_TOKEN, ATHENA_COMPANION_API_KEY, ATHENA_SITE_URL
sudo deploy/discord-bot/install.sh            # démarre le service si le fichier est rempli
```

Le fichier appartient à `root:athena-bot` avec les droits `0640`. Ne jamais coller le jeton dans un message, un ticket ou un journal.

Options du script :

| Option | Effet |
| --- | --- |
| *(aucune)* | Installe ou met à jour, active au démarrage, démarre le service seulement s'il est arrêté et que le fichier d'environnement est rempli. |
| `--restart` | Redémarre aussi le service s'il tourne (à utiliser après une mise à jour du code du bot). |
| `--no-start` | Installe sans rien démarrer. |

## Vérifier que le bot tourne

```bash
sudo /opt/athena-discord-bot/status.sh
```

Il affiche : service actif ou non, activé au démarrage, nombre de redémarrages, âge du battement de cœur (fichier `/run/athena-discord-bot/heartbeat`, écrit toutes les 30 secondes), réponse du point de santé local `http://127.0.0.1:8765/health` (joignable depuis le serveur seulement) et les dernières lignes du journal. Code de sortie 0 si tout va bien, 1 sinon : utilisable par une supervision.

Le point de santé répond 200 `{"status":"ok", ...}` quand le bot est connecté à Discord et que la file du site a été interrogée il y a moins de trois minutes, 503 sinon.

Commandes utiles : `sudo systemctl status athena-discord-bot`, `sudo tail -f /var/log/athena-discord-bot/bot.log`.

Côté site : Intégrations › Bot Discord Athena. L'état passe de « En attente du bot » à « Connecté » dans les dix minutes qui suivent la présence du bot sur le serveur (ou aussitôt, à son arrivée). Le bouton « Tester la connexion » demande un message d'essai dans le salon de l'équipe, qui arrive en moins d'une minute.

## Mettre à jour

Le déploiement du site (workflow « Deploy VPS ») met à jour le dépôt mais **ne démarre ni ne redémarre jamais le bot**. S'il constate que le code du bot a changé, il l'écrit dans son journal. Pour appliquer la mise à jour, au moment choisi :

```bash
cd /var/www/athena.ttrd.fr
sudo deploy/discord-bot/install.sh --restart
sudo /opt/athena-discord-bot/status.sh
```

Le redémarrage dure quelques secondes ; les publications et rappels en attente reprennent au passage suivant (les accusés sont conservés dans `/var/lib/athena-discord-bot/state.sqlite3` : ne pas le supprimer, et ne lancer qu'une seule instance).

## Journaux

Le bot écrit dans `/var/log/athena-discord-bot/bot.log`. Le jeton, la clé et le contenu des commandes n'y apparaissent jamais (seuls le type d'erreur et le genre de rappel sont notés). La rotation est assurée par logrotate (`/etc/logrotate.d/athena-discord-bot`) : hebdomadaire, huit archives compressées, 50 Mo au plus avant rotation anticipée.

## Si quelque chose ne va pas

| Constat | Cause probable et remède |
| --- | --- |
| `status.sh` : service arrêté, fichier d'environnement vide | Remplir `/etc/athena-discord-bot/bot.env` puis relancer `install.sh`. |
| Journal : `LoginFailure` ou `Improper token` | Jeton erroné ou réinitialisé : en générer un nouveau au portail, mettre à jour le fichier, `sudo systemctl restart athena-discord-bot`. |
| Journal : `Site unavailable` ou refus 401 | `ATHENA_SITE_URL` inexact (avec `/public` si besoin) ou clé différente de celle des Intégrations de la plateforme. |
| La page du back-office reste « En attente du bot » | Le bot n'est pas sur le serveur (relancer l'invitation), ou son service est arrêté. |
| « Bot absent » | Aucun signe de vie depuis deux heures : service arrêté ou bot retiré du serveur. |
| L'invitation échoue à son retour | L'adresse de retour manque aux Redirects OAuth2 du portail, ou la personne n'a pas « Gérer le serveur » sur ce serveur. |
| Les commandes n'apparaissent pas | Les commandes globales mettent parfois jusqu'à une heure à se propager après la première connexion. |
| Rôles non synchronisés | Le rôle du bot doit être **au-dessus** des deux rôles désignés, avoir la permission *Gérer les rôles*, et les rôles ne doivent pas porter de pouvoir de gestion (le bot les refuse). |

## Retirer le bot

Dans le back-office : « Retirer le serveur » (efface les réglages, le bot cesse de répondre pour la communauté). Sur Discord : Paramètres du serveur › Intégrations › Athena › Retirer. Pour arrêter le service sur le VPS : `sudo systemctl disable --now athena-discord-bot`.

## English summary

The Discord bot runs as the `athena-discord-bot` systemd service under the unprivileged `athena-bot` account. Install or update it manually with `sudo deploy/discord-bot/install.sh` (add `--restart` to apply a code update; the site deployment never restarts the bot). Put the bot token and the API key only in `/etc/athena-discord-bot/bot.env` (mode 0640, outside the repository). No privileged intent is required; `ATHENA_COMPANION_MEMBERS_INTENT=1` is only for the optional welcome message. In the Developer Portal, enable *Public Bot* and add `https://<site>/back-office/discord-bot/retour` to the OAuth2 redirects. Check health with `sudo /opt/athena-discord-bot/status.sh` (service, heartbeat file, local `127.0.0.1:8765/health`, last log lines). Logs are in `/var/log/athena-discord-bot/bot.log`, rotated weekly.
