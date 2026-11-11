/*
    Banques de phrases des IA (fn_iarpGen) : intention -> ton -> [[français, anglais]…]. Ton « * » : toutes les
    personnalités. Tons : calme, nerveux, agressif, mefiant, bavard, laconique, corrompu, effraye.
    Variables remplacées à l'affichage (fn_iarpFill) : {city} {employer} {job} {travel} {pob} {contact} {address} {name}
    {first} {age} {family} {know} {sir} {player} {weapon}. Une phrase n'utilise que des faits de la couverture de l'IA :
    sans le fait, le serveur choisit « dunno » (jamais d'invention).
    Décors : _open (début), _hes (hésitation), _close (fin), _stage (didascalie selon le contexte), _local (langue du
    pays, traduite entre crochets pour qui ne la parle pas), _told (déjà raconté à un autre joueur).
    Une mission peut ajouter des phrases avec un fichier iarp_bank.sqf à sa racine, même format : elles s'ajoutent.
    Phrase [fr, en, [étiquettes]] : « hint » (laisse entendre qu'elle sait quelque chose) et thèmes de scénario (armed,
    explosives, military, wanted, drugs, politics) : écartées quand le cadre fixé par le maître du jeu les interdit (fn_iarpGen).
    Clé « réplique.intention » (« intelNo.seenArmed », « comply.stepBack ») : variante propre à une question, prise avant la
    réplique générale quand elle existe (fn_iarpServer).
    Exemple : createHashMapFromArray [["greet", createHashMapFromArray [["*", [["Kalimera !", "Kalimera!"]]]]]]
*/
private _m = { createHashMapFromArray _this };
private _rows = [
    // ------------------------------------------------------------------ couverture
    ["origin", [
        ["*", [
            ["Je viens de {city}.", "I'm from {city}."],
            ["De {city}, j'y habite.", "From {city}, that's where I live."],
            ["J'habite à {city}, depuis longtemps.", "I live in {city}, have for a long time."],
            ["{city}. C'est là que je vis.", "{city}. That's where I live."],
            ["Moi ? De {city}.", "Me? From {city}."],
            ["Je suis de {city}, {sir}.", "I'm from {city}, {sir}."],
            ["À {city}, pas loin d'ici.", "In {city}, not far from here."],
            ["On vit à {city}, ma famille et moi.", "We live in {city}, my family and me."]
        ]],
        ["nerveux", [
            ["Je… je viens de {city}. Pourquoi ?", "I… I'm from {city}. Why?"],
            ["De {city} ! De {city}, je vous jure.", "From {city}! From {city}, I swear."]
        ]],
        ["mefiant", [
            ["Pourquoi vous voulez savoir ? … {city}.", "Why do you want to know? … {city}."],
            ["{city}. Ça vous regarde ?", "{city}. Is that your business?"]
        ]],
        ["laconique", [["{city}.", "{city}."], ["De {city}.", "{city}."]]],
        ["bavard", [
            ["Oh, de {city} ! Vous connaissez ? Il y a le marché le jeudi, et le café de mon cousin.", "Oh, from {city}! Do you know it? There's the market on Thursdays, and my cousin's café."],
            ["Je suis de {city}, toute ma vie là-bas, mon père aussi, et son père avant lui.", "I'm from {city}, all my life, my father too, and his father before him."]
        ]],
        ["agressif", [["De {city}. Et alors ?", "From {city}. So what?"], ["{city}. Vous avez fini ?", "{city}. Are you done?"]]],
        ["effraye", [["De {city}… s'il vous plaît, je n'ai rien fait.", "From {city}… please, I haven't done anything."]]]
    ]],
    ["employer", [
        ["*", [
            ["Je travaille pour {employer}.", "I work for {employer}."],
            ["Chez {employer}.", "At {employer}."],
            ["{employer}, depuis quelques années.", "{employer}, for a few years now."],
            ["Mon employeur ? {employer}.", "My employer? {employer}."],
            ["Je suis chez {employer}, {sir}.", "I'm with {employer}, {sir}."],
            ["Pour {employer}. Vous pouvez demander là-bas.", "For {employer}. You can ask them."]
        ]],
        ["nerveux", [["Pour… pour {employer}. C'est un vrai travail.", "For… for {employer}. It's a real job."], ["{employer} ! Je travaille pour {employer}.", "{employer}! I work for {employer}."]]],
        ["mefiant", [["Pourquoi ? … {employer}.", "Why? … {employer}."], ["{employer}. Vous allez les appeler ?", "{employer}. Are you going to call them?"]]],
        ["laconique", [["{employer}.", "{employer}."]]],
        ["bavard", [["Chez {employer} ! Le patron est dur, mais il paie à l'heure, c'est rare par ici.", "At {employer}! The boss is tough, but he pays on time, that's rare around here."]]],
        ["agressif", [["{employer}. Ça change quoi pour vous ?", "{employer}. What difference does it make to you?"]]],
        ["effraye", [["Je… pour {employer}, je ne fais que mon travail.", "I… for {employer}, I'm just doing my job."]]]
    ]],
    ["job", [
        ["*", [
            ["Je suis {job}.", "I'm a {job}."],
            ["{job}, de métier.", "{job}, by trade."],
            ["Je travaille comme {job}.", "I work as a {job}."],
            ["Mon métier ? {job}.", "My job? {job}."],
            ["{job}. Ça nourrit la famille.", "{job}. It feeds the family."],
            ["Je fais {job}, {sir}.", "I'm a {job}, {sir}."]
        ]],
        ["nerveux", [["Je… {job}. Je suis {job}.", "I… {job}. I'm a {job}."]]],
        ["mefiant", [["{job}. Pourquoi, c'est interdit ?", "{job}. Why, is that forbidden?"]]],
        ["laconique", [["{job}.", "{job}."]]],
        ["bavard", [["{job}, depuis que je suis jeune. Ce n'est pas facile, mais j'aime ça.", "{job}, since I was young. It isn't easy, but I like it."]]],
        ["agressif", [["{job}. Je bosse, moi.", "{job}. I work, me."]]]
    ]],
    ["travel", [
        ["*", [
            ["{travel}.", "{travel}."],
            ["Eh bien… {travel}.", "Well… {travel}."],
            ["{travel}, rien de plus.", "{travel}, nothing more."],
            ["Je vous l'ai dit : {travel}.", "I told you: {travel}."],
            ["C'est simple : {travel}.", "It's simple: {travel}."],
            ["{travel}, {sir}.", "{travel}, {sir}."]
        ]],
        ["nerveux", [["{travel}… voilà, c'est tout.", "{travel}… that's all."], ["Rien de spécial ! {travel}.", "Nothing special! {travel}."]]],
        ["mefiant", [["Ça ne vous regarde pas trop… {travel}.", "That's not really your business… {travel}."]]],
        ["laconique", [["{travel}.", "{travel}."]]],
        ["bavard", [["{travel}. Et avec ces routes, ça prend des heures, vous savez.", "{travel}. And with these roads, it takes hours, you know."]]],
        ["agressif", [["{travel}. Je peux circuler, non ?", "{travel}. I'm allowed to travel, right?"]]]
    ]],
    ["pob", [
        ["*", [
            ["Je suis né à {pob}.", "I was born in {pob}."],
            ["À {pob}.", "In {pob}."],
            ["{pob}, comme mes parents.", "{pob}, like my parents."],
            ["Né à {pob}, {sir}.", "Born in {pob}, {sir}."],
            ["À {pob}. C'est écrit sur mes papiers.", "In {pob}. It's on my papers."]
        ]],
        ["nerveux", [["À… {pob}. Oui, {pob}.", "In… {pob}. Yes, {pob}."]]],
        ["laconique", [["{pob}.", "{pob}."]]],
        ["mefiant", [["{pob}. Vous voulez aussi ma pointure ?", "{pob}. Do you want my shoe size too?"]]]
    ]],
    ["contact", [
        ["*", [
            ["Vous pouvez appeler {contact}.", "You can call {contact}."],
            ["{contact} peut le confirmer.", "{contact} can confirm it."],
            ["Demandez à {contact}, il me connaît bien.", "Ask {contact}, they know me well."],
            ["{contact}. Je vous donne son numéro si vous voulez.", "{contact}. I can give you the number."],
            ["{contact}, {sir}.", "{contact}, {sir}."]
        ]],
        ["nerveux", [["{contact} ! Appelez {contact}, il vous dira.", "{contact}! Call {contact}, they'll tell you."]]],
        ["laconique", [["{contact}.", "{contact}."]]],
        ["mefiant", [["Pourquoi, vous ne me croyez pas ? … {contact}.", "Why, you don't believe me? … {contact}."]]]
    ]],
    ["address", [
        ["*", [["L'adresse est sur ma carte, {sir}.", "The address is on my card, {sir}."], ["{address}, vous pouvez vérifier.", "{address}, you can check."], 
            ["J'habite {address}.", "I live at {address}."],
            ["{address}.", "{address}."],
            ["Mon adresse ? {address}.", "My address? {address}."],
            ["C'est {address}, {sir}.", "It's {address}, {sir}."]
        ]],
        ["mefiant", [["{address}. Vous allez venir chez moi ?", "{address}. Are you coming to my house?"]]],
        ["laconique", [["{address}.", "{address}."]]]
    ]],
    ["name", [
        ["*", [
            ["Je m'appelle {name}.", "My name is {name}."],
            ["{name}.", "{name}."],
            ["{name}, {sir}.", "{name}, {sir}."],
            ["On m'appelle {first}. {name}.", "People call me {first}. {name}."],
            ["Mon nom est {name}.", "My name is {name}."],
            ["{name}. C'est sur mes papiers.", "{name}. It's on my papers."]
        ]],
        ["nerveux", [["{name}… je m'appelle {name}.", "{name}… my name is {name}."]]],
        ["mefiant", [["Et vous, c'est quoi votre nom ? … {name}.", "And what's yours? … {name}."]]],
        ["laconique", [["{name}.", "{name}."]]],
        ["bavard", [["{name} ! Mais tout le monde m'appelle {first}, même ma mère.", "{name}! But everyone calls me {first}, even my mother."]]],
        ["agressif", [["{name}. Notez-le bien.", "{name}. Write it down."]]]
    ]],
    // « Ton prénom ? » : le prénom seul, précisément.
    ["firstName", [
        ["*", [["Mon prénom ? {first}.", "My first name? {first}."], ["{first}.", "{first}."], ["C'est {first}, {sir}.", "It's {first}, {sir}."], ["{first}. {name} en entier.", "{first}. {name} in full."],
            ["On m'appelle {first}.", "People call me {first}."], ["{first}, comme mon grand-père.", "{first}, like my grandfather."]]],
        ["mefiant", [["{first}. Pourquoi ?", "{first}. Why?"]]]
    ]],
    ["age", [
        ["*", [["J'aurai {age} ans cette année.", "I'll be {age} this year."], ["{age}, pourquoi ?", "{age}, why?"], ["J'ai {age} ans.", "I'm {age}."], ["{age} ans.", "{age} years old."], ["{age} ans, {sir}.", "{age}, {sir}."], ["{age}. Je ne les fais pas, hein ?", "{age}. I don't look it, eh?"]]],
        ["laconique", [["{age}.", "{age}."]]],
        ["mefiant", [["{age}. Pourquoi ?", "{age}. Why?"]]]
    ]],
    ["family", [
        ["*", [["{family}, c'est tout ce que j'ai.", "{family}, that's all I have."], ["Ça va, merci. {family}.", "They're fine, thanks. {family}."], ["{family}.", "{family}."], ["Ma famille ? {family}.", "My family? {family}."], ["{family}. Ils m'attendent.", "{family}. They're waiting for me."], ["{family}, {sir}.", "{family}, {sir}."]]],
        ["effraye", [["{family}… s'il vous plaît, laissez-les en dehors de ça.", "{family}… please, leave them out of this."]]],
        ["mefiant", [["Laissez ma famille tranquille. … {family}.", "Leave my family alone. … {family}."]]],
        ["laconique", [["{family}.", "{family}."]]]
    ]],
    // Fait absent de la couverture : la personne ne sait pas, elle n'invente rien.
    ["dunno", [
        ["*", [["Je n'en ai aucune idée, {sir}.", "I have no idea, {sir}."], ["Je ne sais pas.", "I don't know."], ["Aucune idée.", "No idea."], ["Je ne saurais pas vous dire.", "I couldn't tell you."], ["Ça, je ne sais pas, {sir}.", "That I don't know, {sir}."], ["Je préfère ne pas répondre.", "I'd rather not answer."]]],
        ["laconique", [["Sais pas.", "Dunno."]]],
        ["nerveux", [["Je… je ne sais pas, vraiment.", "I… I really don't know."]]]
    ]],
    // État « refuse » de l'automate : il ne répond plus aux questions.
    ["refuse", [
        ["*", [["Je n'ai rien à vous dire.", "I have nothing to say to you."], ["(Il hausse les épaules sans répondre.)", "(He shrugs without answering.)"], ["Je ne réponds plus.", "I'm not answering anymore."], ["Laissez-moi tranquille.", "Leave me alone."], ["J'ai le droit de me taire.", "I have the right to remain silent."], ["(Il détourne le regard.)", "(He looks away.)"]]],
        ["agressif", [["Allez vous faire voir.", "Get lost."], ["Vous n'aurez rien de moi.", "You'll get nothing from me."]]]
    ]],
    // Fuite, agressivité, renforts : pas de réponse, seulement ce qu'on voit.
    ["silent", [
        ["*", [["(Il fait comme s'il n'avait rien entendu.)", "(He acts as if he hadn't heard.)"], ["(Il regarde ailleurs.)", "(He looks elsewhere.)"], ["(Il ne vous répond pas.)", "(He doesn't answer.)"], ["(Il vous ignore.)", "(He ignores you.)"], ["(Il ne vous écoute plus.)", "(He's not listening anymore.)"], ["(Aucune réponse.)", "(No answer.)"]]]
    ]],
    // ------------------------------------------------------------------ ordres
    ["comply", [
        ["*", [["D'accord, d'accord.", "Okay, okay."], ["Oui, {sir}.", "Yes, {sir}."], ["Comme vous voulez.", "As you wish."], ["Voilà, voilà.", "There, there."], ["Je fais ce que vous dites.", "I'm doing what you say."], ["Pas de problème.", "No problem."], ["(Il obéit sans un mot.)", "(He complies without a word.)"]]],
        ["nerveux", [["Oui ! Oui, je le fais, ne tirez pas !", "Yes! Yes, I'm doing it, don't shoot!"], ["D'accord… doucement…", "Okay… easy…"]]],
        ["effraye", [["Ne me faites pas de mal…", "Don't hurt me…"], ["Je fais tout ce que vous voulez !", "I'll do whatever you want!"]]],
        ["agressif", [["(Il obéit en vous fusillant du regard.)", "(He complies, glaring at you.)"], ["C'est bon, c'est bon.", "Fine, fine."]]],
        ["mefiant", [["…D'accord.", "…Fine."], ["(Il s'exécute lentement, sans vous quitter des yeux.)", "(He complies slowly, never taking his eyes off you.)"]]],
        ["laconique", [["Bien.", "Fine."], ["(Il hoche la tête.)", "(He nods.)"]]]
    ]],
    ["noComply", [
        ["*", [["Non.", "No."], ["Pourquoi je ferais ça ?", "Why would I do that?"], ["Je ne bougerai pas.", "I'm not moving."], ["(Il ne bouge pas.)", "(He doesn't move.)"], ["Vous n'avez pas le droit.", "You have no right."]]],
        ["agressif", [["Certainement pas.", "Absolutely not."], ["Essayez de m'y obliger.", "Try and make me."]]],
        ["nerveux", [["Je… non, non, je ne peux pas.", "I… no, no, I can't."]]],
        ["effraye", [["(Il est paralysé par la peur.)", "(He's frozen with fear.)"]]]
    ]],
    ["trunkNone", [
        ["*", [["Il n'y a pas de voiture, {sir}.", "There's no car, {sir}."], ["Quel véhicule ? Je suis venu à pied.", "What vehicle? I came on foot."], ["Je n'ai rien à ouvrir.", "I have nothing to open."], ["Quel coffre ? Je n'ai pas de voiture ici.", "What trunk? I don't have a car here."], ["Je suis à pied, {sir}.", "I'm on foot, {sir}."], ["Ce n'est pas mon véhicule.", "That's not my vehicle."]]]
    ]],
    ["dismountNone", [
        ["*", [["Je suis à pied, vous voyez bien.", "I'm on foot, you can see that."], ["Je ne suis pas en voiture.", "I'm not in a car."], ["(Il montre ses pieds.) Déjà à pied.", "(He points at his feet.) Already on foot."], ["Il n'y a rien dont descendre.", "There's nothing to get out of."], ["Je suis déjà à pied.", "I'm already on foot."], ["Descendre de quoi ?", "Get out of what?"]]]
    ]],
    // ------------------------------------------------------------------ fouille
    ["searchYes", [
        ["*", [["Je n'ai rien sur moi, vous verrez.", "I have nothing on me, you'll see."], ["Allez-y, je n'ai rien à cacher.", "Go ahead, I have nothing to hide."], ["Fouillez, si vous voulez.", "Search me if you want."], ["Faites vite, s'il vous plaît.", "Make it quick, please."], ["(Il écarte les bras.)", "(He spreads his arms.)"]]],
        ["nerveux", [["Euh… oui, bien sûr… allez-y.", "Uh… yes, of course… go ahead."]]]
    ]],
    ["searchNo", [
        ["*", [["Vous n'avez pas le droit de me fouiller.", "You have no right to search me."], ["Non, pas de fouille.", "No, no search."], ["Pourquoi ? Je n'ai rien fait.", "Why? I haven't done anything."], ["(Il recule d'un pas.)", "(He takes a step back.)"]]],
        ["agressif", [["Touchez-moi et vous verrez.", "Touch me and you'll see."]]],
        ["mefiant", [["Sur quel motif ?", "On what grounds?"]]]
    ]],
    // ------------------------------------------------------------------ pression
    ["threat", [
        ["*", [["Calmez-vous, je n'ai rien fait !", "Calm down, I haven't done anything!"], ["Pas la peine de menacer.", "No need for threats."], ["D'accord, d'accord, on se calme.", "Okay, okay, let's calm down."], ["Je n'ai rien à voir avec ça !", "I have nothing to do with it!"]]],
        ["agressif", [["Vous me menacez ? Vous ne savez pas à qui vous parlez.", "You're threatening me? You don't know who you're talking to."], ["Allez-y, essayez.", "Go on, try it."], ["Vous ne me faites pas peur.", "You don't scare me."]]],
        ["effraye", [["Non ! Non, pitié !", "No! No, please!"], ["Je vous en supplie, j'ai des enfants !", "I beg you, I have children!"]]],
        ["nerveux", [["S'il vous plaît, pas ça… je vais tout vous dire !", "Please, not that… I'll tell you everything!", ["hint"]], ["Ne tirez pas, ne tirez pas !", "Don't shoot, don't shoot!"]]],
        ["mefiant", [["Je connais mes droits.", "I know my rights."], ["Je veux parler à votre chef.", "I want to talk to your superior."]]]
    ]],
    ["bribeYes", [
        ["*", [["Bon… je n'ai rien vu, d'accord ?", "Well… I didn't see anything, okay?"], ["C'est gentil. Entre nous, alors.", "That's kind. Between us, then."], ["(Il empoche discrètement.)", "(He pockets it discreetly.)"], ["On peut s'arranger, oui.", "We can work something out, yes."]]],
        ["corrompu", [["Ah, là on se comprend.", "Ah, now we understand each other."], ["Avec ça, j'ai la mémoire qui revient.", "With that, my memory's coming back."], ["Vous êtes quelqu'un de raisonnable, {sir}.", "You're a reasonable person, {sir}."]]]
    ]],
    ["bribeNo", [
        ["*", [["Gardez votre argent.", "Keep your money."], ["Je ne suis pas à vendre.", "I'm not for sale."], ["Vous m'insultez.", "You insult me."], ["Non merci. Je ne veux pas d'ennuis.", "No thanks. I don't want trouble."]]],
        ["agressif", [["Vous me prenez pour qui ?", "Who do you take me for?"]]],
        ["effraye", [["Non, non, si on me voit prendre ça…", "No, no, if they see me take that…", ["hint"]]]]
    ]],
    ["badge", [
        ["*", [["Ah. Très bien, {sir}.", "Ah. Very well, {sir}."], ["(Il regarde le badge un long moment.)", "(He looks at the badge for a long time.)"], ["Bien, {sir}. Que voulez-vous savoir ?", "Alright, {sir}. What do you want to know?"], ["D'accord, je comprends.", "Okay, I understand."], ["(Il se redresse, plus attentif.)", "(He straightens up, more attentive.)"]]],
        ["mefiant", [["Un badge, ça s'imprime.", "A badge can be printed."]]],
        ["agressif", [["Et alors ? Ça ne vous donne pas tous les droits.", "So what? That doesn't give you every right."]]]
    ]],
    // ------------------------------------------------------------------ renseignement
    ["intelYes", [
        ["*", [["Puisque vous demandez… {know}", "Since you ask… {know}"], ["Bon… {know}", "Well… {know}"], ["Je vais vous dire ce que je sais : {know}", "I'll tell you what I know: {know}"], ["Entre nous ? {know}", "Between us? {know}"], ["(Il baisse la voix.) {know}", "(He lowers his voice.) {know}"]]],
        ["nerveux", [["Si on apprend que c'est moi… {know}", "If they find out it was me… {know}"]]],
        ["bavard", [["Ah, ça ! Tout le monde en parle au village : {know}", "Oh, that! Everyone in the village talks about it: {know}"]]]
    ]],
    // Paliers (savoir « Vague : / Précis : / Complet : ») : vague (pression, confiance moyenne), complet (confiance, aveu).
    ["intelVague", [
        ["*", [["J'ai entendu dire que… {know}", "I've heard that… {know}"], ["Je ne sais pas grand-chose. {know}", "I don't know much. {know}"],
            ["On raconte des choses… {know}", "People say things… {know}"], ["(Il hésite.) Peut-être… {know}", "(He hesitates.) Maybe… {know}"],
            ["Je ne suis sûr de rien, mais… {know}", "I'm not sure of anything, but… {know}"], ["Tout ce que je sais : {know}", "All I know is: {know}"]]],
        ["nerveux", [["Je… je ne veux pas d'ennuis. {know} C'est tout.", "I… I don't want trouble. {know} That's all."]]]
    ]],
    ["intelFull", [
        ["*", [["Bon. Je vais tout vous dire : {know}", "Fine. I'll tell you everything: {know}"], ["(Il baisse la voix.) Écoutez bien : {know}", "(He lowers his voice.) Listen carefully: {know}"],
            ["Vous m'avez rendu service, alors voilà : {know}", "You did right by me, so here: {know}"], ["Il y a autre chose. {know}", "There's something else. {know}"],
            ["Ce que personne ne vous dira : {know}", "What nobody else will tell you: {know}"], ["(Il regarde autour de lui.) {know} Mais vous ne tenez pas ça de moi.", "(He looks around.) {know} But you didn't hear it from me."]]]
    ]],
    ["intelAgain", [
        ["*", [["Je vous l'ai déjà dit : {know}", "I already told you: {know}"], ["Comme je vous disais : {know}", "Like I was saying: {know}"],
            ["Encore ? {know}", "Again? {know}"], ["Je n'en sais pas plus. {know}", "I don't know more than that. {know}"],
            ["Je me répète : {know}", "I'll say it again: {know}"], ["(Il soupire.) {know} C'est tout ce que je sais.", "(He sighs.) {know} That's all I know."]]]
    ]],
    ["intelNo", [
        ["*", [["Je ne sais rien.", "I don't know anything."], ["Je n'ai rien vu.", "I didn't see anything."], ["Je ne me mêle pas de ça.", "I stay out of that.", ["hint"]], ["Demandez à quelqu'un d'autre.", "Ask someone else."], ["Rien à signaler, {sir}.", "Nothing to report, {sir}."],
            ["Non, je n'ai rien vu de spécial.", "No, I haven't seen anything special."], ["Je ne suis au courant de rien.", "I'm not aware of anything."]]],
        ["nerveux", [["Rien ! Je ne sais rien, je vous jure.", "Nothing! I don't know anything, I swear."]]],
        ["mefiant", [["Et si je savais quelque chose, pourquoi je vous le dirais ?", "And if I knew something, why would I tell you?", ["hint"]]]],
        ["corrompu", [["Ça dépend… qu'est-ce que j'y gagne ?", "That depends… what's in it for me?", ["hint"]]]],
        ["effraye", [["S'ils savent que je parle, ils me tuent…", "If they know I talk, they'll kill me…", ["armed", "hint"]]]]
    ]],
    ["sensitive", [
        ["*", [["Je ne vois pas de quoi vous parlez.", "I don't know what you're talking about."], ["Pourquoi vous me demandez ça ?", "Why are you asking me that?"], ["Je ne sais rien là-dessus.", "I know nothing about that."], ["(Il change de sujet un peu trop vite.)", "(He changes the subject a little too fast.)"]]],
        ["nerveux", [["Quoi ? Non… non, rien à voir avec moi.", "What? No… no, nothing to do with me."]]],
        ["agressif", [["Ne parlez pas de ça.", "Don't talk about that."]]]
    ]],
    ["weaponsNo", [
        ["*", [["Je n'ai jamais touché une arme.", "I've never touched a weapon."], ["Rien, fouillez-moi si vous voulez.", "Nothing, search me if you want."], ["Une arme ? Pour quoi faire ?", "A weapon? What for?"], ["Non, je ne suis pas armé.", "No, I'm not armed."], ["Pas d'arme, {sir}.", "No weapon, {sir}."], ["Moi ? Jamais.", "Me? Never."]]],
        ["nerveux", [["Non ! Non, aucune arme.", "No! No weapon at all."]]]
    ]],
    ["weaponsYes", [
        ["*", [["Oui… {weapon}. Je l'ai sur moi.", "Yes… {weapon}. I have it on me."], ["{weapon}. J'ai le droit, non ?", "{weapon}. I'm allowed, aren't I?"], ["Juste {weapon}, pour la route.", "Just {weapon}, for the road."], ["Oui, j'ai {weapon}. Pour me défendre.", "Yes, I have {weapon}. For self-defence."], ["J'ai {weapon}, oui.", "I have {weapon}, yes."], ["Juste {weapon}. Tout le monde en a une, ici.", "Just {weapon}. Everyone has one here.", ["armed"]]]],
        ["agressif", [["Oui, et alors ?", "Yes, so what?"]]]
    ]],
    // ------------------------------------------------------------------ confrontation (« vos papiers sont faux », « tu mens »)
    // Aveu : {secret} (secret de l'atelier ou ligne libre de l'histoire), {truth} (la vérité sur un mensonge).
    ["confess", [
        ["*", [["(Il baisse les yeux.) D'accord… {secret}", "(He lowers his eyes.) Alright… {secret}"], ["Bon. Bon, c'est vrai… {secret}", "Fine. Fine, it's true… {secret}"],
            ["(Il craque.) Je vais tout vous dire. {secret}", "(He breaks down.) I'll tell you everything. {secret}"], ["Vous avez gagné. {secret}", "You win. {secret}"],
            ["(Long silence.) … {secret}", "(Long silence.) … {secret}"], ["Je n'en peux plus de mentir. {secret}", "I can't keep lying. {secret}"]]],
        ["nerveux", [["D'accord, d'accord ! {secret} Ne me faites pas de mal…", "Okay, okay! {secret} Don't hurt me…"]]],
        ["agressif", [["(Il crache par terre.) Très bien. {secret} Content ?", "(He spits on the ground.) Fine. {secret} Happy?"]]],
        ["effraye", [["Pitié… {secret} C'est tout, je vous jure.", "Please… {secret} That's all, I swear."]]]
    ]],
    ["confessTruth", [
        ["*", [["Bon… c'est vrai, j'ai menti : {truth}.", "Fine… it's true, I lied: {truth}."], ["(Il soupire.) En vrai, {truth}.", "(He sighs.) Actually, {truth}."],
            ["D'accord. La vérité, c'est que {truth}.", "Okay. The truth is {truth}."], ["Je vous ai raconté n'importe quoi. {truth}, voilà.", "I told you nonsense. {truth}, there."],
            ["(Il baisse la tête.) Pardon… {truth}.", "(He hangs his head.) Sorry… {truth}."], ["J'avais peur, c'est tout. {truth}.", "I was scared, that's all. {truth}."]]],
        ["agressif", [["Bon, et alors ? {truth}. Ça change quoi ?", "So what? {truth}. What does it change?"]]]
    ]],
    ["confessVague", [
        ["*", [["D'accord… mes papiers ne sont pas à moi.", "Okay… my papers aren't mine."], ["(Il baisse les yeux.) Ce n'est pas ce que vous croyez.", "(He lowers his eyes.) It's not what you think."],
            ["Bon. Je ne suis pas qui je prétends.", "Fine. I'm not who I say I am."], ["Je ne peux pas tout vous dire… mais oui, j'ai menti.", "I can't tell you everything… but yes, I lied."],
            ["(Il craque.) C'est vrai, c'est vrai…", "(He breaks down.) It's true, it's true…"], ["On m'a obligé, je vous jure.", "They forced me, I swear."]]]
    ]],
    ["confessed", [
        ["*", [["Je vous ai déjà tout dit.", "I already told you everything."], ["Je n'ai rien d'autre à avouer.", "I have nothing else to confess."], ["C'est tout, je vous le jure.", "That's all, I swear."],
            ["Qu'est-ce que vous voulez de plus ?", "What more do you want?"], ["J'ai déjà avoué, ça ne suffit pas ?", "I already confessed, isn't that enough?"], ["(Il secoue la tête.) Il n'y a rien d'autre.", "(He shakes his head.) There's nothing else."]]]
    ]],
    ["confrontDeny", [
        ["*", [["Non, non, je dis la vérité.", "No, no, I'm telling the truth."], ["Vous vous trompez de personne.", "You've got the wrong person."], ["Mes papiers sont en règle, vérifiez.", "My papers are in order, check them."],
            ["Je n'ai rien à voir avec ça.", "I have nothing to do with it."], ["Pourquoi je vous mentirais ?", "Why would I lie to you?"], ["Demandez à qui vous voulez, c'est la vérité.", "Ask anyone you like, it's the truth."]]],
        ["nerveux", [["Non ! Non, je… je ne mens pas !", "No! No, I… I'm not lying!"], ["(Il transpire.) C'est la vérité, je vous jure…", "(He sweats.) It's the truth, I swear…"]]],
        ["mefiant", [["Prouvez-le.", "Prove it."], ["Vous n'avez rien contre moi.", "You've got nothing on me."]]],
        ["laconique", [["Faux.", "Wrong."], ["Non.", "No."]]]
    ]],
    ["confrontAngry", [
        ["*", [["Vous me traitez de menteur ?", "You're calling me a liar?"], ["Ça suffit ! Je n'ai rien fait !", "That's enough! I haven't done anything!"], ["Retirez ce que vous avez dit.", "Take that back."],
            ["Vous cherchez les ennuis ?", "Are you looking for trouble?"], ["(Il serre les poings.) Faites attention à ce que vous dites.", "(He clenches his fists.) Watch what you say."], ["Je ne répondrai plus à rien.", "I won't answer anything else."]]]
    ]],
    ["confrontInnocent", [
        ["*", [["Quoi ? Mais non ! Je n'ai rien fait !", "What? No! I haven't done anything!"], ["Vous vous trompez, je vous assure.", "You're mistaken, I assure you."], ["Vérifiez, vous verrez que c'est vrai.", "Check, you'll see it's true."],
            ["Pourquoi vous dites ça ?", "Why would you say that?"], ["Je suis un honnête homme, {sir}.", "I'm an honest man, {sir}."], ["Je ne comprends pas, tout est vrai.", "I don't understand, it's all true."]]],
        ["agressif", [["Vous m'insultez, là.", "Now you're insulting me."], ["Allez accuser quelqu'un d'autre.", "Go accuse someone else."]]],
        ["effraye", [["Non, non, s'il vous plaît, je n'ai rien fait…", "No, no, please, I didn't do anything…"]]]
    ]],
    ["confrontFlee", [
        ["*", [["(Il recule d'un pas… puis se met à courir.)", "(He steps back… then breaks into a run.)"], ["Non ! (Il s'enfuit.)", "No! (He runs off.)"], ["(Il jette un regard affolé autour de lui et détale.)", "(He glances around in panic and bolts.)"],
            ["Laissez-moi ! (Il part en courant.)", "Leave me alone! (He runs away.)"], ["(Il pâlit, recule, puis s'enfuit.)", "(He goes pale, backs off, then flees.)"], ["Je… non ! (Il prend la fuite.)", "I… no! (He takes flight.)"]]]
    ]],
    // ------------------------------------------------------------------ conversation
    ["calm", [
        ["*", [["D'accord… merci.", "Okay… thank you."], ["(Il souffle un peu.)", "(He breathes out a little.)"], ["Bon. Bon, d'accord.", "Alright. Alright."], ["Je suis calme, je suis calme.", "I'm calm, I'm calm."]]],
        ["mefiant", [["On verra bien.", "We'll see."]]],
        ["agressif", [["Je suis très calme.", "I'm perfectly calm."]]]
    ]],
    ["help", [
        ["*", [["Je n'ai besoin de rien, {sir}.", "I don't need anything, {sir}."], ["Merci, mais tout va bien.", "Thanks, but everything's fine."], ["Non, ça va, merci.", "No, I'm fine, thanks."], ["Ça ira, merci.", "I'll manage, thanks."], ["Vous êtes gentil.", "That's kind of you."]]],
        ["effraye", [["Oui… aidez-moi, s'il vous plaît.", "Yes… help me, please."]]],
        ["mefiant", [["Je n'ai besoin de rien de vous.", "I don't need anything from you."]]]
    ]],
    ["greet", [
        ["*", [["Bonjour.", "Hello."], ["Bonjour, {sir}.", "Hello, {sir}."], ["Salut.", "Hi."], ["Bonjour… il y a un problème ?", "Hello… is there a problem?"], ["Bonjour à vous.", "Good day to you."], ["(Il vous salue d'un signe de tête.)", "(He greets you with a nod.)"]]],
        ["nerveux", [["B… bonjour.", "H… hello."]]],
        ["mefiant", [["…Bonjour. Qu'est-ce que vous voulez ?", "…Hello. What do you want?"]]],
        ["bavard", [["Bonjour, bonjour ! Belle journée, hein ?", "Hello, hello! Nice day, isn't it?"]]],
        ["agressif", [["Quoi ?", "What?"]]],
        ["laconique", [["(Signe de tête.)", "(A nod.)"]]]
    ]],
    ["bye", [
        ["*", [["À la prochaine.", "See you."], ["Que Dieu vous garde.", "God keep you."], ["Au revoir.", "Goodbye."], ["Bonne journée, {sir}.", "Have a good day, {sir}."], ["Au revoir… enfin.", "Goodbye… finally."], ["Faites attention à vous.", "Take care."]]],
        ["laconique", [["(Il hoche la tête.)", "(He nods.)"]]]
    ]],
    ["thanks", [
        ["*", [["C'est normal.", "It's nothing."], ["Avec plaisir, {sir}.", "My pleasure, {sir}."], ["Ce n'est rien.", "It's nothing."], ["De rien.", "You're welcome."], ["Je vous en prie.", "Don't mention it."], ["Pas de quoi.", "No problem."]]],
        ["mefiant", [["Hm.", "Hm."]]]
    ]],
    ["sorry", [
        ["*", [["Ne vous inquiétez pas.", "Don't worry."], ["Ce n'est pas grave, {sir}.", "It's not a problem, {sir}."], ["Ça arrive.", "It happens."], ["Ce n'est rien.", "It's nothing."], ["Ça va, ça va.", "It's fine, it's fine."], ["Pas grave.", "No harm done."]]]
    ]],
    // Une phrase avec {job}, {city}, {employer}… n'est choisie que si la couverture de l'IA a ce fait (fn_iarpGen).
    ["howareyou", [
        ["*", [["Ça va, merci.", "I'm fine, thanks."], ["On fait aller.", "Getting by."], ["Comme on peut, avec tout ça.", "As well as can be, with all this.", ["hint"]],
            ["Ça va. La journée a été longue chez {employer}.", "Fine. It's been a long day at {employer}."], ["Pas mal. Je rentre à {city} ce soir.", "Not bad. I'm heading back to {city} tonight."],
            ["Fatigué. Le métier de {job}, vous savez…", "Tired. Being a {job}, you know…"], ["Bien, merci. Et vous, {sir} ?", "Good, thanks. And you, {sir}?"],
            ["Ça va, ça va. Rien de spécial.", "Fine, fine. Nothing special."]]],
        ["bavard", [["Oh, ça va, mais les prix ont encore augmenté au marché, vous imaginez ?", "Oh, I'm fine, but prices went up again at the market, can you imagine?"]]],
        ["nerveux", [["Ça va… oui, ça va.", "Fine… yes, fine."]]]
    ]],
    ["yes", [
        ["*", [["Bon.", "Fine."], ["Très bien.", "Very well."], ["Comme vous dites.", "As you say."], ["D'accord.", "Okay."], ["Bien.", "Good."], ["Si vous le dites.", "If you say so."]]]
    ]],
    ["no", [
        ["*", [["Bon, d'accord.", "Alright then."], ["Si vous le dites.", "If you say so."], ["Hm.", "Hm."], ["Ah bon.", "Oh, okay."], ["Comme vous voulez.", "As you like."], ["Bien.", "Fine."]]]
    ]],
    // Rien de rapproché : jamais un simple « Pardon ? » ; une piste (papiers, d'où il vient), qui devient sa question.
    ["unknown", [
        ["*", [["Pardon, je n'ai pas compris. Vous voulez voir mes papiers ?", "Sorry, I didn't understand. Do you want to see my papers?"],
            ["Je ne comprends pas… c'est pour mes papiers ?", "I don't understand… is this about my papers?"],
            ["(Il vous regarde sans comprendre.) Mes papiers ?", "(He looks at you blankly.) My papers?"],
            ["Je ne suis pas sûr de comprendre, {sir}. Vous voulez mes papiers ?", "I'm not sure I understand, {sir}. You want my papers?"],
            ["Comment ça ? Vous voulez voir mes papiers ?", "What do you mean? You want to see my papers?"],
            ["Pardon ? Si c'est un contrôle, j'ai mes papiers.", "Sorry? If this is a check, I have my papers."]]]
    ]],
    ["smalltalk", [
        ["*", [["Oui, il fait bon aujourd'hui.", "Yes, it's nice today."], ["Pas trop, avec tout ce qui se passe.", "Not much, with everything going on.", ["hint"]], ["La même chose que d'habitude.", "Same as usual."],
            ["Ici, rien ne change jamais.", "Nothing ever changes around here."], ["À {city}, ça va, c'est calme.", "In {city}, it's fine, it's quiet."], ["On fait avec, {sir}.", "We make do, {sir}."]]],
        ["bavard", [["Oh, vous savez, le marché, les voisins, la famille… comme toujours !", "Oh, you know, the market, the neighbours, the family… as always!"]]],
        ["mefiant", [["…Vous n'êtes pas là pour parler de la pluie et du beau temps.", "…You're not here to talk about the weather."]]],
        ["laconique", [["Hm.", "Hm."]]]
    ]],
    ["introduce", [
        ["*", [["Enchanté, {player}.", "Nice to meet you, {player}."], ["Bonjour, {player}. Moi, c'est {first}.", "Hello, {player}. I'm {first}."], ["Enchanté. {name}.", "Nice to meet you. {name}."],
            ["D'accord, {player}. Que puis-je pour vous ?", "Alright, {player}. What can I do for you?"], ["Ravi. On m'appelle {first}.", "Pleased. People call me {first}."], ["{player}… d'accord. Moi c'est {first}.", "{player}… alright. I'm {first}."]]],
        ["mefiant", [["Et qu'est-ce que vous me voulez, {player} ?", "And what do you want from me, {player}?"]]]
    ]],
    // Salut redit dans la même conversation : pas un deuxième « Bonjour » comme si de rien n'était.
    ["greetAgain", [
        ["*", [["On s'est déjà dit bonjour, {sir}.", "We've already said hello, {sir}."], ["Oui, oui, bonjour. Encore.", "Yes, yes, hello. Again."], ["Bonjour encore. Je peux vous aider ?", "Hello again. Can I help you?"],
            ["(Il hoche la tête.) Oui ?", "(He nods.) Yes?"], ["Vous vouliez autre chose ?", "Did you want something else?"], ["Toujours là, {sir}.", "Still here, {sir}."]]]
    ]],
    ["drugs", [
        ["*", [["De la drogue ? Non, jamais.", "Drugs? No, never."], ["Je ne touche pas à ça.", "I don't touch that stuff."], ["Non, rien de tout ça.", "No, nothing like that."],
            ["Fouillez si vous voulez, je n'ai rien.", "Search me if you want, I have nothing."], ["Moi ? Je suis {job}, pas trafiquant.", "Me? I'm a {job}, not a dealer."], ["Non, {sir}.", "No, {sir}."]]],
        ["nerveux", [["Non ! Non, pas de drogue, je vous jure.", "No! No drugs, I swear."]]],
        ["agressif", [["Vous me prenez pour un dealer ?", "You take me for a dealer?"]]]
    ]],
    ["money", [
        ["*", [["Un peu, pour les courses.", "A little, for groceries."], ["Ma paie de la semaine.", "My week's pay."], ["Quelques billets, pas plus.", "A few notes, no more."],
            ["C'est mon argent, je l'ai gagné chez {employer}.", "It's my money, I earned it at {employer}."], ["Juste de quoi rentrer à {city}.", "Just enough to get back to {city}."], ["Pas grand-chose, {sir}.", "Not much, {sir}."]]],
        ["mefiant", [["Pourquoi, vous voulez me le prendre ?", "Why, are you going to take it?"]]],
        ["nerveux", [["C'est… c'est pour ma famille.", "It's… it's for my family."]]]
    ]],
    ["vehicle", [
        ["*", [["Oui, c'est ma voiture.", "Yes, it's my car."], ["C'est la voiture de mon cousin.", "It's my cousin's car."], ["Je l'utilise pour le travail.", "I use it for work."],
            ["Je n'ai pas de voiture ici.", "I don't have a car here."], ["C'est celle de {employer}.", "It belongs to {employer}."], ["Oui, {sir}. Les papiers sont dedans.", "Yes, {sir}. The papers are inside."]]],
        ["mefiant", [["Pourquoi ? Elle vous plaît ?", "Why? You like it?"]]]
    ]],
    // Papiers et fouille par la conversation : il répond tout de suite, puis l'action ACE (contrôle d'identité) suit.
    ["papersWait", [
        ["*", [["Mes papiers ? Une seconde…", "My papers? One second…"], ["(Il fouille dans ses poches.)", "(He goes through his pockets.)"], ["Oui, oui, je les ai… attendez.", "Yes, yes, I have them… wait."],
            ["D'accord, je les sors.", "Okay, I'm getting them out."], ["Un instant, {sir}.", "One moment, {sir}."], ["(Il cherche dans sa veste.)", "(He searches his jacket.)"]]],
        ["nerveux", [["Mes… mes papiers ? Oui… oui, bien sûr.", "My… my papers? Yes… yes, of course."]]],
        ["mefiant", [["Encore ? … Bon.", "Again? … Fine."]]]
    ]],
    ["papersCloser", [
        ["*", [["Approchez, je vous les montre.", "Come closer, I'll show you."], ["Venez, je ne vais pas vous les lancer.", "Come here, I'm not going to throw them at you."], ["Je les ai, approchez-vous.", "I have them, come closer."],
            ["Si vous voulez les voir, venez.", "If you want to see them, come over."], ["(Il vous fait signe d'approcher.)", "(He waves you closer.)"], ["Plus près, {sir}.", "Closer, {sir}."]]]
    ]],
    ["searchWait", [
        ["*", [["(Il se laisse faire.)", "(He lets you.)"], ["Allez-y…", "Go ahead…"], ["Faites vite.", "Make it quick."], ["(Il ne bouge pas.)", "(He doesn't move.)"], ["Je n'ai rien, vous verrez.", "I have nothing, you'll see."], ["D'accord, d'accord.", "Okay, okay."]]]
    ]],
    // Gestes du menu « Faire une action » (et /me compris) : menottes, papiers rendus, photo, geste sans effet.
    ["cuffYes", [
        ["*", [["(Il tend les poignets sans résister.)", "(He holds out his wrists without resisting.)"], ["Pourquoi ? Je n'ai rien fait…", "Why? I haven't done anything…"], ["D'accord… doucement.", "Okay… gently."],
            ["(Il baisse la tête.)", "(He lowers his head.)"], ["Vous faites une erreur, {sir}.", "You're making a mistake, {sir}."], ["Ça serre…", "That's tight…"]]]
    ]],
    ["cuffNo", [
        ["*", [["Non ! Vous n'avez pas le droit !", "No! You have no right!"], ["(Il recule et se débat.)", "(He backs away and struggles.)"], ["Pas question !", "No way!"],
            ["Ne me touchez pas !", "Don't touch me!"], ["(Il retire ses mains d'un coup.)", "(He yanks his hands away.)"], ["Lâchez-moi !", "Let go of me!"]]]
    ]],
    ["givePapers", [
        ["*", [["Merci.", "Thank you."], ["(Il range ses papiers.)", "(He puts his papers away.)"], ["Tout est en ordre, alors ?", "Everything's in order, then?"], ["Merci, {sir}.", "Thank you, {sir}."],
            ["Je peux y aller ?", "Can I go?"], ["(Il reprend ses papiers, soulagé.)", "(He takes his papers back, relieved.)"]]]
    ]],
    ["photoNo", [
        ["*", [["Je ne connais pas cette personne.", "I don't know this person."], ["(Il regarde la photo.) Non, jamais vu.", "(He looks at the photo.) No, never seen him."], ["Ça ne me dit rien.", "Doesn't ring a bell."],
            ["Non, désolé.", "No, sorry."], ["Il y a beaucoup de monde qui passe ici…", "Lots of people pass through here…"], ["(Il secoue la tête.)", "(He shakes his head.)"]]],
        ["nerveux", [["Non… non, je ne le connais pas.", "No… no, I don't know him."]]]
    ]],
    ["watchMe", [
        ["*", [["(Il vous regarde faire.)", "(He watches you.)"], ["(Il hausse un sourcil.)", "(He raises an eyebrow.)"], ["Qu'est-ce que vous faites ?", "What are you doing?"],
            ["(Il attend sans rien dire.)", "(He waits without a word.)"], ["(Il suit vos gestes des yeux.)", "(His eyes follow your movements.)"], ["Euh… d'accord.", "Uh… okay."]]]
    ]],
    // ------------------------------------------------------------------ questions de l'IA (initiative) et suite d'un oui / non
    ["askArrest", [
        ["*", [["Vous allez m'arrêter ?", "Are you going to arrest me?"], ["Je vais avoir des ennuis ?", "Am I going to be in trouble?"], ["Vous m'emmenez ?", "Are you taking me away?"],
            ["Je vais finir en prison ?", "Am I going to jail?"], ["C'est grave ? Vous allez m'arrêter ?", "Is it serious? Are you going to arrest me?"], ["(Inquiet.) Vous allez m'embarquer ?", "(Worried.) Are you going to take me in?"]]]
    ]],
    ["askGo", [
        ["*", [["Je peux partir, maintenant ?", "Can I go now?"], ["On a fini ? Je peux y aller ?", "Are we done? Can I go?"], ["Je peux reprendre ma route ?", "Can I be on my way?"],
            ["Vous avez encore besoin de moi ?", "Do you still need me?"], ["Je suis attendu… je peux y aller ?", "People are waiting for me… can I go?"], ["C'est bon pour moi ?", "Am I good to go?"]]]
    ]],
    ["askMoney", [
        ["*", [["Ça vaut bien un petit quelque chose, non ?", "That's worth a little something, isn't it?"], ["Vous avez de quoi me remercier ?", "Do you have something to thank me with?"],
            ["Pour quelques billets, je pourrais me souvenir…", "For a few notes, I might remember…"], ["Qu'est-ce que j'y gagne, moi ?", "What's in it for me?"],
            ["Un petit geste, et on discute ?", "A small gesture, and we talk?"], ["Tout se paie, ici. Vous payez ?", "Everything has a price here. Are you paying?"]]]
    ]],
    ["askHelp", [
        ["*", [["Vous pouvez m'aider ? J'ai mal…", "Can you help me? It hurts…"], ["Il me faut un médecin… vous pouvez faire quelque chose ?", "I need a doctor… can you do something?"],
            ["S'il vous plaît, aidez-moi.", "Please, help me."], ["Vous avez de quoi me soigner ?", "Do you have anything to treat me?"],
            ["Je saigne… vous allez m'aider ?", "I'm bleeding… will you help me?"], ["(Il grimace.) Vous m'aidez ?", "(He winces.) Will you help me?"]]]
    ]],
    ["askArrestYes", [
        ["*", [["Non… s'il vous plaît…", "No… please…"], ["Je n'ai rien fait, je vous le jure !", "I didn't do anything, I swear!"], ["(Il blêmit.)", "(He goes pale.)"],
            ["Ma famille… qui va s'occuper de ma famille ?", "My family… who'll look after my family?"], ["Pitié, laissez-moi une chance.", "Please, give me a chance."], ["(Il baisse la tête, résigné.)", "(He hangs his head, resigned.)"]]]
    ]],
    ["askArrestNo", [
        ["*", [["Merci… merci.", "Thank you… thank you."], ["(Il souffle, soulagé.)", "(He breathes out, relieved.)"], ["Ah, tant mieux.", "Oh, good."],
            ["Merci, {sir}. Vraiment.", "Thank you, {sir}. Really."], ["Je vous crois.", "I believe you."], ["D'accord… d'accord, merci.", "Okay… okay, thanks."]]]
    ]],
    ["askGoYes", [
        ["*", [["Merci, bonne journée.", "Thanks, have a good day."], ["Merci, {sir}.", "Thank you, {sir}."], ["Au revoir.", "Goodbye."],
            ["(Il s'éloigne sans demander son reste.)", "(He leaves without waiting.)"], ["Enfin. Merci.", "Finally. Thanks."], ["Bonne journée à vous.", "Good day to you."]]]
    ]],
    ["askGoNo", [
        ["*", [["D'accord… j'attends.", "Okay… I'll wait."], ["Encore ? Bon…", "Still? Fine…"], ["(Il soupire et attend.)", "(He sighs and waits.)"],
            ["Comme vous voulez.", "As you wish."], ["Je n'ai pas toute la journée, vous savez.", "I don't have all day, you know."], ["Bien, {sir}.", "Very well, {sir}."]]]
    ]],
    ["askMoneyNo", [
        ["*", [["Tant pis pour vous.", "Too bad for you."], ["Alors je ne sais rien.", "Then I know nothing."], ["Dommage.", "Pity."],
            ["Comme vous voulez. Moi, j'ai oublié.", "As you like. Me, I've forgotten."], ["Rien n'est gratuit, ici.", "Nothing's free here."], ["(Il hausse les épaules.)", "(He shrugs.)"]]]
    ]],
    ["askHelpYes", [
        ["*", [["Merci… merci beaucoup.", "Thank you… thank you so much."], ["Que Dieu vous bénisse.", "God bless you."], ["Vous êtes quelqu'un de bien.", "You're a good person."],
            ["(Il se laisse faire.)", "(He lets you help.)"], ["Merci, {sir}.", "Thank you, {sir}."], ["Je n'oublierai pas.", "I won't forget."]]]
    ]],
    ["askHelpNo", [
        ["*", [["Vous allez me laisser comme ça ?", "You're going to leave me like this?"], ["(Il vous regarde, incrédule.)", "(He stares at you in disbelief.)"], ["Je vois…", "I see…"],
            ["Merci pour rien.", "Thanks for nothing."], ["Vous n'avez pas de cœur.", "You have no heart."], ["(Il serre les dents.)", "(He grits his teeth.)"]]]
    ]],
    // ------------------------------------------------------------------ tirs proches (fn_iarpReact « fired »)
    ["shotsDown", [
        ["*", [["Ne tirez pas ! (Il se jette au sol.)", "Don't shoot! (He throws himself to the ground.)"], ["(Il se plaque au sol, les mains sur la tête.)", "(He hits the ground, hands over his head.)"],
            ["À terre ! Ne tirez pas !", "Get down! Don't shoot!"], ["(Il s'aplatit par terre en criant.)", "(He flattens himself on the ground, shouting.)"],
            ["Mon Dieu ! (Il se couche.)", "Oh God! (He lies down.)"], ["Arrêtez ! Arrêtez de tirer !", "Stop! Stop shooting!"]]]
    ]],
    ["shotsSurrender", [
        ["*", [["Ne tirez pas ! Je me rends !", "Don't shoot! I surrender!"], ["(Il lève les mains en tremblant.) Pitié !", "(He raises his hands, shaking.) Mercy!"],
            ["Je n'ai rien fait ! Ne tirez pas !", "I didn't do anything! Don't shoot!"], ["D'accord, d'accord ! Les mains en l'air !", "Okay, okay! Hands up!"],
            ["(Il lève les bras bien haut.)", "(He raises his arms high.)"], ["Je me rends, je me rends !", "I give up, I give up!"]]]
    ]],
    ["shotsFlee", [
        ["*", [["(Il part en courant, plié en deux.)", "(He runs off, bent double.)"], ["Au secours ! (Il s'enfuit.)", "Help! (He runs away.)"],
            ["(Il détale sans se retourner.)", "(He bolts without looking back.)"], ["Ils tirent ! Ils tirent ! (Il s'enfuit.)", "They're shooting! They're shooting! (He flees.)"],
            ["(Il court se mettre à l'abri.)", "(He runs for cover.)"], ["Non, non, non ! (Il prend la fuite.)", "No, no, no! (He takes flight.)"]]]
    ]],
    // ================================================================== 4.3.x : beaucoup plus de questions
    // Règle : une réplique « auto » n'apporte aucun élément de scénario (arme, groupe armé, lieu sensible, personne, évènement)
    // absent des données de l'IA. 3e élément d'une phrase : étiquettes (fn_iarpGen « forbid ») ; « hint » : laisse entendre
    // qu'elle sait quelque chose (jamais chez une IA sans savoir) ; armed, explosives, military, wanted, drugs, politics :
    // thème de scénario (jamais pour « Rien de sensible », ni pour un thème interdit par le maître du jeu).
    // Faits facultatifs de l'histoire de couverture : {phone} {nation} {langs} {since} {with} {alibi} {cargo} {waiting} {often} ;
    // {time} (heure du jeu), {here} (lieu le plus proche sur la carte).
    // ------------------------------------------------------------------ identité (suite)
    ["phone", [
        ["*", [["Mon numéro ? {phone}.", "My number? {phone}."], ["C'est le {phone}.", "It's {phone}."], ["Je n'ai pas de téléphone sur moi.", "I don't have a phone on me."],
            ["Mon téléphone est à la maison.", "My phone is at home."], ["Je n'ai plus de batterie, de toute façon.", "My battery's dead anyway."], ["Un téléphone ? Ça coûte cher, ici.", "A phone? That's expensive around here."]]],
        ["mefiant", [["Pourquoi vous voulez mon numéro ?", "Why do you want my number?"]]]
    ]],
    ["nation", [
        ["*", [["Je suis {nation}.", "I'm {nation}."], ["{nation}, comme mes parents.", "{nation}, like my parents."], ["Nationalité ? {nation}.", "Nationality? {nation}."],
            ["C'est écrit sur mes papiers.", "It's written on my papers."], ["Je suis d'ici, {sir}.", "I'm from here, {sir}."], ["D'ici. Toute ma famille est d'ici.", "From here. My whole family is from here."]]],
        ["mefiant", [["Pourquoi, ça change quelque chose ?", "Why, does it change anything?"]]]
    ]],
    ["speak", [
        ["*", [["Je parle {langs}.", "I speak {langs}."], ["{langs}, surtout.", "{langs}, mostly."], ["Un peu. Assez pour vous comprendre.", "A little. Enough to understand you."],
            ["Je comprends, oui. Je parle mal, mais je comprends.", "I understand, yes. I speak badly, but I understand."], ["Quelques mots seulement.", "Just a few words."], ["Oui, je vous comprends.", "Yes, I understand you."]]]
    ]],
    // ------------------------------------------------------------------ présence (réponse sans fait : la même ensuite, mémoire de l'IA)
    ["since", [
        ["*", [["Depuis {since}.", "Since {since}."], ["Je suis là depuis {since}.", "I've been here since {since}."], ["Pas longtemps. Une demi-heure, peut-être.", "Not long. Half an hour, maybe."],
            ["Depuis ce matin.", "Since this morning."], ["Je viens d'arriver.", "I just got here."], ["Un moment. Je n'ai pas regardé l'heure.", "A while. I didn't check the time."]]],
        ["laconique", [["Pas longtemps.", "Not long."]]]
    ]],
    ["with", [
        ["*", [["Avec {with}.", "With {with}."], ["Je suis avec {with}.", "I'm with {with}."], ["Seul, {sir}.", "Alone, {sir}."], ["Je suis seul.", "I'm on my own."],
            ["Personne. Juste moi.", "Nobody. Just me."], ["Tout seul, comme d'habitude.", "All alone, as usual."]]],
        ["mefiant", [["Pourquoi, vous cherchez quelqu'un ?", "Why, are you looking for someone?"]]]
    ]],
    ["lateHour", [
        ["*", [["Je rentre chez moi, c'est tout.", "I'm going home, that's all."], ["Je n'arrivais pas à dormir.", "I couldn't sleep."], ["Je finis tard, le travail.", "I finish late, work."],
            ["{travel}. Ça ne pouvait pas attendre.", "{travel}. It couldn't wait."], ["Je ne savais pas qu'il était si tard.", "I didn't know it was so late."], ["Je me dépêche de rentrer, {sir}.", "I'm hurrying home, {sir}."]]],
        ["nerveux", [["Je… je rentre, je rentre tout de suite.", "I… I'm going home, right now."]]]
    ]],
    ["alibi", [
        ["*", [["{alibi}.", "{alibi}."], ["Hier soir ? {alibi}.", "Last night? {alibi}."], ["Chez moi, avec ma famille.", "At home, with my family."], ["À la maison. Je dormais.", "At home. I was asleep."],
            ["Chez moi, comme tous les soirs.", "At home, like every night."], ["Je ne suis pas sorti, {sir}.", "I didn't go out, {sir}."]]],
        ["mefiant", [["Pourquoi ? Il s'est passé quelque chose ?", "Why? Did something happen?"]]],
        ["nerveux", [["Chez… chez moi ! Demandez à ma famille.", "At… at home! Ask my family."]]]
    ]],
    ["often", [
        ["*", [["{often}.", "{often}."], ["Oui, presque tous les jours.", "Yes, almost every day."], ["De temps en temps.", "Now and then."], ["Quand j'ai besoin, oui.", "When I need to, yes."],
            ["C'est mon chemin habituel.", "It's my usual way."], ["Pas souvent, non.", "Not often, no."]]]
    ]],
    ["stayLong", [
        ["*", [["Le temps qu'il faut, puis je rentre.", "As long as it takes, then I go home."], ["Pas longtemps, j'espère.", "Not long, I hope."], ["Je repars dès que vous me laissez partir.", "I'm leaving as soon as you let me go."],
            ["Quelques minutes.", "A few minutes."], ["Ce soir je serai rentré.", "I'll be home tonight."], ["Ça dépend de vous, {sir}.", "That depends on you, {sir}."]]]
    ]],
    ["waitingFor", [
        ["*", [["J'attends {waiting}.", "I'm waiting for {waiting}."], ["{waiting}.", "{waiting}."], ["Personne. Je me repose un peu.", "Nobody. I'm just resting a bit."],
            ["Un ami. Il est en retard.", "A friend. He's late."], ["Personne, {sir}.", "Nobody, {sir}."], ["J'attends que vous ayez fini, surtout.", "Mostly I'm waiting for you to finish."]]]
    ]],
    ["house", [
        ["*", [["Oui, c'est chez moi.", "Yes, this is my home."], ["Non, ce n'est pas ma maison.", "No, it's not my house."], ["J'habite {address}.", "I live at {address}."],
            ["Ma famille vit ici.", "My family lives here."], ["Je ne sais pas qui habite là.", "I don't know who lives there."], ["Chez moi, c'est à {city}.", "My home is in {city}."]]],
        ["mefiant", [["Pourquoi ? Vous voulez entrer ?", "Why? Do you want to come in?"]]]
    ]],
    ["cargo", [
        ["*", [["{cargo}.", "{cargo}."], ["Juste {cargo}.", "Just {cargo}."], ["Des affaires personnelles.", "Personal things."], ["Rien de spécial. Des courses.", "Nothing special. Groceries."],
            ["Ce que j'ai acheté au marché.", "What I bought at the market."], ["Pas grand-chose. Vous pouvez regarder.", "Not much. You can look."]]],
        ["nerveux", [["Rien ! Enfin… des affaires, c'est tout.", "Nothing! Well… things, that's all."]]]
    ]],
    ["declare", [
        ["*", [["Rien à déclarer.", "Nothing to declare."], ["Non, rien.", "No, nothing."], ["Juste mes affaires.", "Just my things."], ["{cargo}, c'est tout.", "{cargo}, that's all."],
            ["Rien, {sir}.", "Nothing, {sir}."], ["Je n'ai rien qui se déclare.", "Nothing that needs declaring."]]]
    ]],
    // ------------------------------------------------------------------ renseignement précis, sans savoir : neutre, crédible, aucun détail
    ["intelNo.seenArmed", [
        ["*", [["Non, je n'ai rien vu de tel.", "No, I haven't seen anything like that."], ["Non. Je vais juste au marché, moi.", "No. I'm just going to the market."], ["Je n'ai vu personne, {sir}.", "I haven't seen anyone, {sir}."],
            ["Non, rien. Je ne fais pas attention à ça.", "No, nothing. I don't pay attention to that."], ["Je n'ai rien remarqué.", "I didn't notice anything."], ["Non, désolé.", "No, sorry."]]]
    ]],
    ["intelNo.seenVehicles", [
        ["*", [["Des voitures passent, comme partout. Rien de spécial.", "Cars go by, like everywhere. Nothing special."], ["Je n'ai pas fait attention.", "I didn't pay attention."], ["Non, je n'ai rien remarqué.", "No, I didn't notice anything."],
            ["Je ne regarde pas les voitures, {sir}.", "I don't watch the cars, {sir}."], ["Pas que je sache.", "Not that I know of."], ["Rien d'inhabituel.", "Nothing unusual."]]]
    ]],
    ["intelNo.strangers", [
        ["*", [["Pas que j'aie vu.", "Not that I've seen."], ["Je ne connais pas tout le monde, vous savez.", "I don't know everyone, you know."], ["Non, rien d'inhabituel.", "No, nothing unusual."],
            ["Les gens vont et viennent. Je ne fais pas attention.", "People come and go. I don't pay attention."], ["Non, {sir}.", "No, {sir}."], ["Je ne suis pas d'ici depuis assez longtemps pour le dire.", "I haven't been here long enough to say."]]]
    ]],
    ["intelNo.explosives", [
        ["*", [["Non. Je ne connais rien à ça.", "No. I know nothing about that."], ["Moi ? Non, rien du tout.", "Me? No, nothing at all."], ["Je n'en sais rien, {sir}.", "I don't know anything, {sir}."],
            ["Non, je n'ai rien vu.", "No, I haven't seen anything."], ["Je ne m'occupe pas de ces choses-là.", "I don't deal with things like that."], ["Rien, désolé.", "Nothing, sorry."]]]
    ]],
    ["intelNo.mines", [
        ["*", [["Je ne sais pas, {sir}. Je prends toujours le même chemin.", "I don't know, {sir}. I always take the same way."], ["Je n'en sais rien.", "I have no idea."], ["Personne ne m'a rien dit.", "Nobody told me anything."],
            ["Je ne suis pas au courant.", "I'm not aware of anything."], ["Aucune idée, désolé.", "No idea, sorry."], ["Je ne peux pas vous dire, je ne sais pas.", "I can't tell you, I don't know."]]]
    ]],
    ["intelNo.whereIs", [
        ["*", [["Je ne sais pas de qui vous parlez.", "I don't know who you mean."], ["Aucune idée, {sir}.", "No idea, {sir}."], ["Je ne connais pas cette personne.", "I don't know that person."],
            ["Demandez au village, moi je ne sais pas.", "Ask in the village, I don't know."], ["Je ne peux pas vous aider.", "I can't help you."], ["Je ne sais pas.", "I don't know."]]]
    ]],
    ["intelNo.leader", [
        ["*", [["Je ne sais pas. Je ne m'occupe pas de ça.", "I don't know. I don't get involved in that."], ["Personne ne me commande, {sir}.", "Nobody gives me orders, {sir}."], ["Je ne sais pas qui commande.", "I don't know who's in charge."],
            ["Moi, je fais mon travail, c'est tout.", "I do my job, that's all."], ["Aucune idée.", "No idea."], ["Je ne suis pas au courant de ces choses.", "I don't know about such things."]]]
    ]],
    ["intelNo.howMany", [
        ["*", [["Je n'ai vu personne, je ne peux pas compter.", "I didn't see anyone, so I can't count."], ["Je ne sais pas de quoi vous parlez.", "I don't know what you mean."], ["Aucune idée, {sir}.", "No idea, {sir}."],
            ["Je ne sais pas.", "I don't know."], ["Je n'ai rien vu, alors…", "I didn't see anything, so…"], ["Désolé, je ne peux pas vous dire.", "Sorry, I can't tell you."]]]
    ]],
    ["intelNo.whenPassed", [
        ["*", [["Je ne sais pas, je n'ai rien vu.", "I don't know, I didn't see anything."], ["Je ne peux pas vous dire.", "I can't tell you."], ["Aucune idée de l'heure.", "No idea what time."],
            ["Je n'étais pas là, je crois.", "I wasn't there, I think."], ["Je ne sais pas, {sir}.", "I don't know, {sir}."], ["Je n'ai pas fait attention.", "I didn't pay attention."]]]
    ]],
    ["intelNo.happened", [
        ["*", [["Je ne sais pas, je viens d'arriver.", "I don't know, I just got here."], ["Rien, que je sache.", "Nothing, as far as I know."], ["Je n'ai rien vu, {sir}.", "I didn't see anything, {sir}."],
            ["Je ne suis au courant de rien.", "I'm not aware of anything."], ["Je ne sais pas. Je m'occupe de mes affaires.", "I don't know. I mind my own business."], ["Aucune idée.", "No idea."]]]
    ]],
    ["intelNo.heard", [
        ["*", [["Non, je n'ai rien entendu.", "No, I didn't hear anything."], ["Rien d'anormal.", "Nothing out of the ordinary."], ["Non, c'était calme.", "No, it was quiet."],
            ["Je n'ai pas fait attention.", "I didn't pay attention."], ["Non, {sir}.", "No, {sir}."], ["J'étais à l'intérieur, je n'ai rien entendu.", "I was inside, I didn't hear anything."]]]
    ]],
    // Bruit réellement entendu en jeu (tirs proches, fn_iarpReact) : la seule chose qu'une IA sans savoir peut dire.
    ["heardShots", [
        ["*", [["Oui, des coups de feu, tout près. J'ai eu très peur.", "Yes, shots, very close. I was terrified."], ["Oui ! Ça tirait tout près d'ici.", "Yes! There was shooting close by."],
            ["Comment ne pas les entendre ? C'était juste à côté.", "How could I not? It was right next to me."], ["Oui. Je me suis jeté par terre.", "Yes. I threw myself on the ground."],
            ["Oui, tout à l'heure. Je ne sais pas d'où ça venait.", "Yes, a moment ago. I don't know where it came from."], ["Oui… j'en tremble encore.", "Yes… I'm still shaking."]]]
    ]],
    ["intelNo.whereWent", [
        ["*", [["Je ne sais pas, je n'ai vu personne partir.", "I don't know, I didn't see anyone leave."], ["Aucune idée, {sir}.", "No idea, {sir}."], ["Je ne peux pas vous dire.", "I can't tell you."],
            ["Je n'ai pas regardé.", "I didn't look."], ["Je ne sais pas de qui vous parlez.", "I don't know who you mean."], ["Je n'ai rien vu.", "I didn't see anything."]]]
    ]],
    ["intelNo.describe", [
        ["*", [["Je n'ai vu personne, je ne peux pas décrire.", "I didn't see anyone, I can't describe anything."], ["Je ne sais pas, désolé.", "I don't know, sorry."], ["Je ne me souviens de rien.", "I don't remember anything."],
            ["Je n'ai pas fait attention.", "I didn't pay attention."], ["Je ne peux pas vous aider, {sir}.", "I can't help you, {sir}."], ["Aucune idée.", "No idea."]]]
    ]],
    ["intelNo.cache", [
        ["*", [["Non, je ne sais rien de ça.", "No, I don't know anything about that."], ["Moi ? Non.", "Me? No."], ["Je n'en ai jamais entendu parler.", "I've never heard of anything like that."],
            ["Je ne sais pas, {sir}.", "I don't know, {sir}."], ["Non, rien.", "No, nothing."], ["Je ne suis au courant de rien.", "I'm not aware of anything."]]]
    ]],
    ["intelNo.base", [
        ["*", [["Je ne sais pas, {sir}.", "I don't know, {sir}."], ["Je ne vais jamais par là-bas.", "I never go out that way."], ["Aucune idée.", "No idea."],
            ["Je ne m'occupe pas de ces choses.", "I don't deal with those things."], ["Je ne peux pas vous dire.", "I can't tell you."], ["Je ne connais que mon chemin.", "I only know my own way."]]]
    ]],
    ["intelNo.checkpoints", [
        ["*", [["Je ne sais pas, je ne vais pas loin.", "I don't know, I don't go far."], ["Je ne fais pas attention aux contrôles. Je passe, c'est tout.", "I don't pay attention to checks. I just go through."],
            ["Aucune idée, {sir}.", "No idea, {sir}."], ["Je ne peux pas vous dire.", "I can't tell you."], ["Ça change tout le temps, je ne sais pas.", "It changes all the time, I don't know."], ["Pas que je sache.", "Not that I know of."]]]
    ]],
    ["intelNo.safety", [
        ["*", [["Ça va. Les gens vivent, travaillent…", "It's alright. People live, work…"], ["Calme, je crois. Enfin, pour moi.", "Quiet, I think. For me, anyway."], ["Comme partout, {sir}.", "Like anywhere, {sir}."],
            ["Je ne me plains pas.", "I'm not complaining."], ["Je ne sais pas trop. Je vais du travail à la maison.", "I don't really know. I go from work to home."], ["Ça dépend des jours.", "It depends on the day."]]]
    ]],
    ["intelNo.problems", [
        ["*", [["Les problèmes de tout le monde : l'argent, le travail.", "Everyone's problems: money, work."], ["Les prix augmentent, c'est tout.", "Prices go up, that's all."], ["Rien de spécial, {sir}.", "Nothing special, {sir}."],
            ["Pas plus qu'ailleurs.", "No more than anywhere else."], ["Je ne vois pas de problème.", "I don't see a problem."], ["L'eau, l'électricité… le quotidien.", "Water, power… daily life."]]]
    ]],
    ["intelNo.roadSafe", [
        ["*", [["Je ne sais pas, je ne vais pas par là.", "I don't know, I don't go that way."], ["Moi j'y passe, mais je ne peux rien vous promettre.", "I drive it, but I can't promise you anything."],
            ["Aucune idée, {sir}.", "No idea, {sir}."], ["Je ne peux pas vous dire.", "I can't tell you."], ["Demandez à quelqu'un qui roule plus que moi.", "Ask someone who drives more than me."], ["Je ne sais pas.", "I don't know."]]]
    ]],
    ["intelNo.knowPerson", [
        ["*", [["Non, je ne le connais pas.", "No, I don't know him."], ["Ça ne me dit rien.", "Doesn't ring a bell."], ["Il y a beaucoup de monde ici, je ne connais pas tout le monde.", "Lots of people here, I don't know everyone."],
            ["Non, désolé.", "No, sorry."], ["Jamais vu.", "Never seen him."], ["Je ne crois pas, {sir}.", "I don't think so, {sir}."]]]
    ]],
    ["intelNo.threatened", [
        ["*", [["Non, personne. Pourquoi ?", "No, nobody. Why?"], ["Non, tout va bien.", "No, everything's fine."], ["Personne ne me menace, {sir}.", "Nobody's threatening me, {sir}."],
            ["Non. On me laisse tranquille.", "No. People leave me alone."], ["Non, non.", "No, no."], ["Pas du tout.", "Not at all."]]]
    ]],
    // Thème interdit par le maître du jeu : neutre, sans rien laisser entendre.
    ["offLimits", [
        ["*", [["Je ne sais rien là-dessus.", "I don't know anything about that."], ["Je ne parle pas de ça, {sir}.", "I don't talk about that, {sir}."], ["Ça, je n'en sais rien.", "That, I know nothing about."],
            ["Je ne peux rien vous dire là-dessus.", "I can't tell you anything about that."], ["Ce n'est pas mon affaire.", "It's none of my business."], ["Je n'ai pas d'avis là-dessus.", "I have no opinion on that."]]]
    ]],
    // ------------------------------------------------------------------ situation locale (vie ordinaire, aucun élément de scénario)
    ["village", [
        ["*", [["C'est un endroit simple. Les gens travaillent, les enfants vont à l'école.", "It's a simple place. People work, children go to school."], ["Tout le monde se connaît, ici.", "Everyone knows everyone here."],
            ["La vie est dure, mais on s'entraide.", "Life is hard, but we help each other."], ["C'est calme, la plupart du temps.", "It's quiet, most of the time."], ["Il n'y a pas grand-chose à dire, {sir}.", "There's not much to say, {sir}."], ["Ici, c'est {here}.", "This is {here}."]]],
        ["bavard", [["Oh, le village ! Le marché, la fontaine, les vieux qui jouent aux cartes… rien ne change jamais.", "Oh, the village! The market, the fountain, the old men playing cards… nothing ever changes."]]],
        ["mefiant", [["Pourquoi vous vous intéressez au village ?", "Why are you interested in the village?"]]]
    ]],
    ["water", [
        ["*", [["On se débrouille. Ce n'est pas toujours facile.", "We manage. It's not always easy."], ["L'eau, c'est un vrai souci, l'été.", "Water is a real problem in summer."], ["Il y en a, mais il faut la faire bouillir.", "There is some, but you have to boil it."],
            ["On en a assez pour vivre.", "We have enough to live."], ["Demandez au village, ils vous diront.", "Ask in the village, they'll tell you."], ["Ça va, pour l'instant.", "It's fine, for now."]]]
    ]],
    ["food", [
        ["*", [["On mange ce qu'on a.", "We eat what we have."], ["Ça dépend des semaines.", "It depends on the week."], ["Les prix ont beaucoup augmenté.", "Prices have gone up a lot."],
            ["On s'en sort, merci.", "We get by, thanks."], ["Pas toujours assez, non.", "Not always enough, no."], ["Le marché a ce qu'il faut, quand on a l'argent.", "The market has what you need, if you have money."]]]
    ]],
    ["medical", [
        ["*", [["Il faut aller en ville pour voir un médecin.", "You have to go to town to see a doctor."], ["Pas ici, non.", "Not here, no."], ["Les médicaments sont rares et chers.", "Medicine is rare and expensive."],
            ["On se soigne comme on peut.", "We treat ourselves as best we can."], ["Je ne sais pas, je ne suis jamais malade.", "I don't know, I'm never sick."], ["Vous avez des médicaments, vous ?", "Do you have any medicine?"]]]
    ]],
    ["needs", [
        ["*", [["Du travail, surtout.", "Work, above all."], ["De l'eau propre et des médicaments.", "Clean water and medicine."], ["Que tout redevienne normal.", "For everything to go back to normal."],
            ["Qu'on nous laisse vivre tranquilles.", "To be left in peace."], ["Je n'ai besoin de rien, {sir}.", "I don't need anything, {sir}."], ["Demandez au chef du village, il saura mieux.", "Ask the village elder, he'll know better."]]]
    ]],
    ["directions", [
        ["*", [["Ce n'est pas loin, demandez en chemin.", "It's not far, ask on the way."], ["Je ne suis pas sûr. Demandez au village.", "I'm not sure. Ask in the village."], ["Par là, je crois.", "That way, I think."],
            ["Je ne connais pas bien le coin.", "I don't know the area well."], ["Suivez la route, vous verrez.", "Follow the road, you'll see."], ["Je ne peux pas vous dire exactement, {sir}.", "I can't tell you exactly, {sir}."]]]
    ]],
    ["whereHere", [
        ["*", [["Vous êtes à {here}.", "You're in {here}."], ["Ici, c'est {here}.", "This is {here}."], ["{here}, {sir}.", "{here}, {sir}."], ["Près de {here}.", "Near {here}."],
            ["C'est {here}. Vous êtes perdu ?", "It's {here}. Are you lost?"], ["Vous ne savez pas où vous êtes ?", "You don't know where you are?"]]]
    ]],
    // ------------------------------------------------------------------ relations
    ["neighbors", [
        ["*", [["Des gens bien. On s'entraide.", "Good people. We help each other."], ["Je les connais un peu, oui.", "I know them a little, yes."], ["Bonjour, bonsoir. Pas plus.", "Hello, goodbye. No more."],
            ["Je ne me mêle pas de leur vie.", "I stay out of their lives."], ["Ils sont gentils.", "They're nice."], ["Comme partout : certains oui, d'autres non.", "Like anywhere: some yes, some no."]]]
    ]],
    ["chief", [
        ["*", [["Il faut demander au village.", "You'd have to ask in the village."], ["Je ne sais pas s'il est là aujourd'hui.", "I don't know if he's here today."], ["Les anciens se retrouvent souvent sur la place.", "The elders often meet on the square."],
            ["Je ne peux pas vous dire où il est.", "I can't tell you where he is."], ["C'est un homme respecté.", "He's a respected man."], ["Allez au centre, quelqu'un vous indiquera.", "Go to the centre, someone will show you."]]]
    ]],
    ["police", [
        ["*", [["Ils font leur travail.", "They do their job."], ["On ne les voit pas beaucoup.", "We don't see them much."], ["Je n'ai pas de problème avec eux.", "I have no problem with them."],
            ["Je préfère ne pas en parler.", "I'd rather not talk about it."], ["Ça dépend lesquels.", "Depends which ones."], ["Ni bien ni mal, {sir}.", "Neither good nor bad, {sir}."]]]
    ]],
    ["army", [
        ["*", [["Tant que vous nous laissez tranquilles…", "As long as you leave us alone…"], ["Certains sont polis, d'autres non.", "Some are polite, some aren't."], ["Je n'ai pas d'avis.", "I have no opinion."],
            ["Vous faites votre travail, je fais le mien.", "You do your job, I do mine."], ["On verra bien ce que vous ferez.", "We'll see what you do."], ["Je préfère ne pas répondre, {sir}.", "I'd rather not answer, {sir}."]]],
        ["mefiant", [["Vous voulez vraiment savoir ?", "Do you really want to know?"]]],
        ["agressif", [["Vous n'avez rien à faire ici.", "You have no business here."]]]
    ]],
    ["rebelsView", [
        ["*", [["Je ne veux pas d'histoires, avec personne.", "I don't want trouble, with anyone."], ["Je ne fais pas de politique.", "I don't do politics."], ["Je ne suis du côté de personne.", "I'm on nobody's side."],
            ["Je veux juste vivre tranquille.", "I just want to live in peace."], ["Ce n'est pas une question à poser ici.", "That's not a question to ask here."], ["Je n'ai rien à dire là-dessus.", "I have nothing to say about that."]]]
    ]],
    ["trustUs", [
        ["*", [["Ça dépend de ce que vous ferez.", "It depends on what you'll do."], ["Un peu. Pas encore beaucoup.", "A little. Not much yet."], ["La confiance, ça se gagne.", "Trust has to be earned."],
            ["Je ne vous connais pas.", "I don't know you."], ["On verra.", "We'll see."], ["Vous ne m'avez rien fait de mal, pour l'instant.", "You haven't done me any harm, so far."]]],
        ["mefiant", [["Non.", "No."], ["Pourquoi je vous ferais confiance ?", "Why would I trust you?"]]]
    ]],
    // ------------------------------------------------------------------ personnel
    ["afraidYes", [
        ["*", [["Oui… vous me faites peur.", "Yes… you scare me."], ["Bien sûr que j'ai peur.", "Of course I'm scared."], ["Avec vos armes pointées sur moi ? Oui.", "With your guns on me? Yes."],
            ["(Il hoche la tête, la gorge serrée.)", "(He nods, his throat tight.)"], ["Un peu, oui.", "A little, yes."], ["Qui n'aurait pas peur ?", "Who wouldn't be scared?"]]]
    ]],
    ["afraidNo", [
        ["*", [["Non, ça va.", "No, I'm fine."], ["Pourquoi j'aurais peur ? Je n'ai rien fait.", "Why would I be scared? I haven't done anything."], ["Non, {sir}.", "No, {sir}."],
            ["Pas vraiment.", "Not really."], ["Non. Vous êtes polis, au moins.", "No. At least you're polite."], ["Je n'ai rien à craindre.", "I have nothing to fear."]]],
        ["agressif", [["De vous ? Certainement pas.", "Of you? Certainly not."]]]
    ]],
    ["hurtYes", [
        ["*", [["Oui… j'ai mal.", "Yes… it hurts."], ["(Il montre sa blessure.) Ici.", "(He shows his wound.) Here."], ["Oui, il me faut des soins.", "Yes, I need care."],
            ["Je saigne, vous voyez bien !", "I'm bleeding, can't you see!"], ["Ça va aller… je crois.", "I'll be okay… I think."], ["Oui. Vous pouvez m'aider ?", "Yes. Can you help me?"]]]
    ]],
    ["hurtNo", [
        ["*", [["Non, ça va, merci.", "No, I'm fine, thanks."], ["Non, je ne suis pas blessé.", "No, I'm not hurt."], ["Tout va bien, {sir}.", "All good, {sir}."],
            ["Non. Juste fatigué.", "No. Just tired."], ["Non, rien.", "No, nothing."], ["Je vais bien.", "I'm fine."]]]
    ]],
    ["hungry", [
        ["*", [["Un peu, oui.", "A little, yes."], ["Non, merci, j'ai mangé ce matin.", "No, thanks, I ate this morning."], ["Toujours un peu, ici.", "Always a bit, here."],
            ["Ça va, merci.", "I'm fine, thanks."], ["Je mangerai en rentrant.", "I'll eat when I get home."], ["Vous avez quelque chose ?", "Do you have something?"]]]
    ]],
    ["tiredYes", [
        ["*", [["Oui, épuisé.", "Yes, exhausted."], ["La journée a été longue.", "It's been a long day."], ["Oui… je n'ai pas dormi.", "Yes… I didn't sleep."],
            ["Un peu, oui.", "A little, yes."], ["Ça se voit tant que ça ?", "Is it that obvious?"], ["Je ne tiens plus debout.", "I can barely stand."]]]
    ]],
    ["tiredNo", [
        ["*", [["Non, ça va.", "No, I'm fine."], ["Pas plus que d'habitude.", "No more than usual."], ["Un peu, comme tout le monde.", "A bit, like everyone."],
            ["Non, {sir}.", "No, {sir}."], ["Ça ira.", "I'll manage."], ["Non, je viens de me lever.", "No, I just got up."]]]
    ]],
    ["offerYes", [
        ["*", [["Merci, c'est gentil.", "Thanks, that's kind."], ["Avec plaisir, merci.", "Gladly, thank you."], ["(Il accepte avec un petit sourire.)", "(He accepts with a small smile.)"],
            ["Merci, {sir}.", "Thank you, {sir}."], ["Volontiers.", "Gladly."], ["Que Dieu vous le rende.", "May God repay you."]]]
    ]],
    ["offerNo", [
        ["*", [["Non merci.", "No thanks."], ["C'est gentil, mais non.", "That's kind, but no."], ["Je n'ai besoin de rien.", "I don't need anything."],
            ["Non, gardez-le.", "No, keep it."], ["(Il refuse poliment d'un geste.)", "(He politely waves it away.)"], ["Pas maintenant, merci.", "Not now, thanks."]]],
        ["mefiant", [["Je ne prends rien de vous.", "I'm not taking anything from you."]]]
    ]],
    ["cigarette", [
        ["*", [["Je ne fume pas, désolé.", "I don't smoke, sorry."], ["Tenez, une seule.", "Here, just one."], ["J'en ai plus, désolé.", "I'm out, sorry."],
            ["Oui, tenez.", "Yes, here."], ["Non, j'ai arrêté.", "No, I quit."], ["(Il vous tend une cigarette.)", "(He hands you a cigarette.)"]]]
    ]],
    // ------------------------------------------------------------------ ordres (suite) : obéit ; refuse (noComply) ; impossible
    ["comply.dropWeapon", [
        ["*", [["D'accord ! D'accord, je la pose !", "Okay! Okay, I'm putting it down!"], ["(Il pose lentement son arme au sol.)", "(He slowly lays his weapon on the ground.)"], ["Voilà, voilà, ne tirez pas.", "There, there, don't shoot."],
            ["(Il lâche son arme.)", "(He drops his weapon.)"], ["C'est fait. Doucement.", "Done. Easy."], ["Je la pose, regardez.", "I'm putting it down, look."]]]
    ]],
    ["dropNone", [
        ["*", [["Je n'ai rien dans les mains !", "I have nothing in my hands!"], ["Je ne suis pas armé, regardez.", "I'm not armed, look."], ["(Il montre ses mains vides.)", "(He shows his empty hands.)"],
            ["Lâcher quoi ? Je n'ai rien.", "Drop what? I have nothing."], ["Mes mains sont vides, {sir}.", "My hands are empty, {sir}."], ["Je n'ai rien sur moi.", "I have nothing on me."]]]
    ]],
    ["comply.stepBack", [
        ["*", [["Je recule, je recule.", "I'm backing off, I'm backing off."], ["(Il recule de quelques pas.)", "(He takes a few steps back.)"], ["D'accord, je m'éloigne.", "Okay, I'm moving away."],
            ["Voilà, je reste là.", "There, I'll stay here."], ["Pas de problème.", "No problem."], ["(Il s'écarte, les mains visibles.)", "(He steps aside, hands visible.)"]]]
    ]],
    ["comply.comeHere", [
        ["*", [["J'arrive.", "Coming."], ["(Il s'approche prudemment.)", "(He comes closer carefully.)"], ["Oui, {sir}.", "Yes, {sir}."],
            ["D'accord, je viens.", "Okay, I'm coming."], ["(Il avance vers vous.)", "(He walks towards you.)"], ["Me voilà.", "Here I am."]]]
    ]],
    ["comply.turnAround", [
        ["*", [["(Il se retourne lentement.)", "(He slowly turns around.)"], ["D'accord… voilà.", "Okay… there."], ["Je me tourne, je me tourne.", "I'm turning, I'm turning."],
            ["(Il vous tourne le dos, les mains visibles.)", "(He turns his back to you, hands visible.)"], ["Comme ça ?", "Like this?"], ["Ne me faites rien, s'il vous plaît.", "Don't do anything to me, please."]]]
    ]],
    ["comply.sit", [
        ["*", [["(Il s'assoit.)", "(He sits down.)"], ["D'accord.", "Okay."], ["Merci, j'étais fatigué.", "Thanks, I was tired."],
            ["(Il s'assoit par terre.)", "(He sits on the ground.)"], ["Voilà.", "There."], ["Je m'assois, d'accord.", "I'm sitting, okay."]]]
    ]],
    ["comply.quiet", [
        ["*", [["(Il se tait.)", "(He falls silent.)"], ["…", "…"], ["(Il hoche la tête sans un mot.)", "(He nods without a word.)"],
            ["D'accord, d'accord.", "Okay, okay."], ["(Il serre les lèvres.)", "(He presses his lips together.)"], ["Pardon.", "Sorry."]]]
    ]],
    ["comply.hurry", [
        ["*", [["Oui, oui, je me dépêche.", "Yes, yes, I'm hurrying."], ["Je fais aussi vite que je peux !", "I'm going as fast as I can!"], ["(Il accélère.)", "(He speeds up.)"],
            ["D'accord !", "Okay!"], ["Voilà, voilà.", "There, there."], ["Ne criez pas, j'y vais.", "Don't shout, I'm going."]]]
    ]],
    ["comply.handsDown", [
        ["*", [["Merci.", "Thank you."], ["(Il baisse lentement les mains.)", "(He slowly lowers his hands.)"], ["Ah… merci, {sir}.", "Ah… thank you, {sir}."],
            ["(Il souffle et baisse les bras.)", "(He breathes out and lowers his arms.)"], ["Je peux ? D'accord.", "Can I? Okay."], ["Merci, mes bras n'en pouvaient plus.", "Thanks, my arms were aching."]]]
    ]],
    ["comply.bag", [
        ["*", [["(Il ouvre son sac.) Voilà.", "(He opens his bag.) There."], ["Regardez, il n'y a rien.", "Look, there's nothing."], ["Allez-y.", "Go ahead."],
            ["(Il vous tend son sac ouvert.)", "(He holds out his open bag.)"], ["Des affaires, rien d'autre.", "Just things, nothing else."], ["D'accord, d'accord.", "Okay, okay."]]]
    ]],
    ["bagNone", [
        ["*", [["Je n'ai pas de sac.", "I don't have a bag."], ["Quel sac ?", "What bag?"], ["Je n'ai rien sur le dos, {sir}.", "I have nothing on my back, {sir}."],
            ["Pas de sac, regardez.", "No bag, look."], ["Je n'ai que mes poches.", "I only have my pockets."], ["(Il écarte les bras : pas de sac.)", "(He spreads his arms: no bag.)"]]]
    ]],
    // ------------------------------------------------------------------ règles du jeu de rôle
    ["lawyer", [
        ["*", [["Un avocat ? Ici ?", "A lawyer? Here?"], ["Oui, je veux un avocat.", "Yes, I want a lawyer."], ["Je n'ai pas les moyens d'un avocat.", "I can't afford a lawyer."],
            ["Je n'ai rien fait, je n'en ai pas besoin.", "I haven't done anything, I don't need one."], ["Je ne dirai rien sans avocat.", "I won't say anything without a lawyer."], ["Vous allez vraiment m'en trouver un ?", "Will you really find me one?"]]]
    ]],
    ["interpreter", [
        ["*", [["Oui, ce serait mieux.", "Yes, that would be better."], ["Non, je comprends.", "No, I understand."], ["Je comprends un peu. Parlez lentement.", "I understand a little. Speak slowly."],
            ["Si vous voulez.", "If you like."], ["Ça m'aiderait, oui.", "It would help, yes."], ["Pas besoin, {sir}.", "No need, {sir}."]]]
    ]],
    ["cooperateYes", [
        ["*", [["Oui, je vais coopérer.", "Yes, I'll cooperate."], ["Je n'ai rien à cacher, demandez.", "I have nothing to hide, ask."], ["D'accord. Que voulez-vous ?", "Okay. What do you want?"],
            ["Bien sûr, {sir}.", "Of course, {sir}."], ["Je ferai ce que je peux.", "I'll do what I can."], ["Oui. Dites-moi ce qu'il faut faire.", "Yes. Tell me what to do."]]]
    ]],
    ["cooperateNo", [
        ["*", [["Je n'ai rien à vous dire.", "I have nothing to say to you."], ["Pourquoi je vous aiderais ?", "Why would I help you?"], ["Laissez-moi tranquille.", "Leave me alone."],
            ["Je ne veux pas d'ennuis.", "I don't want trouble."], ["Non.", "No."], ["On verra.", "We'll see."]]]
    ]],
    ["rights", [
        ["*", [["D'accord… je comprends.", "Okay… I understand."], ["Alors je ne dis plus rien.", "Then I won't say anything else."], ["Quels droits ? Vous faites ce que vous voulez.", "What rights? You do what you want."],
            ["Je connais mes droits, oui.", "I know my rights, yes."], ["(Il hoche la tête.)", "(He nods.)"], ["Je n'ai rien fait de mal.", "I haven't done anything wrong."]]]
    ]],
    ["wait", [
        ["*", [["D'accord, j'attends.", "Okay, I'll wait."], ["Encore ?", "Still?"], ["(Il soupire et attend.)", "(He sighs and waits.)"],
            ["Pas trop longtemps, s'il vous plaît.", "Not too long, please."], ["Bien, {sir}.", "Very well, {sir}."], ["Je n'ai pas toute la journée.", "I don't have all day."]]]
    ]],
    ["promise", [
        ["*", [["J'espère que vous dites vrai.", "I hope you're telling the truth."], ["D'accord. Je vous crois.", "Okay. I believe you."], ["Les promesses…", "Promises…"],
            ["Merci, {sir}.", "Thank you, {sir}."], ["On verra.", "We'll see."], ["(Il vous regarde longuement, puis hoche la tête.)", "(He looks at you for a long moment, then nods.)"]]]
    ]],
    ["protect", [
        ["*", [["Vraiment ?", "Really?"], ["Merci… j'en ai besoin.", "Thank you… I need it."], ["Et quand vous serez partis ?", "And when you've left?"],
            ["D'accord. Merci.", "Okay. Thank you."], ["Je veux bien vous croire.", "I'd like to believe you."], ["(Il semble un peu soulagé.)", "(He seems a little relieved.)"]]]
    ]],
    ["reward", [
        ["*", [["Je ne sais rien qui vaille une récompense.", "I don't know anything worth a reward."], ["Ce n'est pas l'argent qui me fera parler.", "Money won't make me talk."], ["Je n'ai rien à vendre, {sir}.", "I have nothing to sell, {sir}."],
            ["Intéressant… mais je ne sais rien.", "Interesting… but I don't know anything."], ["Gardez votre argent.", "Keep your money."], ["Si je sais quelque chose, je vous le dirai.", "If I know something, I'll tell you."]]],
        ["corrompu", [["Combien, exactement ?", "How much, exactly?", ["hint"]]]]
    ]],
    ["whyUs", [
        ["*", [["Pour un contrôle, je suppose.", "For a check, I suppose."], ["Non, et je ne veux pas savoir.", "No, and I don't want to know."], ["Vous êtes des soldats. C'est tout ce que je sais.", "You're soldiers. That's all I know."],
            ["Dites-le-moi, vous.", "You tell me."], ["Je ne sais pas, {sir}.", "I don't know, {sir}."], ["Vous allez me le dire, j'imagine.", "You're going to tell me, I imagine."]]]
    ]],
    // ------------------------------------------------------------------ conversation (suite)
    // « Répète » : sa dernière réplique, redite (fn_iarpServer) ; ces phrases seulement s'il n'a encore rien dit.
    ["repeat", [
        ["*", [["Je n'ai encore rien dit.", "I haven't said anything yet."], ["Quoi ?", "What?"], ["Pardon ?", "Sorry?"], ["Vous m'avez demandé quelque chose ?", "Did you ask me something?"],
            ["(Il vous regarde, interrogateur.)", "(He looks at you, questioning.)"], ["Je vous écoute.", "I'm listening."]]]
    ]],
    ["understand", [
        ["*", [["Oui, j'ai compris.", "Yes, I understood."], ["Oui, {sir}.", "Yes, {sir}."], ["Je comprends.", "I understand."], ["Compris.", "Understood."],
            ["Oui, oui.", "Yes, yes."], ["Un peu. Parlez lentement.", "A little. Speak slowly."]]]
    ]],
    // ------------------------------------------------------------------ hors sujet : insultes, provocations, humour, absurde
    ["insult", [
        ["*", [["Pas la peine d'être grossier.", "No need to be rude."], ["(Il serre les dents.)", "(He grits his teeth.)"], ["Vous parlez comme ça à tout le monde ?", "Do you talk to everyone like that?"],
            ["Je ne vous ai rien fait.", "I haven't done anything to you."], ["Respectez-moi, s'il vous plaît.", "Show me some respect, please."], ["(Il détourne le regard, vexé.)", "(He looks away, offended.)"]]],
        ["agressif", [["Répète ça pour voir.", "Say that again, I dare you."], ["Toi-même.", "Same to you."]]]
    ]],
    ["mock", [
        ["*", [["Riez si vous voulez.", "Laugh if you like."], ["Ça vous amuse ?", "Does that amuse you?"], ["(Il ne relève pas.)", "(He lets it go.)"],
            ["Vous avez fini ?", "Are you done?"], ["Je n'ai pas honte d'avoir peur.", "I'm not ashamed of being afraid."], ["Pensez ce que vous voulez.", "Think what you want."]]]
    ]],
    ["joke", [
        ["*", [["Une blague ? Maintenant ?", "A joke? Now?"], ["Je ne suis pas d'humeur, {sir}.", "I'm not in the mood, {sir}."], ["Un soldat et une chèvre entrent dans un café… non, je ne me souviens plus de la fin.", "A soldier and a goat walk into a café… no, I forget the ending."],
            ["Ma femme dit que je ne suis pas drôle.", "My wife says I'm not funny."], ["La seule blague ici, c'est le prix du pain.", "The only joke around here is the price of bread."], ["(Il esquisse un sourire.) Une autre fois.", "(He half-smiles.) Some other time."]]]
    ]],
    ["laugh", [
        ["*", [["(Il ne rit pas.)", "(He doesn't laugh.)"], ["Content que ça vous fasse rire.", "Glad it makes you laugh."], ["(Il sourit poliment.)", "(He smiles politely.)"],
            ["Ce n'est pas drôle pour moi.", "It's not funny to me."], ["Hm.", "Hm."], ["(Il rit nerveusement.)", "(He laughs nervously.)"]]]
    ]],
    ["bot", [
        ["*", [["Un robot ? Moi ? Je suis {job}.", "A robot? Me? I'm a {job}."], ["Je suis aussi humain que vous, {sir}.", "I'm as human as you, {sir}."], ["Vous avez bu ?", "Have you been drinking?"],
            ["(Il vous regarde comme si vous étiez fou.)", "(He looks at you as if you were mad.)"], ["Je ne comprends pas la question.", "I don't understand the question."], ["Pincez-moi si vous voulez.", "Pinch me if you like."]]]
    ]],
    ["absurd", [
        ["*", [["Pardon ?", "Sorry?"], ["Vous vous moquez de moi ?", "Are you making fun of me?"], ["Drôle de question.", "Strange question."],
            ["Je n'ai jamais réfléchi à ça.", "I've never thought about it."], ["(Il hausse un sourcil.)", "(He raises an eyebrow.)"], ["Vous êtes bizarre, vous.", "You're a strange one."]]]
    ]],
    ["hobbies", [
        ["*", [["Le football, quand j'ai le temps.", "Football, when I have time."], ["Je n'ai pas beaucoup de temps libre.", "I don't have much free time."], ["La musique, un peu.", "Music, a little."],
            ["Ma famille, c'est mon passe-temps.", "My family is my hobby."], ["Je joue aux cartes avec les voisins.", "I play cards with the neighbours."], ["Pourquoi vous me demandez ça ?", "Why are you asking me that?"]]]
    ]],
    ["flirt", [
        ["*", [["Ce n'est pas le moment.", "This isn't the time."], ["(Il vous regarde, gêné.)", "(He looks at you, embarrassed.)"], ["Vous êtes en service, non ?", "You're on duty, aren't you?"],
            ["Restons sérieux, {sir}.", "Let's stay serious, {sir}."], ["Hm… non.", "Hm… no."], ["Je vais faire comme si je n'avais rien entendu.", "I'll pretend I didn't hear that."]]]
    ]],
    ["compliment", [
        ["*", [["Merci, c'est gentil.", "Thanks, that's kind."], ["On fait ce qu'on peut.", "We do what we can."], ["Merci, {sir}.", "Thank you, {sir}."],
            ["Ce n'est pas grand-chose, mais c'est chez nous.", "It's not much, but it's home."], ["(Il sourit.)", "(He smiles.)"], ["Vous trouvez ?", "You think so?"]]]
    ]],
    ["time", [
        ["*", [["Il est {time}.", "It's {time}."], ["{time}, à peu près.", "About {time}."], ["Vers {time}, je crois.", "Around {time}, I think."],
            ["Il doit être {time}.", "It must be {time}."], ["{time}, {sir}.", "{time}, {sir}."], ["(Il regarde le ciel.) {time}, à peu près.", "(He looks at the sky.) About {time}."]]]
    ]],
    ["politics", [
        ["*", [["Je ne fais pas de politique.", "I don't do politics."], ["Ici, on évite d'en parler.", "Around here we avoid talking about it."], ["Je n'ai pas d'avis, {sir}.", "I have no opinion, {sir}."],
            ["Les politiciens, ce sont tous les mêmes.", "Politicians are all the same."], ["Je veux juste travailler en paix.", "I just want to work in peace."], ["Ce n'est pas une question pour moi.", "That's not a question for me."]]]
    ]],
    // ------------------------------------------------------------------ décors
    ["_open", [
        ["*", [["Écoutez,", "Listen,"], ["Bon,", "Well,"], ["Alors,", "So,"], ["Vous savez,", "You know,"]]],
        ["bavard", [["Ah, vous savez,", "Ah, you know,"], ["Eh bien, je vais vous dire,", "Well, I'll tell you,"]]],
        ["mefiant", [["Hm.", "Hm."], ["Pourquoi ces questions ?", "Why all these questions?"]]],
        ["agressif", [["Encore ?", "Again?"], ["Franchement…", "Honestly…"]]],
        ["corrompu", [["Entre nous,", "Between us,"]]]
    ]],
    // Réponse redemandée (même réponse qu'avant, mémoire de l'IA) et « Répète » (sa dernière réplique).
    ["_again", [
        ["*", [["Comme je vous l'ai dit :", "As I told you:"], ["Je vous l'ai dit :", "I told you:"], ["Je répète :", "I'll say it again:"], ["Comme tout à l'heure :", "Same as before:"]]]
    ]],
    ["_repeat", [
        ["*", [["Je disais :", "I was saying:"], ["J'ai dit :", "I said:"], ["(Plus fort.)", "(Louder.)"]]]
    ]],
    ["_hes", [
        ["*", [["Euh…", "Uh…"], ["Hm…", "Hm…"]]],
        ["nerveux", [["Je… euh…", "I… uh…"], ["C'est que…", "It's just…"], ["(Il avale sa salive.)", "(He swallows.)"]]],
        ["effraye", [["(Sa voix tremble.)", "(His voice shakes.)"], ["Je… je…", "I… I…"]]],
        ["ivre", [["Hic…", "Hic…"], ["(Il cherche ses mots.)", "(He fumbles for words.)"]]]
    ]],
    ["_close", [
        ["*", [["Voilà.", "That's it."], ["C'est tout.", "That's all."], ["Vous pouvez vérifier.", "You can check."], ["Je peux y aller ?", "Can I go?"]]],
        ["bavard", [["Enfin, c'est comme ça.", "Well, that's how it is."], ["Vous voulez savoir autre chose ?", "Anything else you want to know?"]]],
        ["nerveux", [["Je peux partir maintenant ?", "Can I leave now?"]]],
        ["agressif", [["On a fini ?", "Are we done?"]]],
        ["corrompu", [["On peut toujours s'arranger, hein.", "We can always work something out, eh."]]]
    ]],
    ["_stage", [
        ["aimed", [["(Il fixe le canon de votre arme.)", "(He stares at your gun barrel.)"], ["(Il garde les mains bien visibles.)", "(He keeps his hands in plain sight.)"]]],
        ["wounded", [["(Il grimace de douleur.)", "(He winces in pain.)"], ["(Il se tient le bras, le souffle court.)", "(He holds his arm, short of breath.)"]]],
        ["drunk", [["(Il titube un peu.)", "(He sways a little.)"], ["(Il sent l'alcool.)", "(He smells of alcohol.)"]]],
        ["tired", [["(Il a l'air épuisé.)", "(He looks exhausted.)"]]],
        ["vehicle", [["(Il garde les mains sur le volant.)", "(He keeps his hands on the wheel.)"], ["(Il baisse la vitre.)", "(He rolls down the window.)"]]],
        ["night", [["À cette heure-ci ?", "At this hour?"], ["(Il plisse les yeux dans le noir.)", "(He squints in the dark.)"]]],
        ["checkpoint", [["Encore un contrôle…", "Another checkpoint…"], ["(Il jette un œil à la barrière.)", "(He glances at the barrier.)"]]]
    ]],
    // Sujet déjà raconté à un autre joueur (fn_iarpMem « told ») : même camp (colleague) ou non (other).
    ["_told", [
        ["colleague", [["Je l'ai déjà dit à votre collègue.", "I already told your colleague."], ["Votre collègue m'a déjà demandé ça.", "Your colleague already asked me that."],
            ["Encore ? Je l'ai dit à l'autre, tout à l'heure.", "Again? I told the other one earlier."], ["Vous ne vous parlez pas entre vous ?", "Don't you people talk to each other?"]]],
        ["other", [["Je l'ai déjà dit à quelqu'un d'autre.", "I already told someone else."], ["On me l'a déjà demandé aujourd'hui.", "Someone already asked me that today."],
            ["Comme je l'ai dit à l'autre…", "Like I told the other one…"]]]
    ]],
    // Langue du pays (pays fictifs de l'identité SSE), traduite entre crochets pour qui ne la parle pas.
    ["_local", [
        ["ALT", [["Ne, ne…", "Ne, ne…"], ["Málista…", "Málista…"], ["Kaliméra…", "Kaliméra…"], ["Den xéro…", "Den xéro…"]]],
        ["TNA", [["Io, io…", "Io, io…"], ["Bula…", "Bula…"], ["Sega…", "Sega…"], ["Vinaka…", "Vinaka…"]]],
        ["LVN", [["Tak, tak…", "Tak, tak…"], ["Dzień dobry…", "Dzień dobry…"], ["Nie wiem…", "Nie wiem…"], ["Dobrze…", "Dobrze…"]]],
        ["MLD", [["Bah oui…", "Bah oui…"], ["Ma foi…", "Ma foi…"], ["Bé, voilà…", "Bé, voilà…"]]],
        ["*", [["(Il répond dans sa langue.)", "(He answers in his own language.)"]]]
    ]]
];
(_rows apply { [_x select 0, (_x select 1) call _m] }) call _m

