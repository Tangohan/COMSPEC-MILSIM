# COMSPEC ATAK — Interface Web de Commandement Tactique

**Le système C2 temps réel pour Arma 3**

---

## C'est quoi COMSPEC ATAK ?

COMSPEC ATAK est l'interface web de commandement et contrôle de la plateforme Athena. Elle vous permet de suivre et coordonner vos opérations Arma 3 en temps réel depuis votre navigateur, que vous soyez en jeu ou non.

C'est le tableau de bord du commandant moderne : carte interactive, positions des unités, communications, ordres, intel, tout centralisé sur une seule interface.

---

## Pour qui ?

**Commandement hors-jeu**
Staff, S3 ops, commandants de secteur. Vous suivez la mission depuis le web, envoyez des ordres, coordonnez les appuis, sans être dans Arma.

**JTAC**
Interface dédiée pour gérer vos appels CAS 9-Line, suivre les avions disponibles, leurs armements, et coordonner les frappes en temps réel.

**Médecins**
Réception des alertes médicales depuis le terrain, suivi de l'état des blessés, coordination des évacuations.

**RTO et liaisons**
Journal complet de toutes les communications et événements. Gestion du réseau radio structuré.

**Opérateurs terrain**
Accès mobile pendant les pauses pour consulter la carte, voir les positions des sections, envoyer de l'intel.

**Planification**
Avant mission, utilisez ATAK pour briefer, placer des marqueurs de planification, définir les objectifs.

---

## Interface principale

### En-tête

Toujours visible en haut : logo COMSPEC avec lien vers le portail, heure Zulu synchronisée, votre indicatif et statut de connexion, boutons pour accéder à votre compte, configuration, état de santé et aide.

### Barre métrique

Juste sous l'en-tête : compteurs temps réel pour unités connectées, caméras actives, marqueurs déployés, intel reçues, pings actifs, ordres en cours, alertes médicales, avions disponibles. Un coup d'œil pour savoir où vous en êtes.

### Carte centrale

Occupe tout l'espace central. Carte Leaflet interactive avec fonds adaptés à chaque terrain Arma. Superpositions pour unités amies, ennemis, caméras, marqueurs, pings, désignateurs laser, détections SIGINT, avions, hélicoptères. Grille militaire affichée dynamiquement selon le zoom. Contrôles de zoom, déplacement, sélection de couches.

### Panneau gauche : Informations et Visualisation

Onglets pour caméras terrain, marqueurs collaboratifs, suivi des avions et hélicoptères, état de santé des unités.

### Panneau droit : Communication et Action

Onglets pour chat temps réel, ordres formels, alertes médicales, gestion radio, pings visuels, interface JTAC, journal de liaison complet.

---

## Fonctionnalités détaillées

### Suivi des unités

Positions temps réel de tous les joueurs connectés et équipés. Icônes différenciées par rôle : chef de section, grenadier, médecin, RTO, JTAC, pilote. Code couleur selon la faction et le groupe. Clic sur une unité pour voir ses détails : nom, indicatif, rôle, faction, groupe, dernière position, dernière mise à jour. Filtrage par groupe, faction, rôle pour désengorger la carte.

### Caméras et observation

Liste des caméras déployées sur le terrain par les opérateurs. Chaque caméra affiche son nom, position, orientation, champ de vision. Cone de vision représenté sur la carte pour voir la zone observée. Activation et désactivation des couches caméra selon besoin. Utile pour surveillance de zones critiques, surveillance d'axes d'approche, couverture de flancs.

### Marqueurs collaboratifs

Création de marqueurs depuis le web, visibles en jeu par les joueurs équipés. Types de marqueurs : objectif, danger, zone sécurisée, RDV, ennemi signalé, position clé. Chaque marqueur avec description, auteur, horodatage. Édition et suppression selon permissions. Synchronisation bidirectionnelle : marqueurs créés en jeu visibles sur le web, et inversement.

### Chat et messagerie

Canal de chat temps réel entre tous les utilisateurs ATAK connectés. Messages horodatés avec indicatif de l'auteur. Notifications sonores pour nouveaux messages. Historique persistant pour retrouver les conversations. Permet coordination rapide sans passer par la radio in-game. Utile pour ordres écrits, partage d'intel, coordination inter-sections.

### Système d'ordres formels

