#include "script_component.hpp"

class CfgPatches {
    class comspec_sse_evidence {
        name = "COMSPEC SSE - Evidence Items";
        units[] = {};
        weapons[] = {
            "COMSPEC_SSE_EvidenceBag",
            "COMSPEC_SSE_Gloves",
            "COMSPEC_SSE_Camera",
            "COMSPEC_SSE_FingerprintKit",
            "COMSPEC_SSE_DNKit",
            "COMSPEC_SSE_SEEKII",
            "COMSPEC_SSE_USB",
            "COMSPEC_SSE_SDCard",
            "COMSPEC_SSE_HardDrive",
            "COMSPEC_SSE_Laptop",
            "COMSPEC_SSE_Phone",
            "COMSPEC_SSE_Smartphone",
            "COMSPEC_SSE_DocumentBag",
            "COMSPEC_SSE_Notebook",
            "COMSPEC_SSE_SatPhone",
            "COMSPEC_SSE_Terminal",
            "COMSPEC_SSE_HeadBag"
        };
        requiredVersion = REQUIRED_VERSION;
        requiredAddons[] = {
            "comspec_sse_main",
            "A3_Weapons_F",
            "cba_main",
            "cba_common",
            "ace_common",
            "A3_Characters_F"
        };
        author = "COMSPEC";
        VERSION_CONFIG;
    };
};

class CfgWeapons {
    // Compatible inventaire ACE / Arsenal (CBA MiscItem)
    class CBA_MiscItem;
    class CBA_MiscItem_ItemInfo;

    class COMSPEC_SSE_ItemBase: CBA_MiscItem {
        scope = 1;
        author = "COMSPEC";
        descriptionShort = "$STR_COMSPEC_SSE_CFG_F1B11E4B26";
        picture = "\A3\Weapons_F\Data\placeholder_co.paa";
        model = "\A3\weapons_F\ammo\mag_univ.p3d";
        class ItemInfo: CBA_MiscItem_ItemInfo {
            mass = 1;
        };
    };

    class COMSPEC_SSE_EvidenceBag: COMSPEC_SSE_ItemBase {
        scope = 2;
        displayName = "$STR_COMSPEC_SSE_CFG_0BF3305EBB";
        descriptionShort = "$STR_COMSPEC_SSE_CFG_C5E048FE44";
        class ItemInfo: CBA_MiscItem_ItemInfo { mass = 1; };
    };

    class COMSPEC_SSE_Gloves: COMSPEC_SSE_ItemBase {
        scope = 2;
        displayName = "$STR_COMSPEC_SSE_CFG_46E7D52BE6";
        descriptionShort = "$STR_COMSPEC_SSE_CFG_A0C18D0B0C";
        class ItemInfo: CBA_MiscItem_ItemInfo { mass = 0.5; };
    };

    class COMSPEC_SSE_Camera: COMSPEC_SSE_ItemBase {
        scope = 2;
        displayName = "$STR_COMSPEC_SSE_CFG_3FE9D437FE";
        descriptionShort = "$STR_COMSPEC_SSE_CFG_F0BB7B9475";
        class ItemInfo: CBA_MiscItem_ItemInfo { mass = 3; };
    };

    class COMSPEC_SSE_FingerprintKit: COMSPEC_SSE_ItemBase {
        scope = 2;
        displayName = "$STR_COMSPEC_SSE_CFG_71D3057E17";
        descriptionShort = "$STR_COMSPEC_SSE_CFG_164973B5AB";
        class ItemInfo: CBA_MiscItem_ItemInfo { mass = 4; };
    };

    class COMSPEC_SSE_DNKit: COMSPEC_SSE_ItemBase {
        scope = 2;
        displayName = "$STR_COMSPEC_SSE_CFG_8EF80BA73D";
        descriptionShort = "$STR_COMSPEC_SSE_CFG_748B65B7BD";
        class ItemInfo: CBA_MiscItem_ItemInfo { mass = 4; };
    };

    class COMSPEC_SSE_SEEKII: COMSPEC_SSE_ItemBase {
        scope = 2;
        displayName = "$STR_COMSPEC_SSE_CFG_E1C548AE79";
        descriptionShort = "$STR_COMSPEC_SSE_CFG_3B68E1E3A6";
        class ItemInfo: CBA_MiscItem_ItemInfo { mass = 8; };
    };

    class COMSPEC_SSE_USB: COMSPEC_SSE_ItemBase {
        scope = 2;
        displayName = "$STR_COMSPEC_SSE_CFG_6A3B88669A";
        descriptionShort = "$STR_COMSPEC_SSE_CFG_6A673B4425";
        class ItemInfo: CBA_MiscItem_ItemInfo { mass = 0.5; };
    };

