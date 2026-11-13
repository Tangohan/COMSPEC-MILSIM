/*
    Couche « hors sujet » de l'IA RP (fn_iarpOffTopic, lue par fn_iarpData « offtopic ») : ce que l'IA fait d'un message
    qui n'est pas une vraie demande (grossièreté, avances, insulte, question absurde ou hors du monde, charabia, relance).
    [[section, valeur]…]
      sms       [[mot, remplacement]…]  écriture SMS ramenée au français écrit avant l'interprète (fn_iarpNorm, mot entier)
      cats      [[catégorie, poids, [motifs], [mots]]…]  repérage, avant les demandes à effet (papiers, ordres, aveu…) :
                motifs comme le lexique (texte normalisé, espace = limite de mot), mots entiers ou « début* »
      not       motifs qui annulent un mot repéré (« tu me prends pour un con » est une accusation, pas une insulte)
      junk      mots de charabia (« azerty », « blabla »), en plus des mots sans voyelle ou tapés sur une rangée du clavier
      actions   demandes à effet réel : jamais déclenchées sous le seuil de confiance de l'interprète (fn_iarpIntent)
      mineOk    répliques où la phrase type de la personne (atelier IA) peut suivre ; jamais après un ordre, un refus…
      more      relance (« Et alors ? ») : [[groupe, [répliques…]]…] -> phrases « otMore.<groupe> » de la banque
      lexicon   entrées ajoutées au lexique (même format que data\iarp_lexicon.sqf) : tutoiement, formes courtes, relance
      bank      phrases ajoutées à la banque (même format que data\iarp_bank.sqf) ; didascalie entre parenthèses, en tête
    Les phrases ne reprennent jamais un mot grossier du joueur et n'inventent rien (pas de fait, pas de lieu).
*/
[
    ["sms", [
        ["kel", "quel"], ["kelle", "quelle"], ["koi", "quoi"], ["kwa", "quoi"], ["ki", "qui"], ["ke", "que"], ["pk", "pourquoi"], ["pq", "pourquoi"],
        ["pkoi", "pourquoi"], ["pourkoi", "pourquoi"], ["vs", "vous"], ["chui", "je suis"], ["chuis", "je suis"], ["jsuis", "je suis"], ["tas", "t as"],
        ["jsp", "je sais pas"], ["jte", "je te"], ["wsh", "wesh"], ["dacc", "d accord"], ["dac", "d accord"],
        ["oklm", "tranquille"], ["prenon", "prenom"], ["prenoms", "prenom"], ["tn", "ton"], ["ds", "dans"], ["tt", "tout"], ["pr", "pour"], 
        ["bcp", "beaucoup"], ["cmt", "comment"], ["cmnt", "comment"], ["cb", "combien"], ["tjr", "toujours"], ["tjrs", "toujours"],
        ["papies", "papiers"], ["identiter", "identite"], ["natio", "nationalite"]
    ]],
    ["cats", [
        ["sexual", 3, [" tu veux ma bite", " suce ", " suce moi", " sucer ", " couche avec", " coucher avec", " on baise", " baise moi", " a poil", " tout nu", " toute nue",
            " montre moi tes seins", " montre tes seins", " te deshabiller", " deshabille toi", " t es bonne", " suck my", " blow job",
            " sleep with me", " get naked", " show me your tits", " take off your clothes", " wanna fuck", " fuck me"],
            ["cul", "culs", "bite", "bites", "zizi", "teub", "chatte", "nichon*", "seins", "sexe", "sexy", "porno*", "porn", "branle*", "fesse", "fesses", "nibar*",
                "foufoune", "chibre", "penis", "vagin", "orgasme", "fellation", "sodom*", "partouze", "preservatif", "viol", "violer", "violee",
                "dick", "cock", "pussy", "tits", "boobs", "sex", "blowjob", "naked", "nude", "horny"]],
        ["insult", 2.5, [" t es con", " t es conne", " tu es con", " vous etes con", " sale con", " gros con", " pauvre con", " espece de", " va te faire", " va chier",
            " nique ta", " nique toi", " ferme la ta", " fils de", " sale chien", " sale race", " sale arabe", " sale noir", " sale blanc", " sale juif", " fuck you",
            " screw you", " piece of shit", " son of a bitch", " go to hell"],
            ["con", "conne", "cons", "connard*", "connasse*", "pute", "putes", "merdeux", "encule*", "enfoire*", "batard*", "fdp", "ntm", "nique*", "niquer",
                "bouffon*", "guignol*", "abruti*", "debile*", "idiot*", "imbecile*", "cretin*", "gogol", "mongol", "attarde*", "pd", "pede", "tapette", "salope*", "salaud*",
                "ordure*", "raclure", "pouffiasse", "grognasse", "boloss*", "naze", "clochard", "negre", "bougnoule", "youpin", "bicot", "moron", "stupid", "asshole",
                "bitch", "bastard", "dumbass", "jerk", "dickhead", "retard", "faggot", "nigger"]],
        ["advance", 1.5, [" je t aime", " je vous aime", " tu me plais", " vous me plaisez", " on sort ensemble", " t es belle", " t es beau", " tu es belle", " tu es beau",
            " fais moi un calin", " un bisou", " un calin", " embrasse moi", " epouse moi", " mon coeur", " ma cherie", " mon cheri", " love you", " kiss me", " give me a hug",
            " marry me", " you re hot", " you are hot"],
            ["calin", "calins", "bisou*", "embrasse*", "cherie", "kiss", "hug", "darling", "sweetheart"]],
        ["personal", 1.2, [" combien tu peses", " combien vous pesez", " tu peses combien", " vous pesez combien", " ton poids", " votre poids", " quelle taille tu",
            " tu fais quelle taille", " tu mesures combien", " vous mesurez combien", " t as une copine", " tu as une copine", " t as un copain", " tu as un copain",
            " t as une meuf", " tu as une meuf", " t es puceau", " tu es puceau", " t es vierge", " tu es vierge", " tu es riche", " t es riche", " combien tu gagnes",
            " combien vous gagnez", " ton salaire", " votre salaire", " tu portes quoi", " ta religion", " votre religion", " tu dors ou ce soir", " how much do you weigh",
            " your weight", " how tall are you", " girlfriend", " boyfriend", " are you a virgin", " how much do you earn", " your salary"],
            ["peses", "pesez", "poids", "puceau", "vierge", "salaire", "weigh", "virgin"]],
        ["world", 2, [" ben laden", " oussama", " michael jackson", " harry potter", " star wars", " tour eiffel", " pere noel", " coupe du monde", " call of duty",
            " the moon landing", " santa claus"],
            ["laden", "obama", "trump", "macron", "poutine", "putin", "biden", "hitler", "staline", "stalin", "napoleon", "einstein", "elvis", "mickey", "batman", "superman",
                "spiderman", "pokemon", "minecraft", "fortnite", "arma", "bohemia", "steam", "discord", "netflix", "youtube", "tiktok", "instagram",
                "facebook", "twitter", "playstation", "xbox", "bitcoin", "crypto", "ronaldo", "messi", "mbappe", "zidane",
                "chatgpt", "google"]],
        ["absurd", 1, [" la lune", " les etoiles", " un million", " un milliard", " super pouvoir", " superpouvoir", " voyage dans le temps", " la fin du monde",
            " un tresor", " baguette magique", " chante moi", " chante une", " danse pour", " fais le poirier", " fais une roulade", " fais le singe", " imite un",
            " the moon", " a million", " sing me", " dance for me", " do a backflip", " a unicorn", " time travel"],
            ["lune", "licorne*", "teleport*", "moon", "unicorn*", "superpower*"]]
    ]],
    ["not", [" pour un con", " pour une conne", " pour des cons", " pour un idiot", " pour une idiote", " for an idiot", " for a fool", " papier toilette", " cul de sac"]],
    ["junk", ["bla", "blabla", "blablabla", "blah", "azerty", "qwerty", "asdf", "qsdf", "zxcv", "wxcv", "aze", "qsd", "test", "tests", "testing", "hmm", "hmmm", "euh",
        "heu", "rien", "nothing", "lorem", "ipsum", "xxx", "zzz", "abc", "abcd", "osef", "rien", "truc", "machin", "bidule", "nimp", "nawak", "lalala", "trololo", "jsp"]],
    ["actions", ["papers", "search", "handsup", "halt", "kneel", "follow", "leave", "dismount", "trunk", "dropWeapon", "stepBack", "comeHere", "turnAround", "sit", "quiet",
        "hurry", "handsDown", "bag", "threat", "bribe", "confront", "cooperate"]],
    ["mineOk", ["howareyou", "smalltalk", "greet", "origin", "employer", "job", "travel", "pob", "family", "village", "often", "since", "stayLong", "lateHour", "hobbies",
        "compliment", "time", "politics", "help", "thanks", "papersWait", "searchYes", "weaponsNo", "drugs", "money", "confrontInnocent", "intelNo", "dunno", "otSmall",
        "otWorld", "otPersonal"]],
    // Relance : groupe d'après la dernière réplique de l'IA (sans dernière réplique : « none »).
    ["more", [
        ["refuse", ["noComply", "refuse", "searchNo", "bribeNo", "cooperateNo", "cuffNo", "offerNo", "noComply.leave", "noComply.halt", "noComply.follow", "noComply.handsup",
            "noComply.kneel", "noComply.sit", "noComply.comeHere", "noComply.stepBack", "leaveCuffed", "followCuffed"]],
        ["deny", ["confrontDeny", "confrontInnocent", "confrontAngry", "weaponsNo", "drugs", "sensitive"]],
        ["nothing", ["intelNo", "dunno", "photoNo", "offLimits", "otWorld", "whyUs", "reward"]],
        ["intel", ["intelVague", "intelYes", "intelFull", "intelAgain", "heardShots", "confess", "confessTruth", "confessVague", "confessed"]],
        ["cover", ["origin", "employer", "job", "travel", "pob", "contact", "address", "name", "firstName", "age", "family", "phone", "nation", "speak", "since", "with",
            "lateHour", "alibi", "often", "stayLong", "waitingFor", "house", "cargo", "declare", "vehicle", "money", "weaponsYes"]],
        ["comply", ["comply", "searchYes", "papersWait", "searchWait", "cuffYes", "givePapers", "cooperateYes", "offerYes", "papersCloser", "comply.leave", "comply.halt",
            "comply.handsup", "comply.follow", "comply.kneel", "comply.dropWeapon", "comply.stepBack", "comply.comeHere", "comply.turnAround", "comply.sit", "comply.quiet",
            "comply.hurry", "comply.handsDown", "comply.bag"]],
        ["small", ["howareyou", "smalltalk", "greet", "greetAgain", "hobbies", "compliment", "joke", "laugh", "time", "politics", "village", "water", "food", "medical",
            "needs", "directions", "whereHere", "neighbors", "chief", "police", "army", "hungry", "tiredYes", "tiredNo", "cigarette", "bot", "otSmall", "otSteer", "thanks",
            "sorry", "help", "introduce", "understand", "yes", "no"]],
        ["offended", ["insult", "mock", "flirt", "otInsult", "otInsult2", "otInsultDur", "otInsultPro", "otSexual", "otSexualDur", "otSexualPro", "otEnd", "otEndPro", "otCold",
            "otPersonal", "absurd"]],
        ["fear", ["threat", "askArrest", "askArrestYes", "afraidYes", "hurtYes", "calm", "protect", "promise", "shotsDown", "shotsSurrender"]],
        ["miss", ["unknown", "otMiss1", "otMiss2", "otLost", "otGibberish"]]
    ]],
    // Ajouts au lexique : tutoiement, formes courtes et ordres, relance. Exemples : repris par l'onglet GUIDE (thème indiqué).
    ["lexicon", [
        ["name", 1, [" ton nom", " t appelles", " tu t appelle", " c est quoi ton prenom", " quel est ton prenom", " ton nom de famille", " nom de famille",
            " ton nom complet", " comment on t appelle", " t es qui toi", " dis moi ton nom", " donne moi ton nom", " ton blase"],
            [["Quel est ton prénom ?", "What's your first name?"], ["Ton nom ?", "Your name?"]], "identity"],
        ["age", 1, [" age ", " t as quel age", " tu as quel age", " quel age tu as", " quel age as tu", " quel age t as", " ta date de naissance", " votre annee de naissance",
            " ton annee de naissance", " tu es ne quand", " t es ne quand", " vous etes ne quand", " tu es nee quand", " t as combien d annees", " tu as quel age toi", " age please"],
            [["T'as quel âge ?", "How old are you?"], ["Âge ?", "Age?"]], "identity"],
        ["nation", 1.2, [" tu es de quelle nationalite", " t es de quelle nationalite", " tu es d ou comme nationalite", " ta nationalite", " tu es quoi comme nationalite"],
            [["Ta nationalité ?", "Your nationality?"]], "identity"],
        ["address", 1.1, [" ton adresse", " t habites ou exactement", " ton adresse exacte", " tu habites a quelle adresse", " c est quoi ton adresse"],
            [["C'est quoi ton adresse ?", "What's your address?"]], "identity"],
        ["job", 1, [" ton job", " ton taf", " ton travail", " ta profession", " t es quoi comme", " tu es quoi comme", " tu fais quoi comme boulot", " tu fais quoi comme metier",
            " tu bosses ou", " tu travailles ou", " vous travaillez ou", " ton emploi", " votre emploi"],
            [["Ta profession ?", "Your job?"], ["Tu bosses où ?", "Where do you work?"]], "cover"],
        ["papers", 1, [" tes docs", " vos docs", " montre tes papiers", " montre moi tes papiers", " sors tes papiers", " file moi tes papiers", " passe moi tes papiers",
            " donne moi tes papiers", " donne tes papiers", " papiers stp", " papiers svp", " tes papelards",
            " vos papelards", " papelard"],
            [["Tes papiers.", "Your papers."], ["Montre-moi tes papiers.", "Show me your papers."]], "identity"],
        ["origin", 1, [" tu viens de ou", " vous venez de ou", " de ou tu viens", " de ou vous venez", " tu viens d ou toi", " t es d ou toi", " d ou tu sors", " tu sors d ou",
            " tu es originaire", " t es originaire", " ou tu habites", " tu creches ou"],
            [["D'où tu viens ?", "Where are you from?"]], "cover"],
        ["travel", 1, [" ou tu vas", " tu vas ou", " ou tu te rends", " tu te rends ou", " ou vous vous rendez", " vous vous rendez ou", " ta destination", " votre destination",
            " tu fous quoi ici", " tu fous quoi la", " tu fais quoi par ici", " qu est ce que tu fous", " ce que tu fais la", " tu fais quoi dans le coin", " tu vas ou comme ca"],
            [["Où tu vas ?", "Where are you going?"], ["Tu fais quoi ici ?", "What are you doing here?"]], "cover"],
        ["with", 1.3, [" t es avec qui", " tu es avec qui", " tu es venu avec qui", " t es venu avec qui", " avec qui t es", " avec qui tu es", " t es tout seul",
            " tu es tout seul", " vous etes tout seul", " tu es venu seul", " t es venu seul"],
            [["T'es avec qui ?", "Who are you with?"]], "presence"],
        ["intel", 0.9, [" t as vu quelqu un", " tu as vu quelqu un", " vous avez vu quelqu un", " vu quelqu un", " t as rien vu", " tu as rien vu", " t as vu quoi",
            " tu as vu quoi", " qu est ce que t as vu", " seen anyone", " saw anyone", " saw anything"],
            [["T'as vu quelqu'un ?", "Have you seen anyone?"]], "intel"],
        ["weapons", 1.1, [" t es arme", " tu es arme", " t as une arme", " tu portes une arme", " tu as un flingue", " t as un flingue", " un flingue", " t as un couteau",
            " tu as un couteau", " arme sur toi", " armes sur toi", " t es armee", " tu es armee"],
            [["T'es armé ?", "Are you armed?"]], "search"],
        ["comeHere", 1.2, [" avancez", " avance ", " avance toi", " avancez vous", " avance vers moi", " avancez vers moi", " move forward", " step forward", " come forward"],
            [["Avancez.", "Step forward."]], "orders"],
        ["leave", 1.1, [" tu peux partir", " vous pouvez circuler", " tu peux circuler", " circule ", " casse toi", " cassez vous", " va t en", " allez vous en", " allez y "],
            [["Tu peux partir.", "You can go."]], "orders"],
        ["halt", 1.2, [" ne bouge pas", " bouge plus", " bougez plus", " pas bouger", " arrete toi la", " arretez vous la", " stop la"], [], "orders"],
        ["family", 1, [" ta mere va", " ta mere elle", " ta mere est", " ta mere habite", " votre mere va", " votre mere est", " ta mere vit", " ton pere va", " ton pere est"], [], "identity"],
        ["followup", 1, [" et alors", " et ensuite", " et apres", " et puis ", " et donc", " c est tout", " continue", " continuez", " explique", " expliquez", " expliquer",
            " developpe", " developpez", " precise", " precisez", " pourquoi ", " pourquoi ca", " comment ca ", " vraiment", " ah bon", " c est a dire", " so what",
            " and then", " go on", " explain", " why ", " why is that", " really", " what do you mean", " meaning what", " how come", " is that all", " that s all"],
            [["Et alors ?", "So what?"], ["Pourquoi ?", "Why?"], ["C'est-à-dire ?", "Meaning what?"]], "talk"]
    ]],
    ["bank", [
        // ---------------------------------------------------------------- incompris : jamais deux fois la même tournure de suite
        ["otMiss1", [
            ["*", [["Pardon ?", "Sorry?"], ["Comment ?", "What?"], ["Vous pouvez répéter ?", "Can you say that again?"], ["Je n'ai pas compris.", "I didn't understand."],
                ["Pardon, je n'ai pas bien compris.", "Sorry, I didn't quite get that."], ["Hein ? Répétez, s'il vous plaît.", "Huh? Say that again, please."],
                ["Excusez-moi, vous disiez ?", "Excuse me, you were saying?"], ["Je n'ai pas saisi.", "I didn't catch that."]]],
            ["nerveux", [["P-pardon ? Je n'ai pas compris…", "S-sorry? I didn't understand…"], ["Quoi ? Qu'est-ce que vous dites ?", "What? What are you saying?"],
                ["Je… je n'ai pas compris, désolé.", "I… I didn't understand, sorry."], ["Pardon ? Répétez, s'il vous plaît…", "Sorry? Say it again, please…"],
                ["Comment ? Je n'ai pas entendu.", "What? I didn't hear."], ["Excusez-moi… vous disiez ?", "Sorry… you were saying?"]]],
            ["mefiant", [["Pardon ?", "Sorry?"], ["Qu'est-ce que vous dites ?", "What are you saying?"], ["Je ne comprends pas où vous voulez en venir.", "I don't see what you're getting at."],
                ["Répétez.", "Say that again."], ["Hm ? Je n'ai pas compris.", "Hm? I didn't understand."], ["Vous dites quoi, là ?", "What are you saying, now?"]]],
            ["agressif", [["Quoi ?", "What?"], ["Vous dites quoi ?", "What are you saying?"], ["Parlez clairement.", "Speak clearly."], ["J'ai rien compris.", "I didn't get any of that."],
                ["Hein ? Articulez.", "Huh? Speak up."], ["Répétez, je n'ai rien compris.", "Say it again, I didn't get it."]]],
            ["effraye", [["Pardon… je n'ai pas compris…", "Sorry… I didn't understand…"], ["Quoi ? Je… je ne comprends pas…", "What? I… I don't understand…"],
                ["S'il vous plaît, répétez…", "Please, say it again…"], ["Je n'ai pas compris, pardon…", "I didn't understand, sorry…"],
                ["(Il tremble.) Pardon ?", "(He's shaking.) Sorry?"], ["Je… quoi ?", "I… what?"]]],
            ["laconique", [["Pardon ?", "Sorry?"], ["Hein ?", "Huh?"], ["Comment ?", "What?"], ["Répétez.", "Again."]]],
            ["bavard", [["Pardon ? Avec le bruit, je n'ai rien entendu.", "Sorry? With the noise, I didn't hear a thing."], ["Excusez-moi, je n'ai pas suivi, vous pouvez répéter ?", "Sorry, I didn't follow, can you repeat?"],
                ["Comment ? Je n'ai pas bien compris ce que vous disiez.", "What? I didn't quite understand what you said."], ["Pardon, j'étais ailleurs. Vous disiez ?", "Sorry, my mind was elsewhere. You were saying?"]]]
        ]],
        ["otMiss2", [
            ["*", [["Je ne comprends toujours pas. Vous pouvez le dire autrement ?", "I still don't understand. Can you say it another way?"],
                ["Désolé, je ne vois pas ce que vous voulez dire.", "Sorry, I don't see what you mean."], ["Je ne suis pas sûr de comprendre ce que vous demandez.", "I'm not sure I understand what you're asking."],
                ["Dites-le plus simplement, s'il vous plaît.", "Say it more simply, please."], ["Je n'arrive pas à vous suivre.", "I can't follow you."],
                ["Encore une fois, plus lentement ?", "Once more, more slowly?"], ["Je ne comprends pas votre question.", "I don't understand your question."],
                ["Vous me demandez quoi, exactement ?", "What exactly are you asking me?"]]],
            ["nerveux", [["Je… je ne comprends toujours pas, pardon.", "I… I still don't understand, sorry."], ["Je ne vois pas ce que vous voulez, vraiment…", "I really don't see what you want…"],
                ["Dites-moi simplement ce que je dois faire.", "Just tell me what I need to do."], ["Pardon, je ne comprends pas ce que vous me demandez…", "Sorry, I don't understand what you're asking…"],
                ["Je ne suis pas sûr de comprendre…", "I'm not sure I understand…"], ["Vous pouvez dire ça autrement ?", "Can you say that another way?"]]],
            ["mefiant", [["Je ne vois pas ce que vous cherchez.", "I don't see what you're after."], ["Soyez clair. Vous voulez quoi ?", "Be clear. What do you want?"],
                ["Je ne comprends pas votre question.", "I don't understand your question."], ["Où voulez-vous en venir ?", "What are you getting at?"],
                ["Dites-le franchement.", "Say it plainly."], ["Je ne joue pas aux devinettes.", "I'm not playing guessing games."]]],
            ["agressif", [["Je ne comprends rien à ce que vous racontez.", "I don't understand a word you're saying."], ["Vous voulez quoi, à la fin ?", "What do you want, in the end?"],
                ["Parlez normalement.", "Talk normally."], ["Ça n'a aucun sens.", "That makes no sense."], ["Dites ce que vous voulez ou laissez-moi.", "Say what you want or leave me be."],
                ["Encore ? Je ne comprends pas.", "Again? I don't understand."]]],
            ["effraye", [["Je ne comprends pas… qu'est-ce que vous voulez ?", "I don't understand… what do you want?"], ["Pardon, pardon… je ne comprends pas…", "Sorry, sorry… I don't understand…"],
                ["Dites-moi ce que je dois faire, s'il vous plaît.", "Tell me what to do, please."], ["Je ne vois pas… je ne sais pas…", "I don't see… I don't know…"],
                ["(Sa voix tremble.) Je ne comprends pas.", "(His voice shakes.) I don't understand."], ["S'il vous plaît, plus simplement…", "Please, more simply…"]]]
        ]],
        ["otLost", [
            ["*", [["Je ne comprends pas ce que vous voulez.", "I don't understand what you want."], ["Je ne vois vraiment pas ce que vous me demandez.", "I really don't see what you're asking me."],
                ["Dites-moi simplement ce que vous voulez.", "Just tell me what you want."], ["Je ne sais pas quoi vous répondre.", "I don't know what to answer."],
                ["On ne se comprend pas, je crois.", "I don't think we understand each other."], ["Je ne comprends pas. Qu'attendez-vous de moi ?", "I don't understand. What do you expect from me?"],
                ["(Il secoue la tête.) Je ne comprends pas.", "(He shakes his head.) I don't understand."], ["Désolé, je ne peux pas vous aider si je ne comprends pas.", "Sorry, I can't help you if I don't understand."]]],
            ["nerveux", [["Je ne comprends pas ce que vous voulez… je suis désolé.", "I don't understand what you want… I'm sorry."], ["Qu'est-ce que je dois faire ? Je ne comprends pas.", "What should I do? I don't understand."],
                ["Je ne sais pas quoi vous dire…", "I don't know what to tell you…"], ["Pardon, vraiment, je ne comprends pas.", "Sorry, really, I don't understand."],
                ["Je ne vois pas ce que vous attendez…", "I don't see what you expect…"], ["(Il se frotte les mains.) Je ne comprends pas…", "(He rubs his hands.) I don't understand…"]]],
            ["mefiant", [["Je ne vois pas où vous voulez en venir.", "I don't see where you're going with this."], ["Si vous voulez quelque chose, dites-le.", "If you want something, say it."],
                ["Je n'ai rien à répondre à ça.", "I have no answer to that."], ["Vous perdez votre temps.", "You're wasting your time."],
                ["Je ne comprends pas, et je n'aime pas ça.", "I don't understand, and I don't like it."], ["Hm. Je ne vois pas.", "Hm. I don't see."]]],
            ["agressif", [["J'ai rien compris, et j'ai pas que ça à faire.", "I didn't get a thing, and I have better things to do."], ["Vous parlez pour ne rien dire.", "You're talking for nothing."],
                ["Ça suffit, je ne comprends rien.", "Enough, I don't understand anything."], ["Dites ce que vous voulez, une fois pour toutes.", "Say what you want, once and for all."],
                ["Je ne vais pas deviner.", "I'm not going to guess."], ["Vous me faites perdre mon temps.", "You're wasting my time."]]]
        ]],
        // Charabia (« azerty », « bla bla ») : il ne cherche pas de sens.
        ["otGibberish", [
            ["*", [["Pardon ? Ça ne veut rien dire.", "Sorry? That doesn't mean anything."], ["Vous parlez quelle langue ?", "What language are you speaking?"],
                ["(Il fronce les sourcils.) Hein ?", "(He frowns.) Huh?"], ["Je n'ai rien compris à ça.", "I didn't understand that at all."],
                ["Qu'est-ce que vous dites ?", "What are you saying?"], ["Ça va, vous ?", "Are you alright?"], ["C'est un code ?", "Is that a code?"]]],
            ["agressif", [["Vous vous moquez de moi ?", "Are you making fun of me?"], ["Parlez normalement.", "Talk normally."], ["C'est quoi, ce charabia ?", "What's this gibberish?"],
                ["Vous avez bu ?", "Have you been drinking?"]]],
            ["nerveux", [["Je… je ne comprends pas ce que vous dites.", "I… I don't understand what you're saying."], ["C'est… c'est un code ?", "Is… is that a code?"],
                ["Pardon ? Ça ne veut rien dire…", "Sorry? That doesn't mean anything…"], ["Hein ? Je ne comprends pas.", "Huh? I don't understand."]]]
        ]],
        // ---------------------------------------------------------------- insulte, provocation
        ["otInsult", [
            ["*", [["Pas la peine d'être grossier.", "No need to be rude."], ["Je ne vous permets pas.", "I won't allow that."], ["Restez poli, s'il vous plaît.", "Stay polite, please."],
                ["Vous parlez comme ça à tout le monde ?", "Do you talk to everyone like that?"], ["Je ne vous ai rien fait.", "I haven't done anything to you."],
                ["(Il se raidit.) Pardon ?", "(He stiffens.) Excuse me?"], ["Respectez-moi, s'il vous plaît.", "Show me some respect, please."], ["C'est comme ça que vous parlez aux gens ?", "Is that how you talk to people?"]]],
            ["nerveux", [["Pourquoi vous me parlez comme ça ?", "Why are you talking to me like that?"], ["Je… je ne vous ai rien fait.", "I… I haven't done anything to you."],
                ["Calmez-vous, s'il vous plaît.", "Calm down, please."], ["Pas besoin d'insulter, je réponds.", "No need for insults, I'm answering."],
                ["(Il recule un peu.) D'accord, d'accord…", "(He backs off a little.) Okay, okay…"], ["Je ne cherche pas d'histoires.", "I'm not looking for trouble."]]],
            ["mefiant", [["Je vois. C'est comme ça que vous travaillez.", "I see. That's how you work."], ["Ça ne vous mènera nulle part.", "That won't get you anywhere."],
                ["Je m'en souviendrai.", "I'll remember that."], ["Vous croyez que ça va m'aider à parler ?", "You think that'll make me talk?"],
                ["(Il vous fixe sans rien dire.)", "(He stares at you without a word.)"], ["Charmant.", "Charming."]]],
            ["agressif", [["Répétez ça pour voir.", "Say that again, I dare you."], ["Faites attention à ce que vous dites.", "Watch your mouth."], ["Vous cherchez les ennuis ?", "Are you looking for trouble?"],
                ["Pour qui vous vous prenez ?", "Who do you think you are?"], ["(Il serre les poings.) Doucement.", "(He clenches his fists.) Easy."], ["On ne me parle pas comme ça.", "Nobody talks to me like that."]]],
            ["effraye", [["Pardon… je ne voulais pas vous énerver…", "Sorry… I didn't mean to upset you…"], ["S'il vous plaît, ne vous fâchez pas…", "Please, don't be angry…"],
                ["Je fais ce que vous voulez, ne criez pas…", "I'll do what you want, don't shout…"], ["(Il baisse les yeux.) Pardon…", "(He lowers his eyes.) Sorry…"],
                ["Je ne vous ai rien fait, je vous jure…", "I didn't do anything to you, I swear…"], ["Pas la peine… je réponds…", "There's no need… I'm answering…"]]],
            ["laconique", [["Hm.", "Hm."], ["Poli.", "Polite."], ["(Il ne relève pas.)", "(He lets it go.)"], ["Charmant.", "Charming."]]],
            ["bavard", [["Oh là, pas besoin de ce langage ! On peut discuter normalement, non ?", "Whoa, no need for that language! We can talk normally, can't we?"],
                ["Ma mère m'a appris à parler poliment, la vôtre aussi, j'espère.", "My mother taught me to speak politely, I hope yours did too."],
                ["Allons, allons, restons polis, ça ira plus vite.", "Come now, let's stay polite, it'll go faster."]]]
        ]],
        ["otInsult2", [
            ["*", [["Je ne vais pas vous le répéter : arrêtez.", "I won't tell you again: stop."], ["Encore une fois et je ne vous parle plus.", "Once more and I stop talking to you."],
                ["Ça suffit maintenant.", "That's enough now."], ["Vous dépassez les bornes.", "You're crossing the line."], ["Je n'accepte pas qu'on me parle comme ça.", "I don't accept being spoken to like that."],
                ["(Il se détourne à moitié.) Vous êtes sérieux ?", "(He half turns away.) Are you serious?"], ["Je réponds aux questions, pas aux insultes.", "I answer questions, not insults."],
                ["Arrêtez ça, ou je m'en vais.", "Stop that, or I'm leaving."]]],
            ["agressif", [["La prochaine fois, ça finira mal.", "Next time, it'll end badly."], ["Continuez et vous allez le regretter.", "Keep going and you'll regret it."],
                ["Encore un mot comme ça…", "One more word like that…"], ["Vous me cherchez, là.", "You're asking for it."], ["(Il fait un pas vers vous.) Ça suffit.", "(He takes a step toward you.) Enough."],
                ["Je vous préviens.", "I'm warning you."]]],
            ["effraye", [["Arrêtez, s'il vous plaît…", "Stop, please…"], ["Pourquoi vous faites ça ?", "Why are you doing this?"], ["Laissez-moi, s'il vous plaît…", "Leave me be, please…"],
                ["Je n'ai rien fait pour mériter ça…", "I didn't do anything to deserve this…"], ["(Il a les larmes aux yeux.) Arrêtez…", "(His eyes well up.) Stop…"], ["S'il vous plaît, non…", "Please, no…"]]]
        ]],
        // Insurgé, recherché : mépris ; policier, agent : refus professionnel.
        ["otInsultDur", [
            ["*", [["(Il crache par terre.)", "(He spits on the ground.)"], ["Tu ne me fais pas peur.", "You don't scare me."], ["Insulte autant que tu veux.", "Insult me all you want."],
                ["C'est tout ce que tu as ?", "Is that all you've got?"], ["(Il sourit avec mépris.)", "(He smiles with contempt.)"], ["Les mots, ça ne coûte rien.", "Words cost nothing."],
                ["Tu parles beaucoup pour un soldat.", "You talk a lot for a soldier."], ["Continue, ça m'amuse.", "Go on, it amuses me."]]],
            ["agressif", [["Répète ça, et on verra.", "Say that again and we'll see."], ["Fais attention à toi.", "Watch yourself."], ["Tu vas le regretter.", "You'll regret that."],
                ["(Il vous toise.) Doucement.", "(He looks you up and down.) Easy."], ["On se reverra.", "We'll meet again."], ["Tu n'es rien, ici.", "You're nothing here."]]]
        ]],
        ["otInsultPro", [
            ["*", [["On va rester correct, d'accord ?", "Let's keep it civil, alright?"], ["Je ne réponds pas à ce ton.", "I don't answer to that tone."], ["Restez professionnel.", "Stay professional."],
                ["Ce n'est pas comme ça que ça se passe.", "That's not how this works."], ["Je note votre comportement.", "I'm noting your behaviour."], ["Calmez-vous, et on reprend.", "Calm down, and we'll start again."],
                ["Vous voulez parler à mon supérieur ?", "Do you want to talk to my superior?"], ["Ça suffit. Soyez correct.", "Enough. Be proper."]]]
        ]],
        // ---------------------------------------------------------------- grossièreté sexuelle, harcèlement : choqué, jamais de détail
        ["otSexual", [
            ["*", [["Pardon ? Vous n'avez pas honte ?", "Excuse me? Aren't you ashamed?"], ["Ce n'est pas le moment.", "This isn't the time."], ["Je ne répondrai pas à ça.", "I won't answer that."],
                ["Vous êtes sérieux ?", "Are you serious?"], ["(Il vous regarde, choqué.)", "(He looks at you, shocked.)"], ["C'est déplacé.", "That's out of line."],
                ["Gardez ça pour vous.", "Keep that to yourself."], ["Je fais comme si je n'avais rien entendu.", "I'll pretend I didn't hear that."]]],
            ["nerveux", [["Qu-quoi ? Non, non…", "Wh-what? No, no…"], ["Je… je ne veux pas parler de ça.", "I… I don't want to talk about that."], ["Pourquoi vous dites ça ?", "Why are you saying that?"],
                ["(Il rougit et détourne les yeux.)", "(He blushes and looks away.)"], ["S'il vous plaît, arrêtez.", "Please, stop."], ["Ce n'est pas drôle…", "That's not funny…"]]],
            ["mefiant", [["Vous êtes là pour ça ?", "Is that what you're here for?"], ["Charmant. Autre chose ?", "Charming. Anything else?"], ["Je ne réponds pas à ce genre de question.", "I don't answer that kind of question."],
                ["Ça ne vous regarde pas.", "That's none of your business."], ["(Il vous fixe, glacial.)", "(He stares at you, icy.)"], ["Très professionnel.", "Very professional."]]],
            ["agressif", [["Répétez ça, pour voir.", "Say that again, I dare you."], ["Vous allez vous calmer tout de suite.", "You're going to calm down right now."], ["Dégoûtant.", "Disgusting."],
                ["Encore une remarque comme ça…", "One more remark like that…"], ["Vous me prenez pour qui ?", "Who do you take me for?"], ["(Il serre la mâchoire.) Ça suffit.", "(He clenches his jaw.) Enough."]]],
            ["effraye", [["Non… s'il vous plaît…", "No… please…"], ["Laissez-moi tranquille…", "Leave me alone…"], ["(Il recule, effrayé.)", "(He backs away, frightened.)"],
                ["Je ne veux pas…", "I don't want…"], ["Arrêtez, je vous en prie.", "Stop, I beg you."], ["Pourquoi vous me faites ça ?", "Why are you doing this to me?"]]]
        ]],
        ["otSexualDur", [
            ["*", [["Tu es malade.", "You're sick."], ["(Il crache par terre.)", "(He spits on the ground.)"], ["Pathétique.", "Pathetic."], ["Va dire ça ailleurs.", "Go say that somewhere else."],
                ["Tu n'as aucun honneur.", "You have no honour."], ["(Il détourne le regard, dégoûté.)", "(He looks away, disgusted.)"]]]
        ]],
        ["otSexualPro", [
            ["*", [["Ce genre de remarque n'a rien à faire ici.", "That kind of remark has no place here."], ["Je vais faire comme si je n'avais rien entendu. Une fois.", "I'll pretend I didn't hear that. Once."],
                ["C'est inacceptable.", "That's unacceptable."], ["Reprenez-vous.", "Get a grip."], ["Je ne réponds pas à ça.", "I don't answer that."], ["Ça finira dans mon rapport.", "That'll go in my report."]]]
        ]],
        // Conversation coupée (provocations répétées) : il ne parle plus pendant un moment.
        ["otEnd", [
            ["*", [["Laissez-moi tranquille.", "Leave me alone."], ["Je n'ai plus rien à vous dire.", "I have nothing more to say to you."], ["C'est fini, je ne vous parle plus.", "That's it, I'm not talking to you anymore."],
                ["Ça suffit. Au revoir.", "Enough. Goodbye."], ["(Il vous tourne le dos.)", "(He turns his back on you.)"], ["Je m'en vais. Trouvez quelqu'un d'autre.", "I'm leaving. Find someone else."],
                ["Allez parler à quelqu'un d'autre.", "Go talk to someone else."], ["Non. On arrête là.", "No. We stop here."]]],
            ["agressif", [["Dégagez.", "Get lost."], ["J'en ai assez de vous.", "I've had enough of you."], ["Fichez-moi la paix.", "Leave me in peace."],
                ["(Il vous tourne le dos, furieux.)", "(He turns his back, furious.)"], ["Allez au diable.", "Go to hell."], ["Plus un mot.", "Not another word."]]],
            ["effraye", [["Laissez-moi partir, s'il vous plaît…", "Let me go, please…"], ["(Il se recroqueville et ne répond plus.)", "(He shrinks back and stops answering.)"],
                ["Je ne veux plus parler…", "I don't want to talk anymore…"], ["S'il vous plaît, laissez-moi…", "Please, leave me be…"], ["(Il évite votre regard.)", "(He avoids your gaze.)"],
                ["Je ne dirai plus rien…", "I won't say anything else…"]]]
        ]],
        ["otEndPro", [
            ["*", [["Cette conversation est terminée.", "This conversation is over."], ["On arrête là. Je ferai mon rapport.", "We stop here. I'll file my report."], ["Je n'ai plus rien à vous dire.", "I have nothing more to say to you."],
                ["Allez voir mon supérieur.", "Go see my superior."], ["(Il note quelque chose et vous ignore.)", "(He writes something down and ignores you.)"], ["Terminé.", "Done."]]]
        ]],
        ["otCold", [
            ["*", [["(Il ne vous répond pas.)", "(He doesn't answer you.)"], ["Je vous ai dit de me laisser tranquille.", "I told you to leave me alone."], ["Je ne vous parle plus.", "I'm not talking to you."],
                ["(Il regarde ailleurs.)", "(He looks elsewhere.)"], ["Non.", "No."], ["Laissez-moi.", "Leave me."], ["(Il fait comme si vous n'étiez pas là.)", "(He acts as if you weren't there.)"],
                ["Vous n'avez pas compris ?", "Didn't you understand?"]]],
            ["agressif", [["Dégagez, j'ai dit.", "Get lost, I said."], ["Toujours là ?", "Still here?"], ["(Il vous ignore ostensiblement.)", "(He pointedly ignores you.)"],
                ["Fichez-moi la paix.", "Leave me in peace."], ["Je ne vous parle plus.", "I'm not talking to you."], ["Allez-vous-en.", "Go away."]]]
        ]],
        // Excuses après la provocation : il accepte, du bout des lèvres.
        ["otApology", [
            ["*", [["Bon… d'accord. Mais faites attention.", "Well… alright. But watch it."], ["J'accepte vos excuses.", "I accept your apology."], ["Ça va. N'en parlons plus.", "Fine. Let's drop it."],
                ["D'accord. On reprend calmement.", "Alright. Let's start again calmly."], ["(Il soupire.) Bon. Qu'est-ce que vous voulez ?", "(He sighs.) Fine. What do you want?"], ["Très bien. Restons corrects.", "Very well. Let's keep it proper."]]],
            ["mefiant", [["Hm. On verra.", "Hm. We'll see."], ["Des excuses… si vous le dites.", "An apology… if you say so."], ["Je m'en souviendrai quand même.", "I'll still remember."],
                ["Bon. Mais je vous ai à l'œil.", "Fine. But I've got my eye on you."], ["(Il hoche la tête, sans sourire.)", "(He nods, without smiling.)"], ["D'accord. Pour cette fois.", "Alright. This time."]]]
        ]],
        // ---------------------------------------------------------------- avances (câlin, « je t'aime »), questions trop personnelles
        ["flirt", [
            ["nerveux", [["Je… euh… non, merci.", "I… uh… no, thanks."], ["(Il rougit.) Ce n'est pas le moment.", "(He blushes.) This isn't the time."], ["Vous plaisantez, j'espère ?", "You're joking, I hope?"],
                ["Je… je préfère qu'on reste sérieux.", "I… I'd rather we stay serious."], ["Non, non, s'il vous plaît.", "No, no, please."], ["Ça me met mal à l'aise.", "That makes me uncomfortable."]]],
            ["mefiant", [["Vous essayez de m'amadouer ?", "Are you trying to soften me up?"], ["Ça ne marchera pas.", "That won't work."], ["Gardez vos distances.", "Keep your distance."],
                ["Drôle de méthode.", "Strange method."], ["(Il recule d'un pas.)", "(He takes a step back.)"], ["Non merci.", "No thanks."]]],
            ["agressif", [["Ne me touchez pas.", "Don't touch me."], ["Vous vous croyez où ?", "Where do you think you are?"], ["Ça suffit, ces bêtises.", "Enough of this nonsense."],
                ["Restez à votre place.", "Stay in your place."], ["(Il vous repousse du regard.)", "(He pushes you away with a look.)"], ["Non. Et arrêtez.", "No. And stop."]]],
            ["effraye", [["Non… s'il vous plaît…", "No… please…"], ["(Il se raidit.) Non…", "(He stiffens.) No…"], ["Je ne veux pas, laissez-moi.", "I don't want to, leave me be."],
                ["S'il vous plaît, ne m'approchez pas.", "Please, don't come near me."], ["Pourquoi vous dites ça ?", "Why are you saying that?"], ["Je… non.", "I… no."]]]
        ]],
        ["otPersonal", [
            ["*", [["Ça ne vous regarde pas.", "That's none of your business."], ["Drôle de question.", "Strange question."], ["Pourquoi vous voulez savoir ça ?", "Why do you want to know that?"],
                ["Ça, c'est ma vie privée.", "That's my private life."], ["Je ne vois pas le rapport.", "I don't see the connection."], ["Je préfère garder ça pour moi.", "I'd rather keep that to myself."],
                ["Vous posez toujours ce genre de question ?", "Do you always ask that kind of question?"], ["Ce n'est pas important.", "It's not important."]]],
            ["nerveux", [["Euh… pourquoi vous demandez ça ?", "Uh… why are you asking that?"], ["Je… je ne sais pas, ça ne vous regarde pas.", "I… I don't know, it's none of your business."],
                ["C'est pour le contrôle, ça ?", "Is that for the check?"], ["Je préfère ne pas répondre…", "I'd rather not answer…"], ["(Il hésite.) Pourquoi ?", "(He hesitates.) Why?"], ["C'est vraiment nécessaire ?", "Is that really necessary?"]]],
            ["mefiant", [["Qu'est-ce que ça peut vous faire ?", "What's it to you?"], ["Vous fichez quoi, avec ça ?", "What are you filing, with that?"], ["Pas vos affaires.", "Not your business."],
                ["Je ne réponds pas à ça.", "I'm not answering that."], ["Vous êtes curieux, vous.", "You're a curious one."], ["Ça ne sert à rien de demander ça.", "There's no point asking that."]]],
            ["agressif", [["Occupez-vous de vos affaires.", "Mind your own business."], ["Et vous, ça vous regarde ?", "And is it any of yours?"], ["Quelle question…", "What a question…"],
                ["Vous n'avez rien de mieux à faire ?", "Got nothing better to do?"], ["Je ne réponds pas à ça.", "I'm not answering that."], ["Lâchez-moi avec ça.", "Get off my back with that."]]],
            ["bavard", [["Ha ! Ma femme me pose la même question. Disons que ça ne regarde que moi.", "Ha! My wife asks me the same thing. Let's say it's my business."],
                ["Oh, ça, c'est personnel, vous savez. Parlons d'autre chose.", "Oh, that's personal, you know. Let's talk about something else."],
                ["Vous êtes curieux ! Mais je garde ça pour moi.", "You're curious! But I'll keep that to myself."], ["Ah, ça, je ne le dis à personne, même pas à mon cousin.", "Ah, that I tell no one, not even my cousin."]]]
        ]],
        // Question hors du monde (« Où est Ben Laden ? ») : il ne sait pas, il n'invente rien.
        ["otWorld", [
            ["*", [["Je ne sais pas de quoi vous parlez.", "I don't know what you're talking about."], ["Aucune idée, {sir}.", "No idea, {sir}."], ["Ça ne me dit rien.", "That doesn't ring a bell."],
                ["Je ne connais pas ça.", "I don't know that."], ["Je ne vois pas de quoi vous parlez.", "I don't see what you're talking about."], ["Ici, on ne s'occupe pas de ça.", "Around here we don't bother with that."],
                ["Je ne suis pas au courant.", "I'm not aware of that."], ["Vous me demandez ça, à moi ?", "You're asking me that?"]]],
            ["nerveux", [["Je… je ne sais pas qui c'est.", "I… I don't know who that is."], ["Je ne sais rien, je vous jure.", "I don't know anything, I swear."], ["Ça ne me dit rien du tout…", "That means nothing to me…"],
                ["Je ne connais pas, vraiment.", "I don't know it, really."], ["Pourquoi vous me demandez ça ?", "Why are you asking me that?"], ["Je ne vois pas…", "I don't see…"]]],
            ["mefiant", [["C'est un piège, votre question ?", "Is your question a trap?"], ["Je ne vois pas le rapport avec moi.", "I don't see what it has to do with me."], ["Je ne sais pas, et ça ne me regarde pas.", "I don't know, and it's not my business."],
                ["Drôle de question.", "Strange question."], ["Demandez à quelqu'un d'autre.", "Ask someone else."], ["Hm. Aucune idée.", "Hm. No idea."]]],
            ["agressif", [["J'en sais rien, moi.", "How would I know?"], ["Vous vous moquez de moi ?", "Are you making fun of me?"], ["Question idiote.", "Idiotic question."],
                ["Qu'est-ce que j'en sais ?", "What do I know about it?"], ["Pas mon problème.", "Not my problem."], ["Vous avez fini ?", "Are you done?"]]],
            ["bavard", [["Oh, je ne sais pas, moi, je n'ai même pas la télé !", "Oh, I don't know, I don't even have a TV!"], ["Aucune idée ! Ici, les nouvelles arrivent avec trois semaines de décalage.", "No idea! News gets here three weeks late."],
                ["Ça ne me dit rien. Demandez au café, peut-être.", "Doesn't ring a bell. Ask at the café, maybe."], ["Vous me posez des colles, là !", "You've got me stumped there!"]]]
        ]],
        // Absurde (« Donne-moi la lune ») : tons en plus des phrases générales de la banque.
        ["absurd", [
            ["nerveux", [["Je… je ne comprends pas.", "I… I don't understand."], ["C'est une blague ?", "Is this a joke?"], ["Je ne peux pas faire ça…", "I can't do that…"],
                ["Pourquoi vous me demandez ça ?", "Why are you asking me that?"], ["Je ne sais pas quoi répondre…", "I don't know what to answer…"], ["(Il rit nerveusement.)", "(He laughs nervously.)"]]],
            ["mefiant", [["Vous me testez ?", "Are you testing me?"], ["Très drôle.", "Very funny."], ["Je ne joue pas.", "I'm not playing."], ["Et puis quoi encore ?", "What next?"],
                ["(Il hausse un sourcil.)", "(He raises an eyebrow.)"], ["Soyez sérieux.", "Be serious."]]],
            ["agressif", [["Vous vous foutez de moi ?", "Are you messing with me?"], ["Arrêtez vos bêtises.", "Stop your nonsense."], ["J'ai l'air d'un magicien ?", "Do I look like a magician?"],
                ["Ça suffit, les blagues.", "Enough with the jokes."], ["N'importe quoi.", "Nonsense."], ["Vous avez fini de délirer ?", "Done raving?"]]]
        ]],
        // Retour au sujet après quelques échanges de small talk.
        ["otSteer", [
            ["*", [["Vous vouliez quelque chose ?", "Did you want something?"], ["Je peux vous aider ?", "Can I help you?"], ["C'est pour quoi, au juste ?", "What is it about, exactly?"],
                ["Il y a un problème ?", "Is there a problem?"], ["Vous avez besoin de quelque chose ?", "Do you need something?"], ["Je peux y aller, ou… ?", "Can I go, or…?"],
                ["Bon, qu'est-ce que je peux faire pour vous ?", "So, what can I do for you?"], ["Vous voulez autre chose ?", "Anything else you want?"]]],
            ["mefiant", [["Vous n'êtes pas là pour bavarder, si ?", "You're not here to chat, are you?"], ["Venez-en au fait.", "Get to the point."], ["Qu'est-ce que vous voulez vraiment ?", "What do you really want?"],
                ["On parle pour parler, là.", "We're just talking for nothing."], ["Dites-moi ce que vous voulez.", "Tell me what you want."], ["Hm. Et donc ?", "Hm. So?"]]],
            ["bavard", [["Mais je parle, je parle… vous vouliez quelque chose ?", "But I talk and talk… did you want something?"], ["Enfin, je vous retiens. Qu'est-ce qui vous amène ?", "Anyway, I'm keeping you. What brings you?"],
                ["Bon, et vous, qu'est-ce que je peux faire pour vous ?", "Well, and you, what can I do for you?"], ["Mais assez parlé de moi ! Vous cherchez quelque chose ?", "But enough about me! Looking for something?"]]]
        ]],
        // ---------------------------------------------------------------- relance (« Et alors ? ») : il justifie, il n'invente rien
        ["otMore.refuse", [
            ["*", [["Alors j'en ai assez. Je n'ai rien fait, je veux rentrer chez moi.", "So I've had enough. I haven't done anything, I want to go home."],
                ["Alors rien. Je ne vois pas pourquoi je ferais ça.", "So nothing. I don't see why I'd do that."], ["Parce que je n'ai rien à me reprocher.", "Because I have nothing to blame myself for."],
                ["Parce que vous n'avez aucune raison de me demander ça.", "Because you have no reason to ask me that."], ["Je suis fatigué de tout ça, c'est tout.", "I'm tired of all this, that's all."],
                ["Je veux juste qu'on me laisse tranquille.", "I just want to be left alone."]]],
            ["agressif", [["Alors rien. Vous avez entendu.", "So nothing. You heard me."], ["Parce que j'en ai marre, voilà.", "Because I'm fed up, that's why."], ["Je n'ai pas à me justifier.", "I don't have to justify myself."],
                ["Parce que c'est non.", "Because it's no."], ["Vous n'avez rien d'autre à faire ?", "Got nothing else to do?"], ["Je n'ai plus envie de discuter.", "I don't feel like talking anymore."]]]
        ]],
        ["otMore.deny", [
            ["*", [["Alors rien. Je vous ai dit la vérité.", "So nothing. I told you the truth."], ["C'est la vérité, je n'ai rien d'autre à ajouter.", "It's the truth, I have nothing to add."],
                ["Parce que c'est vrai, voilà tout.", "Because it's true, that's all."], ["Vérifiez, vous verrez bien.", "Check, you'll see."], ["Je ne sais pas quoi vous dire de plus.", "I don't know what else to tell you."],
                ["Je n'ai rien à cacher, c'est tout.", "I have nothing to hide, that's all."]]]
        ]],
        ["otMore.nothing", [
            ["*", [["Je vous l'ai dit, je ne sais rien de plus.", "I told you, I don't know anything more."], ["Rien. Il n'y a rien à ajouter.", "Nothing. There's nothing to add."],
                ["Je ne peux pas dire ce que je ne sais pas.", "I can't say what I don't know."], ["C'est tout, vraiment.", "That's all, really."], ["Je n'en sais pas plus, désolé.", "I don't know more, sorry."],
                ["Je ne vais pas inventer, {sir}.", "I'm not going to make things up, {sir}."]]]
        ]],
        ["otMore.intel", [
            ["*", [["C'est tout ce que je sais, vraiment.", "That's all I know, really."], ["Je ne peux pas vous en dire plus.", "I can't tell you more."], ["Je vous ai dit ce que je sais.", "I told you what I know."],
                ["Le reste, je ne le sais pas.", "The rest, I don't know."], ["Je n'en sais pas davantage.", "I don't know any more than that."], ["C'est déjà beaucoup, croyez-moi.", "That's already a lot, believe me."]]]
        ]],
        ["otMore.cover", [
            ["*", [["C'est tout. Il n'y a rien de plus à dire.", "That's all. There's nothing more to say."], ["Rien de spécial, je vis ma vie.", "Nothing special, I live my life."],
                ["C'est simple, c'est comme ça.", "It's simple, that's how it is."], ["Je ne vois pas quoi ajouter.", "I don't see what to add."], ["Voilà, c'est tout.", "There, that's all."],
                ["Rien d'autre, {sir}.", "Nothing else, {sir}."]]]
        ]],
        ["otMore.comply", [
            ["*", [["Je fais ce que vous dites. Qu'est-ce que vous voulez de plus ?", "I'm doing what you say. What more do you want?"], ["C'est fait. Autre chose ?", "It's done. Anything else?"],
                ["Je coopère, vous voyez bien.", "I'm cooperating, you can see that."], ["Voilà. Je peux y aller, après ?", "There. Can I go, after?"], ["Je ne cherche pas d'ennuis.", "I'm not looking for trouble."],
                ["Dites-moi ce qu'il faut faire, je le fais.", "Tell me what to do, I'll do it."]]]
        ]],
        ["otMore.small", [
            ["*", [["Rien de plus. La vie continue, comme on peut.", "Nothing more. Life goes on, as best it can."], ["C'est tout, on fait avec.", "That's all, we make do."], ["Rien de spécial, vraiment.", "Nothing special, really."],
                ["Ici, les jours se ressemblent.", "Here, the days are all alike."], ["Oh, rien. Je parlais, c'est tout.", "Oh, nothing. I was just talking."], ["C'est comme ça, ici.", "That's how it is here."]]]
        ]],
        ["otMore.offended", [
            ["*", [["Alors vous devriez avoir honte, c'est tout.", "So you should be ashamed, that's all."], ["Alors rien. Je n'ai pas envie d'en parler.", "So nothing. I don't feel like talking about it."],
                ["Parce qu'on ne parle pas comme ça aux gens.", "Because you don't talk to people like that."], ["Vous savez très bien pourquoi.", "You know very well why."], ["Je n'ai rien à ajouter.", "I have nothing to add."],
                ["Restons-en là.", "Let's leave it there."]]]
        ]],
        ["otMore.fear", [
            ["*", [["Alors j'ai peur, voilà. Je ne veux pas d'ennuis.", "So I'm scared, there. I don't want trouble."], ["Parce que je ne sais pas ce que vous allez me faire.", "Because I don't know what you're going to do to me."],
                ["Je veux juste que ça se passe bien.", "I just want this to go well."], ["Je ne veux de mal à personne.", "I don't want to hurt anyone."], ["J'ai une famille, vous comprenez ?", "I have a family, you understand?"],
                ["Je veux rentrer chez moi, c'est tout.", "I want to go home, that's all."]]]
        ]],
        ["otMore.miss", [
            ["*", [["Je ne sais même pas de quoi on parle.", "I don't even know what we're talking about."], ["Et alors quoi ? Je n'ai pas compris votre question.", "So what? I didn't understand your question."],
                ["Je ne vois pas ce que vous voulez.", "I don't see what you want."], ["Dites-moi d'abord ce que vous voulez.", "Tell me first what you want."], ["Vous parlez de quoi ?", "What are you talking about?"],
                ["Je suis perdu, là.", "I'm lost here."]]]
        ]],
        ["otMore.none", [
            ["*", [["C'est tout ce que j'ai à dire.", "That's all I have to say."], ["Pourquoi quoi ?", "Why what?"], ["Comment ça ?", "What do you mean?"], ["Je n'ai rien dit de spécial.", "I didn't say anything special."],
                ["De quoi vous parlez ?", "What are you talking about?"], ["Rien. Il n'y a rien.", "Nothing. There's nothing."]]]
        ]],
        // ---------------------------------------------------------------- ordres de base : réaction propre à l'ordre
        ["comply.leave", [
            ["*", [["Merci. Bonne journée.", "Thanks. Have a good day."], ["Enfin. Merci, {sir}.", "Finally. Thank you, {sir}."], ["Je peux y aller ? Merci.", "I can go? Thank you."],
                ["(Il reprend sa route sans se retourner.)", "(He goes on his way without looking back.)"], ["Bon. Au revoir.", "Right. Goodbye."], ["Merci, au revoir.", "Thanks, goodbye."]]],
            ["nerveux", [["Merci… merci beaucoup.", "Thank you… thank you so much."], ["(Il s'éloigne d'un pas pressé.)", "(He walks off quickly.)"], ["D'accord, je m'en vais, je m'en vais.", "Okay, I'm going, I'm going."]]],
            ["agressif", [["Pas trop tôt.", "About time."], ["(Il part en marmonnant.)", "(He leaves, muttering.)"], ["Enfin.", "Finally."]]]
        ]],
        ["comply.halt", [
            ["*", [["D'accord, je ne bouge plus.", "Okay, I'm not moving."], ["(Il s'arrête net.)", "(He stops dead.)"], ["Je reste là, {sir}.", "I'm staying here, {sir}."],
                ["Voilà, je m'arrête.", "There, I'm stopping."], ["Je ne bouge pas.", "I'm not moving."], ["D'accord. Qu'est-ce qu'il y a ?", "Okay. What is it?"]]],
            ["nerveux", [["Je ne bouge pas, je ne bouge pas !", "I'm not moving, I'm not moving!"], ["(Il se fige.) D'accord…", "(He freezes.) Okay…"], ["Je m'arrête ! Ne tirez pas.", "I'm stopping! Don't shoot."]]]
        ]],
        ["comply.handsup", [
            ["*", [["(Il lève les mains.) Voilà.", "(He raises his hands.) There."], ["D'accord, les mains en l'air.", "Okay, hands up."], ["(Il lève lentement les mains.)", "(He slowly raises his hands.)"],
                ["Voilà, je n'ai rien.", "There, I have nothing."], ["Calmez-vous, je lève les mains.", "Calm down, I'm raising my hands."], ["(Les mains bien visibles.) Comme ça ?", "(Hands in plain sight.) Like this?"]]],
            ["nerveux", [["(Il lève les mains en tremblant.) Ne tirez pas !", "(He raises his hands, shaking.) Don't shoot!"], ["Voilà, voilà ! Mes mains !", "There, there! My hands!"], ["D'accord ! D'accord !", "Okay! Okay!"]]]
        ]],
        ["comply.follow", [
            ["*", [["D'accord, je vous suis.", "Okay, I'll follow you."], ["(Il vous emboîte le pas.)", "(He falls in behind you.)"], ["On va où ?", "Where are we going?"], ["Je viens, {sir}.", "I'm coming, {sir}."],
                ["Bon. Je vous suis.", "Fine. I'll follow."], ["C'est loin ?", "Is it far?"]]],
            ["nerveux", [["On va où ? Je… d'accord.", "Where are we going? I… okay."], ["(Il vous suit, inquiet.)", "(He follows you, worried.)"], ["D'accord, d'accord, je viens.", "Okay, okay, I'm coming."]]]
        ]],
        ["comply.kneel", [
            ["*", [["(Il se met à genoux.)", "(He kneels down.)"], ["D'accord, je me mets à terre.", "Okay, I'm getting down."], ["Voilà. Doucement.", "There. Easy."], ["(Il s'agenouille lentement.)", "(He slowly kneels.)"],
                ["Comme ça ?", "Like this?"], ["Je suis à terre, {sir}.", "I'm down, {sir}."]]],
            ["nerveux", [["(Il tombe à genoux.) Ne tirez pas !", "(He drops to his knees.) Don't shoot!"], ["D'accord ! Je me couche !", "Okay! I'm getting down!"], ["Voilà, voilà…", "There, there…"]]]
        ]],
        ["noComply.leave", [
            ["*", [["Je partirai quand je voudrai.", "I'll leave when I want."], ["Non, je reste ici.", "No, I'm staying here."], ["Vous ne me donnez pas d'ordres.", "You don't give me orders."],
                ["(Il croise les bras.)", "(He crosses his arms.)"], ["C'est chez moi, ici.", "This is my home."], ["Pas encore.", "Not yet."]]]
        ]],
        ["leaveCuffed", [
            ["*", [["Partir ? Avec ces menottes ?", "Leave? With these cuffs?"], ["Enlevez-moi ça d'abord.", "Take these off first."], ["Comment je pars, comme ça ?", "How am I supposed to leave like this?"],
                ["(Il montre ses poignets.)", "(He shows his wrists.)"], ["Détachez-moi, alors.", "Untie me, then."], ["Je veux bien, mais… les menottes.", "I'd like to, but… the cuffs."]]]
        ]],
        ["noComply.halt", [
            ["*", [["Pourquoi je m'arrêterais ? Je n'ai rien fait.", "Why would I stop? I haven't done anything."], ["J'ai le droit de marcher.", "I have the right to walk."], ["Je suis pressé.", "I'm in a hurry."],
                ["(Il ne s'arrête pas.)", "(He doesn't stop.)"], ["Laissez-moi passer.", "Let me through."], ["Je n'ai pas le temps.", "I don't have time."]]]
        ]],
        ["followCuffed", [
            ["*", [["Avec les menottes ? Emmenez-moi, alors.", "With the cuffs on? Take me, then."], ["(Il montre ses poignets attachés.) Je ne peux pas marcher comme ça.", "(He shows his bound wrists.) I can't walk like this."],
                ["Tenez-moi par le bras, je vous suis.", "Hold my arm and I'll come."], ["Menotté, je n'irai pas loin tout seul.", "Cuffed, I won't get far on my own."],
                ["C'est vous qui me conduisez, non ?", "You're the one leading me, aren't you?"], ["(Il attend qu'on l'escorte.)", "(He waits to be escorted.)"]]]
        ]],
        ["noComply.follow", [
            ["effraye", [["Non… non, je ne vais nulle part avec vous.", "No… no, I'm not going anywhere with you."], ["(Il recule d'un pas.) Laissez-moi ici, s'il vous plaît.", "(He takes a step back.) Leave me here, please."],
                ["Pour m'emmener où ? Non, j'ai trop peur.", "To take me where? No, I'm too scared."], ["Je ne bouge pas. Je vous en prie.", "I'm not moving. I beg you."]]],
            ["*", [["Je ne vous suis nulle part.", "I'm not following you anywhere."], ["Où ça ? Pourquoi ?", "Where? Why?"], ["Je ne bouge pas d'ici.", "I'm not moving from here."],
                ["(Il reste planté là.)", "(He stays rooted there.)"], ["Pas sans savoir pourquoi.", "Not without knowing why."], ["Non. Dites-moi d'abord ce qui se passe.", "No. Tell me first what's going on."]]]
        ]],
        ["noComply.handsup", [
            ["*", [["Pourquoi ? Je n'ai rien fait.", "Why? I haven't done anything."], ["Je ne suis pas un criminel.", "I'm not a criminal."], ["(Il garde les mains le long du corps.)", "(He keeps his hands by his sides.)"],
                ["Non. Expliquez-moi d'abord.", "No. Explain first."], ["Vous n'avez pas de raison.", "You have no reason."], ["Je ne lève rien du tout.", "I'm not raising anything."]]]
        ]],
        ["noComply.kneel", [
            ["*", [["À genoux ? Non.", "On my knees? No."], ["Je ne me mettrai pas par terre.", "I won't get on the ground."], ["(Il reste debout.)", "(He stays standing.)"],
                ["Je n'ai rien fait pour ça.", "I've done nothing to deserve that."], ["Pas question.", "No way."], ["Vous plaisantez ?", "Are you joking?"]]]
        ]]
    ]]
]
