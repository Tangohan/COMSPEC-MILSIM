/**
 * MapUI — shell interface C2 (top bar, tool rail, panneaux flottants).
 */
export class MapUI {
  /**
   * @param {HTMLElement} root — .tac-c2-shell
   */
  constructor(root) {
    this.root = root;
    this.tool = 'select';
    this._bindToolRail();
    this._bindClock();
  }

  _bindToolRail() {
    const self = this;
    if (!this.root) return;
    this.root.querySelectorAll('[data-tool]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        self.root.querySelectorAll('[data-tool]').forEach(function (b) {
          b.classList.remove('is-active');
        });
        btn.classList.add('is-active');
        self.tool = btn.getAttribute('data-tool');
        try {
          window.dispatchEvent(new CustomEvent('atak:tool-changed', { detail: self.tool }));
        } catch (e) { /* ignore */ }
      });
    });
  }

  _bindClock() {
    const el = this.root && this.root.querySelector('[data-tac-clock]');
    if (!el) return;
    function tick() {
      const d = new Date();
      el.textContent = d.toISOString().slice(11, 19) + ' Z';
    }
    tick();
    setInterval(tick, 1000);
  }

  setNetworkStatus(status) {
    const el = this.root && this.root.querySelector('[data-tac-network]');
    if (!el) return;
    el.textContent = status || 'Opérationnel';
    el.classList.toggle('tac-net--ok', status === 'Opérationnel' || status === 'ONLINE');
    el.classList.toggle('tac-net--warn', status === 'Dégradé' || status === 'DEGRADED');
  }
}

if (typeof window !== 'undefined') {
  window.MapUI = MapUI;
}
