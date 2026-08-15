#define MPU5_ID(IDN) class ACRE_MPU5_ID_##IDN: ACRE_MPU5 { acre_hasUnique = 0; acre_isUnique = 1; acre_baseClass = "ACRE_MPU5"; ace_arsenal_uniqueBase = "ACRE_MPU5"; acre_uniqueId = IDN; scope = 1; scopeArsenal = 0; scopeCurator = 0; class Armory { disabled = 1; }; }

class CfgPatches
{
    class Iceman_ACRE_MPU5
    {
        name = "MPU-5 Persistent Systems";
        author = "Cole / Codex";
        requiredVersion = 2.14;
        requiredAddons[] = {"cba_main", "acre_api", "acre_main", "acre_sys_components", "acre_sys_core", "acre_sys_data", "acre_sys_modes", "acre_sys_radio", "acre_sys_signal", "acre_sys_sounds", "acre_ace_interact"};
        units[] = {};
        weapons[] = {"ACRE_MPU5"};
    };
};

class Extended_PostInit_EventHandlers
{
    class Iceman_ACRE_MPU5
    {
        init = "call compile preprocessFileLineNumbers '\ACRE_MPU5\XEH_postInit.sqf'";
    };
};

class Extended_PreInit_EventHandlers
{
    class Iceman_ACRE_MPU5
    {
        init = "call compile preprocessFileLineNumbers '\ACRE_MPU5\XEH_preInit.sqf'";
    };
};

class CfgWeapons
{
    class ACRE_BaseRadio;
    class CBA_MiscItem_ItemInfo;

    class ACRE_MPU5: ACRE_BaseRadio
    {
        author = "Cole / Codex";
        displayName = "MPU-5 Persistent Systems";
        useActionTitle = "MPU-5 Persistent Systems";
        descriptionShort = "ACRE-compatible MPU-5 / Wave Relay MANET radio node";
        picture = "\ACRE_MPU5\data\mpu5cropped.paa";
        model = "\idi\acre\addons\sys_prc152\data\models\PRC152.p3d";
        scope = 2;
        scopeArsenal = 2;
        scopeCurator = 2;
        acre_hasUnique = 1;
        acre_isRadio = 1;
        acre_baseClass = "ACRE_MPU5";
        Iceman_WR_isMPU5 = 1;

        class Library
        {
            libTextDesc = "MPU-5 Persistent Systems Wave Relay MANET node";
        };

        acre_arsenalStats_frequencyMin = 2200e6;
        acre_arsenalStats_frequencyMax = 2500e6;
        acre_arsenalStats_transmitPower = 5000;
        acre_arsenalStats_effectiveRange = "Wave Relay simulated MANET";
        acre_arsenalStats_externalSpeaker = 1;

        class ItemInfo: CBA_MiscItem_ItemInfo
        {
            mass = 12;
            scope = 0;
        };
    };

