#include "script_component.hpp"

/*
    COMSPEC SSE — objets de preuve, outils de terrain et caisses de kit.

    - CfgWeapons : objets d'inventaire (CBA MiscItem). Modèles vanilla (posés au sol, ils
      gardent l'allure de l'objet réel), icônes propres au mod.
    - CfgVehicles « COMSPEC_SSE_Obj_* » : pièces posables dans Eden / Zeus (catégorie
      « COMSPEC SSE - Preuves »), dérivées des objets Land_* vanilla. Le serveur les marque
      SSE à l'init (comspec_sse_fnc_evidenceObjInit, type lu dans comspec_sse_type).
    - CfgVehicles « Item_COMSPEC_SSE_* » : outils posés au sol, à ramasser.
    - Caisses de kit (ReammoBox vanilla) : remplacent l'ancien « kit SSE » qui faisait
      apparaître les objets dans l'inventaire.
    Les kits FaradayBag, SwabKit, EvidenceMarkers et la valise DomexCase n'ont ici que leur
    config : leur usage est câblé ailleurs (terminal ATAK, interactions physiques).
*/

class CfgPatches {
    class comspec_sse_evidence {
        name = "COMSPEC SSE - Evidence Items";
        units[] = {
            "COMSPEC_SSE_Obj_Phone",
            "COMSPEC_SSE_Obj_Smartphone",
            "COMSPEC_SSE_Obj_SatPhone",
            "COMSPEC_SSE_Obj_Radio",
            "COMSPEC_SSE_Obj_Laptop",
            "COMSPEC_SSE_Obj_Tablet",
            "COMSPEC_SSE_Obj_PCTower",
            "COMSPEC_SSE_Obj_HardDrive",
            "COMSPEC_SSE_Obj_USB",
            "COMSPEC_SSE_Obj_SDCard",
            "COMSPEC_SSE_Obj_Notebook",
            "COMSPEC_SSE_Obj_Documents",
            "COMSPEC_SSE_Obj_Ledger",
            "COMSPEC_SSE_Obj_Photos",
            "COMSPEC_SSE_Obj_Map",
            "COMSPEC_SSE_Obj_Cash",
            "COMSPEC_SSE_Obj_Suitcase",
            "Item_COMSPEC_SSE_EvidenceBag",
            "Item_COMSPEC_SSE_Gloves",
            "Item_COMSPEC_SSE_Camera",
            "Item_COMSPEC_SSE_FingerprintKit",
            "Item_COMSPEC_SSE_DNKit",
            "Item_COMSPEC_SSE_SwabKit",
            "Item_COMSPEC_SSE_SEEKII",
            "Item_COMSPEC_SSE_DocumentBag",
            "Item_COMSPEC_SSE_Terminal",
            "Item_COMSPEC_SSE_HeadBag",
            "Item_COMSPEC_SSE_FaradayBag",
            "Item_COMSPEC_SSE_EvidenceMarkers",
            "COMSPEC_SSE_Box_Team",
            "COMSPEC_SSE_Box_Biometrics",
            "COMSPEC_SSE_Box_Digital",
            "COMSPEC_SSE_DomexCase"
        };
        weapons[] = {
            "COMSPEC_SSE_EvidenceBag",
            "COMSPEC_SSE_Gloves",
            "COMSPEC_SSE_Camera",
            "COMSPEC_SSE_FingerprintKit",
            "COMSPEC_SSE_DNKit",
            "COMSPEC_SSE_SwabKit",
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
            "COMSPEC_SSE_HeadBag",
            "COMSPEC_SSE_FaradayBag",
            "COMSPEC_SSE_EvidenceMarkers"
        };
        requiredVersion = REQUIRED_VERSION;
        requiredAddons[] = {
            "comspec_sse_main",
            "comspec_sse_core",
            "A3_Weapons_F",
            "A3_Structures_F",
            "cba_main",
            "cba_common",
            "cba_xeh",
            "ace_common",
            "A3_Characters_F",
            "comspec_atak_native_main"
        };
        author = "COMSPEC";
        VERSION_CONFIG;
    };
};

class CfgFunctions {
    class comspec_sse {
        tag = "comspec_sse";
        class evidence {
            file = "z\comspec_sse\addons\evidence\functions";
            class evidenceObjInit {};
        };
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
        model = "\A3\Structures_F\Items\Documents\File2_F.p3d";
        class ItemInfo: CBA_MiscItem_ItemInfo {
            mass = 1;
        };
    };

    class COMSPEC_SSE_EvidenceBag: COMSPEC_SSE_ItemBase {
        scope = 2;
        picture = "\z\comspec_sse\addons\evidence\data\icons\evidencebag_ca.paa";
        model = "\A3\Structures_F_EPA\Items\Medical\BloodBag_F.p3d";
        displayName = "$STR_COMSPEC_SSE_CFG_0BF3305EBB";
        descriptionShort = "$STR_COMSPEC_SSE_CFG_C5E048FE44";
        class ItemInfo: CBA_MiscItem_ItemInfo { mass = 1; };
    };

