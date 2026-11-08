# Charge inconnue en erreur 422 et tickets sans cause

- **Contexte** : ticket #936 sur `POST /api/atak/explosive-timers`, et ticket #835 « Session refusée par le poste ».
- **Symptôme** : une charge qui explose peut remonter « Enregistrement impossible » (422). Le titre d'un ticket ne dit pas s'il s'agit d'une panne, de la formule ou d'une absence de communauté.
- **Cause** : le dépôt rend `null` pour un statut autre que `armed` quand la charge n'a jamais été enregistrée, et le contrôleur traduit tout `null` en 422. Le diagnostic serveur existait, mais seulement dans le détail du ticket.
- **Correctif** : la fin d'une charge inconnue répond 200 avec `ignored: unknown_charge`. Un identifiant invalide garde le 422, avec un message précis. Le titre des nouveaux tickets commence par la cause quand elle est connue.
- **Fichiers touchés** : `AtakApiController.php`, `AtakExplosiveTimerRepository.php`, `AtakModReportCause.php`, `AtakModReportDiscordTicketService.php`.
- **Vérification** : contrôle de syntaxe, puis tests en CI. Reste à comprendre pourquoi l'armement de cette charge n'était pas enregistré : le ticket ne le dit pas.
- **Statut** : corrigé, à vérifier en production.