    MPU5_ID(1);
    MPU5_ID(2);
    MPU5_ID(3);
    MPU5_ID(4);
    MPU5_ID(5);
    MPU5_ID(6);
    MPU5_ID(7);
    MPU5_ID(8);
    MPU5_ID(9);
    MPU5_ID(10);
    MPU5_ID(11);
    MPU5_ID(12);
    MPU5_ID(13);
    MPU5_ID(14);
    MPU5_ID(15);
    MPU5_ID(16);
    MPU5_ID(17);
    MPU5_ID(18);
    MPU5_ID(19);
    MPU5_ID(20);
    MPU5_ID(21);
    MPU5_ID(22);
    MPU5_ID(23);
    MPU5_ID(24);
    MPU5_ID(25);
    MPU5_ID(26);
    MPU5_ID(27);
    MPU5_ID(28);
    MPU5_ID(29);
    MPU5_ID(30);
    MPU5_ID(31);
    MPU5_ID(32);
    MPU5_ID(33);
    MPU5_ID(34);
    MPU5_ID(35);
    MPU5_ID(36);
    MPU5_ID(37);
    MPU5_ID(38);
    MPU5_ID(39);
    MPU5_ID(40);
    MPU5_ID(41);
    MPU5_ID(42);
    MPU5_ID(43);
    MPU5_ID(44);
    MPU5_ID(45);
    MPU5_ID(46);
    MPU5_ID(47);
    MPU5_ID(48);
    MPU5_ID(49);
    MPU5_ID(50);
    MPU5_ID(51);
    MPU5_ID(52);
    MPU5_ID(53);
    MPU5_ID(54);
    MPU5_ID(55);
    MPU5_ID(56);
    MPU5_ID(57);
    MPU5_ID(58);
    MPU5_ID(59);
    MPU5_ID(60);
    MPU5_ID(61);
    MPU5_ID(62);
    MPU5_ID(63);
    MPU5_ID(64);
    MPU5_ID(65);
    MPU5_ID(66);
    MPU5_ID(67);
    MPU5_ID(68);
    MPU5_ID(69);
    MPU5_ID(70);
    MPU5_ID(71);
    MPU5_ID(72);
    MPU5_ID(73);
    MPU5_ID(74);
    MPU5_ID(75);
    MPU5_ID(76);
    MPU5_ID(77);
    MPU5_ID(78);
    MPU5_ID(79);
    MPU5_ID(80);
    MPU5_ID(81);
    MPU5_ID(82);
    MPU5_ID(83);
    MPU5_ID(84);
    MPU5_ID(85);
    MPU5_ID(86);
    MPU5_ID(87);
    MPU5_ID(88);
    MPU5_ID(89);
    MPU5_ID(90);
    MPU5_ID(91);
    MPU5_ID(92);
    MPU5_ID(93);
    MPU5_ID(94);
    MPU5_ID(95);
    MPU5_ID(96);
    MPU5_ID(97);
    MPU5_ID(98);
    MPU5_ID(99);
    MPU5_ID(100);
    MPU5_ID(101);
    MPU5_ID(102);
    MPU5_ID(103);
    MPU5_ID(104);
    MPU5_ID(105);
    MPU5_ID(106);
    MPU5_ID(107);
    MPU5_ID(108);
    MPU5_ID(109);
    MPU5_ID(110);
    MPU5_ID(111);
    MPU5_ID(112);
    MPU5_ID(113);
    MPU5_ID(114);
    MPU5_ID(115);
    MPU5_ID(116);
    MPU5_ID(117);
    MPU5_ID(118);
    MPU5_ID(119);
    MPU5_ID(120);
    MPU5_ID(121);
    MPU5_ID(122);
    MPU5_ID(123);
    MPU5_ID(124);
    MPU5_ID(125);
    MPU5_ID(126);
    MPU5_ID(127);
    MPU5_ID(128);
};

class CfgFunctions
{
    class Iceman
    {
        class ACRE_MPU5
        {
            file = "\ACRE_MPU5\functions";
            class mpu5_buildPreset {};
            class mpu5_cloneChannel {};
            class mpu5_createChannel {};
            class mpu5_filterRadioActions {};
            class mpu5_getChannelData {};
            class mpu5_getChannelDescription {};
            class mpu5_getCurrentChannel {};
            class mpu5_getCurrentChannelData {};
            class mpu5_getExternalAudioPosition {};
            class mpu5_getListInfo {};
            class mpu5_getOnOffState {};
            class mpu5_getSpatial {};
            class mpu5_getState {};
            class mpu5_getStates {};
            class mpu5_getVolume {};
            class mpu5_handleBeginTransmission {};
            class mpu5_handleEndTransmission {};
            class mpu5_handleMultipleTransmissions {};
            class mpu5_handlePTTDown {};
            class mpu5_handlePTTUp {};
            class mpu5_handleSignalData {};
            class mpu5_initializeRadio {};
            class mpu5_isExternalAudio {};
            class mpu5_keyAcrePtt {};
            class mpu5_playTalkgroupCue {};
            class mpu5_publicChannelData {};
            class mpu5_setChannelData {};
            class mpu5_setCurrentChannel {};
            class mpu5_setOnOffState {};
            class mpu5_setSpatial {};
            class mpu5_setState {};
            class mpu5_setVolume {};
            class mpu5_singleChannelAvailability {};
            class mpu5_singleChannelSpeaking {};
        };
    };
};