    // Pas de gants vanilla : le masque anti-poussière tient lieu d'équipement de protection posé au sol.
    class COMSPEC_SSE_Gloves: COMSPEC_SSE_ItemBase {
        scope = 2;
        picture = "\z\comspec_sse\addons\evidence\data\icons\gloves_ca.paa";
        model = "\A3\Structures_F\Items\Tools\DustMask_F.p3d";
        displayName = "$STR_COMSPEC_SSE_CFG_46E7D52BE6";
        descriptionShort = "$STR_COMSPEC_SSE_CFG_29BA873C30";
        class ItemInfo: CBA_MiscItem_ItemInfo { mass = 0.5; };
    };

    class COMSPEC_SSE_Camera: COMSPEC_SSE_ItemBase {
        scope = 2;
        picture = "\z\comspec_sse\addons\evidence\data\icons\camera_ca.paa";
        model = "\A3\Structures_F_Heli\Items\Electronics\Camera_01_F.p3d";
        displayName = "$STR_COMSPEC_SSE_CFG_3FE9D437FE";
        descriptionShort = "$STR_COMSPEC_SSE_CFG_F0BB7B9475";
        class ItemInfo: CBA_MiscItem_ItemInfo { mass = 3; };
    };

    class COMSPEC_SSE_FingerprintKit: COMSPEC_SSE_ItemBase {
        scope = 2;
        picture = "\z\comspec_sse\addons\evidence\data\icons\fingerprintkit_ca.paa";
        model = "\A3\Structures_F_Heli\Items\Luggage\PlasticCase_01_small_F.p3d";
        displayName = "$STR_COMSPEC_SSE_CFG_71D3057E17";
        descriptionShort = "$STR_COMSPEC_SSE_CFG_CDABB7E48D";
        class ItemInfo: CBA_MiscItem_ItemInfo { mass = 4; };
    };

    class COMSPEC_SSE_DNKit: COMSPEC_SSE_ItemBase {
        scope = 2;
        picture = "\z\comspec_sse\addons\evidence\data\icons\dnakit_ca.paa";
        model = "\A3\Structures_F_Heli\Items\Luggage\MetalCase_01_small_F.p3d";
        displayName = "$STR_COMSPEC_SSE_CFG_8EF80BA73D";
        descriptionShort = "$STR_COMSPEC_SSE_CFG_748B65B7BD";
        class ItemInfo: CBA_MiscItem_ItemInfo { mass = 4; };
    };

    class COMSPEC_SSE_SwabKit: COMSPEC_SSE_ItemBase {
        scope = 2;
        picture = "\z\comspec_sse\addons\evidence\data\icons\swabkit_ca.paa";
        model = "\A3\Structures_F_EPA\Items\Medical\WaterPurificationTablets_F.p3d";
        displayName = "$STR_COMSPEC_SSE_CFG_88B181E6CE";
        descriptionShort = "$STR_COMSPEC_SSE_CFG_124659002B";
        class ItemInfo: CBA_MiscItem_ItemInfo { mass = 1; };
    };

    class COMSPEC_SSE_SEEKII: COMSPEC_SSE_ItemBase {
        scope = 2;
        picture = "\z\comspec_sse\addons\evidence\data\icons\seekii_ca.paa";
        model = "\A3\Structures_F_Heli\Items\Electronics\Tablet_01_F.p3d";
        displayName = "$STR_COMSPEC_SSE_CFG_E1C548AE79";
        descriptionShort = "$STR_COMSPEC_SSE_CFG_3B68E1E3A6";
        class ItemInfo: CBA_MiscItem_ItemInfo { mass = 8; };
    };

    class COMSPEC_SSE_USB: COMSPEC_SSE_ItemBase {
        scope = 2;
        picture = "\z\comspec_sse\addons\evidence\data\icons\usb_ca.paa";
        model = "\A3\Props_F_Oldman\Items\USB_Dongle_01_F.p3d";
        displayName = "$STR_COMSPEC_SSE_CFG_6A3B88669A";
        descriptionShort = "$STR_COMSPEC_SSE_CFG_6A673B4425";
        class ItemInfo: CBA_MiscItem_ItemInfo { mass = 0.5; };
    };

    class COMSPEC_SSE_SDCard: COMSPEC_SSE_ItemBase {
        scope = 2;
        picture = "\z\comspec_sse\addons\evidence\data\icons\sdcard_ca.paa";
        model = "\A3\Props_F_Oldman\Items\Documents\CreditCard_01_F.p3d";
        displayName = "$STR_COMSPEC_SSE_CFG_A70A7140C5";
        descriptionShort = "$STR_COMSPEC_SSE_CFG_9D41DC238C";
        class ItemInfo: CBA_MiscItem_ItemInfo { mass = 0.2; };
    };

