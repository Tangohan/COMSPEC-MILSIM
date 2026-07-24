/* COMSPEC ATAK - Effets roleplay (dégradation liaison, dysfonctionnements) */

(function () {
  'use strict';

  const RoleplayEffects = {
    config: {
      enabled: false,
      packetLoss: 0,
      latencyRange: null,
      disconnectRisk: false,
      sensorEnabled: false,
    },

    /**
     * Applique un effet visuel de glitch sur un élément.
     */
    applyGlitchEffect(element, duration = 200) {
      if (!element || !this.config.enabled) return;
      
      element.classList.add('atak-glitch');
      setTimeout(() => {
        element.classList.remove('atak-glitch');
      }, duration);
    },

    /**
     * Applique un effet de parasites sur la carte.
     */
    applyMapInterference() {
      if (!this.config.enabled) return;
      
      const mapContainer = document.querySelector('.leaflet-container');
      if (!mapContainer) return;
      
      mapContainer.classList.add('atak-interference');
      setTimeout(() => {
        mapContainer.classList.remove('atak-interference');
      }, 500);
    },

    /**
     * Affiche un message d'erreur de liaison contextualisé.
     */
    showConnectionError(message, duration = 3000) {
      const existing = document.getElementById('atak-roleplay-error');
      if (existing) {
        existing.remove();
      }

      const div = document.createElement('div');
      div.id = 'atak-roleplay-error';
      div.className = 'atak-roleplay-error';
      div.innerHTML = `
        <div class="atak-roleplay-error-icon">⚠</div>
        <div class="atak-roleplay-error-text">${message}</div>
      `;
      
      document.body.appendChild(div);
      
      setTimeout(() => {
        div.classList.add('atak-roleplay-error--visible');
      }, 50);
      
      setTimeout(() => {
        div.classList.remove('atak-roleplay-error--visible');
        setTimeout(() => div.remove(), 300);
      }, duration);
    },

    /**
     * Met à jour les indicateurs de qualité réseau avec les effets roleplay.
     */
    updateNetworkQualityIndicators(quality, latency, packetLoss) {
      if (!this.config.enabled) return;
      
      const qualityEl = document.getElementById('atak-metric-quality-value');
      const latencyEl = document.getElementById('atak-metric-latency-value');
      const lossEl = document.getElementById('atak-metric-loss-value');
      
      if (qualityEl && this.config.disconnectRisk) {
        // Effet clignotant si risque de déconnexion
        qualityEl.classList.add('atak-blink-warn');
      }
      
      if (lossEl && this.config.packetLoss > 0) {
        lossEl.textContent = this.config.packetLoss.toFixed(2) + ' %';
        lossEl.classList.add('atak-metric-warn');
      }
    },

    /**
     * Applique des effets de dégradation sur les données d'unités.
     */
    degradeUnitData(units) {
      if (!this.config.enabled || !Array.isArray(units)) return units;
      
      return units.map(unit => {
        // Simuler occasionnellement des positions "sautées"
        if (this.config.packetLoss > 0 && Math.random() * 100 < this.config.packetLoss) {
          return { ...unit, _roleplay_degraded: true };
        }
        
        // Appliquer des messages de statut capteur
        if (this.config.sensorEnabled && unit.extra) {
          const extra = typeof unit.extra === 'string' ? JSON.parse(unit.extra) : unit.extra;
          if (extra.sensor_status && extra.sensor_message) {
            unit._sensor_warning = extra.sensor_message;
          }
        }
        
        return unit;
      });
    },

    /**
     * Affiche un badge d'avertissement capteur sur une unité.
     */
    showSensorWarning(unitElement, message) {
      if (!unitElement || !message) return;
      
      const badge = document.createElement('span');
      badge.className = 'atak-sensor-warning';
      badge.textContent = '⚠ ' + message;
      badge.title = message;
      
      unitElement.appendChild(badge);
    },

    /**
     * Initialise les statistiques roleplay depuis l'API.
     */
    async fetchRoleplayStats() {
      try {
        const response = await fetch('/api/atak/roleplay-stats', {
          credentials: 'include',
          cache: 'no-store',
        });
        
        if (response.ok) {
          const data = await response.json();
          this.config.enabled = data.network?.enabled || data.sensor?.enabled || false;
          this.config.packetLoss = data.network?.packet_loss || 0;
          this.config.latencyRange = data.network?.latency_range || null;
          this.config.disconnectRisk = data.network?.disconnect_risk || false;
          this.config.sensorEnabled = data.sensor?.enabled || false;
        }
      } catch (err) {
        console.warn('[atak-roleplay] Could not fetch roleplay stats:', err);
      }
    },

    /**
     * Intercepte les erreurs API pour afficher des messages contextualisés.
     */
    handleApiError(error, xhr) {
      if (!this.config.enabled) return false;
      
      if (xhr && xhr.status === 503) {
        try {
          const data = JSON.parse(xhr.responseText);
          if (data.error === 'connection_lost') {
            this.showConnectionError(data.message || 'Liaison temporairement indisponible');
            this.applyMapInterference();
            return true;
          } else if (data.error === 'packet_lost') {
            this.applyGlitchEffect(document.querySelector('.atak-os-strip'), 100);
            return true;
          }
        } catch (e) {
          // Ignore parse errors
        }
      }
      
      return false;
    },

    /**
     * Démarre les effets roleplay.
     */
    init() {
      // Récupérer la config au démarrage
      this.fetchRoleplayStats();
      
      // Rafraîchir la config toutes les 5 minutes
      setInterval(() => this.fetchRoleplayStats(), 5 * 60 * 1000);
      
      console.log('[atak-roleplay] Effets roleplay initialisés');
    },
  };

  // Export global
  window.AtakRoleplayEffects = RoleplayEffects;

  // Auto-initialisation
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => RoleplayEffects.init());
  } else {
    RoleplayEffects.init();
  }
})();
