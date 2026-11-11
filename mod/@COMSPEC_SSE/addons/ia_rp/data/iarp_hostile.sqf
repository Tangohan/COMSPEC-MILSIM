/*
    Répliques des IA hostiles (fn_iarpAggro « pick ») : insultes, menaces et défi d'un face-à-face tendu, en jeu de rôle.
    [[section, valeur]…], chaque réplique [français, anglais] écrite à la main ; didascalie entre parenthèses.
      levels     paliers, du plus calme au plus violent : irritated (agacé), hostile, brink (au bord de la bagarre : dit en
                 bousculant), fight (pendant les coups), after (après avoir frappé), flee (en s'enfuyant)
      personas   caractères : * (sans trait, ou « hostile »), irritable, drunk (ivre), fanatic, resentful (rancunier),
                 proud (fier), bluff (lâche qui bluffe)
      lines      [palier, caractère, [répliques…]] : 3 répliques au moins par case (palier × caractère)
      triggers   [déclencheur, [répliques…]] : insulted, searched, papers, pushed, weapon, ordered ; dites aux paliers
                 irritated, hostile et brink quand ce déclencheur vient de jouer
      registers  [registre, [répliques…]] : street (rue), rural (campagne), educated (instruit), foreign (français
                 hésitant d'un étranger) ; registre tiré une fois par personne, gardé
      countries  [code du registre des pays, [répliques…]] : couleur locale selon le pays de ses papiers (FRA, DEU, USA,
                 AUT, BEL)
    Tirage : case du palier × 3, déclencheur × 2, registre × 1, pays × 1 ; jamais une des 8 dernières répliques de
    cette IA tant qu'il en reste d'autres.
    Limites : langage cru et menaces de bagarre, rien d'autre : aucune injure visant une origine, une religion, un sexe
    ou une orientation, rien de sexuel, aucune personne réelle. Aucun guillemet droit dans une réplique.
    Extension par mission : variable missionNamespace comspec_sse_iarpExtra_hostile (même format, ajouts seulement).
*/
[
    ["levels", ["irritated", "hostile", "brink", "fight", "after", "flee"]],
    ["personas", ["*", "irritable", "drunk", "fanatic", "resentful", "proud", "bluff"]],
    ["lines", [
        ["irritated", "*", [
            ["Vous me voulez quoi, à la fin ?", "What do you want from me, for God's sake?"],
            ["Lâchez-moi un peu.", "Get off my back."],
            ["(Il souffle, excédé.) Ça va durer longtemps ?", "(He huffs, fed up.) Is this going to take long?"],
            ["J'ai rien à vous dire.", "I've got nothing to say to you."],
            ["Vous commencez à me fatiguer.", "You're starting to wear me out."]
        ]],
        ["irritated", "irritable", [
            ["Oh, ça va, hein ! Fichez-moi la paix !", "Oh, give it a rest! Leave me alone!"],
            ["Encore vous ? J'en ai ma claque.", "You again? I've had it."],
            ["Vous me courez sur le système.", "You're getting on my last nerve."],
            ["(Il lève les yeux au ciel.) C'est pas vrai…", "(He rolls his eyes.) Unbelievable…"]
        ]],
        ["irritated", "drunk", [
            ["Hé… hé, parle moins fort, toi.", "Hey… hey, keep it down, you."],
            ["Qu'est-ce t'as à me regarder comme ça ?", "What're you lookin' at me like that for?"],
            ["(Il titube.) J't'ai rien demandé, moi.", "(He sways.) I didn't ask you for nothin'."],
            ["Laisse-moi finir mon verre, d'accord ?", "Let me finish my drink, all right?"]
        ]],
        ["irritated", "fanatic", [
            ["Vous n'avez rien à faire sur cette terre.", "You have no business on this land."],
            ["Je ne parle pas aux occupants.", "I don't talk to occupiers."],
            ["(Il vous toise sans un mot.)", "(He looks you up and down without a word.)"],
            ["Un jour, vous partirez. Tous.", "One day you'll leave. All of you."]
        ]],
        ["irritated", "resentful", [
            ["Vous, je vous ai déjà vus à l'œuvre.", "I've seen what your lot does."],
            ["J'oublie rien, moi. Rien.", "I don't forget. Not a thing."],
            ["La dernière fois, vous avez tout cassé chez moi.", "Last time, you wrecked my place."],
            ["(Il serre la mâchoire.) Qu'est-ce que vous voulez encore ?", "(He clenches his jaw.) What do you want this time?"]
        ]],
        ["irritated", "proud", [
            ["On ne me parle pas sur ce ton.", "Nobody talks to me in that tone."],
            ["Je suis chez moi, ici. Un peu de respect.", "This is my home. Show some respect."],
            ["Vous savez à qui vous parlez ?", "Do you know who you're talking to?"],
            ["(Il redresse le menton.) Surveillez vos manières.", "(He lifts his chin.) Mind your manners."]
        ]],
        ["irritated", "bluff", [
            ["Faites attention à ce que vous dites, hein.", "Watch what you say, all right."],
            ["Je connais du monde, moi. Du beau monde.", "I know people. Serious people."],
            ["(Il jette un œil derrière lui.) Vous devriez pas rester là.", "(He glances behind him.) You shouldn't hang around here."],
            ["Me cherchez pas. C'est un conseil.", "Don't push me. That's advice."]
        ]],
        ["hostile", "*", [
            ["Dégage.", "Get lost."],
            ["Casse-toi de chez moi !", "Get the hell off my property!"],
            ["T'as pas honte ?", "Aren't you ashamed of yourself?"],
            ["Tu vas le regretter, je te le dis.", "You're going to regret this, I'm telling you."],
            ["(Il crache par terre.) Voilà ce que je pense de vous.", "(He spits on the ground.) That's what I think of you."],
            ["Ferme-la et tire-toi.", "Shut it and clear off."]
        ]],
        ["hostile", "irritable", [
            ["Ta gueule ! Ta gueule, maintenant !", "Shut up! Just shut up now!"],
            ["Barre-toi avant que je m'énerve pour de bon !", "Beat it before I really lose it!"],
            ["Tu me soûles, tu comprends ça ? Tu me soûles !", "You're doing my head in, got it? Doing my head in!"],
            ["(Il tape du poing dans sa paume.) Encore un mot.", "(He smacks his fist into his palm.) One more word."]
        ]],
        ["hostile", "drunk", [
            ["T'veux te battre ? Hein ? T'veux te battre ?", "You wanna fight? Huh? You wanna fight?"],
            ["Viens là, toi… viens là que j't'explique.", "C'mere, you… c'mere so I can explain."],
            ["(Il renverse son verre.) Regarde c'que tu m'fais faire, abruti !", "(He spills his drink.) Look what you made me do, moron!"],
            ["J'suis pas soûl. C'est toi qu'es moche.", "I'm not drunk. You're just ugly."]
        ]],
        ["hostile", "fanatic", [
            ["Vous paierez pour tout ce que vous avez fait ici.", "You will pay for everything you've done here."],
            ["Votre place est dans une fosse, pas chez nous.", "Your place is in a pit, not among us."],
            ["Je n'ai pas peur de vous. Ni de mourir.", "I'm not afraid of you. Or of dying."],
            ["(Il crache à vos pieds.) Chiens.", "(He spits at your feet.) Dogs."]
        ]],
        ["hostile", "resentful", [
            ["Mon frère, c'est vous qui l'avez emmené. Je sais que c'est vous.", "My brother, you took him. I know it was you."],
            ["Vous avez la mémoire courte. Pas moi.", "You've got a short memory. I don't."],
            ["Un jour, je vous le ferai payer. Un par un.", "One day I'll make you pay. One by one."],
            ["Regardez-moi bien. Je veux que vous vous souveniez de ma tête.", "Take a good look. I want you to remember my face."]
        ]],
        ["hostile", "proud", [
            ["Vous m'insultez devant ma maison. Sortez.", "You insult me in front of my own house. Get out."],
            ["Personne ne me donne d'ordres. Personne.", "Nobody gives me orders. Nobody."],
            ["Baissez les yeux quand vous me parlez.", "Lower your eyes when you speak to me."],
            ["J'ai nourri des hommes meilleurs que vous.", "I've fed better men than you."]
        ]],
        ["hostile", "bluff", [
            ["Je vais te casser ta gueule ! … Enfin, me tente pas.", "I'm gonna smash your face in! … Well, don't tempt me."],
            ["Retiens-moi ! Quelqu'un me retient, sinon je réponds de rien !", "Hold me back! Somebody hold me back or I won't answer for it!"],
            ["T'as de la chance que je sois de bonne humeur.", "You're lucky I'm in a good mood."],
            ["(Il avance d'un pas, puis recule de deux.) Fais gaffe, hein !", "(He steps forward, then back two.) Watch it, yeah!"]
        ]],
        ["brink", "*", [
            ["Touche-moi encore et je te défonce.", "Touch me again and I'll wreck you."],
            ["Recule. Recule, je te dis !", "Back off. Back off, I said!"],
            ["Je vais te casser ta gueule !", "I'm gonna smash your face in!"],
            ["(Il vous repousse des deux mains.) Dégage de là !", "(He shoves you with both hands.) Get out of here!"],
            ["Tu veux qu'on règle ça ? Maintenant ?", "You want to settle this? Right now?"]
        ]],
        ["brink", "irritable", [
            ["J'en peux plus de ta tronche ! Dégage !", "I can't stand your face any longer! Get out!"],
            ["Ça y est, tu m'as trouvé !", "That's it, you've found me now!"],
            ["(Il vous bouscule.) Pousse-toi !", "(He barges into you.) Move!"],
            ["Je vais te péter la mâchoire, tu m'entends ?", "I'm gonna break your jaw, you hear me?"]
        ]],
        ["brink", "drunk", [
            ["Pousse-toi d'là… pousse-toi, j'te dis !", "Outta my way… outta my way, I said!"],
            ["(Il vous attrape par le col.) Répète un peu, pour voir ?", "(He grabs you by the collar.) Say that again, go on?"],
            ["J'vais t'en coller une, tu vas pas comprendre.", "I'm gonna land you one, you won't know what hit you."],
            ["Tiens-moi ça… non, laisse. J'vais te démonter.", "Hold this… no, leave it. I'm gonna take you apart."]
        ]],
        ["brink", "fanatic", [
            ["Ne posez plus jamais la main sur moi.", "Never lay a hand on me again."],
            ["Vous voulez un martyr ? Venez.", "You want a martyr? Come on."],
            ["(Il vous repousse du plat de la main.) Arrière !", "(He pushes you back with the flat of his hand.) Get back!"],
            ["Cette terre vous vomira. Et moi avec elle.", "This land will spit you out. And so will I."]
        ]],
        ["brink", "resentful", [
            ["Ça fait des années que j'attends ça.", "I've been waiting years for this."],
            ["Pour mon frère. Pour ma maison.", "For my brother. For my house."],
            ["(Il vous pousse violemment.) Vous vous souvenez, maintenant ?", "(He shoves you hard.) Remember now?"],
            ["Aujourd'hui, c'est moi qui pose les questions.", "Today I'm the one asking the questions."]
        ]],
        ["brink", "proud", [
            ["Vous avez levé la main sur moi. Grosse erreur.", "You raised a hand to me. Big mistake."],
            ["On ne m'humilie pas. Pas deux fois.", "Nobody humiliates me. Not twice."],
            ["(Il écarte votre bras d'un revers.) Ne me touchez pas.", "(He knocks your arm aside.) Don't touch me."],
            ["Posez votre arme et venez me le redire en face.", "Put your weapon down and say that to my face."]
        ]],
        ["brink", "bluff", [
            ["Je… je vais te démolir ! Approche pas !", "I… I'll demolish you! Stay back!"],
            ["(Il lève les poings, les genoux qui tremblent.) Viens, viens !", "(He raises his fists, knees shaking.) Come on, come on!"],
            ["J'ai fait de la boxe, moi ! Un peu !", "I used to box, you know! A bit!"],
            ["Un pas de plus et… et tu verras !", "One more step and… and you'll see!"]
        ]],
        ["fight", "*", [
            ["Tiens ! Prends ça !", "There! Take that!"],
            ["(Il frappe.) Alors, tu fais moins le malin ?", "(He swings.) Not so clever now, are you?"],
            ["Encore ? T'en veux encore ?", "More? You want more?"],
            ["Je t'avais prévenu !", "I warned you!"],
            ["(Un grognement, un coup.)", "(A grunt, a blow.)"]
        ]],
        ["fight", "irritable", [
            ["Tu vas la fermer, oui ?!", "Will you shut up now?!"],
            ["Voilà ! Voilà ce que t'as gagné !", "There! That's what you get!"],
            ["(Il cogne en hurlant.) Dégage ! Dégage !", "(He swings, screaming.) Get out! Get out!"],
            ["J'en ai marre ! Marre de vous tous !", "I'm sick of it! Sick of all of you!"]
        ]],
        ["fight", "drunk", [
            ["(Il frappe dans le vide, puis recommence.) Bouge pas, toi !", "(He swings at thin air, then tries again.) Hold still, you!"],
            ["T'as vu ? T'as vu ça ?", "See that? Did you see that?"],
            ["Y en a deux, de toi… j'tape celui du milieu.", "There's two of you… I'll hit the one in the middle."],
            ["Tiens ! Et… et tiens !", "Take that! And… and that!"]
        ]],
        ["fight", "fanatic", [
            ["Pour les nôtres !", "For our people!"],
            ["(Il frappe sans un cri.)", "(He strikes without a sound.)"],
            ["Vous ne nous ferez jamais plier !", "You will never break us!"],
            ["Frappez-moi, tuez-moi, d'autres viendront !", "Hit me, kill me, others will come!"]
        ]],
        ["fight", "resentful", [
            ["Ça, c'est pour mon frère !", "That one's for my brother!"],
            ["Et ça, pour la maison !", "And that's for the house!"],
            ["(Il frappe, les dents serrées.) Vous vous souvenez, maintenant ?", "(He strikes, teeth clenched.) Do you remember now?"],
            ["J'ai compté chaque jour. Chaque jour !", "I counted every day. Every day!"]
        ]],
        ["fight", "proud", [
            ["Voilà pour ton insolence !", "That's for your insolence!"],
            ["On apprend le respect, ici !", "We teach respect around here!"],
            ["(Il frappe droit, sans hâte.)", "(He strikes straight, unhurried.)"],
            ["Relève-toi, que je recommence !", "Get up so I can do it again!"]
        ]],
        ["fight", "bluff", [
            ["(Il frappe en fermant les yeux.) Aaah !", "(He swings with his eyes shut.) Aaah!"],
            ["Pardon ! … Non, pas pardon ! Tiens !", "Sorry! … No, not sorry! Take that!"],
            ["Tu l'as cherché ! C'est toi qui l'as cherché !", "You asked for it! You're the one who asked for it!"],
            ["(Il donne une gifle maladroite et recule aussitôt.)", "(He lands a clumsy slap and backs away at once.)"]
        ]],
        ["after", "*", [
            ["Maintenant, dégage.", "Now get lost."],
            ["(Il secoue sa main.) T'as compris, cette fois ?", "(He shakes out his hand.) Got the message this time?"],
            ["Et reviens pas.", "And don't come back."],
            ["Voilà. C'est tout ce que t'auras de moi.", "There. That's all you'll get from me."]
        ]],
        ["after", "irritable", [
            ["Voilà ! Content ? T'es content ?", "There! Happy? Are you happy?"],
            ["Fallait pas me chercher. Fallait pas.", "Shouldn't have pushed me. Shouldn't have."],
            ["(Il respire fort.) Fiche le camp. Tout de suite.", "(He breathes hard.) Clear off. Right now."],
            ["Ça fait du bien, tiens.", "That felt good, actually."]
        ]],
        ["after", "drunk", [
            ["(Il regarde son poing, étonné.) Ah ben… ça marche encore.", "(He looks at his fist, surprised.) Well… still works."],
            ["Tu… tu l'as pas volée, celle-là.", "You… you had that one coming."],
            ["Bon. J'retourne boire.", "Right. Back to my drink."],
            ["(Il manque de tomber.) Qui a gagné ? C'est moi ?", "(He nearly falls over.) Who won? Was it me?"]
        ]],
        ["after", "fanatic", [
            ["Ce n'est que le début.", "This is only the beginning."],
            ["Allez le dire aux vôtres.", "Go and tell your people."],
            ["(Il essuie sa main sur sa chemise.) Je ne regrette rien.", "(He wipes his hand on his shirt.) I regret nothing."],
            ["Arrêtez-moi. D'autres se lèveront.", "Arrest me. Others will rise."]
        ]],
        ["after", "resentful", [
            ["On est loin d'être quittes.", "We're a long way from even."],
            ["Ça ne me rend pas mon frère. Mais ça soulage.", "It doesn't bring my brother back. But it helps."],
            ["(Il vous fixe.) La prochaine fois, je ne m'arrêterai pas.", "(He stares at you.) Next time I won't stop."],
            ["Souvenez-vous de ce jour.", "Remember this day."]
        ]],
        ["after", "proud", [
            ["Voilà. L'honneur est sauf.", "There. Honour is satisfied."],
            ["Maintenant, vous me parlerez autrement.", "Now you'll speak to me differently."],
            ["(Il rajuste sa veste.) Partez.", "(He straightens his jacket.) Leave."],
            ["Vous l'avez cherché, vous l'avez eu.", "You asked for it, you got it."]
        ]],
        ["after", "bluff", [
            ["(Il regarde sa main, horrifié.) Je… j'ai pas fait exprès !", "(He stares at his hand, horrified.) I… I didn't mean to!"],
            ["Voilà ! Et… et je peux recommencer ! Peut-être !", "There! And… and I can do it again! Maybe!"],
            ["Ne me frappez pas ! C'est vous qui avez commencé !", "Don't hit me! You started it!"],
            ["Oh là là… oh là là, qu'est-ce que j'ai fait…", "Oh no… oh no, what have I done…"]
        ]],
        ["flee", "*", [
            ["(Il tourne les talons et file.)", "(He turns on his heel and bolts.)"],
            ["Vous m'aurez pas !", "You won't get me!"],
            ["C'est pas fini ! Je reviendrai !", "This isn't over! I'll be back!"],
            ["(Il s'enfuit sans se retourner.)", "(He runs off without looking back.)"]
        ]],
        ["flee", "irritable", [
            ["Allez tous vous faire voir !", "To hell with the lot of you!"],
            ["(Il part en courant, en jurant.)", "(He runs off, cursing.)"],
            ["Foutez-moi la paix, c'est tout ce que je demande !", "Leave me the hell alone, that's all I ask!"],
            ["Vous m'avez assez vu !", "You've seen enough of me!"]
        ]],
        ["flee", "drunk", [
            ["(Il part en zigzag.) J'reviens… j'vais chercher mes copains !", "(He zigzags away.) I'll be back… gonna get my mates!"],
            ["Vous m'avez pas vu ! J'étais pas là !", "You never saw me! I wasn't here!"],
            ["(Il trébuche, se relève et repart.)", "(He trips, gets up and keeps going.)"],
            ["Attendez-moi là… bougez pas…", "Wait right there… don't move…"]
        ]],
        ["flee", "fanatic", [
            ["Nous nous reverrons. Et je ne serai pas seul.", "We'll meet again. And I won't be alone."],
            ["(Il disparaît entre les maisons.)", "(He vanishes between the houses.)"],
            ["Tout le village saura ce que vous faites !", "The whole village will hear what you do!"],
            ["Vous ne dormirez plus tranquilles ici !", "You'll never sleep easy here again!"]
        ]],
        ["flee", "resentful", [
            ["Je sais où vous dormez.", "I know where you sleep."],
            ["(Il recule sans vous quitter des yeux, puis s'enfuit.)", "(He backs away, eyes locked on you, then runs.)"],
            ["Ce n'est que partie remise.", "This is only postponed."],
            ["Je n'oublierai pas vos visages.", "I won't forget your faces."]
        ]],
        ["flee", "proud", [
            ["Je ne fuis pas. Je vais chercher les miens.", "I'm not running. I'm fetching my family."],
            ["(Il s'éloigne à grands pas, sans courir.)", "(He strides away, refusing to run.)"],
            ["Vous entendrez parler de moi.", "You'll be hearing about me."],
            ["Ce village a une mémoire, messieurs.", "This village has a memory, gentlemen."]
        ]],
        ["flee", "bluff", [
            ["(Il détale.) Me suivez pas ! Je suis dangereux !", "(He scarpers.) Don't follow me! I'm dangerous!"],
            ["Maman !", "Mum!"],
            ["C'était pour rire ! C'était pour rire !", "I was joking! I was only joking!"],
            ["(Il court en se protégeant la tête.)", "(He runs with his arms over his head.)"]
        ]]
    ]],
    ["triggers", [
        ["insulted", [
            ["Répète un peu ce que tu viens de dire ?", "Say that again, go on?"],
            ["Tu parles à qui, là ?", "Who do you think you're talking to?"],
            ["On m'insulte pas. Pas chez moi.", "Nobody insults me. Not on my own doorstep."],
            ["Ta mère t'a appris à parler comme ça ?", "Did your mother teach you to talk like that?"],
            ["Ravale ça tout de suite.", "Take that back right now."]
        ]],
        ["searched", [
            ["Enlève tes pattes !", "Get your paws off me!"],
            ["Tu me fouilles pas. Personne me fouille.", "You're not searching me. Nobody searches me."],
            ["(Il repousse votre main.) Touche pas à mes poches !", "(He bats your hand away.) Keep out of my pockets!"],
            ["Vous cherchez quoi ? Y a rien, là-dedans !", "What are you looking for? There's nothing in there!"],
            ["Bas les pattes, j'ai dit !", "Hands off, I said!"]
        ]],
        ["papers", [
            ["Mes papiers ? Encore ? Vous les avez vus hier !", "My papers? Again? You saw them yesterday!"],
            ["Je vous montre rien du tout.", "I'm not showing you a thing."],
            ["Vous avez qu'à les deviner, mes papiers.", "You can guess what my papers say."],
            ["Tous les jours la même comédie. J'en ai assez.", "Same circus every day. I've had enough."],
            ["(Il tapote sa poche sans rien sortir.) Ils sont très bien là.", "(He pats his pocket and takes nothing out.) They're fine where they are."]
        ]],
        ["pushed", [
            ["Tu m'as poussé ? Tu m'as poussé, là ?", "Did you just push me? Did you just push me?"],
            ["Touche-moi encore et je te défonce.", "Touch me again and I'll wreck you."],
            ["(Il vous rend la bousculade.) Chacun son tour !", "(He shoves you right back.) My turn!"],
            ["Me bouscule pas !", "Don't you shove me!"],
            ["Tu veux jouer à ça ? D'accord.", "You want to play that game? Fine."]
        ]],
        ["weapon", [
            ["Tu crois que ton fusil me fait peur ?", "You think your rifle scares me?"],
            ["Vas-y, tire. Devant tout le monde.", "Go on, shoot. In front of everyone."],
            ["Baisse ça. Baisse ça ou je te le fais manger.", "Lower that. Lower it or I'll make you eat it."],
            ["Facile, avec une arme. Pose-la, pour voir.", "Easy with a gun. Put it down and let's see."],
            ["(Il avance vers le canon.) Alors ? Qu'est-ce que t'attends ?", "(He walks towards the muzzle.) Well? What are you waiting for?"]
        ]],
        ["ordered", [
            ["Tu me donnes pas d'ordres.", "You don't give me orders."],
            ["Je suis pas ton chien.", "I'm not your dog."],
            ["Fais-le toi-même.", "Do it yourself."],
            ["Et si je dis non ? Tu fais quoi ?", "And if I say no? What then?"],
            ["(Il croise les bras.) Non.", "(He folds his arms.) No."]
        ]]
    ]],
    ["registers", [
        ["street", [
            ["Wesh, tu te crois où, là ?", "Yo, where do you think you are?"],
            ["Bouge de là, frère, je rigole pas.", "Move, bro, I'm not kidding."],
            ["Tu fais le chaud avec ton gilet, hein ?", "Acting tough in your vest, huh?"],
            ["Sur ma vie, tu vas le regretter.", "On my life, you'll regret this."]
        ]],
        ["rural", [
            ["Fichez le camp de mes terres, nom d'un chien !", "Get off my land, damn it!"],
            ["Chez nous, on règle ça à la fourche.", "Round here we settle this with a pitchfork."],
            ["J'ai lâché les chiens pour moins que ça.", "I've set the dogs loose for less."],
            ["Vous piétinez mon champ, en plus.", "And you're trampling my field, on top of it."]
        ]],
        ["educated", [
            ["Votre attitude est parfaitement inadmissible.", "Your behaviour is utterly unacceptable."],
            ["Je vous conseille vivement de changer de ton.", "I strongly advise you to change your tone."],
            ["Vous outrepassez vos droits, et vous le savez.", "You are overstepping your rights, and you know it."],
            ["Je noterai votre matricule, soyez-en certain.", "I shall be taking your service number, be sure of it."]
        ]],
        ["foreign", [
            ["Toi… pas toucher moi ! Compris ?", "You… no touch me! Understand?"],
            ["Moi pas peur de toi. Pas peur !", "Me not afraid of you. Not afraid!"],
            ["Toi partir. Maintenant. Vite !", "You go. Now. Quick!"],
            ["(Il jure dans sa langue, puis :) Va-t'en !", "(He swears in his own language, then:) Go away!"]
        ]]
    ]],
    ["countries", [
        ["FRA", [
            ["Non mais ça va pas, la tête ?", "Have you completely lost your mind?"],
            ["Je vais porter plainte, vous allez voir !", "I'm filing a complaint, you'll see!"],
            ["On est en France, ici, pas chez les sauvages !", "This is France, not the wild!"]
        ]],
        ["DEU", [
            ["Das reicht ! Ça suffit, j'ai dit !", "Das reicht! That's enough, I said!"],
            ["Chez nous, on respecte les règles. Vous, non.", "Where I come from we respect the rules. You don't."],
            ["Verschwinde ! Disparais !", "Verschwinde! Get lost!"]
        ]],
        ["USA", [
            ["Hey ! Je connais mes droits, mon gars !", "Hey! I know my rights, buddy!"],
            ["Vous aurez des nouvelles de mon avocat.", "You'll be hearing from my lawyer."],
            ["Back off ! Recule, tout de suite !", "Back off! Right now!"]
        ]],
        ["AUT", [
            ["Schleich di ! Fiche le camp !", "Schleich di! Clear off!"],
            ["Ici, on n'aime pas qu'on nous bouscule.", "We don't take kindly to being pushed around here."],
            ["Geh weg, sonst kracht's ! Pars, ou ça va claquer !", "Geh weg, sonst kracht's! Go, or there'll be trouble!"]
        ]],
        ["BEL", [
            ["Mais enfin, ça va pas, non, une fois ?", "Come on now, what's wrong with you?"],
            ["Allez, oust ! Du balai !", "Go on, shoo! Out!"],
            ["Tu vas voir ce que tu vas prendre, fieu !", "You'll see what you've got coming, mate!"]
        ]]
    ]]
]