    class COMSPEC_SSE_HardDrive: COMSPEC_SSE_ItemBase {
        scope = 2;
        picture = "\z\comspec_sse\addons\evidence\data\icons\harddrive_ca.paa";
        model = "\A3\Structures_F_Heli\Items\Electronics\GamingSet_01_powersupply_F.p3d";
        displayName = "$STR_COMSPEC_SSE_CFG_CC99BC0C14";
        descriptionShort = "$STR_COMSPEC_SSE_CFG_517F2D54EE";
        class ItemInfo: CBA_MiscItem_ItemInfo { mass = 6; };
    };

    class COMSPEC_SSE_Laptop: COMSPEC_SSE_ItemBase {
        scope = 2;
        picture = "\z\comspec_sse\addons\evidence\data\icons\laptop_ca.paa";
        model = "\A3\Structures_F\Items\Electronics\Laptop_F.p3d";
        displayName = "$STR_COMSPEC_SSE_CFG_B8FDDCC5A5";
        descriptionShort = "$STR_COMSPEC_SSE_CFG_1E9CD8A636";
        class ItemInfo: CBA_MiscItem_ItemInfo { mass = 25; };
    };

    class COMSPEC_SSE_Phone: COMSPEC_SSE_ItemBase {
        scope = 2;
        picture = "\z\comspec_sse\addons\evidence\data\icons\phone_ca.paa";
        model = "\A3\Structures_F\Items\Electronics\MobilePhone_old_F.p3d";
        displayName = "$STR_COMSPEC_SSE_CFG_032C76A969";
        descriptionShort = "$STR_COMSPEC_SSE_CFG_BBF98C00EA";
        class ItemInfo: CBA_MiscItem_ItemInfo { mass = 2; };
    };

    class COMSPEC_SSE_Smartphone: COMSPEC_SSE_ItemBase {
        scope = 2;
        picture = "\z\comspec_sse\addons\evidence\data\icons\smartphone_ca.paa";
        model = "\A3\Structures_F\Items\Electronics\MobilePhone_smart_F.p3d";
        displayName = "$STR_COMSPEC_SSE_CFG_866CA02F45";
        descriptionShort = "$STR_COMSPEC_SSE_CFG_D3B4386273";
        class ItemInfo: CBA_MiscItem_ItemInfo { mass = 2; };
    };

    class COMSPEC_SSE_DocumentBag: COMSPEC_SSE_ItemBase {
        scope = 2;
        picture = "\z\comspec_sse\addons\evidence\data\icons\documentbag_ca.paa";
        model = "\A3\Structures_F\Items\Documents\File2_F.p3d";
        displayName = "$STR_COMSPEC_SSE_CFG_FCEFB1E024";
        descriptionShort = "$STR_COMSPEC_SSE_CFG_5F3D250E41";
        class ItemInfo: CBA_MiscItem_ItemInfo { mass = 1; };
    };

    class COMSPEC_SSE_Notebook: COMSPEC_SSE_ItemBase {
        scope = 2;
        picture = "\z\comspec_sse\addons\evidence\data\icons\notebook_ca.paa";
        model = "\A3\Structures_F\Items\Documents\Notepad_F.p3d";
        displayName = "$STR_COMSPEC_SSE_CFG_4B3C750225";
        descriptionShort = "$STR_COMSPEC_SSE_CFG_50149D262E";
        class ItemInfo: CBA_MiscItem_ItemInfo { mass = 2; };
    };

    class COMSPEC_SSE_SatPhone: COMSPEC_SSE_ItemBase {
        scope = 2;
        picture = "\z\comspec_sse\addons\evidence\data\icons\satphone_ca.paa";
        model = "\A3\Structures_F\Items\Electronics\SatellitePhone_F.p3d";
        displayName = "$STR_COMSPEC_SSE_CFG_C850F67D9F";
        descriptionShort = "$STR_COMSPEC_SSE_CFG_235268B0C2";
        class ItemInfo: CBA_MiscItem_ItemInfo { mass = 5; };
    };

    class COMSPEC_SSE_Terminal: COMSPEC_SSE_ItemBase {
        scope = 2;
        picture = "\z\comspec_sse\addons\evidence\data\icons\terminal_ca.paa";
        model = "\A3\Structures_F_Heli\Items\Electronics\Tablet_01_F.p3d";
        displayName = "$STR_COMSPEC_SSE_CFG_28D07A4B05";
        descriptionShort = "$STR_COMSPEC_SSE_CFG_08315E4DF9";
        class ItemInfo: CBA_MiscItem_ItemInfo { mass = 10; };
    };

    // Sac de tête (capture) : porté dans l'inventaire, posé sur un prisonnier.
    class COMSPEC_SSE_HeadBag: COMSPEC_SSE_ItemBase {
        scope = 2;
        picture = "\z\comspec_sse\addons\evidence\data\icons\headbag_ca.paa";
        model = "\A3\Structures_F\Civ\Market\Sack_F.p3d";
        displayName = "$STR_COMSPEC_SSE_CFG_6C1099D135";
        descriptionShort = "$STR_COMSPEC_SSE_CFG_03C897BAC7";
        class ItemInfo: CBA_MiscItem_ItemInfo { mass = 2; };
    };

