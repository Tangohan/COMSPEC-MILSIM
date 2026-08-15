params [["_baseFreq", 32]];

private _preset = call acre_main_fnc_fastHashCreate;
private _channels = [];

for "_i" from 0 to 15 do {
    _channels pushBack ([_i + 1, _baseFreq + (_i * 0.025)] call Iceman_fnc_mpu5_createChannel);
};

_preset setVariable ["channels", _channels];
_preset
