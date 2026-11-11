/*
    TABLE UNIQUE EFFET -> FICHIER SON. Pour changer le son d'un effet, modifier UNE ligne « fichiers[] » ci-dessous
    (fichiers dans sounds\, OGG Vorbis ; _enreg = enregistrement sous licence acquis par COMSPEC, _synth = synthétisé). Le code ne cite aucun
    nom de fichier : il lit cette table (fn_playBang, fn_tinnitus, fn_fired, fn_burst).

    Effet            | Fichier(s)                                   | Origine        | Durée   | Usage
    BangUnique       | bang_unique_a/b/c_enreg.ogg                  | enregistrement | 2,4/2,4/1,2 s | 1 lecture par flash (N fois), tirage sans répétition, pitch ±8 %, volume ±2 dB (à 60 % de variation), lectures superposées
    BangGrave        | bang_grave_enreg.ogg                         | enregistrement | 1,0 s   | couche grave jouée en même temps que le 1er flash seulement
    BangEtouffe      | bang_interieur_etouffe_enreg.ogg             | enreg. traité  | 2,8 s   | à la place du bang quand un mur sépare l'auditeur du flash
    BangLointain     | bang_lointain_enreg.ogg                      | enreg. traité  | 3,8 s   | à la place du bang dehors au-delà de 150 m (retard 343 m/s)
    Acouphene        | acouphene_attaque/segment/fin_enreg.ogg      | enreg. + enveloppes | 0,5 / 3,03 / 6 s | UN acouphène par joueur : attaque une fois, boucles enchaînées, fin une fois
    AcoupheneSynth   | acouphene_attaque/segment/fin_synth.ogg      | synthèse       | idem    | variante choisie par le réglage « Acouphène : son »
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

    BANG spéciaux (grenades COMSPEC_FB_Japan / _Women / _Surprise / _German) : le son du thème remplace le bang
    (BangUnique + BangGrave, et leurs variantes étouffée / lointaine), mêmes volume, portée et couche proche 2D.
    Sons fournis par COMSPEC (tools/gen_fb_theme_sounds.py : sonie ramenée à celle du bang standard, jamais plus fort).
    Thème          | Fichier 3D (mono)        | Couche 2D                      | Durée  | Flashs | Lecture
    Theme_Japan    | bang_japan_enreg.ogg     | bang_japan_stereo_enreg.ogg    | 0,73 s | 3      | à chaque flash, un flash toutes les ~0,5 s
    Theme_Women    | bang_women_enreg.ogg     | bang_women_stereo_enreg.ogg    | 1,13 s | 3      | à chaque flash, un flash toutes les ~0,75 s
    Theme_Surprise | bang_suprise_enreg.ogg   | bang_suprise_enreg.ogg (mono)  | 6,36 s | 5      | UNE fois au 1er flash, flashs calés sur les attaques du son
    Theme_German   | bang_german_enreg.ogg    | bang_german_stereo_enreg.ogg   | 12,6 s | 9      | UNE fois au 1er flash, flashs calés sur les attaques du son
*/
class COMSPEC_FB_Sons {
    // ui[] : classes CfgSounds de la couche proche 2D (même ordre que fichiers[]) ;
    // volume : multiplicateur de playSound3D, multiplié ensuite par le réglage « Volume des flashbangs » (200 % par
    // défaut : BangUnique joue à 5, le plafond d'une lecture ; au-delà, 2 ou 3 lectures superposées) ;
    // distance : portée max (m) ; pitchVar : variation de hauteur (+/-).
    class BangUnique {
        fichiers[] = {"bang_unique_a_enreg.ogg", "bang_unique_b_enreg.ogg", "bang_unique_c_enreg.ogg"};
        ui[] = {"COMSPEC_FB_UI_BangA", "COMSPEC_FB_UI_BangB", "COMSPEC_FB_UI_BangC"};
        volume = 2.5; distance = 900; pitchVar = 0.08; volumeVarDb = 2;
    };
    class BangGrave {
        fichiers[] = {"bang_grave_enreg.ogg"};
        ui[] = {"COMSPEC_FB_UI_Grave"};
        volume = 2.2; distance = 400; pitchVar = 0.03; volumeVarDb = 1;
    };
    class BangEtouffe {
        fichiers[] = {"bang_interieur_etouffe_enreg.ogg"};
        volume = 2.0; distance = 400; pitchVar = 0.08; volumeVarDb = 2;
    };
    class BangLointain {
        fichiers[] = {"bang_lointain_enreg.ogg"};
        volume = 3.0; distance = 2000; pitchVar = 0.08; volumeVarDb = 2;
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
        volume = 1.2; distance = 300; pitchVar = 0.08; volumeVarDb = 2;
    };
    class Drill_BangReel {
        fichiers[] = {"bang_unique_a_enreg.ogg", "bang_unique_b_enreg.ogg", "bang_unique_c_enreg.ogg"};
        ui[] = {"COMSPEC_FB_UI_BangA", "COMSPEC_FB_UI_BangB", "COMSPEC_FB_UI_BangC"};
        volume = 2.5; distance = 900; pitchVar = 0.08; volumeVarDb = 2;
    };
    class Drill_Rafale {
        fichiers[] = {"bang_rafale_enreg.ogg"};
        ui[] = {"COMSPEC_FB_UI_Rafale"};
        volume = 2.5; distance = 900; pitchVar = 0.08; volumeVarDb = 2;
    };
    class Drill_Sifflet {
        fichiers[] = {"sifflet_exercice_synth.ogg"};
        volume = 0.4; distance = 200; pitchVar = 0.05; volumeVarDb = 1.5;
    };
    class Drill_Cloche {
        fichiers[] = {"cloche_exercice_synth.ogg"};
        volume = 0.9; distance = 250; pitchVar = 0.05; volumeVarDb = 1.5;
    };
    // BANG spéciaux. unique = 1 : le son est joué UNE fois, au premier flash (sons longs) ; 0 : à chaque flash.
    // instants[] : instants des flashs (s depuis le 1er), calés sur les attaques du son ; vide : un flash tous les
    // « intervalle » s (au moins 0,6 x la durée du son, +/- 10 % x variation). duree : durée du fichier (s).
    // teintes[] : couleurs du flash et des étincelles, une tirée au hasard à chaque flash (fn_fx).
    class Theme_Japan {
        fichiers[] = {"bang_japan_enreg.ogg"};
        ui[] = {"COMSPEC_FB_UI_Japan"};
        volume = 2.5; distance = 900; pitchVar = 0.03; volumeVarDb = 1;
        duree = 0.73; unique = 0; intervalle = 0.5; instants[] = {};
        teintes[] = {{1, 1, 1}, {1, 0.3, 0.26}};
    };
    class Theme_Women {
        fichiers[] = {"bang_women_enreg.ogg"};
        ui[] = {"COMSPEC_FB_UI_Women"};
        volume = 2.5; distance = 900; pitchVar = 0.03; volumeVarDb = 1;
        duree = 1.13; unique = 0; intervalle = 0.75; instants[] = {};
        teintes[] = {{1, 0.45, 0.72}, {0.72, 0.45, 1}, {0.4, 0.95, 0.92}};
    };
    class Theme_Surprise {
        fichiers[] = {"bang_suprise_enreg.ogg"};
        ui[] = {"COMSPEC_FB_UI_Surprise"};
        volume = 2.5; distance = 900; pitchVar = 0; volumeVarDb = 0;
        duree = 6.36; unique = 1; intervalle = 1.5; instants[] = {0, 1.44, 2.76, 4.34, 6.23};
        teintes[] = {{1, 0.3, 0.3}, {1, 0.62, 0.2}, {1, 0.92, 0.3}, {0.35, 1, 0.4}, {0.35, 0.65, 1}, {0.72, 0.42, 1}};
    };
    class Theme_German {
        fichiers[] = {"bang_german_enreg.ogg"};
        ui[] = {"COMSPEC_FB_UI_German"};
        volume = 2.5; distance = 900; pitchVar = 0; volumeVarDb = 0;
        duree = 12.6; unique = 1; intervalle = 1.4; instants[] = {0, 1.58, 3.11, 4.71, 6.38, 7.87, 9.97, 11.03, 12.07};
        teintes[] = {{1, 0.82, 0.38}, {1, 0.72, 0.28}};
    };
    class Levier {
        fichiers[] = {"levier_cuillere_synth.ogg"};
        volume = 0.8; distance = 25; pitchVar = 0.05; volumeVarDb = 1;
    };
    // Acouphène : UN acouphène continu par joueur (fn_tinnitus, fn_tinnitusTick) : attaque (une fois) -> boucles de 3 s
    // enchaînées (fondus de 30 ms, fondu croisé) -> fin (décroissance de 6 s). Classes CfgSounds jouées par playSoundUI.
    class Acouphene {
        attaque = "COMSPEC_FB_TinnAttaque";
        boucle = "COMSPEC_FB_TinnBoucle";
        fin = "COMSPEC_FB_TinnFin";
        dureeAttaque = 0.5;
        dureeBoucle = 3;
        dureeFin = 6;
    };
    class AcoupheneSynth: Acouphene {
        attaque = "COMSPEC_FB_TinnAttaqueSynth";
        boucle = "COMSPEC_FB_TinnBoucleSynth";
        fin = "COMSPEC_FB_TinnFinSynth";
    };
};