    class COMSPEC_SSE_FaradayBag: COMSPEC_SSE_ItemBase {
        scope = 2;
        picture = "\z\comspec_sse\addons\evidence\data\icons\faradaybag_ca.paa";
        model = "\A3\Structures_F_Heli\Items\Airport\RotorCoversBag_01_F.p3d";
        displayName = "$STR_COMSPEC_SSE_CFG_43AE29872B";
        descriptionShort = "$STR_COMSPEC_SSE_CFG_ABEBEF7E33";
        class ItemInfo: CBA_MiscItem_ItemInfo { mass = 1; };
    };

    class COMSPEC_SSE_EvidenceMarkers: COMSPEC_SSE_ItemBase {
        scope = 2;
        picture = "\z\comspec_sse\addons\evidence\data\icons\evidencemarkers_ca.paa";
        model = "\A3\Structures_F_Heli\Items\Luggage\PlasticCase_01_small_F.p3d";
        displayName = "$STR_COMSPEC_SSE_CFG_430B0F3E9F";
        descriptionShort = "$STR_COMSPEC_SSE_CFG_516BD343FE";
        class ItemInfo: CBA_MiscItem_ItemInfo { mass = 3; };
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
        picture = "\z\comspec_sse\addons\evidence\data\icons\headbag_ca.paa";
        mass = 2;
        identityTypes[] = {};
    };
};

class CfgEditorCategories {
    class COMSPEC_SSE_EdEvidence {
        displayName = "$STR_COMSPEC_SSE_CFG_33141040EE";
    };
    class COMSPEC_SSE_EdKits {
        displayName = "$STR_COMSPEC_SSE_CFG_2528475D3C";
    };
};

class CfgEditorSubcategories {
    class COMSPEC_SSE_EdDigital {
        displayName = "$STR_COMSPEC_SSE_CFG_BFFAB189C7";
    };
    class COMSPEC_SSE_EdPaper {
        displayName = "$STR_COMSPEC_SSE_CFG_687C82861C";
    };
    class COMSPEC_SSE_EdValuables {
        displayName = "$STR_COMSPEC_SSE_CFG_98A09F2C4C";
    };
    class COMSPEC_SSE_EdTools {
        displayName = "$STR_COMSPEC_SSE_CFG_96DB5DE772";
    };
    class COMSPEC_SSE_EdBoxes {
        displayName = "$STR_COMSPEC_SSE_CFG_8DEF2E7D60";
    };
};

// Ligne de contenu d'une caisse de kit (TransportItems).
#define SSE_STOCK(ITEM,N) class _xx_##ITEM { name = #ITEM; count = N; }

class CfgVehicles {
    class Land_MobilePhone_old_F;
    class Land_MobilePhone_smart_F;
    class Land_SatellitePhone_F;
    class Land_PortableLongRangeRadio_F;
    class Land_Laptop_unfolded_F;
    class Land_Tablet_01_F;
    class Land_PCSet_01_case_F;
    class Land_Notepad_F;
    class Land_File1_F;
    class Land_File2_F;
    class Land_FilePhotos_F;
    class Land_Map_unfolded_F;
    class Land_Money_F;
    class Land_Suitcase_F;
    class Item_Base_F;
    class Box_NATO_Support_F;

