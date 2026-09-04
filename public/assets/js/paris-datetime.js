(function (global) {
  'use strict';
  var TIME_ZONE = 'Europe/Paris';
  function utcDate(value) {
    if (value instanceof Date) return value;
    var raw = String(value == null ? '' : value).trim();
    if (!raw) return null;
    if (!/[zZ]|[+-]\d\d(?::?\d\d)?$/.test(raw)) raw = raw.replace(' ', 'T') + 'Z';
    var date = new Date(raw);
    return isNaN(date.getTime()) ? null : date;
  }
  function format(value, options, fallback) {
    var date = utcDate(value);
    if (!date) return fallback == null ? '—' : fallback;
    var config = Object.assign({ timeZone: TIME_ZONE, day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false }, options || {});
    return new Intl.DateTimeFormat('fr-FR', config).format(date);
  }
  global.AthenaDateTime = { TIME_ZONE: TIME_ZONE, parseUtc: utcDate, format: format };
})(window);