#define FB_UI_SOUND(CLS,FILE) class CLS { name = QUOTE(CLS); sound[] = {QPATHTOF(sounds\FILE), 1, 1}; titles[] = {}; }
class CfgSounds {
    FB_UI_SOUND(COMSPEC_FB_TinnAttaque,acouphene_attaque_enreg.ogg);
    FB_UI_SOUND(COMSPEC_FB_TinnBoucle,acouphene_segment_enreg.ogg);
    FB_UI_SOUND(COMSPEC_FB_TinnFin,acouphene_fin_enreg.ogg);
    FB_UI_SOUND(COMSPEC_FB_TinnAttaqueSynth,acouphene_attaque_synth.ogg);
    FB_UI_SOUND(COMSPEC_FB_TinnBoucleSynth,acouphene_segment_synth.ogg);
    FB_UI_SOUND(COMSPEC_FB_TinnFinSynth,acouphene_fin_synth.ogg);
    // Couche proche 2D des bangs (fn_playBang, ui[] de la table).
    FB_UI_SOUND(COMSPEC_FB_UI_BangA,bang_unique_a_enreg.ogg);
    FB_UI_SOUND(COMSPEC_FB_UI_BangB,bang_unique_b_enreg.ogg);
    FB_UI_SOUND(COMSPEC_FB_UI_BangC,bang_unique_c_enreg.ogg);
    FB_UI_SOUND(COMSPEC_FB_UI_Grave,bang_grave_enreg.ogg);
    FB_UI_SOUND(COMSPEC_FB_UI_Rafale,bang_rafale_enreg.ogg);
    // BANG spéciaux : version stéréo quand la source l'est (SUPRISE est mono).
    FB_UI_SOUND(COMSPEC_FB_UI_Japan,bang_japan_stereo_enreg.ogg);
    FB_UI_SOUND(COMSPEC_FB_UI_Women,bang_women_stereo_enreg.ogg);
    FB_UI_SOUND(COMSPEC_FB_UI_Surprise,bang_suprise_enreg.ogg);
    FB_UI_SOUND(COMSPEC_FB_UI_German,bang_german_stereo_enreg.ogg);
};
