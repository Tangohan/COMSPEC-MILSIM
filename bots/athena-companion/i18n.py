TEXT = {
 'fr': {
  'linked':'ATHENA Companion est relié à votre communauté. Les événements seront publiés dans ce salon.',
  'invalid':'Le code est invalide ou expiré. Créez un nouveau code dans le back-office Athena.',
  'bound':'Ce serveur ou cette communauté est déjà relié. Déliez-le dans Athena avant de changer de serveur.',
  'failed':'Athena est indisponible ou le bot n’est pas encore configuré. Réessayez plus tard.',
  'permissions':'Il me faut les permissions Voir le salon, Envoyer des messages, Intégrer des liens, Voir les anciens messages et Ajouter des réactions.',
  'guild_only':'Utilisez cette commande dans un salon texte du serveur.',
  'admin_only':'Cette commande est réservée aux membres ayant la permission Gérer le serveur.',
  'help':'Reliez votre serveur avec /athena connect, le code du back-office et le salon de publication. Pour répondre aux événements : liez votre compte Discord à Athena puis choisissez un seul emoji : ✅ présent · ❌ absent · ❓ peut-être.',
  'start':'Début','location':'Lieu','cancelled':'Événement annulé','disabled':'Synchronisation désactivée',
  'vote':'✅ Présent · ❌ Absent · ❓ Peut-être\nChoisissez un seul emoji. Votre compte Discord doit être relié à Athena. Ces réponses ne valident pas le pointage effectif.',
  'open':'Ouvrir dans Athena','link':'Relier mon compte Discord',
 },
 'en': {
  'linked':'ATHENA Companion is connected to your community. Events will be published in this channel.',
  'invalid':'The code is invalid or expired. Generate a new code in the Athena back office.',
  'bound':'This server or community is already connected. Unlink it in Athena before changing servers.',
  'failed':'Athena is unavailable or the bot is not configured yet. Please try again later.',
  'permissions':'I need View Channel, Send Messages, Embed Links, Read Message History and Add Reactions permissions.',
  'guild_only':'Use this command in a server text channel.',
  'admin_only':'This command requires the Manage Server permission.',
  'help':'Connect your server with /athena connect, the code from the back office and the publication channel. To RSVP: link Discord to Athena and choose one emoji: ✅ attending · ❌ not attending · ❓ maybe.',
  'start':'Start','location':'Location','cancelled':'Event cancelled','disabled':'Sync disabled',
  'vote':'✅ Attending · ❌ Not attending · ❓ Maybe\nChoose one emoji. Your Discord account must be linked to Athena. These responses do not mark actual attendance.',
  'open':'Open in Athena','link':'Link my Discord account',
 }
}

def tr(key, locale='en'):
    return TEXT['fr' if str(locale).startswith('fr') else 'en'][key]
