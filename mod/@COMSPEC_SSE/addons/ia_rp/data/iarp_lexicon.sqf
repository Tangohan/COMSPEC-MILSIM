/*
    Lexique de l'interprète d'intentions (fn_iarpIntent) : [intention, poids, [motifs…]].
    Motifs écrits comme le texte normalisé (fn_iarpNorm) : minuscules, sans accents, ponctuation et apostrophes en
    espaces. Une espace en début ou en fin de motif = limite de mot (« d ou », « hi » seul et pas dans « chips »).
    Score d'une intention : somme des longueurs des motifs trouvés × poids ; la plus haute gagne, à égalité l'ordre
    ci-dessous. Une mission peut ajouter des motifs (ou de nouvelles intentions) avec un fichier iarp_lexicon.sqf à sa
    racine, même format : les motifs d'une intention connue s'ajoutent aux siens.
    Exemple : [["recette", 1, [" la recette", " cuisine "]]] et des phrases « recette » dans iarp_bank.sqf.
    Fautes de frappe (fn_iarpIntent) : un mot de 5 lettres et plus absent du lexique est rapproché d'un mot des motifs
    (mêmes deux premières lettres, une lettre de différence, deux à partir de 8 lettres, lettres doublées ou manquantes).
    « _keep » : mots courants jamais corrigés, parce qu'ils ressemblent trop à un mot du lexique (« ferme » / « femme »).
    Une mission peut en ajouter de la même façon : [["_keep", 0, ["fermier"]]].
*/
[
    ["_keep", 0, ["ferme", "fermes", "plage", "plages", "mairie", "truck", "trucks", "great", "green", "parlez", "sauvez", "demandez",
        "fautes", "faute", "conforme", "porte", "portes", "portee", "armee", "armees", "emploi", "bless",
        "souffre", "offre", "chiffre", "cartes", "plaque", "plaques", "soldat", "soldats", "pompiers", "letter", "later", "better",
        "fellow", "places", "lines", "treat", "sweet", "dormir", "rester", "venue", "ville", "vielle", "vieille", "visage", "voyage"]],
    // --- Questions de légende (réponses tirées de la couverture de l'IA)
    ["origin", 1, [" d ou venez", " d ou vous venez", " vous venez d ou", " venez d ou", " d ou etes vous", " vous etes d ou", " vous etes d ici", " originaire", " vous habitez ou", " ou habitez", " ou vivez", " where are you from", " where do you come from", " where you from", " where do you live", " are you from here", " d ou tu viens", " tu viens d ou", " t habites ou"]],
    ["employer", 1, [" pour qui travaillez", " pour qui vous travaillez", " qui vous emploie", " votre employeur", " votre patron", " pour qui tu bosses", " pour qui tu travailles", " who do you work for", " who is your employer", " your employer", " your boss", " qui est votre chef"]],
    ["job", 1, [" votre metier", " quel metier", " que faites vous dans la vie", " vous faites quoi", " votre travail", " votre profession", " vous travaillez dans", " tu fais quoi", " what do you do", " your job", " what is your job", " your profession", " for a living"]],
    ["travel", 1, [" raison du deplacement", " raison de votre", " motif ", " pourquoi etes vous ici", " pourquoi vous etes la", " qu est ce que vous faites ici", " que faites vous ici", " vous faites quoi ici", " ou allez vous", " vous allez ou", " ou vous allez", " tu vas ou", " what are you doing here", " why are you here", " where are you going", " where are you headed", " reason for", " purpose of"]],
    ["pob", 1, [" ou etes vous ne", " vous etes ne ou", " lieu de naissance", " ne ou ", " where were you born", " place of birth", " birthplace", " tu es ne ou"]],
    ["contact", 1, [" qui peut confirmer", " qui peut le confirmer", " quelqu un peut confirmer", " un temoin", " qui peut se porter garant", " qui peut temoigner", " who can confirm", " anyone can confirm", " can anyone vouch", " who can vouch"]],
    ["address", 1, [" votre adresse", " quelle adresse", " vous habitez a quelle", " your address", " what is your address", " ou se trouve votre maison", " ta maison"]],
    ["name", 1, [" comment vous appelez", " comment tu t appelles", " votre nom", " quel est votre nom", " vous vous appelez comment", " vous etes qui", " qui etes vous", " what is your name", " what s your name", " your name", " who are you", " ton nom "]],
    ["age", 1, [" quel age", " votre age", " vous avez quel age", " how old", " your age", " ne en quelle annee", " date de naissance", " date of birth"]],
    ["family", 1, [" votre famille", " vous etes marie", " des enfants", " vos enfants", " votre femme", " votre mari", " your family", " are you married", " any children", " your wife", " your husband", " ta famille"]],
    // --- Papiers et fouille : l'action ACE habituelle (barre de progression) prend le relais
    ["papers", 1, [" papiers", " vos papiers", " pieces d identite", " piece d identite", " carte d identite", " passeport", " documents s il", " vos documents", " papers", " your papers", " identification", " your id ", " id please", " show me your id", " license"]],
    ["search", 1, [" je vais vous fouiller", " on va vous fouiller", " fouille", " fouiller", " palpation", " videz vos poches", " vider vos poches", " search you", " empty your pockets", " pat you down", " frisk"]],
    // --- Ordres simples
    ["handsup", 1.2, [" mains en l air", " les mains en l air", " haut les mains", " levez les mains", " leve les mains", " mains sur la tete", " montrez vos mains", " hands up", " put your hands up", " hands in the air", " show me your hands", " hands on your head"]],
    ["follow", 1.2, [" suivez moi", " venez avec moi", " suis moi", " viens avec moi", " accompagnez moi", " follow me", " come with me", " this way"]],
    ["halt", 1.2, [" stop ", " arretez vous", " arrete toi", " halte", " ne bougez plus", " bougez pas", " on ne bouge plus", " restez ici", " restez la", " attendez ici", " freeze", " don t move", " stay here", " wait here", " halt "]],
    ["leave", 1.1, [" circulez", " vous pouvez partir", " vous pouvez y aller", " allez y ", " degagez", " partez", " rentrez chez vous", " c est bon pour moi", " you can go", " move along", " get lost", " go home", " you are free to go"]],
    ["kneel", 1.2, [" a genoux", " mettez vous a genoux", " couchez vous", " a terre", " au sol", " allongez vous", " par terre", " get down", " on your knees", " kneel", " lie down", " on the ground"]],
    ["dismount", 1.2, [" descendez du vehicule", " descendez de la voiture", " sortez du vehicule", " sortez de la voiture", " descendez ", " sortez ", " get out of the car", " step out", " get out of the vehicle", " out of the car"]],
    ["trunk", 1.2, [" ouvrez le coffre", " ouvre le coffre", " le coffre", " ouvrez la portiere", " ouvrez le vehicule", " open the trunk", " open the boot", " open the car", " open the door", " the trunk"]],
    // --- Pression, argent, renseignement
    ["threat", 1.1, [" je vais vous tuer", " je vais te tuer", " je vais tirer", " je tire", " vous allez le regretter", " sinon je", " ou je tire", " je vous arrete", " en prison", " vous allez payer", " je vais vous buter", " i will shoot", " i ll shoot", " i will kill you", " or else", " you ll regret", " you are under arrest", " i ll arrest you", " to jail", " last warning", " dernier avertissement"]],
    ["bribe", 1.1, [" argent", " billet", " dollars", " euros", " pot de vin", " un petit cadeau", " payer ", " je vous paie", " je te paie", " combien pour", " money", " cash", " bribe", " i ll pay", " i can pay", " how much for", " a little gift"]],
    ["intel", 1, [" que savez vous", " vous savez quelque chose", " avez vous vu", " vous avez vu", " des insurges", " des rebelles", " des combattants", " des hommes armes", " des etrangers", " quelque chose de suspect", " du nouveau", " des informations", " un renseignement", " what do you know", " have you seen", " did you see", " insurgents", " rebels", " armed men", " anything suspicious", " any information", " strangers"]],
    ["weapons", 1.1, [" vous etes arme", " etes pas arme", " pas d arme", " avez vous des armes", " vous avez une arme", " des armes sur vous", " une arme", " are you armed", " do you have a weapon", " any weapons", " carrying a weapon"]],
    ["calm", 1, [" calmez vous", " du calme", " on ne vous veut pas de mal", " pas de panique", " tout va bien", " n ayez pas peur", " rassurez vous", " detendez vous", " calm down", " relax", " we mean no harm", " don t be afraid", " take it easy", " it s okay", " it s ok "]],
    ["help", 1, [" besoin d aide", " vous avez besoin", " on peut vous aider", " je peux vous aider", " vous etes blesse", " ca va aller", " need help", " can we help", " can i help", " are you hurt", " are you injured"]],
    // --- Politesse et conversation
    ["greet", 0.6, [" bonjour", " bonsoir", " salut", " salam", " salaam", " coucou", " hello", " good morning", " good evening", " hey ", " hi ", " yo "]],
    ["bye", 0.7, [" au revoir", " bonne journee", " bonne soiree", " a plus", " adieu", " goodbye", " bye ", " see you", " have a nice day"]],
    ["thanks", 0.7, [" merci", " je vous remercie", " thank you", " thanks", " cheers"]],
    ["sorry", 0.7, [" pardon", " desole", " excusez moi", " excuse me", " sorry", " my apologies"]],
    ["howareyou", 0.8, [" comment allez vous", " comment ca va", " ca va ", " vous allez bien", " how are you", " how s it going", " you okay", " are you ok"]],
    ["yes", 0.5, [" oui ", " d accord", " ok ", " bien sur", " exact", " yes ", " yeah", " sure ", " right "]],
    ["no", 0.5, [" non ", " pas du tout", " jamais", " no ", " nope", " never "]]
]
