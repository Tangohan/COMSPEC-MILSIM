# Intégrations externes

## Courriel transactionnel

- Transport configurable via **`.env`** et **`config/email.php`** : écriture locale (fichiers), SMTP générique, ou fournisseurs courants (variables documentées dans `.env.example`).
- **`EmailService`** / **`EmailTransportResolver`** : envoi des notifications (inscription, sécurité, présence, invitations, etc.).
- En développement, le mode **fichier** permet de vérifier les messages sans serveur SMTP.

## Paiements (Stripe)

- Routes publiques pour le parcours de création de communauté et **webhook** Stripe (`StripeWebhookController`) — typiquement exclu du mode maintenance pour traiter les événements de paiement.
- Les secrets Stripe doivent être configurés côté serveur (variables d’environnement) et ne jamais figurer dans le dépôt.

## Clients tactiques et API

- Endpoints sous préfixes dédiés (ATAK, intelligence, logistique, etc.) pour des **clients externes** ou scripts ; authentification par **clé API** sur les chemins tactiques (voir `ComspecTacticalApiMiddleware` et configuration tactique).
- Respecter la version des contrats attendus par les applications mobiles ou outils terrain.

## Modération antivirus (optionnel)

- Si activée (`MODERATION_*` dans l’environnement), intégration possible avec **ClamAV** et règles heuristiques pour les fichiers uploadés — quarantaine et durée de rétention configurables.

## Géolocalisation des connexions (optionnel)

- Des mentions dans `MAIL_GEOIP_ENABLED` peuvent enrichir les alertes de sécurité (selon implémentation).

## Santé

- **`/api/health`** : point de contrôle pour load balancer ou supervision.

## Voir aussi

- [Configuration et déploiement](configuration-et-deploiement.md)
- [Sécurité et permissions](securite-et-permissions.md)
