/*
    Banques de phrases des IA (fn_iarpGen) : intention -> ton -> [[français, anglais]…]. Ton « * » : toutes les
    personnalités. Tons : calme, nerveux, agressif, mefiant, bavard, laconique, corrompu, effraye.
    Variables remplacées à l'affichage (fn_iarpFill) : {city} {employer} {job} {travel} {pob} {contact} {address} {name}
    {first} {age} {family} {know} {sir} {player} {weapon}. Une phrase n'utilise que des faits de la couverture de l'IA :
    sans le fait, le serveur choisit « dunno » (jamais d'invention).
    Décors : _open (début), _hes (hésitation), _close (fin), _stage (didascalie selon le contexte), _local (langue du
    pays, traduite entre crochets pour qui ne la parle pas), _told (déjà raconté à un autre joueur).
    Une mission peut ajouter des phrases avec un fichier iarp_bank.sqf à sa racine, même format : elles s'ajoutent.
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
        ["nerveux", [["S'il vous plaît, pas ça… je vais tout vous dire !", "Please, not that… I'll tell you everything!"], ["Ne tirez pas, ne tirez pas !", "Don't shoot, don't shoot!"]]],
        ["mefiant", [["Je connais mes droits.", "I know my rights."], ["Je veux parler à votre chef.", "I want to talk to your superior."]]]
    ]],
    ["bribeYes", [
        ["*", [["Bon… je n'ai rien vu, d'accord ?", "Well… I didn't see anything, okay?"], ["C'est gentil. Entre nous, alors.", "That's kind. Between us, then."], ["(Il empoche discrètement.)", "(He pockets it discreetly.)"], ["On peut s'arranger, oui.", "We can work something out, yes."]]],
        ["corrompu", [["Ah, là on se comprend.", "Ah, now we understand each other."], ["Avec ça, j'ai la mémoire qui revient.", "With that, my memory's coming back."], ["Vous êtes quelqu'un de raisonnable, {sir}.", "You're a reasonable person, {sir}."]]]
    ]],
    ["bribeNo", [
        ["*", [["Gardez votre argent.", "Keep your money."], ["Je ne suis pas à vendre.", "I'm not for sale."], ["Vous m'insultez.", "You insult me."], ["Non merci. Je ne veux pas d'ennuis.", "No thanks. I don't want trouble."]]],
        ["agressif", [["Vous me prenez pour qui ?", "Who do you take me for?"]]],
        ["effraye", [["Non, non, si on me voit prendre ça…", "No, no, if they see me take that…"]]]
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
        ["*", [["Je ne sais rien.", "I don't know anything."], ["Je n'ai rien vu.", "I didn't see anything."], ["Je ne me mêle pas de ça.", "I stay out of that."], ["Demandez à quelqu'un d'autre.", "Ask someone else."], ["Rien à signaler, {sir}.", "Nothing to report, {sir}."]]],
        ["nerveux", [["Rien ! Je ne sais rien, je vous jure.", "Nothing! I don't know anything, I swear."]]],
        ["mefiant", [["Et si je savais quelque chose, pourquoi je vous le dirais ?", "And if I knew something, why would I tell you?"]]],
        ["corrompu", [["Ça dépend… qu'est-ce que j'y gagne ?", "That depends… what's in it for me?"]]],
        ["effraye", [["S'ils savent que je parle, ils me tuent…", "If they know I talk, they'll kill me…"]]]
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
        ["*", [["Oui… {weapon}. Je l'ai sur moi.", "Yes… {weapon}. I have it on me."], ["{weapon}. J'ai le droit, non ?", "{weapon}. I'm allowed, aren't I?"], ["Juste {weapon}, pour la route.", "Just {weapon}, for the road."], ["Oui, j'ai {weapon}. Pour me défendre.", "Yes, I have {weapon}. For self-defence."], ["J'ai {weapon}, oui.", "I have {weapon}, yes."], ["Juste {weapon}. Tout le monde en a une, ici.", "Just {weapon}. Everyone has one here."]]],
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
    ["howareyou", [
        ["*", [["Ça va, merci.", "I'm fine, thanks."], ["On fait aller.", "Getting by."], ["Comme on peut, avec tout ça.", "As well as can be, with all this."], ["Ça allait mieux avant le contrôle.", "Better before the checkpoint."]]],
        ["bavard", [["Oh, ça va, mais les prix ont encore augmenté au marché, vous imaginez ?", "Oh, I'm fine, but prices went up again at the market, can you imagine?"]]],
        ["nerveux", [["Ça va… oui, ça va.", "Fine… yes, fine."]]]
    ]],
    ["yes", [
        ["*", [["Bon.", "Fine."], ["Très bien.", "Very well."], ["Comme vous dites.", "As you say."], ["D'accord.", "Okay."], ["Bien.", "Good."], ["Si vous le dites.", "If you say so."]]]
    ]],
    ["no", [
        ["*", [["Bon, d'accord.", "Alright then."], ["Si vous le dites.", "If you say so."], ["Hm.", "Hm."], ["Ah bon.", "Oh, okay."], ["Comme vous voulez.", "As you like."], ["Bien.", "Fine."]]]
    ]],
    ["unknown", [
        ["*", [["Pardon, je n'ai pas compris.", "Sorry, I didn't understand."], ["Vous pouvez répéter ?", "Can you say that again?"], ["Je ne vois pas ce que vous voulez dire.", "I don't see what you mean."], ["(Il vous regarde sans comprendre.)", "(He looks at you blankly.)"], ["Comment ça ?", "What do you mean?"], ["Je ne suis pas sûr de comprendre, {sir}.", "I'm not sure I understand, {sir}."], ["Je ne comprends pas.", "I don't understand."], ["Pardon ?", "Sorry?"], ["Je ne comprends pas ce que vous voulez.", "I don't understand what you want."]]]
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
    // ------------------------------------------------------------------ décors
    ["_open", [
        ["*", [["Écoutez,", "Listen,"], ["Bon,", "Well,"], ["Alors,", "So,"], ["Vous savez,", "You know,"]]],
        ["bavard", [["Ah, vous savez,", "Ah, you know,"], ["Eh bien, je vais vous dire,", "Well, I'll tell you,"]]],
        ["mefiant", [["Hm.", "Hm."], ["Pourquoi ces questions ?", "Why all these questions?"]]],
        ["agressif", [["Encore ?", "Again?"], ["Franchement…", "Honestly…"]]],
        ["corrompu", [["Entre nous,", "Between us,"]]]
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