Envoi d'ordres structurés depuis le web vers les joueurs en jeu. Chaque ordre avec type, contenu, cible, urgence, auteur, horodatage. Notification in-game pour les joueurs concernés : popup, son, entrée dans leur tablette. Liste des ordres en cours avec statut : envoyé, acquitté, exécuté. Traçabilité complète pour audit. Types d'ordres : mouvement vers position, engagement cible, repli, attente, demande intel, changement de fréquence radio.

### Alertes médicales et TCCC

Réception automatique des alertes médicales depuis le terrain. Détails du blessé : indicatif, position, gravité, type de blessure, soins déjà administrés. État temps réel : conscient, inconscient, stabilisé, en EVAC. Affichage sur la carte avec icône spécifique rouge. Permet au commandement de prioriser les EVAC, coordonner les médecins, ajuster le plan selon les pertes.

### Pings visuels et sonores

Système de pings pour attirer l'attention sur une zone précise de la carte. Ping visuel : cercle animé sur la carte, visible par tous. Ping sonore : notification audio pour tous les connectés. Durée configurable avant disparition automatique. Utile pour signaler contact ennemi immédiat, demander appui sur zone précise, coordonner mouvement synchronisé.

### Interface JTAC et appels CAS

Module dédié aux contrôleurs aériens avancés. Liste des avions et hélicoptères disponibles avec indicatif, type, armement embarqué, statut, dernière position. Gestion des appels CAS 9-Line depuis le web ou le terrain. Chaque appel avec les neuf lignes réglementaires : coordonnées cible, élévation, description, type marquage, position amis, egress, remarques, danger proximité, ToT. Visualisation de l'appel sur la carte avec zones de danger. Suivi du statut : demandé, approuvé, en route, en attaque, terminé. Permet coordination précise des frappes, évite les erreurs de communication, assure la sécurité des troupes amies.

### Suivi des avions et armements

Positions temps réel des avions et hélicoptères en vol. Détails pour chaque appareil : indicatif, type, armement restant, carburant, statut mission. Visualisation des trajectoires d'approche et de sortie. Permet au commandement et aux JTAC de savoir qui est disponible, quel armement reste, combien de temps avant retour base.

### Désignateurs laser

Affichage des désignations laser actives sur la carte. Chaque désignateur avec code laser, position, orientation, durée restante. Permet coordination entre JTAC au sol et pilotes pour frappes de précision guidées laser. Évite les erreurs de code laser et les frappes sur mauvaise cible.

### Détections SIGINT

Affichage des détections de signaux électromagnétiques ennemis. Chaque détection avec position estimée, type de signal, puissance, horodatage. Permet de localiser les communications ennemies, identifier les postes de commandement adverses, planifier les frappes prioritaires.

### Journal de liaison complet

Enregistrement chronologique de tous les événements importants. Connexions et déconnexions, marqueurs créés, ordres envoyés, alertes médicales, appels CAS, détections ennemies, changements de statut. Chaque entrée horodatée avec auteur et détails. Filtres par type, période, auteur. Export possible pour archivage et AAR. Permet reconstitution complète de la mission, audit des actions, amélioration continue via RETEX.

---

## Configuration et personnalisation

### Gestion multi-contexte

ATAK supporte plusieurs contextes opérationnels simultanés. Chaque contexte représente une mission ou un théâtre d'opérations distinct. Vous pouvez basculer entre contextes pour suivre plusieurs missions en parallèle. Données isolées par contexte : marqueurs, ordres, unités, intel. Utile pour unités gérant plusieurs serveurs Arma simultanés, entraînements parallèles aux opérations, préparation mission future tout en suivant la mission en cours.

### Paramètres de carte

Choix du fond de carte adapté au terrain Arma joué : Altis, Tanoa, Malden, Stratis, terrains communautaires. Affichage ou masquage des grilles militaires. Réglage de l'opacité des couches superposées. Centrage automatique sur les unités ou mode manuel. Zoom par défaut et limites de zoom.

### Notifications et sons

Activation ou désactivation des sons pour nouveaux messages, ordres reçus, alertes médicales, pings, détections ennemies. Réglage du volume général. Choix des types de notifications à recevoir. Permet adapter l'interface à votre rôle : un JTAC veut les alertes CAS, un médecin les alertes médicales, un commandant tout.

### Permissions granulaires

Selon votre rôle dans Athena, vous avez accès à certaines fonctionnalités. Lecture seule pour observateurs et stagiaires. Création de marqueurs pour opérateurs confirmés. Envoi d'ordres pour sous-officiers et officiers. Gestion des appels CAS pour JTAC certifiés. Administration complète pour staff. Permet sécuriser les actions critiques tout en donnant de l'autonomie aux rôles appropriés.

