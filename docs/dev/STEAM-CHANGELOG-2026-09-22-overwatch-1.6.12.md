[h1]COMSPEC Overwatch — Mise à jour 1.6.12[/h1]
[b]Publication : 22/09/2026[/b]
[b]Pack :[/b] Overwatch 1.6.12 · Athena 1.0.166 · Extension 2.0.51
[quote]
[b]Important :[/b] quittez Arma 3 complètement (pas seulement le lobby), mettez à jour le pack Workshop, puis relancez. Sur serveur dédié, rechargez aussi le mod côté serveur.
[/quote]

[h2]Correction — Position qui disparaît du poste[/h2]
Certains opérateurs restaient visibles en jeu avec une liaison affichée comme correcte, alors que le poste les voyait apparaître et disparaître. Il fallait souvent forcer une resynchronisation.

[list]
[*] La position est maintenue plus longtemps côté poste lorsqu’elle arrive un peu plus lentement ;
[*] si le téléphone s’ouvre tard ou après un retour en jeu, les échanges de position repartent seuls ;
[*] la resynchronisation force vraiment la reprise, y compris quand le dernier envoi semblait encore bon ;
[*] le bandeau de liaison n’affiche plus « OK » lorsque les données ne peuvent pas partir.
[/list]

[h2]Nouveau — Charge mission portée par le serveur[/h2]
Sur le serveur dédié (ou l’hôte), une partie du travail commun à toute la mission est regroupée pour éviter que chaque joueur refasse la même chose.

[list]
[*] Les relais ATAK sont synchronisés une fois pour la mission ;
[*] les zones et réglages roleplay du portail sont récupérés une fois, puis appliqués pour tout le monde ;
[*] les ordres de déplacement destinés aux IA alliées sont lus une fois côté serveur ;
[*] les repères placés par Zeus sont regroupés avant envoi au poste, avec une file anti-spam ;
[*] chaque opérateur continue d’envoyer sa propre position, son téléphone et son état médical comme avant.
[/list]

[h2]Réglage[/h2]
Dans les options CBA Overwatch : [b]Hub serveur (charge mission)[/b] (activé par défaut). Sur serveur dédié, renseignez l’adresse du portail et la clé de communauté dans les réglages du serveur.

[b]Versions à vérifier en jeu :[/b] Overwatch 1.6.12 · Athena 1.0.166 · Extension 2.0.51