    // ------------------------------------------------------------ pièces de preuve
    // Objet vanilla + type SSE (comspec_sse_type, lu par comspec_sse_fnc_evidenceObjInit).
    class COMSPEC_SSE_Obj_Phone: Land_MobilePhone_old_F {
        author = "COMSPEC";
        scope = 2;
        scopeCurator = 2;
        displayName = "$STR_COMSPEC_SSE_CFG_7B15FC7613";
        editorCategory = "COMSPEC_SSE_EdEvidence";
        editorSubcategory = "COMSPEC_SSE_EdDigital";
        comspec_sse_type = "PHONE";
    };
    class COMSPEC_SSE_Obj_Smartphone: Land_MobilePhone_smart_F {
        author = "COMSPEC";
        scope = 2;
        scopeCurator = 2;
        displayName = "$STR_COMSPEC_SSE_CFG_C2FA969DBB";
        editorCategory = "COMSPEC_SSE_EdEvidence";
        editorSubcategory = "COMSPEC_SSE_EdDigital";
        comspec_sse_type = "PHONE";
    };
    class COMSPEC_SSE_Obj_SatPhone: Land_SatellitePhone_F {
        author = "COMSPEC";
        scope = 2;
        scopeCurator = 2;
        displayName = "$STR_COMSPEC_SSE_CFG_95EA9F3A0A";
        editorCategory = "COMSPEC_SSE_EdEvidence";
        editorSubcategory = "COMSPEC_SSE_EdDigital";
        comspec_sse_type = "PHONE";
    };
    class COMSPEC_SSE_Obj_Radio: Land_PortableLongRangeRadio_F {
        author = "COMSPEC";
        scope = 2;
        scopeCurator = 2;
        displayName = "$STR_COMSPEC_SSE_CFG_932C8B7F30";
        editorCategory = "COMSPEC_SSE_EdEvidence";
        editorSubcategory = "COMSPEC_SSE_EdDigital";
        comspec_sse_type = "RADIO";
    };
    class COMSPEC_SSE_Obj_Laptop: Land_Laptop_unfolded_F {
        author = "COMSPEC";
        scope = 2;
        scopeCurator = 2;
        displayName = "$STR_COMSPEC_SSE_CFG_F362018DB4";
        editorCategory = "COMSPEC_SSE_EdEvidence";
        editorSubcategory = "COMSPEC_SSE_EdDigital";
        comspec_sse_type = "COMPUTER";
    };
    class COMSPEC_SSE_Obj_Tablet: Land_Tablet_01_F {
        author = "COMSPEC";
        scope = 2;
        scopeCurator = 2;
        displayName = "$STR_COMSPEC_SSE_CFG_D5EB867897";
        editorCategory = "COMSPEC_SSE_EdEvidence";
        editorSubcategory = "COMSPEC_SSE_EdDigital";
        comspec_sse_type = "COMPUTER";
    };
    class COMSPEC_SSE_Obj_PCTower: Land_PCSet_01_case_F {
        author = "COMSPEC";
        scope = 2;
        scopeCurator = 2;
        displayName = "$STR_COMSPEC_SSE_CFG_B926BFDE9F";
        editorCategory = "COMSPEC_SSE_EdEvidence";
        editorSubcategory = "COMSPEC_SSE_EdDigital";
        comspec_sse_type = "COMPUTER";
    };
    class COMSPEC_SSE_Obj_HardDrive: Land_MobilePhone_old_F {
        author = "COMSPEC";
        scope = 2;
        scopeCurator = 2;
        displayName = "$STR_COMSPEC_SSE_CFG_8BD1B86B56";
        model = "\A3\Structures_F_Heli\Items\Electronics\GamingSet_01_powersupply_F.p3d";
        editorCategory = "COMSPEC_SSE_EdEvidence";
        editorSubcategory = "COMSPEC_SSE_EdDigital";
        comspec_sse_type = "MEDIA";
    };
    class COMSPEC_SSE_Obj_USB: Land_MobilePhone_old_F {
        author = "COMSPEC";
        scope = 2;
        scopeCurator = 2;
        displayName = "$STR_COMSPEC_SSE_CFG_C0EC90BC1C";
        model = "\A3\Props_F_Oldman\Items\USB_Dongle_01_F.p3d";
        editorCategory = "COMSPEC_SSE_EdEvidence";
        editorSubcategory = "COMSPEC_SSE_EdDigital";
        comspec_sse_type = "MEDIA";
    };
    class COMSPEC_SSE_Obj_SDCard: Land_MobilePhone_old_F {
        author = "COMSPEC";
        scope = 2;
        scopeCurator = 2;
        displayName = "$STR_COMSPEC_SSE_CFG_DBE91E171E";
        model = "\A3\Props_F_Oldman\Items\Documents\CreditCard_01_F.p3d";
        editorCategory = "COMSPEC_SSE_EdEvidence";
        editorSubcategory = "COMSPEC_SSE_EdDigital";
        comspec_sse_type = "MEDIA";
    };
    class COMSPEC_SSE_Obj_Notebook: Land_Notepad_F {
        author = "COMSPEC";
        scope = 2;
        scopeCurator = 2;
        displayName = "$STR_COMSPEC_SSE_CFG_A71DAD0BE0";
        editorCategory = "COMSPEC_SSE_EdEvidence";
        editorSubcategory = "COMSPEC_SSE_EdPaper";
        comspec_sse_type = "DOCUMENT";
    };
    class COMSPEC_SSE_Obj_Documents: Land_File1_F {
        author = "COMSPEC";
        scope = 2;
        scopeCurator = 2;
        displayName = "$STR_COMSPEC_SSE_CFG_5AD25706D4";
        editorCategory = "COMSPEC_SSE_EdEvidence";
        editorSubcategory = "COMSPEC_SSE_EdPaper";
        comspec_sse_type = "DOCUMENT";
    };
    class COMSPEC_SSE_Obj_Ledger: Land_File2_F {
        author = "COMSPEC";
        scope = 2;
        scopeCurator = 2;
        displayName = "$STR_COMSPEC_SSE_CFG_4F964CA2C8";
        editorCategory = "COMSPEC_SSE_EdEvidence";
        editorSubcategory = "COMSPEC_SSE_EdPaper";
        comspec_sse_type = "DOCUMENT";
    };
    class COMSPEC_SSE_Obj_Photos: Land_FilePhotos_F {
        author = "COMSPEC";
        scope = 2;
        scopeCurator = 2;
        displayName = "$STR_COMSPEC_SSE_CFG_BBB9F5D542";
        editorCategory = "COMSPEC_SSE_EdEvidence";
        editorSubcategory = "COMSPEC_SSE_EdPaper";
        comspec_sse_type = "DOCUMENT";
    };
    class COMSPEC_SSE_Obj_Map: Land_Map_unfolded_F {
        author = "COMSPEC";
        scope = 2;
        scopeCurator = 2;
        displayName = "$STR_COMSPEC_SSE_CFG_594C5CA807";
        editorCategory = "COMSPEC_SSE_EdEvidence";
        editorSubcategory = "COMSPEC_SSE_EdPaper";
        comspec_sse_type = "DOCUMENT";
    };
    class COMSPEC_SSE_Obj_Cash: Land_Money_F {
        author = "COMSPEC";
        scope = 2;
        scopeCurator = 2;
        displayName = "$STR_COMSPEC_SSE_CFG_D1D38AAC02";
        editorCategory = "COMSPEC_SSE_EdEvidence";
        editorSubcategory = "COMSPEC_SSE_EdValuables";
        comspec_sse_type = "OBJECT";
    };
    class COMSPEC_SSE_Obj_Suitcase: Land_Suitcase_F {
        author = "COMSPEC";
        scope = 2;
        scopeCurator = 2;
        displayName = "$STR_COMSPEC_SSE_CFG_76E2EB1D5E";
        editorCategory = "COMSPEC_SSE_EdEvidence";
        editorSubcategory = "COMSPEC_SSE_EdValuables";
        comspec_sse_type = "CONTAINER";
    };