class CfgAcreRadioModes
{
    class singleChannel
    {
        availability = "Iceman_fnc_mpu5_singleChannelAvailability";
        speaking = "Iceman_fnc_mpu5_singleChannelSpeaking";
        channelHash[] = {
            "frequencyTX",
            "frequencyRX",
            "power",
            "mode",
            "CTCSSTx",
            "CTCSSRx",
            "modulation",
            "encryption",
            "TEK",
            "trafficRate",
            "syncLength",
            "Iceman_WR_talkgroup",
            "Iceman_WR_frequencyBank",
            "Iceman_ROIP_enabled",
            "Iceman_ROIP_linkId",
            "Iceman_ROIP_gatewayRadioId"
        };
    };
};

class CfgVehicles
{
    class Man;
    class CAManBase: Man
    {
        class ACE_SelfActions
        {
            class ACRE_Interact
            {
                insertChildren = "_this call Iceman_fnc_mpu5_filterRadioActions";
            };
        };
    };
};

class CfgAcreComponents
{
    class ACRE_BaseRadio;

    class ACRE_MPU5: ACRE_BaseRadio
    {
        name = "MPU-5 Persistent Systems";
        isAcre = 1;
        sinadRating = -120;
        sensitivityMin = -120;
        sensitivityMax = -50;
        isPackRadio = 0;
        isDeployable = 0;
        connectors[] = {{"Antenna", 1}};
        defaultComponents[] = {{0, "ACRE_100CM_VHF_TNC"}};

        class InterfaceClasses
        {
            CfgAcreDataInterface = "DefaultRadioInterface";
            CfgAcreInteractInterface = "DefaultRadioInterface";
            CfgAcreTransmissionInterface = "DefaultRadioInterface";
            CfgAcrePhysicalInterface = "DefaultRadioInterface";
        };

        class Interfaces
        {
            class CfgAcreDataInterface
            {
                getListInfo = "Iceman_fnc_mpu5_getListInfo";
                setVolume = "Iceman_fnc_mpu5_setVolume";
                getVolume = "Iceman_fnc_mpu5_getVolume";
                setSpatial = "Iceman_fnc_mpu5_setSpatial";
                getSpatial = "Iceman_fnc_mpu5_getSpatial";
                setChannelData = "Iceman_fnc_mpu5_setChannelData";
                getChannelData = "Iceman_fnc_mpu5_getChannelData";
                getCurrentChannelData = "Iceman_fnc_mpu5_getCurrentChannelData";
                getCurrentChannel = "Iceman_fnc_mpu5_getCurrentChannel";
                setCurrentChannel = "Iceman_fnc_mpu5_setCurrentChannel";
                getStates = "Iceman_fnc_mpu5_getStates";
                getState = "Iceman_fnc_mpu5_getState";
                setState = "Iceman_fnc_mpu5_setState";
                setStateCritical = "Iceman_fnc_mpu5_setState";
                getOnOffState = "Iceman_fnc_mpu5_getOnOffState";
                setOnOffState = "Iceman_fnc_mpu5_setOnOffState";
                initializeComponent = "Iceman_fnc_mpu5_initializeRadio";
                getChannelDescription = "Iceman_fnc_mpu5_getChannelDescription";
                isExternalAudio = "Iceman_fnc_mpu5_isExternalAudio";
                getExternalAudioPosition = "Iceman_fnc_mpu5_getExternalAudioPosition";
            };
            class CfgAcrePhysicalInterface
            {
                getExternalAudioPosition = "Iceman_fnc_mpu5_getExternalAudioPosition";
            };
            class CfgAcreTransmissionInterface
            {
                handleBeginTransmission = "Iceman_fnc_mpu5_handleBeginTransmission";
                handleEndTransmission = "Iceman_fnc_mpu5_handleEndTransmission";
                handleSignalData = "Iceman_fnc_mpu5_handleSignalData";
                handleMultipleTransmissions = "Iceman_fnc_mpu5_handleMultipleTransmissions";
                handlePTTDown = "Iceman_fnc_mpu5_handlePTTDown";
                handlePTTUp = "Iceman_fnc_mpu5_handlePTTUp";
            };
            class CfgAcreInteractInterface
            {
                openGui = "";
                closeGui = "";
            };
        };
    };
};
