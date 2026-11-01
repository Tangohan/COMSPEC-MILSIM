# Liens des salons Discord affichés en clair (Intégrations, Paramètres)

## Contexte

Refonte de la page Intégrations › Discord (salon de l'organisation, salons à part, liens d'invitation des effectifs).

## Symptôme

- Le lien webhook du salon commun et ceux des « Autre salon » étaient réécrits en clair dans les champs de la page Intégrations : toute personne voyant l'écran (ou une capture) pouvait écrire dans le salon.
- Les Paramètres de la communauté réaffichaient aussi ce lien, et enregistrer les paramètres avec le champ vide **effaçait** le salon commun sans prévenir.
- Un lien en `http://` était accepté (réécrit en https).

## Cause

Les liens étaient stockés tels quels dans les réglages de la communauté et renvoyés dans l'attribut `value` des champs. Deux écrans écrivaient la même valeur.

## Correctif

- `DiscordWebhookDirectory` : liens chiffrés au repos (`SecretCipher`, clé de l'application), jamais renvoyés vers l'écran (forme masquée `discord.com/api/webhooks/1234…/••••`). Champ vide = garder le lien enregistré. Un ancien lien en clair reste lu et est chiffré au premier enregistrement ; les anciens « Autre salon » sont repris dans la liste des salons chiffrés.
- Un seul écran règle le salon : les Paramètres renvoient vers Intégrations › Discord et n'écrivent plus le lien.
- `DiscordChannelInput` n'accepte plus que `https` (domaines Discord uniquement, comme avant).

## Fichiers touchés

`app/Services/Integrations/DiscordWebhookDirectory.php`, `app/Services/Integrations/DiscordEventRelayService.php`, `app/Services/Sse/SseTransmissionDiscordService.php`, `app/Support/DiscordChannelInput.php`, `app/Controllers/Admin/Organization/OrganizationIntegrationsController.php`, `app/Controllers/Admin/Organization/OrganizationSettingsController.php`, `views/admin/organization/integrations.php`, `views/admin/organization/settings.php`.

## Vérification

`tests/Unit/DiscordWebhookDirectoryTest.php` (aucun lien en clair dans les réglages ni dans la liste, champ vide conservé, ancien lien relu puis chiffré, http et domaines étrangers refusés) ; rendu de la page sans lien en clair.

Les relais du journal des transmissions terrain (SSE) gardent leur propre liste, déjà masquée à l'écran mais pas encore chiffrée en base.

## Statut

Corrigé.
