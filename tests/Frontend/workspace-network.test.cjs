const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const root = path.join(__dirname, '../..');

test('the shared help script parses for authenticated workspaces', () => {
  const template = fs.readFileSync(path.join(root, 'views/partials/portal_help_modal.php'), 'utf8');
  const script = template.match(/<script>([\s\S]*?)<\/script>/)[1]
    .replace(/<\?=[\s\S]*?\?>/g, JSON.stringify('/fixture'));
  assert.doesNotThrow(() => new vm.Script(script));
});

function worker() {
  const handlers = {};
  const context = vm.createContext({
    URL, Response,
    self: {location: {origin: 'https://athena.example'}, addEventListener: (name, fn) => { handlers[name] = fn; }},
  });
  vm.runInContext(fs.readFileSync(path.join(root, 'public/sw.js'), 'utf8'), context);
  return {context, handlers};
}
const request = (suffix, headers = {}) => ({url: 'https://athena.example' + suffix, method: 'GET', mode: 'cors', destination: '', headers: new Headers(headers)});

test('private workspace fragments bypass offline responses with and without /public', () => {
  const {context, handlers} = worker();
  for (const req of [request('/public/back-office/ressources/effectifs/fonctions?vue=categories'), request('/back-office/ressources/effectifs/fonctions'), request('/another-page', {'X-Effectifs-Fragment': 'jobs'}), request('/another-page', {'X-Grade-Fragment': '1'})]) {
    assert.equal(context.shouldBypassServiceWorker(req), true);
    let intercepted = false;
    handlers.fetch({request: req, respondWith() { intercepted = true; }});
    assert.equal(intercepted, false);
  }
});

test('HTML fragment requests are also recognised by the existing navigation bypass', () => {
  const {context} = worker();
  assert.equal(context.isNavigationRequest(request('/public/back-office/ressources/effectifs/fonctions', {Accept: 'text/html'})), true);
});

test('static styles and map tiles retain their offline cache policy', () => {
  const {context} = worker();
  assert.equal(context.shouldBypassServiceWorker(request('/public/assets/css/design-system.css')), false);
  assert.equal(context.isMapTileRequest(request('/public/api/atak/tiles/1/2/3')), true);
  assert.equal(context.shouldBypassServiceWorker(request('/public/api/atak/tiles/1/2/3')), false);
});