    class COMSPEC_SSE_SDCard: COMSPEC_SSE_ItemBase {
        scope = 2;
        displayName = "$STR_COMSPEC_SSE_CFG_A70A7140C5";
        descriptionShort = "$STR_COMSPEC_SSE_CFG_9D41DC238C";
        class ItemInfo: CBA_MiscItem_ItemInfo { mass = 0.2; };
    };

    class COMSPEC_SSE_HardDrive: COMSPEC_SSE_ItemBase {
        scope = 2;
        displayName = "$STR_COMSPEC_SSE_CFG_CC99BC0C14";
        descriptionShort = "$STR_COMSPEC_SSE_CFG_517F2D54EE";
        class ItemInfo: CBA_MiscItem_ItemInfo { mass = 6; };
    };

    class COMSPEC_SSE_Laptop: COMSPEC_SSE_ItemBase {
        scope = 2;
        displayName = "$STR_COMSPEC_SSE_CFG_B8FDDCC5A5";
        descriptionShort = "$STR_COMSPEC_SSE_CFG_1E9CD8A636";
        class ItemInfo: CBA_MiscItem_ItemInfo { mass = 25; };
    };

    class COMSPEC_SSE_Phone: COMSPEC_SSE_ItemBase {
        scope = 2;
        displayName = "$STR_COMSPEC_SSE_CFG_032C76A969";
        descriptionShort = "$STR_COMSPEC_SSE_CFG_BBF98C00EA";
        class ItemInfo: CBA_MiscItem_ItemInfo { mass = 2; };
    };

    class COMSPEC_SSE_Smartphone: COMSPEC_SSE_ItemBase {
        scope = 2;
        displayName = "$STR_COMSPEC_SSE_CFG_866CA02F45";
        descriptionShort = "$STR_COMSPEC_SSE_CFG_D3B4386273";
        class ItemInfo: CBA_MiscItem_ItemInfo { mass = 2; };
    };

    class COMSPEC_SSE_DocumentBag: COMSPEC_SSE_ItemBase {
        scope = 2;
        displayName = "$STR_COMSPEC_SSE_CFG_FCEFB1E024";
        descriptionShort = "$STR_COMSPEC_SSE_CFG_5F3D250E41";
        class ItemInfo: CBA_MiscItem_ItemInfo { mass = 1; };
    };

    class COMSPEC_SSE_Notebook: COMSPEC_SSE_ItemBase {
        scope = 2;
        displayName = "$STR_COMSPEC_SSE_CFG_4B3C750225";
        descriptionShort = "$STR_COMSPEC_SSE_CFG_50149D262E";
        class ItemInfo: CBA_MiscItem_ItemInfo { mass = 2; };
    };

    class COMSPEC_SSE_SatPhone: COMSPEC_SSE_ItemBase {
        scope = 2;
        displayName = "$STR_COMSPEC_SSE_CFG_C850F67D9F";
        descriptionShort = "$STR_COMSPEC_SSE_CFG_235268B0C2";
        class ItemInfo: CBA_MiscItem_ItemInfo { mass = 5; };
    };

    class COMSPEC_SSE_Terminal: COMSPEC_SSE_ItemBase {
        scope = 2;
        displayName = "$STR_COMSPEC_SSE_CFG_28D07A4B05";
        descriptionShort = "$STR_COMSPEC_SSE_CFG_08315E4DF9";
        class ItemInfo: CBA_MiscItem_ItemInfo { mass = 10; };
    };

    // Sac de tête (capture) : porté dans l'inventaire, posé sur un prisonnier par action ACE.
    class COMSPEC_SSE_HeadBag: COMSPEC_SSE_ItemBase {
        scope = 2;
        displayName = "$STR_COMSPEC_SSE_CFG_6C1099D135";
        descriptionShort = "$STR_COMSPEC_SSE_CFG_03C897BAC7";
        class ItemInfo: CBA_MiscItem_ItemInfo { mass = 2; };
    };
};

// Ce que porte le prisonnier. Modèle provisoire (cagoule noire vanilla) : un réglage CBA
// permet d'utiliser un vrai sac de tête d'un autre mod ou du DLC.
class CfgGlasses {
    class G_Balaclava_blk;
    class COMSPEC_SSE_G_HeadBag: G_Balaclava_blk {
        author = "COMSPEC";
        scope = 2;
        displayName = "$STR_COMSPEC_SSE_CFG_087971B5AB";
        mass = 2;
        identityTypes[] = {};
    };
};
