<?php declare(strict_types=1); ?>
<section id="passage-fichiers" class="site-docs__section">
    <h2>Passage aux fichiers</h2>
    <p class="site-docs__lead">
        Le terminal interroge les fichiers de la communauté comme un terminal de patrouille : personne, véhicule, document,
        relevé biométrique, téléphone saisi. Tout est fictif : seules les données du jeu et du registre sont lues.
    </p>
    <h3>Faire une requête</h3>
    <ul>
        <li>Une seule case suffit : nom, alias, surnom, numéro de papier, plaque, téléphone, date de naissance ou référence « IDT-12 ».</li>
        <li>L’orthographe approchante rapproche les noms qui se prononcent pareil (« Hadad » trouve HADDAD).</li>
        <li>Le signalement seul marche aussi : taille en cm ou en pieds-pouces (« 5'10" »), poids, âge, cheveux, yeux, signes.</li>
        <li>Chaque requête porte un motif (ex. « CS » contrôle sur la voie publique) et entre au journal.</li>
    </ul>
    <h3>Lire la réponse</h3>
    <ul>
        <li><strong>NO RECORD</strong> (ou NÉANT, KEIN TREFFER selon le pays du terminal) : aucune inscription. Cela ne prouve pas l’identité.</li>
        <li><strong>Possible match</strong> : correspondance approchante, à vérifier avant toute action.</li>
        <li><strong>HIT</strong> : une mention de recherche est active ; confirmez auprès du service d’origine.</li>
        <li>La fiche montre en tête les mises en garde (armé, sécurité des intervenants), puis « Déjà contrôlé il y a… ».</li>
    </ul>
    <h3>Recoupements et surveillances</h3>
    <p>
        La fiche propose les documents qui citent le sujet : comptes rendus, fiches de renseignement, contrôles,
        extractions de téléphones. Rattachez ce qui le concerne ; « Ce n’est pas lui » écarte définitivement une source.
        Surveillez un nom ou un numéro pour être prévenu quand un nouveau document le cite.
    </p>
    <h3>Photothèque</h3>
    <p>
        Filtrez les photos signalétiques par signalement et comparez deux fiches côte à côte. Le site ne fait aucune
        reconnaissance faciale : la comparaison se fait à l’œil.
    </p>
</section>

<section id="telephones-jeu" class="site-docs__section">
    <h2>Téléphones du jeu</h2>
    <p class="site-docs__lead">
        Un joueur peut envoyer au site les données du téléphone ATAK de son personnage, ou celles d’un téléphone saisi
        sur une IA. Le site en fait un rapport d’exploitation : contacts, messages, notes, photos, positions, lieux.
    </p>
    <ul>
        <li>Désactivé par défaut : le joueur l’autorise dans les réglages du téléphone, et peut retirer son accord sur le site.</li>
        <li>En jeu : menu ACE › Téléphone › « Synchroniser avec le web ». Le MJ peut l’interdire pour une mission.</li>
        <li>Seules les données du jeu partent, jamais celles du vrai téléphone du joueur.</li>
        <li>Les numéros connus aux fichiers sont reconnus ; l’extraction se rattache à une fiche.</li>
    </ul>
</section>

<section id="lore-session" class="site-docs__section">
    <h2>Préparer une session : lore et synchro Athena</h2>
    <p class="site-docs__lead">
        Le MJ prépare sur le site les personnes d’une mission et décide ce que les fichiers en savent. Les IA du jeu
        remontent au site, le site redescend en jeu ce qui a été décidé.
    </p>
    <h3>1. Créer la session</h3>
    <p>
        Fichiers et contrôles › Lore des sessions › Nouvelle session. Le code d’opération (ex. « OP-2611-07 ») doit être
        le même que dans le module SSE de la mission : c’est lui qui relie le jeu à la session.
    </p>
    <h3>2. Remonter les IA</h3>
    <p>
        Dans Eden ou Zeus, module SSE « Synchroniser les IA avec Athena » : toutes les IA, un groupe ou une sélection.
        Chaque IA garde le même identifiant d’une session à l’autre. Une IA déjà connue n’écrase jamais ce que le MJ a
        écrit sur le site : seules les données vides sont complétées.
    </p>
    <h3>3. Décider ce qui est connu</h3>
    <ul>
        <li>Chaque donnée est « connue des fichiers » (lue au passage aux fichiers, au SEEK II, sur l’ATAK) ou « cachée (MJ) ».</li>
        <li>Une phase de révélation retarde une donnée : visible quand la session passe en phase 2, par exemple.</li>
        <li>« Inscrite aux fichiers » décochée : la personne existe pour le MJ, le terminal répond « aucune inscription ».</li>
        <li>Ce que la personne sait et les réponses de l’IA servent au jeu ; le caché ne part qu’au compte qui a le droit « Préparer le lore des sessions ».</li>
        <li>Liez les personnes entre elles (famille, associé, cellule), ajoutez adresses, véhicules et téléphones.</li>
    </ul>
    <h3>4. Redescendre en jeu</h3>
    <p>
        Module « Récupérer depuis Athena » (ou rafraîchissement automatique, désactivé par défaut). Le serveur pose les
        données connues sur chaque IA ; une IA créée après la synchronisation est rattachée par son identifiant. La date
        de la dernière récupération s’affiche dans le module et sur la page de la session.
    </p>
    <h3>5. Pendant la mission</h3>
    <p>
        Verrouillez la session : ni le jeu ni le site ne modifient plus les personnes, seule la phase change. Export et
        import JSON ou CSV pour reprendre une session d’une campagne à l’autre ; l’historique garde chaque modification.
    </p>
</section>
