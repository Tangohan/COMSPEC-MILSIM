/*
    Banc d'essai de la couche hors sujet (fn_iarpSelfTest, rejoué hors jeu par tools\iarp_harness.py) : [[section, lignes]…]
      offtopic  [texte, attendu] : ce que la couche en fait (otSexual, otInsult, flirt, otPersonal, otWorld, absurd, otGibberish,
                unknown, small = small talk compris, followup) ; AUCUNE demande à effet (papiers, ordre, aveu, fuite, suivre)
      legit     [texte, intention] : questions et ordres légitimes, tutoiement, vouvoiement, formes courtes, SMS, fautes ;
                tous compris avec la bonne intention, aucun dans la couche hors sujet
      dialog    [[texte, réplique attendue]…] : échanges de 2 à 4 tours avec la même IA (relance, incompris, provocation)
*/
[
    ["offtopic", [
        ["Tu aimes le cul ?", "otSexual"], ["tu veux ma bite", "otSexual"], ["Montre-moi tes seins", "otSexual"], ["T'es bonne toi", "otSexual"], ["On couche ensemble ? Couche avec moi", "otSexual"],
        ["suce moi", "otSexual"], ["t'as un beau cul", "otSexual"], ["Mets-toi à poil", "otSexual"], ["tu regardes du porno ?", "otSexual"], ["show me your tits", "otSexual"],
        ["t'es con", "insult"], ["Espèce d'abruti", "insult"], ["connard", "insult"], ["sale con va", "insult"], ["Va te faire voir", "insult"],
        ["T'es qu'un idiot", "insult"], ["pauvre con", "insult"], ["fuck you", "insult"], ["Bouffon va", "insult"], ["nique ta race", "insult"],
        ["t'es débile ou quoi", "insult"], ["you're stupid", "insult"],
        ["fais-moi un câlin", "flirt"], ["Je t'aime", "flirt"], ["Un bisou ?", "flirt"], ["T'es célibataire ?", "flirt"], ["tu me plais", "flirt"], ["Embrasse-moi", "flirt"],
        ["combien tu pèses", "otPersonal"], ["Tu mesures combien ?", "otPersonal"], ["T'as une copine ?", "otPersonal"], ["Combien tu gagnes par mois ?", "otPersonal"], ["T'es puceau ?", "otPersonal"],
        ["où est Ben Laden", "otWorld"], ["Tu connais Obama ?", "otWorld"], ["Tu joues à Minecraft ?", "otWorld"], ["T'as Netflix ?", "otWorld"], ["Tu préfères Messi ou Ronaldo ?", "otWorld"],
        ["Tu as vu Star Wars ?", "otWorld"],
        ["donne-moi la lune", "absurd"], ["Tu crois aux licornes ?", "absurd"], ["Chante-moi une chanson", "absurd"], ["Donne-moi un million", "absurd"], ["Fais le poirier", "absurd"],
        ["Tu as des super pouvoirs ?", "absurd"], ["Tu crois aux extraterrestres ?", "absurd"],
        ["bla bla", "otGibberish"], ["azerty", "otGibberish"], ["qsdfgh", "otGibberish"], ["blablabla", "otGibberish"], ["hmmm", "otGibberish"], ["zzzz", "otGibberish"], ["123", "otGibberish"],
        ["???", "otGibberish"], ["lorem ipsum", "otGibberish"],
        ["Ma tante joue du piano le mardi", "unknown"], ["La table est en bois", "unknown"], ["Le chat dort sur le canapé", "unknown"],
        ["salut ça va", "small"], ["Il fait beau hein", "small"], ["Tu connais une blague ?", "small"], ["haha", "small"], ["Belle maison", "small"], ["Tu aimes le foot ?", "small"],
        ["Quelle heure est-il ?", "small"], ["T'es un robot ?", "small"],
        ["Et alors ?", "followup"], ["Pourquoi ?", "followup"], ["C'est tout ?", "followup"], ["Vraiment ?", "followup"], ["Explique.", "followup"], ["Et ensuite ?", "followup"],
        ["Comment ça ?", "followup"], ["Continue", "followup"]
    ]],
    ["legit", [
        // prénom, nom
        ["Quel est ton prénom ?", "name"], ["Ton prénom.", "name"], ["Ton prénom ?", "name"], ["Prénom ?", "name"], ["Ton nom ?", "name"], ["Nom ?", "name"], ["Votre nom ?", "name"],
        ["Quel est votre nom ?", "name"], ["Comment tu t'appelles ?", "name"], ["Tu t'appelles comment ?", "name"], ["Comment vous appelez-vous ?", "name"], ["kel est ton prenom", "name"],
        ["c koi ton nom", "name"], ["t'es qui toi ?", "name"], ["Dis-moi ton nom.", "name"], ["Ton nom de famille ?", "name"], ["Nom et prénom.", "name"], ["Ton prenon ?", "name"],
        ["Votre prénom, s'il vous plaît.", "name"], ["Vous vous appelez comment ?", "name"],
        // âge, naissance
        ["Âge ?", "age"], ["Ton âge ?", "age"], ["T'as quel âge ?", "age"], ["Tu as quel âge ?", "age"], ["Quel âge avez-vous ?", "age"], ["Vous avez quel âge ?", "age"],
        ["Ta date de naissance ?", "age"], ["Votre date de naissance ?", "age"], ["Tu es né quand ?", "age"], ["Quel age tu as", "age"],
        // nationalité
        ["Nationalité ?", "nation"], ["Ta nationalité ?", "nation"], ["Quelle est votre nationalité ?", "nation"], ["Tu es de quelle nationalité ?", "nation"],
        ["Tu viens de quel pays ?", "nation"], ["Vous êtes de quel pays ?", "nation"], ["T'es étranger ?", "nation"],
        // adresse
        ["Ton adresse ?", "address"], ["Votre adresse ?", "address"], ["Adresse ?", "address"], ["C'est quoi ton adresse ?", "address"], ["Quelle est votre adresse ?", "address"],
        // profession
        ["Ta profession ?", "job"], ["Profession ?", "job"], ["Ton métier ?", "job"], ["Tu fais quoi dans la vie ?", "job"], ["Vous faites quoi dans la vie ?", "job"],
        ["Quel est votre métier ?", "job"], ["Tu bosses où ?", "job"], ["Ton boulot ?", "job"], ["T'es quoi comme métier ?", "job"], ["Votre profession ?", "job"],
        // papiers
        ["Papiers.", "papers"], ["Tes papiers.", "papers"], ["Tes papiers", "papers"], ["Vos papiers.", "papers"], ["Montre tes papiers", "papers"], ["Montre-moi tes papiers.", "papers"],
        ["Ta carte d'identité", "papers"], ["Ta carte d'identité ?", "papers"], ["Vos papiers, s'il vous plaît.", "papers"], ["papiers stp", "papers"], ["Ton passeport.", "papers"],
        ["Sors tes papiers.", "papers"], ["Donne-moi tes papiers.", "papers"], ["Pièce d'identité ?", "papers"], ["Votre permis de conduire.", "papers"], ["vos papié svp", "papers"],
        // d'où il vient
        ["D'où tu viens", "origin"], ["D'où tu viens ?", "origin"], ["Tu viens d'où ?", "origin"], ["T'es d'où ?", "origin"], ["D'où venez-vous ?", "origin"], ["Vous venez d'où ?", "origin"],
        ["Tu habites où ?", "origin"], ["Vous habitez où ?", "origin"], ["T'es d'ici ?", "origin"], ["Tu viens de où ?", "origin"], ["dou tu viens", "origin"],
        // où il va, ce qu'il fait ici
        ["Où tu vas", "travel"], ["Où tu vas ?", "travel"], ["Tu vas où ?", "travel"], ["Où allez-vous ?", "travel"], ["Vous allez où ?", "travel"], ["Tu te rends où ?", "travel"],
        ["Ta destination ?", "travel"], ["Tu fais quoi ici ?", "travel"], ["Qu'est-ce que tu fais ici ?", "travel"], ["Que faites-vous ici ?", "travel"], ["Tu fous quoi là ?", "travel"],
        ["Pourquoi tu es là ?", "travel"], ["Vous faites quoi ici ?", "travel"],
        // avec qui
        ["T'es avec qui ?", "with"], ["Tu es avec qui ?", "with"], ["Avec qui tu es venu ?", "with"], ["Vous êtes seul ?", "with"], ["T'es seul ?", "with"], ["Avec qui ?", "with"],
        // a-t-il vu quelqu'un
        ["T'as vu quelqu'un ?", "intel"], ["Tu as vu quelqu'un ?", "intel"], ["Vous avez vu quelqu'un ?", "intel"], ["Vous avez vu quelque chose de suspect ?", "intel"],
        ["T'as vu quelque chose ?", "intel"], ["Tu as vu des hommes armés ?", "seenArmed"], ["Vous avez vu des étrangers ?", "strangers"],
        // armé
        ["T'es armé ?", "weapons"], ["Tu es armé ?", "weapons"], ["Vous êtes armé ?", "weapons"], ["Armé ?", "weapons"], ["T'as une arme ?", "weapons"], ["Tu portes une arme ?", "weapons"],
        ["Des armes sur toi ?", "weapons"],
        // ordres de base
        ["Partez.", "leave"], ["Circulez.", "leave"], ["Tu peux partir.", "leave"], ["Vous pouvez y aller.", "leave"], ["Avancez.", "comeHere"], ["Approche.", "comeHere"],
        ["Viens ici.", "comeHere"], ["Reculez !", "stepBack"], ["Recule.", "stepBack"], ["Stop !", "halt"], ["Ne bougez pas !", "halt"], ["Bouge pas.", "halt"],
        ["Mains en l'air !", "handsup"], ["Lève les mains !", "handsup"], ["Asseyez-vous.", "sit"], ["Assieds-toi.", "sit"], ["Suivez-moi.", "follow"], ["Suis-moi.", "follow"],
        ["À genoux !", "kneel"], ["Descends de la voiture.", "dismount"],
        // politesse et small talk comprises
        ["Bonjour.", "greet"], ["Salut !", "greet"], ["Ça va ?", "howareyou"], ["Merci.", "thanks"], ["Au revoir.", "bye"], ["Ta mère va bien ?", "family"]
    ]],
    ["dialog", [
        [["Partez.", "comply"], ["Et alors ?", "otMore.comply"]],
        [["Quel est ton prénom ?", "*"], ["Et alors ?", "otMore.cover"]],
        [["azerty", "otGibberish"], ["bla bla", "otMiss2"], ["qsdf", "otLost"], ["Ton nom ?", "*"]],
        [["Ma tante joue du piano le mardi", "otMiss1"], ["La table est en bois", "otMiss2"], ["Ton prénom.", "*"], ["Le chat dort sur le canapé", "otMiss1"]],
        [["t'es con", "otInsult"], ["connard", "otInsult2"], ["pauvre con", "otEnd"], ["D'où tu viens ?", "otCold"], ["Pardon.", "otApology"], ["D'où tu viens ?", "*"]],
        [["Tu aimes le cul ?", "otSexual"], ["tu veux ma bite", "otEnd"], ["Et alors ?", "otCold"]],
        [["Vous avez vu quelque chose de suspect ?", "intelNo"], ["Vraiment ?", "otMore.nothing"]],
        [["salut ça va", "*"], ["Il fait beau hein", "*+otSteer"]],
        [["où est Ben Laden", "otWorld"], ["Pourquoi ?", "otMore.nothing"]],
        [["Pourquoi ?", "otMore.none"]]
    ]]
]
