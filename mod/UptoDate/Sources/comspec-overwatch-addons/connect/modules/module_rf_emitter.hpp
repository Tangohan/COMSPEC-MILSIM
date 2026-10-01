    class COMSPEC_Module_RfEmitter: Module_F
    {
        author = "COMSPEC";
        scope = 2;
        scopeCurator = 0;
        displayName = "Émetteur RF Fieldwatch";
        category = "COMSPEC_Roleplay";
        function = "comspec_overwatch_connect_fnc_moduleRfEmitter";
        functionPriority = 1;
        isGlobal = 1;
        isTriggerActivated = 0;
        isDisposable = 0;
        is3DEN = 1;
        curatorCanAttach = 0;
        canSetArea = 0;
        canSetAreaHeight = 0;
        canSetAreaShape = 0;
        icon = "\A3\ui_f\data\IGUI\Cfg\simpleTasks\types\listen_ca.paa";
        portrait = "\A3\ui_f\data\IGUI\Cfg\simpleTasks\types\listen_ca.paa";

        class Attributes
        {
            class EmitterLabel
            {
                displayName = "Nom / SSID";
                tooltip = "Nom affiché dans Fieldwatch (ex. IPCam-Lobby, Tile-AirTag).";
                property = "COMSPEC_RfEmitter_Label";
                control = "Edit";
                expression = "_this setVariable ['EmitterLabel',_value,true];";
                defaultValue = """IPCam-Lobby""";
                typeName = "STRING";
            };
            class EmitterBand
            {
                displayName = "Bande";
                tooltip = "wifi | ble | tracker | camera | phone | unknown";
                property = "COMSPEC_RfEmitter_Band";
                control = "Edit";
                expression = "_this setVariable ['EmitterBand',_value,true];";
                defaultValue = """wifi""";
                typeName = "STRING";
            };
            class EmitterSignature
            {
                displayName = "Signature";
                tooltip = "Identifiant de signature (ex. wifi_ipcam, ble_tile).";
                property = "COMSPEC_RfEmitter_Signature";
                control = "Edit";
                expression = "_this setVariable ['EmitterSignature',_value,true];";
                defaultValue = """wifi_ipcam""";
                typeName = "STRING";
            };
            class RangeM
            {
                displayName = "Portée scan (m)";
                tooltip = "Distance max à laquelle Fieldwatch peut entendre cet émetteur.";
                property = "COMSPEC_RfEmitter_Range";
                control = "Edit";
                expression = "_this setVariable ['RangeM',_value,true];";
                defaultValue = "80";
                validate = "number";
                typeName = "NUMBER";
            };
            class PowerDbm
            {
                displayName = "Puissance (dBm)";
                tooltip = "Puissance d’émission simulée à la source (typ. -30 à 20).";
                property = "COMSPEC_RfEmitter_Power";
                control = "Edit";
                expression = "_this setVariable ['PowerDbm',_value,true];";
                defaultValue = "10";
                validate = "number";
                typeName = "NUMBER";
            };
            class EmitterMac
            {
                displayName = "Adresse MAC simulée";
                tooltip = "Vide = générée à la pose.";
                property = "COMSPEC_RfEmitter_Mac";
                control = "Edit";
                expression = "_this setVariable ['EmitterMac',_value,true];";
                defaultValue = """""";
                typeName = "STRING";
            };
        };

        class Arguments
        {
            class EmitterLabel
            {
                displayName = "Nom / SSID";
                description = "Nom affiché dans Fieldwatch";
                typeName = "STRING";
                defaultValue = "IPCam-Lobby";
            };
            class EmitterBand
            {
                displayName = "Bande";
                description = "wifi | ble | tracker | camera | phone";
                typeName = "STRING";
                defaultValue = "wifi";
            };
            class EmitterSignature
            {
                displayName = "Signature";
                description = "Identifiant de signature";
                typeName = "STRING";
                defaultValue = "wifi_ipcam";
            };
            class RangeM
            {
                displayName = "Portée scan (m)";
                description = "Distance max de détection";
                typeName = "NUMBER";
                defaultValue = "80";
            };
            class PowerDbm
            {
                displayName = "Puissance (dBm)";
                description = "Puissance à la source";
                typeName = "NUMBER";
                defaultValue = "10";
            };
            class EmitterMac
            {
                displayName = "MAC simulée";
                description = "Vide = générée";
                typeName = "STRING";
                defaultValue = "";
            };
        };

        class ModuleDescription
        {
            description = "Émetteur RF Fieldwatch. Posez le module, choisissez bande (Wi‑Fi / BLE / tracker / caméra) et portée. En jeu, l’app Fieldwatch du téléphone scanne les émetteurs à proximité et remonte les hits au poste ATAK.";
            position = 1;
            direction = 0;
            optional = 1;
            duplicate = 1;
            synced[] = {};
        };
    };
