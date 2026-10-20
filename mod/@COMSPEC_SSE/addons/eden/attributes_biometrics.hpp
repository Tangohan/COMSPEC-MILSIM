/*
    Attributs Eden — biométrie du sujet (moteur du pack SSE).

    Tout est facultatif : laissé sur « Automatique », le sujet reçoit des empreintes,
    des iris, un visage et un ADN générés, et sa présence en base dépend des taux du
    camp (réglages CBA › COMSPEC SSE › Biométrie — population).
    Variables écrites : COMSPEC_SSE_Bio* (voir comspec_sse_fnc_bioSetProfile).
*/
class COMSPEC_SSE_Biometrie {
    displayName = "$STR_COMSPEC_SSE_CFG_5ED1396E71";
    collapsed = 1;
    class Attributes {
        class COMSPEC_SSE_BioDb {
            displayName = "$STR_COMSPEC_SSE_CFG_157CE773F2";
            tooltip = "$STR_COMSPEC_SSE_CFG_A355663EEA";
            property = "COMSPEC_SSE_BioDb";
            control = "Combo";
            expression = "if (_value isNotEqualTo 'auto') then { _this setVariable ['COMSPEC_SSE_BioDb', _value, isServer]; };";
            defaultValue = "'auto'";
            typeName = "STRING";
            condition = "objectBrain";
            class values {
                class Auto { name = "$STR_COMSPEC_SSE_CFG_4AC6A99CA2"; value = "auto"; default = 1; };
                class Absent { name = "$STR_COMSPEC_SSE_CFG_9749BC9270"; value = "absent"; };
                class Enrole { name = "$STR_COMSPEC_SSE_CFG_82C181917F"; value = "enrole"; };
                class Bewl1 { name = "$STR_COMSPEC_SSE_CFG_176F12A61A"; value = "bewl1"; };
                class Bewl2 { name = "$STR_COMSPEC_SSE_CFG_0F425AF2E9"; value = "bewl2"; };
                class Bewl3 { name = "$STR_COMSPEC_SSE_CFG_975C7AF748"; value = "bewl3"; };
                class Bewl4 { name = "$STR_COMSPEC_SSE_CFG_D9169F85EF"; value = "bewl4"; };
            };
        };
        class COMSPEC_SSE_BioReason {
            displayName = "$STR_COMSPEC_SSE_CFG_CC587FED60";
            tooltip = "$STR_COMSPEC_SSE_CFG_D7546487E0";
            property = "COMSPEC_SSE_BioReason";
            control = "Edit";
            expression = "if (_value isNotEqualTo '') then { _this setVariable ['COMSPEC_SSE_BioReason', _value, isServer]; };";
            defaultValue = "''";
            typeName = "STRING";
            condition = "objectBrain";
        };
        class COMSPEC_SSE_BioFalsePapers {
            displayName = "$STR_COMSPEC_SSE_CFG_9A36AFA781";
            tooltip = "$STR_COMSPEC_SSE_CFG_C1AD41F8F0";
            property = "COMSPEC_SSE_BioFalsePapers";
            control = "Combo";
            expression = "if (_value isNotEqualTo 'auto') then { _this setVariable ['COMSPEC_SSE_BioFalsePapers', _value, isServer]; };";
            defaultValue = "'auto'";
            typeName = "STRING";
            condition = "objectBrain";
            class values {
                class Auto { name = "$STR_COMSPEC_SSE_CFG_434D4780BA"; value = "auto"; default = 1; };
                class Oui { name = "$STR_COMSPEC_SSE_CFG_EAA02F3F33"; value = "oui"; };
                class Non { name = "$STR_COMSPEC_SSE_CFG_A721993901"; value = "non"; };
            };
        };
        class COMSPEC_SSE_BioTrueName {
            displayName = "$STR_COMSPEC_SSE_CFG_F364E0157C";
            tooltip = "$STR_COMSPEC_SSE_CFG_C9D8D09F08";
            property = "COMSPEC_SSE_BioTrueName";
            control = "Edit";
            expression = "if (_value isNotEqualTo '') then { _this setVariable ['COMSPEC_SSE_BioTrueName', _value, isServer]; };";
            defaultValue = "''";
            typeName = "STRING";
            condition = "objectBrain";
        };
        class COMSPEC_SSE_BioId {
            displayName = "$STR_COMSPEC_SSE_CFG_C90174A4E0";
            tooltip = "$STR_COMSPEC_SSE_CFG_73BF33117C";
            property = "COMSPEC_SSE_BioId";
            control = "Edit";
            expression = "if (_value isNotEqualTo '') then { _this setVariable ['COMSPEC_SSE_BioId', _value, isServer]; };";
            defaultValue = "''";
            typeName = "STRING";
            condition = "objectBrain";
        };
        class COMSPEC_SSE_BioRelativeOf {
            displayName = "$STR_COMSPEC_SSE_CFG_3F5EE77F7B";
            tooltip = "$STR_COMSPEC_SSE_CFG_7E91360C00";
            property = "COMSPEC_SSE_BioRelativeOf";
            control = "Edit";
            expression = "if (_value isNotEqualTo '') then { _this setVariable ['COMSPEC_SSE_BioRelativeOf', _value, isServer]; };";
            defaultValue = "''";
            typeName = "STRING";
            condition = "objectBrain";
        };
        class COMSPEC_SSE_BioRelation {
            displayName = "$STR_COMSPEC_SSE_CFG_FAFF6B76A4";
            tooltip = "$STR_COMSPEC_SSE_CFG_99FA083267";
            property = "COMSPEC_SSE_BioRelation";
            control = "Combo";
            expression = "_this setVariable ['COMSPEC_SSE_BioRelation', _value, isServer];";
            defaultValue = "'frere'";
            typeName = "STRING";
            condition = "objectBrain";
            class values {
                class Frere { name = "$STR_COMSPEC_SSE_CFG_DE6493C844"; value = "frere"; default = 1; };
                class Parent { name = "$STR_COMSPEC_SSE_CFG_23D692F07A"; value = "parent"; };
                class Enfant { name = "$STR_COMSPEC_SSE_CFG_97F6976933"; value = "enfant"; };
            };
        };
        class COMSPEC_SSE_BioMissingFingers {
            displayName = "$STR_COMSPEC_SSE_CFG_CD49F91D7E";
            tooltip = "$STR_COMSPEC_SSE_CFG_9BC060EDCD";
            property = "COMSPEC_SSE_BioMissingFingers";
            control = "Edit";
            expression = "if (_value isNotEqualTo '') then { _this setVariable ['COMSPEC_SSE_BioMissingFingers', _value, isServer]; };";
            defaultValue = "''";
            typeName = "STRING";
            condition = "objectBrain";
        };
        class COMSPEC_SSE_BioIrisState {
            displayName = "$STR_COMSPEC_SSE_CFG_422505A418";
            tooltip = "$STR_COMSPEC_SSE_CFG_D910355068";
            property = "COMSPEC_SSE_BioIrisState";
            control = "Combo";
            expression = "if (_value isNotEqualTo 'auto') then { _this setVariable ['COMSPEC_SSE_BioIrisState', _value, isServer]; };";
            defaultValue = "'auto'";
            typeName = "STRING";
            condition = "objectBrain";
            class values {
                class Auto { name = "$STR_COMSPEC_SSE_CFG_F8A3C37BEB"; value = "auto"; default = 1; };
                class Cataracte { name = "$STR_COMSPEC_SSE_CFG_9399696D1F"; value = "cataracte"; };
                class ProtheseOD { name = "$STR_COMSPEC_SSE_CFG_B91825801A"; value = "prothese_od"; };
                class ProtheseOG { name = "$STR_COMSPEC_SSE_CFG_AFED6653E7"; value = "prothese_og"; };
                class LesionOD { name = "$STR_COMSPEC_SSE_CFG_2D4912CAFF"; value = "lesion_od"; };
                class LesionOG { name = "$STR_COMSPEC_SSE_CFG_D646A459AE"; value = "lesion_og"; };
            };
        };
        class COMSPEC_SSE_BioLatentOwners {
            displayName = "$STR_COMSPEC_SSE_CFG_61097AFA92";
            tooltip = "$STR_COMSPEC_SSE_CFG_C89290ABA5";
            property = "COMSPEC_SSE_BioLatentOwners";
            control = "Edit";
            expression = "if (_value isNotEqualTo '') then { _this setVariable ['COMSPEC_SSE_BioLatentOwners', _value, isServer]; };";
            defaultValue = "''";
            typeName = "STRING";
            condition = "objectSimulated + objectVehicle";
        };
    };
};