---

## Temps réel et synchronisation

### Mécanisme de polling

Actuellement, ATAK utilise du polling HTTP pour récupérer les mises à jour. Requête envoyée toutes les quelques secondes vers le serveur. Le serveur répond avec les nouvelles données si disponibles. Latence typique de 2 à 5 secondes selon la configuration. Avantage : simple, compatible avec tous les navigateurs et infrastructures. Inconvénient : pas de vraie instantanéité, consommation bande passante.

### Migration vers WebSocket prévue

Pour améliorer le temps réel, migration vers WebSocket en cours de développement. Connexion permanente entre client et serveur. Serveur pousse les mises à jour instantanément aux clients. Latence réduite à quelques centaines de millisecondes. Avantage : vraie instantanéité, moins de bande passante. Inconvénient : complexité accrue, compatibilité réseau à vérifier.

### Gestion de la déconnexion

Si la connexion est perdue, ATAK affiche une alerte. Tentatives de reconnexion automatique toutes les 10 secondes. Une fois reconnecté, synchronisation automatique des données manquées. Permet de continuer la mission même en cas de coupure réseau temporaire.

---

## Ergonomie et expérience utilisateur

### Interface responsive

ATAK s'adapte automatiquement à la taille de l'écran. Bureau : panneaux latéraux larges, carte centrale spacieuse. Tablette : panneaux réductibles, interface optimisée tactile. Mobile : interface simplifiée, panneaux en overlay, carte plein écran. Permet utiliser ATAK depuis n'importe quel appareil.

### Thème sombre

Interface par défaut en thème sombre pour réduire la fatigue oculaire lors des longues missions. Contraste optimisé pour lisibilité dans toutes les conditions. Icônes et textes clairement visibles sur fond sombre.

### Raccourcis clavier

Accès rapide aux fonctions courantes via clavier. M pour créer un marqueur. C pour ouvrir le chat. O pour envoyer un ordre. J pour ouvrir le journal. Permet fluidité d'utilisation sans quitter le clavier.

### Sons et alertes

Chaque type d'événement a un son distinct. Message reçu : bip court. Ordre reçu : sonnerie radio. Alerte médicale : sirène urgence. Ping : clic sonore. Permet identifier la nature de l'événement sans regarder l'écran.

---

## Performance et optimisation

### Gestion des grosses missions

Pour les missions avec plus de 50 joueurs, ATAK optimise l'affichage. Clustering automatique des icônes proches selon le zoom. Chargement progressif des marqueurs et intel. Désactivation des couches non critiques par défaut. Permet fluidité même sur les très grandes opérations.

### Bande passante

Taille des données optimisée pour limiter la consommation réseau. Compression des positions et marqueurs. Envoi uniquement des changements, pas de tout le tableau à chaque fois. Permet utiliser ATAK même avec connexion modeste.

### Compatibilité navigateur

ATAK fonctionne sur tous les navigateurs modernes : Chrome, Firefox, Edge, Safari. Pas besoin de plugins ni d'extensions. Interface web standard HTML5, CSS3, JavaScript.

---

## Sécurité et accès

### Authentification

Connexion via compte Athena sécurisé. Authentification par identifiant et mot de passe. Support de l'authentification à deux facteurs pour les rôles critiques. Session sécurisée avec expiration automatique après inactivité.

### Isolation multi-tenant

Chaque communauté a son propre espace isolé. Vous ne voyez que les données de votre unité. Impossible d'accéder aux données d'autres communautés sur la même instance Athena. Garantit confidentialité et sécurité des opérations.

### Traçabilité

Toutes les actions critiques sont enregistrées avec auteur, horodatage, détails. Création de marqueurs, envoi d'ordres, appels CAS, modifications de configuration. Permet audit complet et responsabilisation des utilisateurs.

---

## Questions fréquentes

**Faut-il être en jeu pour utiliser ATAK ?**
Non. ATAK est accessible via navigateur web depuis n'importe où. Vous pouvez être hors jeu et suivre la mission, envoyer des ordres, coordonner les appuis.

**Les joueurs en jeu voient-ils ce que je fais sur ATAK ?**
Oui, de manière bidirectionnelle. Les marqueurs que vous créez sur ATAK apparaissent dans leur tablette in-game. Les ordres que vous envoyez leur sont notifiés. Inversement, ce qu'ils font en jeu apparaît sur ATAK.

