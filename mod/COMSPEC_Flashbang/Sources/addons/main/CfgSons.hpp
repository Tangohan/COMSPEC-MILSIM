/*
    TABLE UNIQUE EFFET -> FICHIER SON. Pour changer le son d'un effet, modifier UNE ligne « fichiers[] » ci-dessous
    (fichiers dans sounds\, OGG Vorbis ; _enreg = enregistrement de TTRD, _synth = synthétisé). Le code ne cite aucun
    nom de fichier : il lit cette table (fn_playBang, fn_tinnitus, fn_fired, fn_burst).

    Effet            | Fichier(s)                                   | Origine        | Durée   | Usage
    BangUnique       | bang_unique_a/b/c_enreg.ogg                  | enregistrement | 2,4/2,4/1,2 s | 1 lecture par flash (N fois), tirage sans répétition, pitch ±5 %, volume ±1,5 dB, lectures superposées
    BangGrave        | bang_grave_enreg.ogg                         | enregistrement | 1,0 s   | couche grave jouée en même temps que le 1er flash seulement
    BangEtouffe      | bang_interieur_etouffe_enreg.ogg             | enreg. traité  | 2,8 s   | à la place du bang quand un mur sépare l'auditeur du flash
    BangLointain     | bang_lointain_enreg.ogg                      | enreg. traité  | 3,8 s   | à la place du bang dehors au-delà de 150 m (retard 343 m/s)
    Acouphene        | acouphene_decroissance_enreg.ogg             | enreg. + enveloppe | 60 s | 1 seule lecture par joueur (playSoundUI + offset), prolongée par chaque flash
    AcoupheneSynth   | acouphene_decroissance_synth.ogg             | synthèse       | 60 s    | variante choisie par le réglage « Acouphène : son »
    Levier           | levier_cuillere_synth.ogg                    | synthèse       | 0,4 s   | cuillère qui saute au lancer
    (réserve)        | acouphene_boucle_enreg.ogg                   | enregistrement | 3 s     | non utilisé par le code

    Sons de la grenade DRILL (réglage « Son de la grenade d'entraînement » ou choix du module) : 1 lecture par pop,
    lectures indépendantes (superposées), pitch ±5 %, volume ±1,5 dB comme les bangs ; « Aucun » = fumée seule.
    Choix (drillChoix[])  | Fichier(s)                              | Origine        | Durée
    Drill_Pop (défaut)    | pop_drill_synth.ogg                     | synthèse       | 0,5 s
    Drill_PopSourd        | pop_sourd_synth.ogg                     | synthèse       | 0,7 s
    Drill_BangLeger       | bang_leger_enreg.ogg                    | enreg. raccourci | 0,8 s
    Drill_BangReel        | bang_unique_a/b/c_enreg.ogg             | enregistrement | 2,4/2,4/1,2 s (bang réel, aucun effet)
    Drill_Rafale          | bang_rafale_enreg.ogg                   | enregistrement | 3,1 s
    Drill_Sifflet         | sifflet_exercice_synth.ogg              | synthèse       | 0,8 s
    Drill_Cloche          | cloche_exercice_synth.ogg               | synthèse       | 1,6 s
    Aucun                 | (aucun fichier)                         | -              | -
*/
class COMSPEC_FB_Sons {
    // volume : multiplicateur de playSound3D, multiplié ensuite par le réglage « Volume des flashbangs » (200 % par
    // défaut : BangUnique joue à 5, le plafond d'une lecture ; au-delà, 2 ou 3 lectures superposées) ;
    // distance : portée max (m) ; pitchVar : variation de hauteur (+/-).
    class BangUnique {
        fichiers[] = {"bang_unique_a_enreg.ogg", "bang_unique_b_enreg.ogg", "bang_unique_c_enreg.ogg"};
        volume = 2.5; distance = 900; pitchVar = 0.05; volumeVarDb = 1.5;
    };
    class BangGrave {
        fichiers[] = {"bang_grave_enreg.ogg"};
        volume = 2.2; distance = 400; pitchVar = 0.03; volumeVarDb = 1;
    };
    class BangEtouffe {
        fichiers[] = {"bang_interieur_etouffe_enreg.ogg"};
        volume = 2.0; distance = 400; pitchVar = 0.05; volumeVarDb = 1.5;
    };
    class BangLointain {
        fichiers[] = {"bang_lointain_enreg.ogg"};
        volume = 3.0; distance = 2000; pitchVar = 0.04; volumeVarDb = 1.5;
    };
    // Grenade DRILL : ordre des choix du réglage CBA et des modules (index du réglage = rang dans cette liste).
    drillChoix[] = {"Pop", "PopSourd", "BangLeger", "BangReel", "Rafale", "Sifflet", "Cloche", "Aucun"};
    class Drill_Pop {
        fichiers[] = {"pop_drill_synth.ogg"};
        volume = 1.2; distance = 150; pitchVar = 0.05; volumeVarDb = 1.5;
    };
    class Drill_PopSourd {
        fichiers[] = {"pop_sourd_synth.ogg"};
        volume = 0.8; distance = 120; pitchVar = 0.05; volumeVarDb = 1.5;
    };
    class Drill_BangLeger {
        fichiers[] = {"bang_leger_enreg.ogg"};
        volume = 1.2; distance = 300; pitchVar = 0.05; volumeVarDb = 1.5;
    };
    class Drill_BangReel {
        fichiers[] = {"bang_unique_a_enreg.ogg", "bang_unique_b_enreg.ogg", "bang_unique_c_enreg.ogg"};
        volume = 2.5; distance = 900; pitchVar = 0.05; volumeVarDb = 1.5;
    };
    class Drill_Rafale {
        fichiers[] = {"bang_rafale_enreg.ogg"};
        volume = 2.5; distance = 900; pitchVar = 0.05; volumeVarDb = 1.5;
    };
    class Drill_Sifflet {
        fichiers[] = {"sifflet_exercice_synth.ogg"};
        volume = 0.4; distance = 200; pitchVar = 0.05; volumeVarDb = 1.5;
    };
    class Drill_Cloche {
        fichiers[] = {"cloche_exercice_synth.ogg"};
        volume = 0.9; distance = 250; pitchVar = 0.05; volumeVarDb = 1.5;
    };
    class Levier {
        fichiers[] = {"levier_cuillere_synth.ogg"};
        volume = 0.8; distance = 25; pitchVar = 0.05; volumeVarDb = 1;
    };
    // Acouphène : classe CfgSounds (playSoundUI) ; la courbe a(t) du fichier sert au calcul de l'offset (fn_tinnitus).
    class Acouphene {
        cfgSound = "COMSPEC_FB_Acouphene";
        duree = 60;
        // a(t) = 0,6 e^(-t/2,5) + 0,4 e^(-t/20), fondu de 48 à 60 s (tools\gen_fb_sounds.py)
        courbe[] = {0.6, 2.5, 0.4, 20};
    };
    class AcoupheneSynth: Acouphene {
        cfgSound = "COMSPEC_FB_AcoupheneSynth";
    };
};

class CfgSounds {
    class COMSPEC_FB_Acouphene {
        name = "COMSPEC Flashbang - acouphène (enregistrement)";
        sound[] = {"\z\comspec_flashbang\addons\main\sounds\acouphene_decroissance_enreg.ogg", 1, 1};
        titles[] = {};
    };
    class COMSPEC_FB_AcoupheneSynth {
        name = "COMSPEC Flashbang - acouphène (synthèse)";
        sound[] = {"\z\comspec_flashbang\addons\main\sounds\acouphene_decroissance_synth.ogg", 1, 1};
        titles[] = {};
    };
};
