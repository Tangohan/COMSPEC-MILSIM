# Boîte de contact de la plateforme

Administration du site, Communication, **Boîte de contact** : les e-mails reçus sur l'adresse de contact (par défaut
`contact@athena.ttrd.fr`) arrivent dans le site, rangés en fils de discussion. Vous y répondez depuis le site ; la réponse
part de la même adresse. Réservée à l'administrateur de la plateforme.

## 1. Créer la boîte (une fois)

1. hPanel (Hostinger), Courriels, domaine `athena.ttrd.fr` : créer la boîte `contact@athena.ttrd.fr` (forfait Free Business Email).
2. Choisir un mot de passe fort. Il ne se commite jamais : il va uniquement dans le fichier `.env` du serveur.

## 2. Clés à renseigner dans le `.env` du serveur

| Clé | Rôle | Défaut |
| --- | --- | --- |
| `CONTACT_MAIL_PASSWORD` | Mot de passe de la boîte. **Seule clé obligatoire** : sans elle, l'écran affiche « Boîte non configurée » et le site ne se connecte à rien. | aucun |
| `CONTACT_MAIL_ADDRESS` | Adresse de la boîte, expéditeur des réponses et Reply-To des e-mails automatiques. | `contact@athena.ttrd.fr` |
| `CONTACT_MAIL_USER` | Identifiant IMAP et SMTP. | la valeur de `CONTACT_MAIL_ADDRESS` |
| `CONTACT_MAIL_IMAP_HOST` | Serveur de lecture. | `imap.hostinger.com` |
| `CONTACT_MAIL_IMAP_PORT` | Port IMAP, TLS implicite. | `993` |
| `CONTACT_MAIL_SMTP_HOST` | Serveur d'envoi. | `smtp.hostinger.com` |
| `CONTACT_MAIL_SMTP_PORT` | `465` (SSL) ou `587` (STARTTLS). | `465` |
| `CONTACT_MAIL_FROM_NAME` | Nom affiché de l'expéditeur. | `Athena` |
| `CONTACT_MAIL_SIGNATURE` | Signature ajoutée aux nouveaux messages et aux réponses (`\n` pour un retour à la ligne). | vide |
| `CONTACT_MAIL_SENT_FOLDER` | Dossier IMAP où copier les messages envoyés (copie faite au mieux). | `INBOX.Sent` |
| `CONTACT_MAIL_SEND_PER_HOUR` | Plafond d'envois par heure depuis la boîte. | `20` |
| `MAIL_REPLY_TO_MODE` | `contact` : les réponses aux e-mails automatiques arrivent dans la boîte (Reply-To) ; `none` : aucun Reply-To. | `contact` |

Les e-mails automatiques du site (inscription, code de connexion, alertes) gardent leur expéditeur `no-reply@…`
(`MAIL_FROM_ADDRESS`). Ils portent en plus un Reply-To vers la boîte de contact et une phrase en pied de message. Les
messages envoyés avec le compte d'une communauté ne sont pas modifiés.

## 3. Réglages Hostinger

- Lecture (IMAP) : `imap.hostinger.com`, port 993, SSL/TLS.
- Envoi (SMTP) : `smtp.hostinger.com`, port 465 (SSL) ou 587 (STARTTLS).
- Identifiant : l'adresse e-mail complète. Mot de passe : celui de la boîte.
- Le site applique les plafonds d'envoi du forfait (voir Administration, Envois d'e-mails) : la boîte de contact a son propre plafond horaire.

## 4. Délivrabilité : enregistrements DNS à vérifier

Dans la zone DNS de `athena.ttrd.fr` (hPanel, Domaines, Zone DNS), sans doublon :

- **SPF** : un seul enregistrement TXT `v=spf1 …` à la racine du domaine, qui inclut Hostinger (valeur donnée par hPanel, du type `include:_spf.mail.hostinger.com`). Deux enregistrements SPF invalident les deux.
- **DKIM** : activer la signature dans hPanel (Courriels, DKIM, y compris « DKIM personnalisé ») et ajouter les enregistrements proposés (CNAME ou TXT). Vérifier qu'ils répondent avant d'envoyer.
- **DMARC** : un enregistrement TXT `_dmarc.athena.ttrd.fr`, par exemple `v=DMARC1; p=none; rua=mailto:<adresse de rapports>`, à durcir (`quarantine`) quand les rapports sont propres.

Test simple : envoyer un message depuis la boîte vers une adresse de test et lire les en-têtes (`spf=pass`, `dkim=pass`, `dmarc=pass`).

## 5. Base de données et tâche planifiée

- Migration : étape **`contact_mailbox`** (Pilotage du site, migrations). Elle crée `contact_mail_threads`, `contact_mail_messages`, `contact_mail_attachments` et `contact_mail_sync_state` (`migrations/2026_11_15_010_contact_mailbox.sql`, retour arrière dans `migrations/rollback/contact_mailbox_down.sql`).
- Tâche automatique **`contact_mail_sync`** (Administration, Tâches automatiques) : lit les nouveaux messages toutes les 5 minutes, par UID, sans doublon. Elle part avec le passage planifié habituel du site ; sans `CONTACT_MAIL_PASSWORD`, elle ne fait rien. Le bouton « Actualiser » de la boîte lance la même lecture à la demande.
- Les pièces jointes reçues sont rangées dans `storage/private/contact-mail/` (hors du dossier public, téléchargées par une route protégée).

## 6. Formulaire « Nous contacter »

Quand la boîte est configurée, les messages du formulaire public (`/contact`) deviennent des fils « Formulaire » (adresse non vérifiée, limite de 5 messages par heure et par adresse IP, jeton CSRF et champ piège). Si l'adresse publique de contact du site est celle de la boîte, le message n'est pas envoyé une seconde fois par e-mail.

## 7. Sécurité

- Accès réservé à l'administrateur de la plateforme ; jeton CSRF sur toutes les écritures.
- Corps texte par défaut ; HTML assaini par liste blanche (ni script, ni style, ni iframe, ni formulaire) ; images distantes masquées jusqu'à « Afficher les images ».
- Adresses et objet refusés s'ils contiennent un retour à la ligne (injection d'en-tête). Envois journalisés (journal d'audit), sans le contenu des messages.
- Ni le mot de passe ni le contenu d'un message n'est écrit dans les journaux d'erreurs.