    // ------------------------------------------------------------ outils posés au sol, à ramasser
    class Item_COMSPEC_SSE_EvidenceBag: Item_Base_F {
        author = "COMSPEC";
        scope = 2;
        scopeCurator = 2;
        displayName = "$STR_COMSPEC_SSE_CFG_7BD28D4768";
        vehicleClass = "Items";
        editorCategory = "COMSPEC_SSE_EdKits";
        editorSubcategory = "COMSPEC_SSE_EdTools";
        class TransportItems {
            class COMSPEC_SSE_EvidenceBag { name = "COMSPEC_SSE_EvidenceBag"; count = 1; };
        };
    };
    class Item_COMSPEC_SSE_Gloves: Item_Base_F {
        author = "COMSPEC";
        scope = 2;
        scopeCurator = 2;
        displayName = "$STR_COMSPEC_SSE_CFG_6C543AF206";
        vehicleClass = "Items";
        editorCategory = "COMSPEC_SSE_EdKits";
        editorSubcategory = "COMSPEC_SSE_EdTools";
        class TransportItems {
            class COMSPEC_SSE_Gloves { name = "COMSPEC_SSE_Gloves"; count = 1; };
        };
    };
    class Item_COMSPEC_SSE_Camera: Item_Base_F {
        author = "COMSPEC";
        scope = 2;
        scopeCurator = 2;
        displayName = "$STR_COMSPEC_SSE_CFG_D5245BAA89";
        vehicleClass = "Items";
        editorCategory = "COMSPEC_SSE_EdKits";
        editorSubcategory = "COMSPEC_SSE_EdTools";
        class TransportItems {
            class COMSPEC_SSE_Camera { name = "COMSPEC_SSE_Camera"; count = 1; };
        };
    };
    class Item_COMSPEC_SSE_FingerprintKit: Item_Base_F {
        author = "COMSPEC";
        scope = 2;
        scopeCurator = 2;
        displayName = "$STR_COMSPEC_SSE_CFG_FDF8067B09";
        vehicleClass = "Items";
        editorCategory = "COMSPEC_SSE_EdKits";
        editorSubcategory = "COMSPEC_SSE_EdTools";
        class TransportItems {
            class COMSPEC_SSE_FingerprintKit { name = "COMSPEC_SSE_FingerprintKit"; count = 1; };
        };
    };
    class Item_COMSPEC_SSE_DNKit: Item_Base_F {
        author = "COMSPEC";
        scope = 2;
        scopeCurator = 2;
        displayName = "$STR_COMSPEC_SSE_CFG_B70FF1E6D2";
        vehicleClass = "Items";
        editorCategory = "COMSPEC_SSE_EdKits";
        editorSubcategory = "COMSPEC_SSE_EdTools";
        class TransportItems {
            class COMSPEC_SSE_DNKit { name = "COMSPEC_SSE_DNKit"; count = 1; };
        };
    };
    class Item_COMSPEC_SSE_SwabKit: Item_Base_F {
        author = "COMSPEC";
        scope = 2;
        scopeCurator = 2;
        displayName = "$STR_COMSPEC_SSE_CFG_88B181E6CE";
        vehicleClass = "Items";
        editorCategory = "COMSPEC_SSE_EdKits";
        editorSubcategory = "COMSPEC_SSE_EdTools";
        class TransportItems {
            class COMSPEC_SSE_SwabKit { name = "COMSPEC_SSE_SwabKit"; count = 1; };
        };
    };
    class Item_COMSPEC_SSE_SEEKII: Item_Base_F {
        author = "COMSPEC";
        scope = 2;
        scopeCurator = 2;
        displayName = "$STR_COMSPEC_SSE_CFG_24A895354C";
        vehicleClass = "Items";
        editorCategory = "COMSPEC_SSE_EdKits";
        editorSubcategory = "COMSPEC_SSE_EdTools";
        class TransportItems {
            class COMSPEC_SSE_SEEKII { name = "COMSPEC_SSE_SEEKII"; count = 1; };
        };
    };
    class Item_COMSPEC_SSE_DocumentBag: Item_Base_F {
        author = "COMSPEC";
        scope = 2;
        scopeCurator = 2;
        displayName = "$STR_COMSPEC_SSE_CFG_0BC4F565FF";
        vehicleClass = "Items";
        editorCategory = "COMSPEC_SSE_EdKits";
        editorSubcategory = "COMSPEC_SSE_EdTools";
        class TransportItems {
            class COMSPEC_SSE_DocumentBag { name = "COMSPEC_SSE_DocumentBag"; count = 1; };
        };
    };
    class Item_COMSPEC_SSE_Terminal: Item_Base_F {
        author = "COMSPEC";
        scope = 2;
        scopeCurator = 2;
        displayName = "$STR_COMSPEC_SSE_CFG_3657A10269";
        vehicleClass = "Items";
        editorCategory = "COMSPEC_SSE_EdKits";
        editorSubcategory = "COMSPEC_SSE_EdTools";
        class TransportItems {
            class COMSPEC_SSE_Terminal { name = "COMSPEC_SSE_Terminal"; count = 1; };
        };
    };
    class Item_COMSPEC_SSE_HeadBag: Item_Base_F {
        author = "COMSPEC";
        scope = 2;
        scopeCurator = 2;
        displayName = "$STR_COMSPEC_SSE_CFG_3D7AF5EC00";
        vehicleClass = "Items";
        editorCategory = "COMSPEC_SSE_EdKits";
        editorSubcategory = "COMSPEC_SSE_EdTools";
        class TransportItems {
            class COMSPEC_SSE_HeadBag { name = "COMSPEC_SSE_HeadBag"; count = 1; };
        };
    };
    class Item_COMSPEC_SSE_FaradayBag: Item_Base_F {
        author = "COMSPEC";
        scope = 2;
        scopeCurator = 2;
        displayName = "$STR_COMSPEC_SSE_CFG_43AE29872B";
        vehicleClass = "Items";
        editorCategory = "COMSPEC_SSE_EdKits";
        editorSubcategory = "COMSPEC_SSE_EdTools";
        class TransportItems {
            class COMSPEC_SSE_FaradayBag { name = "COMSPEC_SSE_FaradayBag"; count = 1; };
        };
    };
    class Item_COMSPEC_SSE_EvidenceMarkers: Item_Base_F {
        author = "COMSPEC";
        scope = 2;
        scopeCurator = 2;
        displayName = "$STR_COMSPEC_SSE_CFG_430B0F3E9F";
        vehicleClass = "Items";
        editorCategory = "COMSPEC_SSE_EdKits";
        editorSubcategory = "COMSPEC_SSE_EdTools";
        class TransportItems {
            class COMSPEC_SSE_EvidenceMarkers { name = "COMSPEC_SSE_EvidenceMarkers"; count = 1; };
        };
    };

