# Séries sans fin et réponses Discord / Endless series and Discord RSVPs

## Français

- Appliquer les migrations (`php run-migrations.php`). La tâche `event_automation` est enregistrée dans le planificateur existant et tourne toutes les cinq minutes.
- « Sans fin » conserve une règle persistante et maintient douze créneaux à venir. Chaque création respecte le quota de la formule. Quand il est atteint, la génération reprend à un passage ultérieur disposant de quota. Les créneaux manqués après une interruption ne sont pas créés rétroactivement.
- « Arrêter les prochaines générations » conserve les événements existants. Annuler une série arrête aussi la génération et annule les occurrences suivantes.
- Dans Événements → Configuration Discord, ouvrir la gestion des webhooks pour ajouter, retirer ou tester un salon. Choisir ce salon dans le formulaire d’événement. Une annonce est publiée par occurrence dans les trente jours précédant son début ; la fiche permet de la publier immédiatement. Le salon d’un message publié est verrouillé.
- Pour lire les trois réactions ✅ présent, ❌ absent, ❓ peut-être, installer un bot dans le salon avec Voir le salon, Voir les anciens messages et Ajouter des réactions. Enregistrer son jeton dans la configuration des événements ; il est chiffré au repos et n’est jamais réaffiché. Un webhook seul ne peut pas lire les réactions.
- Les membres doivent relier leur compte Discord à Athena (`/discord/roles-lies`) et choisir un seul emoji. Seuls les membres de la communauté liés par OAuth sont importés. Plusieurs choix sont ignorés jusqu’à correction. Les bots sont ignorés.
- Une réaction inchangée ne remplace pas une correction sur Athena. Un choix différent est importé au passage suivant. Retirer sa réaction supprime uniquement une réponse encore identique à la dernière importation Discord ; les corrections intervenues depuis sont conservées.
- La synchronisation porte sur l’intention de participation (RSVP), jamais sur le pointage effectif. Les événements annulés ne reçoivent plus de réponses. Les messages déjà publiés restent dans Discord.
- Les erreurs d’autorisation, d’accès au webhook ou de limitation Discord apparaissent sur la fiche. Les messages sont traités par lots de dix, avec rotation. Un événement peut donc attendre plusieurs passages si la file contient de nombreux messages.

## English

- Apply migrations (`php run-migrations.php`). The existing scheduler runs `event_automation` every five minutes.
- “No end” persists a repeat rule and maintains twelve upcoming events, subject to plan quotas. Generation resumes when quota is available. Missed events are never backfilled after downtime.
- “Stop generating future events” keeps existing events. Cancelling a series also stops generation and cancels its following events.
- Manage webhook channels from Event Discord settings, then select a channel in the event form. Each occurrence is announced within thirty days of its start, or immediately from its event page. Published messages keep their original channel.
- Add a bot to the channel with View Channel, Read Message History and Add Reactions permissions. Save its token in event settings; it is encrypted at rest and never redisplayed. A webhook alone cannot read reactions.
- Members must link Discord to Athena (`/discord/roles-lies`) and choose exactly one of ✅ attending, ❌ not attending, ❓ maybe. Only verified OAuth links belonging to the community are imported. Conflicting choices and bots are ignored.
- Unchanged reactions never overwrite later website corrections. A changed reaction is imported on the next pass. Removing a reaction clears only a response still matching the last Discord import, preserving later website corrections.
- These responses are RSVPs, not actual attendance. Cancelled events stop syncing. Existing Discord messages remain visible.
- Delivery and permission errors appear on the event page. Ten messages are processed per pass with rotation; a large queue can take several passes.
