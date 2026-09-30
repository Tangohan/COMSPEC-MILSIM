[h1]COMSPEC Overwatch — Mise à jour 1.6.15[/h1]
[b]Publication : 30/09/2026[/b]
[b]Pack :[/b] Overwatch 1.6.15 · Opération Phoenix · Extension 2.0.56
[quote]
[b]Important :[/b] quittez Arma 3 complètement, mettez à jour le pack Workshop, puis relancez. Mettez aussi à jour le portail Athena (poste de commandement) : une grande partie des nouveautés s’affiche côté poste. Sur serveur dédié, rechargez le mod côté serveur.
[/quote]

[h2]Nouveau — Liaison terrain plus calme et plus priorisée[/h2]
Les informations remontées depuis le jeu (positions, états, alertes) partent désormais par lots ordonnés. Les urgences passent en premier ; le reste ne se répète plus si rien n’a changé. Le poste continue d’afficher les opérateurs comme avant.

[list]
[*] les situations critiques (blessé, perte de liaison) sont envoyées avant le reste ;
[*] une position ou un état inchangé n’est plus renvoyé en boucle ;
[*] si le poste n’accepte pas encore ce mode, la liaison reprend seule l’ancien envoi unitaire.
[/list]

[h2]Nouveau — Alertes santé structurées[/h2]
Lorsqu’un opérateur tombe inconscient, entre en arrêt cardiaque ou sort du combat, le poste reçoit une alerte santé dédiée, sans saturer le canal radio général.

[list]
[*] l’alerte apparaît dans l’espace Mission du poste, avec l’indicatif et le type de situation ;
[*] le commandement peut indiquer le statut de secours (à secourir, en cours, traité, hors combat, annulé) ;
[*] un bandeau discret signale les urgences sur l’onglet Mission ;
[*] le CASEVAC manuel reste disponible à côté, pour les évacuations préparées depuis le poste.
[/list]

[h2]Nouveau — Embarquement, débarquement et échanges de tirs[/h2]
Les changements d’état utiles au poste remontent désormais dans le journal de mission, sans noyer le fil Ordre.

[list]
[*] embarquement et débarquement d’un véhicule apparaissent clairement ;
[*] les échanges de tirs significatifs sont journalisés ;
[*] la cadence de position s’adapte à la vitesse (à pied, véhicule, aéronef) pour rester lisible.
[/list]

[h2]Nouveau — Bilan logistique automatique[/h2]
Le poste reçoit un bilan périodique du véhicule ou de l’opérateur (carburant, munitions, équipage, places libres), sans saisie manuelle en mission.

[list]
[*] le bilan se met à jour à l’embarquement, puis régulièrement pendant la mission ;
[*] l’espace Mission liste les besoins de soutien (carburant ou munitions bas / critiques) ;
[*] le poste peut enregistrer une demande de ravitaillement pour un indicatif donné.
[/list]

[h2]Nouveau — Journal des émissions radio[/h2]
Les émissions radio (début, fin, fréquence ou canal, durée) sont journalisées côté poste. Aucune voix n’est enregistrée.

[list]
[*] l’espace Radio conserve la proximité des opérateurs qui émettent en direct ;
[*] un historique des émissions s’ajoute en dessous, pour suivre qui a passé du trafic et quand ;
[*] utile pour reconstituer une séquence radio après action, sans rejouer l’audio.
[/list]

[h2]Nouveau — Pistes d’observation sur la carte[/h2]
Les comptes rendus terrain créent désormais des pistes distinctes du suivi allié. Le poste voit clairement ce qui est observé, et ce qui a été confirmé.

[list]
[*] un SALUTE, une note de reconnaissance ou un bilan des dégâts crée une piste d’observation ;
[*] les signalements radio croisés peuvent faire apparaître une piste d’émetteur probable ;
[*] deux calques : Observations terrain et Évaluations confirmées (séparés des trajectoires alliées) ;
[*] un bilan des dégâts reste candidat jusqu’à confirmation humaine — jamais marqué « détruit » automatiquement ;
[*] depuis le panneau, le poste confirme ou écarte une piste, et choisit le niveau observé pour un bilan des dégâts.
[/list]

[h2]Nouveau — Journal de mission filtrable[/h2]
Le journal (menu Plus) se lit par thème, pour retrouver vite ce qui compte.

[list]
[*] filtres : sanitaire, contact armé, soutien, radio, unités, renseignement ;
[*] un clic sur une entrée localisée peut recentrer la carte lorsque la position est connue.
[/list]

[h2]Amélioration — Suivi aérien[/h2]
Les aéronefs suivis affichent davantage d’état utile au poste (vitesse, dégâts, moteur), en complément du manifeste et des demandes JTAC déjà présents dans l’espace Air.

[h2]Après installation[/h2]
[list]
[*] mettez à jour Overwatch (Workshop) et le portail Athena ;
[*] quittez complètement Arma 3, puis relancez ;
[*] vérifiez en jeu : Overwatch 1.6.15 · Extension 2.0.56 ;
[*] sur le poste : Ctrl+F5, ouvrez Mission (santé / soutien), Radio (historique), Calques (observations), Plus → Journal.
[/list]

[b]Versions à vérifier :[/b] Overwatch 1.6.15 · Extension 2.0.56 · portail Athena à jour · Opération Phoenix
