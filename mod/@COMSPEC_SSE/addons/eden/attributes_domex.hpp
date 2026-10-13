class COMSPEC_SSE_Domex {
    displayName = "$STR_COMSPEC_SSE_CFG_CD47276DEC";
    collapsed = 1;
    class Attributes {
        class comspec_sse_domex_enabled {
            displayName = "$STR_COMSPEC_SSE_CFG_21931F02FE";
            tooltip = "$STR_COMSPEC_SSE_CFG_65874AF125";
            property = "comspec_sse_domex_enabled";
            control = "Checkbox";
            expression = "_this setVariable ['comspec_sse_domex_enabled', _value, isServer]; if (_value) then { _this setVariable ['comspec_sse_enabled', true, isServer]; };";
            defaultValue = "false";
            typeName = "BOOL";
            condition = "objectSimulated - objectBrain";
        };
        class comspec_sse_domex_nodeId {
            displayName = "$STR_COMSPEC_SSE_CFG_68E546FCE7";
            tooltip = "$STR_COMSPEC_SSE_CFG_7EA2B3769C";
            property = "comspec_sse_domex_nodeId";
            control = "Edit";
            expression = "_this setVariable ['comspec_sse_domex_nodeId', _value, isServer];";
            defaultValue = "''";
            typeName = "STRING";
            condition = "objectSimulated - objectBrain";
        };
        class comspec_sse_domex_deviceType {
            displayName = "$STR_COMSPEC_SSE_CFG_3E882DE7BA";
            tooltip = "$STR_COMSPEC_SSE_CFG_69403D5D82";
            property = "comspec_sse_domex_deviceType";
            control = "Combo";
            expression = "_this setVariable ['comspec_sse_domex_deviceType', _value, isServer];";
            defaultValue = "'ordinateur'";
            typeName = "STRING";
            condition = "objectSimulated - objectBrain";
            class values {
                class ordinateur { name = "$STR_COMSPEC_SSE_CFG_CBAB5F515D"; value = "ordinateur"; default = 1; };
                class telephone { name = "$STR_COMSPEC_SSE_CFG_D3B023EABC"; value = "telephone"; };
                class tablette { name = "$STR_COMSPEC_SSE_CFG_7F73B0F8AB"; value = "tablette"; };
                class radio_numerique { name = "$STR_COMSPEC_SSE_CFG_B11BF19E3F"; value = "radio_numerique"; };
                class disque_dur { name = "$STR_COMSPEC_SSE_CFG_A773143115"; value = "disque_dur"; };
                class cle_usb { name = "$STR_COMSPEC_SSE_CFG_B972CCAE08"; value = "cle_usb"; };
                class gps { name = "GPS"; value = "gps"; };
                class appareil_photo { name = "$STR_COMSPEC_SSE_CFG_402DCBB9A9"; value = "appareil_photo"; };
                class support_amovible { name = "$STR_COMSPEC_SSE_CFG_306540C0B4"; value = "support_amovible"; };
            };
        };
        class comspec_sse_domex_owner {
            displayName = "$STR_COMSPEC_SSE_CFG_64155AAEA0";
            tooltip = "$STR_COMSPEC_SSE_CFG_7D10F4C0B7";
            property = "comspec_sse_domex_owner";
            control = "Edit";
            expression = "_this setVariable ['comspec_sse_domex_owner', _value, isServer];";
            defaultValue = "''";
            typeName = "STRING";
            condition = "objectSimulated - objectBrain";
        };
        class comspec_sse_domex_org {
            displayName = "$STR_COMSPEC_SSE_CFG_6E99C1D3B1";
            tooltip = "$STR_COMSPEC_SSE_CFG_F5DBCF8E17";
            property = "comspec_sse_domex_org";
            control = "Edit";
            expression = "_this setVariable ['comspec_sse_domex_org', _value, isServer];";
            defaultValue = "''";
            typeName = "STRING";
            condition = "objectSimulated - objectBrain";
        };
        class comspec_sse_domex_network {
            displayName = "$STR_COMSPEC_SSE_CFG_0D324E2BD2";
            tooltip = "$STR_COMSPEC_SSE_CFG_38621FCCC6";
            property = "comspec_sse_domex_network";
            control = "Edit";
            expression = "_this setVariable ['comspec_sse_domex_network', _value, isServer];";
            defaultValue = "''";
            typeName = "STRING";
            condition = "objectSimulated - objectBrain";
        };
        class comspec_sse_domex_exploitable {
            displayName = "$STR_COMSPEC_SSE_CFG_11891A3D5B";
            tooltip = "$STR_COMSPEC_SSE_CFG_20DC301AE7";
            property = "comspec_sse_domex_exploitable";
            control = "Checkbox";
            expression = "_this setVariable ['comspec_sse_domex_exploitable', _value, isServer];";
            defaultValue = "true";
            typeName = "BOOL";
            condition = "objectSimulated - objectBrain";
        };
        class comspec_sse_domex_accessPhysical {
            displayName = "$STR_COMSPEC_SSE_CFG_41C483829B";
            tooltip = "$STR_COMSPEC_SSE_CFG_591CD4CAED";
            property = "comspec_sse_domex_accessPhysical";
            control = "Checkbox";
            expression = "_this setVariable ['comspec_sse_domex_accessPhysical', _value, isServer];";
            defaultValue = "true";
            typeName = "BOOL";
            condition = "objectSimulated - objectBrain";
        };
        class comspec_sse_domex_accessRemote {
            displayName = "$STR_COMSPEC_SSE_CFG_F3D9E18AC0";
            tooltip = "$STR_COMSPEC_SSE_CFG_3795655AA9";
            property = "comspec_sse_domex_accessRemote";
            control = "Checkbox";
            expression = "_this setVariable ['comspec_sse_domex_accessRemote', _value, isServer];";
            defaultValue = "false";
            typeName = "BOOL";
            condition = "objectSimulated - objectBrain";
        };
        class comspec_sse_domex_security {
            displayName = "$STR_COMSPEC_SSE_CFG_86BC4AF6AC";
            tooltip = "$STR_COMSPEC_SSE_CFG_A56250A2F0";
            property = "comspec_sse_domex_security";
            control = "Combo";
            expression = "_this setVariable ['comspec_sse_domex_security', _value, isServer];";
            defaultValue = "'moyenne'";
            typeName = "STRING";
            condition = "objectSimulated - objectBrain";
            class values {
                class faible { name = "$STR_COMSPEC_SSE_CFG_3DF7A6B1E1"; value = "faible"; };
                class moyenne { name = "$STR_COMSPEC_SSE_CFG_EF33304D4F"; value = "moyenne"; default = 1; };
                class elevee { name = "$STR_COMSPEC_SSE_CFG_4C4DE74821"; value = "elevee"; };
            };
        };
        class comspec_sse_domex_profile {
            displayName = "$STR_COMSPEC_SSE_CFG_3F9713E6BC";
            tooltip = "$STR_COMSPEC_SSE_CFG_171489A891";
            property = "comspec_sse_domex_profile";
            control = "Combo";
            expression = "_this setVariable ['comspec_sse_domex_profile', _value, isServer];";
            defaultValue = "'generique'";
            typeName = "STRING";
            condition = "objectSimulated - objectBrain";
            class values {
                class generique { name = "$STR_COMSPEC_SSE_CFG_C2C5747AD0"; value = "generique"; default = 1; };
                class logistique { name = "$STR_COMSPEC_SSE_CFG_8C266B946B"; value = "logistique"; };
                class commandement { name = "$STR_COMSPEC_SSE_CFG_2AAFBA4D6B"; value = "commandement"; };
                class personnel { name = "$STR_COMSPEC_SSE_CFG_87EECD7729"; value = "personnel"; };
                class radio { name = "$STR_COMSPEC_SSE_CFG_C211FD39A4"; value = "radio"; };
            };
        };
        class comspec_sse_domex_duration {
            displayName = "$STR_COMSPEC_SSE_CFG_5D6D8F519B";
            tooltip = "$STR_COMSPEC_SSE_CFG_69383F7A32";
            property = "comspec_sse_domex_duration";
            control = "Combo";
            expression = "_this setVariable ['comspec_sse_domex_duration', _value, isServer];";
            defaultValue = "'180'";
            typeName = "STRING";
            condition = "objectSimulated - objectBrain";
            class values {
                class t30 { name = "$STR_COMSPEC_SSE_CFG_9C82969A84"; value = "30"; };
                class t60 { name = "$STR_COMSPEC_SSE_CFG_62957ECFDC"; value = "60"; };
                class t120 { name = "$STR_COMSPEC_SSE_CFG_1DD3E663DF"; value = "120"; };
                class t180 { name = "$STR_COMSPEC_SSE_CFG_24E46784CB"; value = "180"; default = 1; };
            };
        };
        class comspec_sse_domex_stage {
            displayName = "$STR_COMSPEC_SSE_CFG_075D1DAA9C";
            tooltip = "$STR_COMSPEC_SSE_CFG_0D9D231C7A";
            property = "comspec_sse_domex_stage";
            control = "Combo";
            expression = "_this setVariable ['comspec_sse_domex_stage', _value, isServer];";
            defaultValue = "'non_identifie'";
            typeName = "STRING";
            condition = "objectSimulated - objectBrain";
            class values {
                class non_identifie { name = "$STR_COMSPEC_SSE_CFG_34DF732954"; value = "non_identifie"; default = 1; };
                class decouvert { name = "$STR_COMSPEC_SSE_CFG_B5D15CFC78"; value = "decouvert"; };
                class acces_en_cours { name = "$STR_COMSPEC_SSE_CFG_C34AAD85BE"; value = "acces_en_cours"; };
                class acces_etabli { name = "$STR_COMSPEC_SSE_CFG_3A745C983B"; value = "acces_etabli"; };
                class exploite { name = "$STR_COMSPEC_SSE_CFG_674BBBD83C"; value = "exploite"; };
            };
        };
        class comspec_sse_domex_p1_type {
            displayName = "$STR_COMSPEC_SSE_CFG_0FDAC35723";
            tooltip = "$STR_COMSPEC_SSE_CFG_5D73893C27";
            property = "comspec_sse_domex_p1_type";
            control = "Combo";
            expression = "_this setVariable ['comspec_sse_domex_p1_type', _value, isServer];";
            defaultValue = "''";
            typeName = "STRING";
            condition = "objectSimulated - objectBrain";
            class values {
                class none { name = "$STR_COMSPEC_SSE_CFG_106A1FD778"; value = ""; default = 1; };
                class message { name = "$STR_COMSPEC_SSE_CFG_68F4145FEE"; value = "message"; };
                class document { name = "$STR_COMSPEC_SSE_CFG_E214B8A299"; value = "document"; };
                class photo { name = "$STR_COMSPEC_SSE_CFG_E99CF9A2DE"; value = "photo"; };
                class contact { name = "$STR_COMSPEC_SSE_CFG_B37456C453"; value = "contact"; };
                class coordinate { name = "$STR_COMSPEC_SSE_CFG_6C68FEA85E"; value = "coordinate"; };
                class frequency { name = "$STR_COMSPEC_SSE_CFG_BAFBFBA73A"; value = "frequency"; };
                class schedule { name = "$STR_COMSPEC_SSE_CFG_DB0ADDB39F"; value = "schedule"; };
                class manifest { name = "$STR_COMSPEC_SSE_CFG_6806BC5723"; value = "manifest"; };
                class objective { name = "$STR_COMSPEC_SSE_CFG_47D1C260DC"; value = "objective"; };
            };
        };
        class comspec_sse_domex_p1_text {
            displayName = "$STR_COMSPEC_SSE_CFG_640F95F149";
            tooltip = "$STR_COMSPEC_SSE_CFG_A96C9B7E3A";
            property = "comspec_sse_domex_p1_text";
            control = "EditMulti5";
            expression = "_this setVariable ['comspec_sse_domex_p1_text', _value, isServer];";
            defaultValue = "''";
            typeName = "STRING";
            condition = "objectSimulated - objectBrain";
        };
        class comspec_sse_domex_p1_quality {
            displayName = "$STR_COMSPEC_SSE_CFG_E55A6761A0";
            tooltip = "$STR_COMSPEC_SSE_CFG_91AFA678C0";
            property = "comspec_sse_domex_p1_quality";
            control = "Combo";
            expression = "_this setVariable ['comspec_sse_domex_p1_quality', _value, isServer];";
            defaultValue = "'complet'";
            typeName = "STRING";
            condition = "objectSimulated - objectBrain";
            class values {
                class complet { name = "$STR_COMSPEC_SSE_CFG_26275EBD66"; value = "complet"; default = 1; };
                class fragment { name = "$STR_COMSPEC_SSE_CFG_5CFBE37779"; value = "fragment"; };
                class leurre_possible { name = "$STR_COMSPEC_SSE_CFG_A2556E2679"; value = "leurre_possible"; };
            };
        };
        class comspec_sse_domex_p1_channel {
            displayName = "$STR_COMSPEC_SSE_CFG_B6A1DA696E";
            tooltip = "$STR_COMSPEC_SSE_CFG_1F2AC7594A";
            property = "comspec_sse_domex_p1_channel";
            control = "Combo";
            expression = "_this setVariable ['comspec_sse_domex_p1_channel', _value, isServer];";
            defaultValue = "'physique'";
            typeName = "STRING";
            condition = "objectSimulated - objectBrain";
            class values {
                class physique { name = "$STR_COMSPEC_SSE_CFG_3B9E13ED84"; value = "physique"; default = 1; };
                class distant { name = "$STR_COMSPEC_SSE_CFG_D390F918C5"; value = "distant"; };
                class les_deux { name = "$STR_COMSPEC_SSE_CFG_35AFDEE848"; value = "les_deux"; };
            };
        };
        class comspec_sse_domex_p1_reveal {
            displayName = "$STR_COMSPEC_SSE_CFG_4BF6C06DD9";
            tooltip = "$STR_COMSPEC_SSE_CFG_41A38A621D";
            property = "comspec_sse_domex_p1_reveal";
            control = "Combo";
            expression = "_this setVariable ['comspec_sse_domex_p1_reveal', _value, isServer];";
            defaultValue = "'immediat'";
            typeName = "STRING";
            condition = "objectSimulated - objectBrain";
            class values {
                class immediat { name = "$STR_COMSPEC_SSE_CFG_97231AC7E7"; value = "immediat"; default = 1; };
                class delai { name = "$STR_COMSPEC_SSE_CFG_D599F5BE5C"; value = "delai"; };
                class acces_etabli { name = "$STR_COMSPEC_SSE_CFG_BEDC037381"; value = "acces_etabli"; };
            };
        };
        class comspec_sse_domex_p1_entities {
            displayName = "$STR_COMSPEC_SSE_CFG_808251D98E";
            tooltip = "$STR_COMSPEC_SSE_CFG_46DF178EF6";
            property = "comspec_sse_domex_p1_entities";
            control = "EditMulti3";
            expression = "_this setVariable ['comspec_sse_domex_p1_entities', _value, isServer];";
            defaultValue = "''";
            typeName = "STRING";
            condition = "objectSimulated - objectBrain";
        };
        class comspec_sse_domex_p2_type {
            displayName = "$STR_COMSPEC_SSE_CFG_B4AB955A65";
            tooltip = "$STR_COMSPEC_SSE_CFG_68BFBFB778";
            property = "comspec_sse_domex_p2_type";
            control = "Combo";
            expression = "_this setVariable ['comspec_sse_domex_p2_type', _value, isServer];";
            defaultValue = "''";
            typeName = "STRING";
            condition = "objectSimulated - objectBrain";
            class values {
                class none { name = "$STR_COMSPEC_SSE_CFG_106A1FD778"; value = ""; default = 1; };
                class message { name = "$STR_COMSPEC_SSE_CFG_68F4145FEE"; value = "message"; };
                class document { name = "$STR_COMSPEC_SSE_CFG_E214B8A299"; value = "document"; };
                class photo { name = "$STR_COMSPEC_SSE_CFG_E99CF9A2DE"; value = "photo"; };
                class contact { name = "$STR_COMSPEC_SSE_CFG_B37456C453"; value = "contact"; };
                class coordinate { name = "$STR_COMSPEC_SSE_CFG_6C68FEA85E"; value = "coordinate"; };
                class frequency { name = "$STR_COMSPEC_SSE_CFG_BAFBFBA73A"; value = "frequency"; };
                class schedule { name = "$STR_COMSPEC_SSE_CFG_DB0ADDB39F"; value = "schedule"; };
                class manifest { name = "$STR_COMSPEC_SSE_CFG_6806BC5723"; value = "manifest"; };
                class objective { name = "$STR_COMSPEC_SSE_CFG_47D1C260DC"; value = "objective"; };
            };
        };
        class comspec_sse_domex_p2_text {
            displayName = "$STR_COMSPEC_SSE_CFG_D8FBCFE837";
            tooltip = "$STR_COMSPEC_SSE_CFG_58AC927D81";
            property = "comspec_sse_domex_p2_text";
            control = "EditMulti5";
            expression = "_this setVariable ['comspec_sse_domex_p2_text', _value, isServer];";
            defaultValue = "''";
            typeName = "STRING";
            condition = "objectSimulated - objectBrain";
        };
        class comspec_sse_domex_p2_quality {
            displayName = "$STR_COMSPEC_SSE_CFG_A0995A97B3";
            tooltip = "$STR_COMSPEC_SSE_CFG_91AFA678C0";
            property = "comspec_sse_domex_p2_quality";
            control = "Combo";
            expression = "_this setVariable ['comspec_sse_domex_p2_quality', _value, isServer];";
            defaultValue = "'complet'";
            typeName = "STRING";
            condition = "objectSimulated - objectBrain";
            class values {
                class complet { name = "$STR_COMSPEC_SSE_CFG_26275EBD66"; value = "complet"; default = 1; };
                class fragment { name = "$STR_COMSPEC_SSE_CFG_5CFBE37779"; value = "fragment"; };
                class leurre_possible { name = "$STR_COMSPEC_SSE_CFG_A2556E2679"; value = "leurre_possible"; };
            };
        };
        class comspec_sse_domex_p2_channel {
            displayName = "$STR_COMSPEC_SSE_CFG_ED7E3F8C39";
            tooltip = "$STR_COMSPEC_SSE_CFG_1F2AC7594A";
            property = "comspec_sse_domex_p2_channel";
            control = "Combo";
            expression = "_this setVariable ['comspec_sse_domex_p2_channel', _value, isServer];";
            defaultValue = "'physique'";
            typeName = "STRING";
            condition = "objectSimulated - objectBrain";
            class values {
                class physique { name = "$STR_COMSPEC_SSE_CFG_3B9E13ED84"; value = "physique"; default = 1; };
                class distant { name = "$STR_COMSPEC_SSE_CFG_D390F918C5"; value = "distant"; };
                class les_deux { name = "$STR_COMSPEC_SSE_CFG_35AFDEE848"; value = "les_deux"; };
            };
        };
        class comspec_sse_domex_p2_reveal {
            displayName = "$STR_COMSPEC_SSE_CFG_F0124FF16F";
            tooltip = "$STR_COMSPEC_SSE_CFG_41A38A621D";
            property = "comspec_sse_domex_p2_reveal";
            control = "Combo";
            expression = "_this setVariable ['comspec_sse_domex_p2_reveal', _value, isServer];";
            defaultValue = "'immediat'";
            typeName = "STRING";
            condition = "objectSimulated - objectBrain";
            class values {
                class immediat { name = "$STR_COMSPEC_SSE_CFG_97231AC7E7"; value = "immediat"; default = 1; };
                class delai { name = "$STR_COMSPEC_SSE_CFG_D599F5BE5C"; value = "delai"; };
                class acces_etabli { name = "$STR_COMSPEC_SSE_CFG_BEDC037381"; value = "acces_etabli"; };
            };
        };
        class comspec_sse_domex_p2_entities {
            displayName = "$STR_COMSPEC_SSE_CFG_2526F9BC57";
            tooltip = "$STR_COMSPEC_SSE_CFG_FAFD121A5A";
            property = "comspec_sse_domex_p2_entities";
            control = "EditMulti3";
            expression = "_this setVariable ['comspec_sse_domex_p2_entities', _value, isServer];";
            defaultValue = "''";
            typeName = "STRING";
            condition = "objectSimulated - objectBrain";
        };
    };
};