    // ------------------------------------------------------------ caisses de kit
    class COMSPEC_SSE_Box_Team: Box_NATO_Support_F {
        author = "COMSPEC";
        scope = 2;
        scopeCurator = 2;
        displayName = "$STR_COMSPEC_SSE_CFG_567B8D6D17";
        editorCategory = "COMSPEC_SSE_EdKits";
        editorSubcategory = "COMSPEC_SSE_EdBoxes";
        maximumLoad = 2000;
        class TransportWeapons {};
        class TransportMagazines {};
        class TransportBackpacks {};
        class TransportItems {
            SSE_STOCK(COMSPEC_SSE_EvidenceBag,20);
            SSE_STOCK(COMSPEC_SSE_Gloves,8);
            SSE_STOCK(COMSPEC_SSE_Camera,1);
            SSE_STOCK(COMSPEC_SSE_EvidenceMarkers,2);
            SSE_STOCK(COMSPEC_SSE_DocumentBag,6);
            SSE_STOCK(COMSPEC_SSE_FaradayBag,4);
            SSE_STOCK(COMSPEC_SSE_HeadBag,4);
        };
    };

    class COMSPEC_SSE_Box_Biometrics: Box_NATO_Support_F {
        author = "COMSPEC";
        scope = 2;
        scopeCurator = 2;
        displayName = "$STR_COMSPEC_SSE_CFG_569DF69C16";
        model = "\A3\Structures_F_Heli\Items\Luggage\MetalCase_01_large_F.p3d";
        hiddenSelections[] = {};
        hiddenSelectionsTextures[] = {};
        editorCategory = "COMSPEC_SSE_EdKits";
        editorSubcategory = "COMSPEC_SSE_EdBoxes";
        maximumLoad = 1000;
        class TransportWeapons {};
        class TransportMagazines {};
        class TransportBackpacks {};
        class TransportItems {
            SSE_STOCK(COMSPEC_SSE_SEEKII,1);
            SSE_STOCK(COMSPEC_SSE_FingerprintKit,2);
            SSE_STOCK(COMSPEC_SSE_SwabKit,6);
            SSE_STOCK(COMSPEC_SSE_DNKit,1);
            SSE_STOCK(COMSPEC_SSE_Gloves,6);
            SSE_STOCK(COMSPEC_SSE_EvidenceBag,6);
        };
    };

