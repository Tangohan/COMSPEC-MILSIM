// Grenades d'inventaire (lancer normal : muzzles ajoutés à Throw dans CfgWeapons.hpp).
// Noms courts (liste d'inventaire et Arsenal coupées vers 20 caractères) : la valeur qui distingue vient en premier
// (« Flash ×5 », « Flash ×5 · 3 s », « Drill · 8 s ») ; le texte explicatif est dans descriptionShort (info-bulle).
class CfgMagazines {
    class HandGrenade;
    class COMSPEC_FB_Flash_Base: HandGrenade {
        author = "COMSPEC";
        scope = 1;
        scopeArsenal = 0;
        scopeCurator = 0;
        mass = 13;
        initSpeed = 18;
        value = 1;
        nameSound = "handgrenade";
        ammo = "COMSPEC_FB_Ammo_1";
        model = QPATHTOF(data\fb_1.p3d);
        picture = QPATHTOF(ui\fb_icon_1_ca.paa);
    };
    class COMSPEC_FB_Flash_1: COMSPEC_FB_Flash_Base {
        scope = 2;
        scopeArsenal = 2;
        scopeCurator = 2;
        displayName = "$STR_COMSPEC_FB_Flash_1_Name";
        displayNameShort = "$STR_COMSPEC_FB_Flash_1_Short";
        descriptionShort = "$STR_COMSPEC_FB_Flash_1_Desc";
        picture = QPATHTOF(ui\fb_icon_1_ca.paa);
        model = QPATHTOF(data\fb_1.p3d);
        ammo = "COMSPEC_FB_Ammo_1";
    };
    class COMSPEC_FB_Flash_2: COMSPEC_FB_Flash_Base {
        scope = 2;
        scopeArsenal = 2;
        scopeCurator = 2;
        displayName = "$STR_COMSPEC_FB_Flash_2_Name";
        displayNameShort = "$STR_COMSPEC_FB_Flash_2_Short";
        descriptionShort = "$STR_COMSPEC_FB_Flash_2_Desc";
        picture = QPATHTOF(ui\fb_icon_2_ca.paa);
        model = QPATHTOF(data\fb_2.p3d);
        ammo = "COMSPEC_FB_Ammo_2";
    };
    class COMSPEC_FB_Flash_3: COMSPEC_FB_Flash_Base {
        scope = 2;
        scopeArsenal = 2;
        scopeCurator = 2;
        displayName = "$STR_COMSPEC_FB_Flash_3_Name";
        displayNameShort = "$STR_COMSPEC_FB_Flash_3_Short";
        descriptionShort = "$STR_COMSPEC_FB_Flash_3_Desc";
        picture = QPATHTOF(ui\fb_icon_3_ca.paa);
        model = QPATHTOF(data\fb_3.p3d);
        ammo = "COMSPEC_FB_Ammo_3";
    };
    class COMSPEC_FB_Flash_4: COMSPEC_FB_Flash_Base {
        scope = 2;
        scopeArsenal = 2;
        scopeCurator = 2;
        displayName = "$STR_COMSPEC_FB_Flash_4_Name";
        displayNameShort = "$STR_COMSPEC_FB_Flash_4_Short";
        descriptionShort = "$STR_COMSPEC_FB_Flash_4_Desc";
        picture = QPATHTOF(ui\fb_icon_4_ca.paa);
        model = QPATHTOF(data\fb_4.p3d);
        ammo = "COMSPEC_FB_Ammo_4";
    };
    class COMSPEC_FB_Flash_5: COMSPEC_FB_Flash_Base {
        scope = 2;
        scopeArsenal = 2;
        scopeCurator = 2;
        displayName = "$STR_COMSPEC_FB_Flash_5_Name";
        displayNameShort = "$STR_COMSPEC_FB_Flash_5_Short";
        descriptionShort = "$STR_COMSPEC_FB_Flash_5_Desc";
        picture = QPATHTOF(ui\fb_icon_5_ca.paa);
        model = QPATHTOF(data\fb_5.p3d);
        ammo = "COMSPEC_FB_Ammo_5";
    };
    class COMSPEC_FB_Flash_6: COMSPEC_FB_Flash_Base {
        scope = 2;
        scopeArsenal = 2;
        scopeCurator = 2;
        displayName = "$STR_COMSPEC_FB_Flash_6_Name";
        displayNameShort = "$STR_COMSPEC_FB_Flash_6_Short";
        descriptionShort = "$STR_COMSPEC_FB_Flash_6_Desc";
        picture = QPATHTOF(ui\fb_icon_6_ca.paa);
        model = QPATHTOF(data\fb_6.p3d);
        ammo = "COMSPEC_FB_Ammo_6";
    };
    class COMSPEC_FB_Flash_7: COMSPEC_FB_Flash_Base {
        scope = 2;
        scopeArsenal = 2;
        scopeCurator = 2;
        displayName = "$STR_COMSPEC_FB_Flash_7_Name";
        displayNameShort = "$STR_COMSPEC_FB_Flash_7_Short";
        descriptionShort = "$STR_COMSPEC_FB_Flash_7_Desc";
        picture = QPATHTOF(ui\fb_icon_7_ca.paa);
        model = QPATHTOF(data\fb_7.p3d);
        ammo = "COMSPEC_FB_Ammo_7";
    };
    class COMSPEC_FB_Flash_8: COMSPEC_FB_Flash_Base {
        scope = 2;
        scopeArsenal = 2;
        scopeCurator = 2;
        displayName = "$STR_COMSPEC_FB_Flash_8_Name";
        displayNameShort = "$STR_COMSPEC_FB_Flash_8_Short";
        descriptionShort = "$STR_COMSPEC_FB_Flash_8_Desc";
        picture = QPATHTOF(ui\fb_icon_8_ca.paa);
        model = QPATHTOF(data\fb_8.p3d);
        ammo = "COMSPEC_FB_Ammo_8";
    };
    class COMSPEC_FB_Flash_9: COMSPEC_FB_Flash_Base {
        scope = 2;
        scopeArsenal = 2;
        scopeCurator = 2;
        displayName = "$STR_COMSPEC_FB_Flash_9_Name";
        displayNameShort = "$STR_COMSPEC_FB_Flash_9_Short";
        descriptionShort = "$STR_COMSPEC_FB_Flash_9_Desc";
        picture = QPATHTOF(ui\fb_icon_9_ca.paa);
        model = QPATHTOF(data\fb_9.p3d);
        ammo = "COMSPEC_FB_Ammo_9";
    };
    class COMSPEC_FB_Flash_50: COMSPEC_FB_Flash_Base {
        scope = 2;
        scopeArsenal = 2;
        scopeCurator = 2;
        displayName = "$STR_COMSPEC_FB_Flash_50_Name";
        displayNameShort = "$STR_COMSPEC_FB_Flash_50_Short";
        descriptionShort = "$STR_COMSPEC_FB_Flash_50_Desc";
        picture = QPATHTOF(ui\fb_icon_50_ca.paa);
        model = QPATHTOF(data\fb_50.p3d);
        ammo = "COMSPEC_FB_Ammo_50";
    };
    class COMSPEC_FB_Drill: COMSPEC_FB_Flash_Base {
        scope = 2;
        scopeArsenal = 2;
        scopeCurator = 2;
        displayName = "$STR_COMSPEC_FB_Drill_Name";
        displayNameShort = "$STR_COMSPEC_FB_Drill_Short";
        descriptionShort = "$STR_COMSPEC_FB_Drill_Desc";
        picture = QPATHTOF(ui\fb_icon_drill_ca.paa);
        model = QPATHTOF(data\fb_drill.p3d);
        ammo = "COMSPEC_FB_Ammo_Drill";
    };