**Puis-je utiliser ATAK sur mobile ?**
Oui. L'interface est responsive et fonctionne sur smartphone et tablette. Fonctionnalités légèrement simplifiées pour s'adapter à l'écran, mais toutes les fonctions critiques sont disponibles.

**Quelle est la latence entre le jeu et ATAK ?**
Actuellement entre 2 et 5 secondes avec le système de polling. Migration vers WebSocket prévue pour descendre sous la seconde.

**Peut-on utiliser ATAK sans le mod Arma ?**
Non. ATAK affiche les données envoyées par le mod COMSPEC Overwatch depuis Arma. Sans le mod, pas de données à afficher. Mod et web sont complémentaires.

**Combien coûte ATAK ?**
ATAK fait partie de la plateforme COMSPEC Athena. Contactez-nous pour connaître les modalités d'accès.

**Faut-il installer quelque chose ?**
Non côté ATAK web. Vous avez juste besoin d'un navigateur. Côté Arma, il faut installer le mod COMSPEC Overwatch via Steam Workshop.

**ATAK fonctionne-t-il avec tous les terrains Arma ?**
Oui. ATAK supporte tous les terrains officiels et communautaires d'Arma 3. La carte web s'adapte automatiquement au terrain détecté.

**Peut-on enregistrer les missions pour les revoir après ?**
Le journal de liaison complet est persistant et exportable. Vous pouvez reconstituer toute la mission via les entrées horodatées. Replay visuel complet en développement.

**ATAK remplace-t-il la radio in-game ?**
Non. ATAK complète la radio, ne la remplace pas. La radio reste essentielle pour les communications tactiques immédiates. ATAK sert pour la coordination stratégique, les ordres formels, l'intel structurée.

---

## Roadmap et évolutions prévues

### Court terme : amélioration du temps réel

Migration vers WebSocket pour latence sous la seconde. Notifications push instantanées pour tous les événements. Amélioration de la stabilité de connexion.

### Moyen terme : enrichissement fonctionnel

Replay complet des missions avec timeline interactive. Module météo avec impact sur les opérations. Gestion avancée des fréquences radio et réseau. Intégration des drones et reconnaissance aérienne. Support de la logistique avec suivi des ravitaillements.

### Long terme : intelligence et automatisation

Analyse automatique des AAR avec IA détectant les écarts doctrine. Suggestions tactiques basées sur l'historique des missions. Wargaming léger pour simulation avant mission. Doctrine versionnée avec SOP intégrées directement dans ATAK.

---

## Pour qui ATAK n'est PAS adapté ?

**Petits groupes jouant occasionnellement**
Si vous jouez à 5 sans structure ni commandement, ATAK est surdimensionné. Une simple radio Discord suffit.

**Unités cherchant juste une tablette in-game**
Si vous voulez juste consulter la carte en jeu sans coordination web, CTAB suffit.

**Groupes ne voulant pas investir dans la structure**
ATAK fait partie d'un écosystème complet. Si vous ne voulez pas gérer unité, formations, briefings, ATAK seul n'a pas de sens.

**Joueurs préférant le run and gun sans coordination**
Si vous détestez les briefings, les ordres formels, la discipline radio, ATAK sera une contrainte inutile.

---

## Pour qui ATAK est fait ?

**Unités structurées avec commandement dédié**
Staff hors-jeu suivant les missions, coordinateurs multi-sections, commandants de secteur.

**JTAC certifiés voulant gérer les CAS professionnellement**
Interface dédiée 9-Line, suivi des avions, coordination frappes.

**Médecins gérant les urgences et EVAC**
Alertes temps réel, suivi des blessés, priorisation EVAC.

**Unités valorisant la discipline et la coordination**
Ordres formels, traçabilité complète, communication structurée.

**Communautés cherchant l'amélioration continue**
Journal complet, RETEX structuré, mémoire institutionnelle.

**Groupes voulant du réalisme opérationnel MILSIM**
Inspiration ATAK militaire réel, procédures standardisées, rôles spécialisés.

---

## Message final

COMSPEC ATAK n'est pas juste une carte web. C'est le système nerveux central de votre unité pendant les opérations. La conscience situationnelle partagée entre le terrain et le commandement. L'outil qui transforme le chaos en coordination.

Comme la chouette d'Athéna qui voit tout dans la nuit, ATAK donne au commandement la vision omnisciente nécessaire pour guider ses troupes vers la victoire.

**La différence entre gagner et perdre, c'est souvent juste savoir où tout le monde se trouve.** 🦉

---

*COMSPEC Athena — Là où la stratégie rencontre la simulation*
