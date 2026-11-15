# Campagne de fiabilisation des données

Chaque année, Athena demande à chaque membre de relire sa propre fiche (identité, matricules, affectation, qualifications, heures, coordonnées) et de confirmer qu'elle est exacte ou de signaler ce qui ne l'est pas. Le système ne modifie jamais une fiche : une correction passe par la validation habituelle (demandes de correction de fiche) ou par la réponse de l'encadrement à un signalement.

## Fonctionnement par défaut

- **Ouverture automatique : oui, pour toute communauté.** La tâche planifiée `data_campaign` (toutes les heures, `CronSchedule`) ouvre la campagne de l'année le 1er décembre, avec date limite le 31 décembre, tous les blocs, tous les membres actifs.
- **Rappels** à J-14, J-3 et le jour J, une seule fois par membre ; notification d'ouverture et e-mail (par la file d'envoi existante), au plus 150 membres servis par passage.
- **Clôture** automatique à la date limite, avec un rapport d'avancement (confirmés, signalements, sans réponse) envoyé aux responsables.
- Pour la gestion (Organisation > Fiabilisation des données) : créer une campagne à la main (dates, parties, unités libres), la modifier, la clôturer, couper l'ouverture automatique (« Ouverture automatique : Non »), couper les e-mails, changer le jour d'ouverture et la date limite de sa communauté.
- Une seule campagne par communauté et par année : une campagne créée à la main empêche Athena d'en ouvrir une seconde (clé unique `tenant + année` pour celles ouvertes par Athena, contrôle de l'existant pour les manuelles).

## Garde-fous (communautés anciennes ou dormantes)

- Aucune ouverture pour une communauté sans membre connecté depuis 90 jours, ni sans membre actif.
- Notifications et e-mails bornés à 150 membres par passage ; le reste part aux passages suivants, sans doublon.
- Si plus de la moitié des fiches n'ont ni prénom ni nom, la campagne s'ouvre quand même et l'avis aux responsables ajoute « beaucoup de fiches incomplètes ».
- Aucune donnée SSE (identités d'emprunt, couvertures, papiers) n'est lue ni enregistrée.

## Commande `data-campaign.php`

Même service que la tâche planifiée (`DataCampaignService::runScheduled`) : aucune logique dupliquée.

```
php data-campaign.php --dry-run                  simulation de la tâche planifiée (n'écrit et n'envoie rien)
php data-campaign.php --open-now --dry-run       simulation de l'ouverture immédiate : communautés et nombres de membres
php data-campaign.php --open-now                 ouverture immédiate pour toute communauté éligible, une à la fois
php data-campaign.php --open-now --tenant=3      une seule communauté (même si elle a coupé l'automatisme)
php data-campaign.php --open-now --deadline=2026-12-31 --blocks=identite,matricules,affectation,qualifications,heures
php data-campaign.php --open-now --no-email      notifications seulement
```

Le bouton « Ouvrir maintenant » de l'écran de gestion appelle le même service pour la communauté courante, avec confirmation du nombre de membres concernés.

La commande affiche en fin de passage : communautés traitées, campagnes créées, clôturées, membres notifiés, e-mails mis en file, rappels, erreurs. Relancer la commande ne renvoie rien de ce qui est déjà parti ; elle continue les lots restants.

### Procédure de lancement sur un serveur

1. Lancer la migration (étape `data_campaigns`) ; retour arrière : `migrations/rollback/data_campaigns_down.sql`.
2. `php data-campaign.php --open-now --dry-run` : relire la liste des communautés et les nombres de membres.
3. `php data-campaign.php --open-now` (ajouter `--tenant=ID` pour commencer par une communauté).
4. Si besoin, relancer la commande : les membres restants sont notifiés, personne ne l'est deux fois.

## Données

Tables `data_campaign_settings`, `data_campaigns`, `data_campaign_members` (réponses par bloc, instantané et empreinte de ce que le membre a confirmé), `data_campaign_flags` (signalements, liés à la demande de correction quand le membre propose une valeur) et `data_campaign_reminders` (journal des envois, clé unique campagne + membre + rappel).