    class COMSPEC_SSE_Box_Digital: Box_NATO_Support_F {
        author = "COMSPEC";
        scope = 2;
        scopeCurator = 2;
        displayName = "$STR_COMSPEC_SSE_CFG_AB3382193F";
        model = "\A3\Structures_F_Heli\Items\Luggage\PlasticCase_01_large_F.p3d";
        hiddenSelections[] = {};
        hiddenSelectionsTextures[] = {};
        editorCategory = "COMSPEC_SSE_EdKits";
        editorSubcategory = "COMSPEC_SSE_EdBoxes";
        maximumLoad = 1000;
        class TransportWeapons {};
        class TransportMagazines {};
        class TransportBackpacks {};
        class TransportItems {
            SSE_STOCK(COMSPEC_SSE_FaradayBag,8);
            SSE_STOCK(COMSPEC_SSE_Terminal,1);
            SSE_STOCK(COMSPEC_SSE_Camera,1);
            SSE_STOCK(COMSPEC_SSE_Gloves,4);
            SSE_STOCK(COMSPEC_SSE_EvidenceBag,8);
        };
    };

    // Valise d'extraction numérique de terrain : posée près du support saisi, pilotée
    // depuis le terminal (usage câblé ailleurs). Elle range aussi ses sacs de Faraday.
    class COMSPEC_SSE_DomexCase: Box_NATO_Support_F {
        author = "COMSPEC";
        scope = 2;
        scopeCurator = 2;
        displayName = "$STR_COMSPEC_SSE_CFG_4E5AC91468";
        model = "\A3\Structures_F_Heli\Items\Luggage\PlasticCase_01_medium_F.p3d";
        hiddenSelections[] = {};
        hiddenSelectionsTextures[] = {};
        editorCategory = "COMSPEC_SSE_EdKits";
        editorSubcategory = "COMSPEC_SSE_EdBoxes";
        maximumLoad = 200;
        comspec_sse_kit = "DOMEX";
        class TransportWeapons {};
        class TransportMagazines {};
        class TransportBackpacks {};
        class TransportItems {
            SSE_STOCK(COMSPEC_SSE_FaradayBag,4);
            SSE_STOCK(COMSPEC_SSE_EvidenceBag,4);
        };
    };
};

// Marquage SSE des pièces posées (Eden, Zeus, script) : init sur toutes les machines,
// la fonction ne travaille que sur le serveur.
#define SSE_XEH(CLS) class CLS { class comspec_sse_evidence { init = "_this call comspec_sse_fnc_evidenceObjInit"; }; }

class Extended_InitPost_EventHandlers {
    SSE_XEH(COMSPEC_SSE_Obj_Phone);
    SSE_XEH(COMSPEC_SSE_Obj_Smartphone);
    SSE_XEH(COMSPEC_SSE_Obj_SatPhone);
    SSE_XEH(COMSPEC_SSE_Obj_Radio);
    SSE_XEH(COMSPEC_SSE_Obj_Laptop);
    SSE_XEH(COMSPEC_SSE_Obj_Tablet);
    SSE_XEH(COMSPEC_SSE_Obj_PCTower);
    SSE_XEH(COMSPEC_SSE_Obj_HardDrive);
    SSE_XEH(COMSPEC_SSE_Obj_USB);
    SSE_XEH(COMSPEC_SSE_Obj_SDCard);
    SSE_XEH(COMSPEC_SSE_Obj_Notebook);
    SSE_XEH(COMSPEC_SSE_Obj_Documents);
    SSE_XEH(COMSPEC_SSE_Obj_Ledger);
    SSE_XEH(COMSPEC_SSE_Obj_Photos);
    SSE_XEH(COMSPEC_SSE_Obj_Map);
    SSE_XEH(COMSPEC_SSE_Obj_Cash);
    SSE_XEH(COMSPEC_SSE_Obj_Suitcase);
};
