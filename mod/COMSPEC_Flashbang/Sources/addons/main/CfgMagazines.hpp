// Grenades d'inventaire (lancer normal : muzzles ajoutés à Throw dans CfgWeapons.hpp).
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
};
