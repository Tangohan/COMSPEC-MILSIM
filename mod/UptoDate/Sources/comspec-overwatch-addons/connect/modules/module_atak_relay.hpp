    class COMSPEC_Module_AtakRelay: Module_F
    {
        author = "COMSPEC";
        scope = 2;
        scopeCurator = 0;
        displayName = "Relais ATAK (mât)";
        category = "COMSPEC_Roleplay";
        function = "comspec_overwatch_connect_fnc_moduleAtakRelay";
        functionPriority = 1;
        isGlobal = 1;
        isTriggerActivated = 0;
        isDisposable = 0;
        is3DEN = 1;
        curatorCanAttach = 0;
        canSetArea = 0;
        canSetAreaHeight = 0;
        canSetAreaShape = 0;
        icon = "\A3\ui_f\data\map\markers\military\flag_CA.paa";
        portrait = "\A3\ui_f\data\map\markers\military\flag_CA.paa";

        class Attributes
        {
            class RelayName
            {
                displayName = "Nom du relais";
                tooltip = "Nom affiché sur le téléphone (Relais AT) et au poste.";
                property = "COMSPEC_AtakRelay_Name";
                control = "Edit";
                expression = "_this setVariable ['RelayName',_value,true];";
                defaultValue = """Relais Nord""";
                typeName = "STRING";
            };
            class RangeM
            {
                displayName = "Portée (m)";
                tooltip = "Rayon dans lequel un téléphone peut s’appuyer sur ce relais. Hors de cette portée, le relais n’est plus utilisable.";
                property = "COMSPEC_AtakRelay_Range";
                control = "Edit";
                expression = "_this setVariable ['RangeM',_value,true];";
                defaultValue = "2000";
                validate = "number";
                typeName = "NUMBER";
            };
            class RelayIdentity
            {
                displayName = "Identité";
                tooltip = "Indicatif réseau du mât (ex. RLY-NORD-01). Visible dans Relais AT et au poste.";
                property = "COMSPEC_AtakRelay_Identity";
                control = "Edit";
                expression = "_this setVariable ['RelayIdentity',_value,true];";
                defaultValue = """RLY-01""";
                typeName = "STRING";
            };
            class RelayIp
            {
                displayName = "Adresse réseau";
                tooltip = "Adresse simulée du mât (ex. 10.12.40.1). Laissez vide pour en générer une à la pose.";
                property = "COMSPEC_AtakRelay_Ip";
                control = "Edit";
                expression = "_this setVariable ['RelayIp',_value,true];";
                defaultValue = """""";
                typeName = "STRING";
            };
            class RelayGateway
            {
                displayName = "Passerelle";
                tooltip = "Passerelle simulée vers le poste (ex. 10.12.0.1). Vide = générée à la pose.";
                property = "COMSPEC_AtakRelay_Gateway";
                control = "Edit";
                expression = "_this setVariable ['RelayGateway',_value,true];";
                defaultValue = """""";
                typeName = "STRING";
            };
            class RelayCertificate
            {
                displayName = "Certificat";
                tooltip = "Libellé du certificat de liaison (ex. Relais Nord — valable jusqu’à décembre 2027).";
                property = "COMSPEC_AtakRelay_Certificate";
                control = "Edit";
                expression = "_this setVariable ['RelayCertificate',_value,true];";
                defaultValue = """Certificat de relais — valable mission""";
                typeName = "STRING";
            };
            class RelaySlots
            {
                displayName = "Places (téléphones)";
                tooltip = "Nombre de téléphones qui peuvent s’appuyer en même temps sur ce mât.";
                property = "COMSPEC_AtakRelay_Slots";
                control = "Edit";
                expression = "_this setVariable ['RelaySlots',_value,true];";
                defaultValue = "8";
                validate = "number";
                typeName = "NUMBER";
            };
            class RelayPowerW
            {
                displayName = "Puissance (W)";
                tooltip = "Puissance d’émission simulée. Passe à zéro si le mât est détruit.";
                property = "COMSPEC_AtakRelay_PowerW";
                control = "Edit";
                expression = "_this setVariable ['RelayPowerW',_value,true];";
                defaultValue = "25";
                validate = "number";
                typeName = "NUMBER";
            };
            class RelayThroughput
            {
                displayName = "Débit de base (Mbit/s)";
                tooltip = "Débit annoncé. Il baisse avec la distance, les dégâts, et tombe à zéro si le mât est détruit.";
                property = "COMSPEC_AtakRelay_Throughput";
                control = "Edit";
                expression = "_this setVariable ['RelayThroughput',_value,true];";
                defaultValue = "12";
                validate = "number";
                typeName = "NUMBER";
            };
            class RelayReliability
            {
                displayName = "Fiabilité de base (%)";
                tooltip = "Fiabilité annoncée. Elle baisse hors portée ou sous les tirs, et tombe à zéro si le mât est détruit.";
                property = "COMSPEC_AtakRelay_Reliability";
                control = "Edit";
                expression = "_this setVariable ['RelayReliability',_value,true];";
                defaultValue = "92";
                validate = "number";
                typeName = "NUMBER";
            };
        };

        class Arguments
        {
            class RelayName
            {
                displayName = "Nom du relais";
                description = "Nom affiché sur Relais AT et au poste";
                typeName = "STRING";
                defaultValue = "Relais Nord";
            };
            class RangeM
            {
                displayName = "Portée (m)";
                description = "Rayon de liaison du relais";
                typeName = "NUMBER";
                defaultValue = "2000";
            };
            class RelayIdentity
            {
                displayName = "Identité";
                description = "Indicatif réseau du mât";
                typeName = "STRING";
                defaultValue = "RLY-01";
            };
            class RelayIp
            {
                displayName = "Adresse réseau";
                description = "Adresse simulée (vide = générée)";
                typeName = "STRING";
                defaultValue = "";
            };
            class RelayGateway
            {
                displayName = "Passerelle";
                description = "Passerelle simulée (vide = générée)";
                typeName = "STRING";
                defaultValue = "";
            };
            class RelayCertificate
            {
                displayName = "Certificat";
                description = "Libellé du certificat de liaison";
                typeName = "STRING";
                defaultValue = "Certificat de relais — valable mission";
            };
            class RelaySlots
            {
                displayName = "Places";
                description = "Téléphones simultanés";
                typeName = "NUMBER";
                defaultValue = "8";
            };
            class RelayPowerW
            {
                displayName = "Puissance (W)";
                description = "Puissance d’émission simulée";
                typeName = "NUMBER";
                defaultValue = "25";
            };
            class RelayThroughput
            {
                displayName = "Débit (Mbit/s)";
                description = "Débit de base";
                typeName = "NUMBER";
                defaultValue = "12";
            };
            class RelayReliability
            {
                displayName = "Fiabilité (%)";
                description = "Fiabilité de base";
                typeName = "NUMBER";
                defaultValue = "92";
            };
        };

        class ModuleDescription
        {
            description = "Tutoriel Eden — Relais ATAK (mât). 1) Placez le module à l’endroit du mât (colline, toit, LZ). 2) Renseignez le nom, l’identité, la portée, le débit, la fiabilité, la puissance, les places, l’adresse réseau, la passerelle et le certificat — tout est visible dans l’application Relais AT du téléphone et sur la carte du poste. 3) Laissez l’adresse et la passerelle vides si vous voulez qu’elles soient générées. 4) Le mât est un objet détruisible : un tir, une charge ou un Zeus « tuer » le met hors service (débit et puissance à zéro, pastille rouge au poste). 5) Si la règle « Exiger un relais » est activée au poste, un téléphone hors de portée d’un mât intact ne transmet plus. 6) Testez : Relais AT doit afficher le mât le plus proche, sa grille, ses places occupées, et passer à « détruit » dès que le mât tombe.";
            position = 1;
            direction = 0;
            optional = 1;
            duplicate = 1;
            synced[] = {};
        };
    };