    // Variantes à délai : COMSPEC_FB_Flash_<N>_D3 / _D8 et COMSPEC_FB_Drill_D3 / _D8 (mèche fixe 3 s / 8 s, CfgAmmo.hpp).
    // FB_MAG_DELAY(classe, munition, fichier, délai) : modèle data\fb_<fichier>_d<délai>.p3d, icône ui\fb_icon_<fichier>_d<délai>_ca.paa.
#define FB_MAG_DELAY(C,A,F,D) class COMSPEC_FB_##C##_D##D: COMSPEC_FB_##C { \
        displayName = QUOTE($STR_COMSPEC_FB_##C##_D##D##_Name); \
        displayNameShort = QUOTE($STR_COMSPEC_FB_##C##_D##D##_Short); \
        descriptionShort = QUOTE($STR_COMSPEC_FB_##C##_D##D##_Desc); \
        picture = QPATHTOF(ui\fb_icon_##F##_d##D##_ca.paa); \
        model = QPATHTOF(data\fb_##F##_d##D##.p3d); \
        ammo = QUOTE(COMSPEC_FB_Ammo_##A##_D##D); \
    }
#define FB_MAG_DELAYS(C,A,F) FB_MAG_DELAY(C,A,F,3); FB_MAG_DELAY(C,A,F,8)
    FB_MAG_DELAYS(Flash_1,1,1);
    FB_MAG_DELAYS(Flash_2,2,2);
    FB_MAG_DELAYS(Flash_3,3,3);
    FB_MAG_DELAYS(Flash_4,4,4);
    FB_MAG_DELAYS(Flash_5,5,5);
    FB_MAG_DELAYS(Flash_6,6,6);
    FB_MAG_DELAYS(Flash_7,7,7);
    FB_MAG_DELAYS(Flash_8,8,8);
    FB_MAG_DELAYS(Flash_9,9,9);
    FB_MAG_DELAYS(Flash_50,50,50);
    FB_MAG_DELAYS(Drill,Drill,drill);

    // Variantes « R » (rafale hyper rapide) : COMSPEC_FB_Flash_<N>_R, même modèle, icône marquée R.
#define FB_MAG_FAST(C,A,F) class COMSPEC_FB_##C##_R: COMSPEC_FB_##C { \
        displayName = QUOTE($STR_COMSPEC_FB_##C##_R_Name); \
        displayNameShort = QUOTE($STR_COMSPEC_FB_##C##_R_Short); \
        descriptionShort = QUOTE($STR_COMSPEC_FB_##C##_R_Desc); \
        picture = QPATHTOF(ui\fb_icon_##F##_r_ca.paa); \
        ammo = QUOTE(COMSPEC_FB_Ammo_##A##_R); \
    }
    FB_MAG_FAST(Flash_2,2,2);
    FB_MAG_FAST(Flash_3,3,3);
    FB_MAG_FAST(Flash_4,4,4);
    FB_MAG_FAST(Flash_5,5,5);
    FB_MAG_FAST(Flash_6,6,6);
    FB_MAG_FAST(Flash_7,7,7);
    FB_MAG_FAST(Flash_8,8,8);
    FB_MAG_FAST(Flash_9,9,9);
    FB_MAG_FAST(Flash_50,50,50);

};
