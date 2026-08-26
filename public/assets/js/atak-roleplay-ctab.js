/* COMSPEC ATAK - Effets roleplay pour tablette cTab ingame */

(function () {
  'use strict';

  function atakApiUrl(path) {
    var pathNorm = path.charAt(0) === '/' ? path : '/' + path;
    var base = String(window.ATAK_API_BASE || window.APP_BASE_URL || '').replace(/\/$/, '');
    if (!base && typeof window.location !== 'undefined') {
      var p = window.location.pathname || '';
      var atakIdx = p.indexOf('/atak');
      base = (window.location.origin || '') + (atakIdx >= 0 ? p.substring(0, atakIdx) : p.replace(/\/[^/]*$/, ''));
    }
    return base + pathNorm;
  }

  function formatReconnectLabel(title, message) {
    var raw = [message, title].filter(Boolean).join(' ');
    var match = String(raw).match(/(\d+)\s*s\b/i);
    if (match) {
      return 'Reconnexion dans ' + match[1] + ' s';
    }
    return 'Reconnexion en cours…';
  }

  // Exposition globale pour injection depuis Arma
  window.AtakRoleplayEffects = {
    /**
     * Overlay unique « Liaison perdue » (panneau C2, un seul compte à rebours).
     */
    showConnectionError(title, message) {
      const overlay = document.getElementById('atak-connection-lost')
        || document.querySelector('.atak-disconnect-overlay');
      if (!overlay) return;

      const timerText = formatReconnectLabel(title, message);
      const titleEl = overlay.querySelector('.atak-connection-lost__title');
      const timerEl = overlay.querySelector('#atak-connection-lost-msg')
        || overlay.querySelector('.atak-connection-lost__timer');
      if (titleEl) titleEl.textContent = 'Liaison perdue';
      if (timerEl) timerEl.textContent = timerText;
      if (!titleEl && !timerEl) {
        overlay.innerHTML = '<div class="atak-connection-lost__panel">'
          + '<p class="atak-connection-lost__title">Liaison perdue</p>'
          + '<p class="atak-connection-lost__timer" id="atak-connection-lost-msg">' + timerText + '</p>'
          + '</div>';
      }
      overlay.classList.add('show');
      overlay.style.display = 'flex';
    },

    /**
     * Cache l'overlay de déconnexion
     */
    hideConnectionError() {
      const overlay = document.getElementById('atak-connection-lost')
        || document.querySelector('.atak-disconnect-overlay');
      if (!overlay) return;
      overlay.classList.remove('show');
      overlay.style.display = 'none';
    },

    /**
     * Applique effet de glitch/corruption sur la carte
     */
    applyGlitchEffect(intensity = 0.3) {
      const map = document.querySelector('.leaflet-container') || document.getElementById('map');
      if (!map) return;
      
      map.classList.add('atak-glitch');
      map.style.setProperty('--glitch-intensity', intensity);
      
      // Effet glitch court
      setTimeout(() => {
        map.classList.remove('atak-glitch');
      }, 150 + Math.random() * 150);
    },

    /**
     * Applique parasites/interférences sur la carte ATAK
     */
    applyMapInterference(intensity = 0.2) {
      const map = document.querySelector('.leaflet-container') || document.getElementById('map');
      if (!map) return;
      
      // Ajouter overlay de parasites
      let interference = map.querySelector('.atak-interference-overlay');
      if (!interference) {
        interference = document.createElement('div');
        interference.className = 'atak-interference-overlay';
        map.appendChild(interference);
      }
      
      interference.style.opacity = intensity;
      interference.style.display = 'block';
      
      // Animation de scan lines
      interference.style.animation = 'atak-scanlines 0.3s linear infinite';
    },

    /**
     * Retire les interférences
     */
    removeMapInterference() {
      const interference = document.querySelector('.atak-interference-overlay');
      if (interference) {
        interference.style.display = 'none';
      }
    },

    /**
     * Affiche avertissement de zone roleplay
     */
    showZoneWarning(zoneName, intensity) {
      let warning = document.querySelector('.atak-zone-warning');
      
      if (!warning) {
        warning = document.createElement('div');
        warning.className = 'atak-zone-warning';
        document.body.appendChild(warning);
      }
      
      const color = intensity > 70 ? '#ff4444' : (intensity > 40 ? '#ffaa00' : '#ffff00');
      warning.innerHTML = `
        <div class="atak-zone-icon" style="color: ${color}">📡</div>
        <div class="atak-zone-text">
          <strong>${zoneName}</strong><br>
          <small>Intensité: ${intensity}%</small>
        </div>
      `;
      warning.style.display = 'flex';
    },

    /**
     * Cache l'avertissement de zone
     */
    hideZoneWarning() {
      const warning = document.querySelector('.atak-zone-warning');
      if (warning) {
        warning.style.display = 'none';
      }
    },

    /**
     * Met à jour l'indicateur de qualité réseau dans l'OS Strip
     */
    updateNetworkQualityIndicators(quality, latency, packetLoss) {
      const lossEl = document.getElementById('atak-metric-loss-value');
      if (lossEl && packetLoss != null) {
        lossEl.textContent = packetLoss.toFixed(1) + ' %';
        
        // Couleur selon intensité
        if (packetLoss > 10) {
          lossEl.style.color = '#ff4444';
        } else if (packetLoss > 5) {
          lossEl.style.color = '#ffaa00';
        } else {
          lossEl.style.color = '#ffff00';
        }
      }
    },

    /**
     * Fait "sauter" un marqueur (effet de packet loss)
     */
    jumpMarker(marker) {
      if (!marker || !marker._icon) return;
      
      const icon = marker._icon;
      const originalPos = {
        x: parseInt(icon.style.marginLeft) || 0,
        y: parseInt(icon.style.marginTop) || 0
      };
      
      // Déplacement aléatoire
      const offsetX = (Math.random() - 0.5) * 30;
      const offsetY = (Math.random() - 0.5) * 30;
      
      icon.style.transition = 'none';
      icon.style.marginLeft = (originalPos.x + offsetX) + 'px';
      icon.style.marginTop = (originalPos.y + offsetY) + 'px';
      
      // Retour à la position
      setTimeout(() => {
        icon.style.transition = 'margin 0.3s ease-out';
        icon.style.marginLeft = originalPos.x + 'px';
        icon.style.marginTop = originalPos.y + 'px';
      }, 100);
    },

    /**
     * Applique effet de "signal faible" sur tous les marqueurs
     */
    degradeAllMarkers(intensity) {
      if (!window.map) return;
      
      window.map.eachLayer(layer => {
        if (layer._icon) {
          layer._icon.style.opacity = 1 - (intensity * 0.5);
          
          // Effet de tremblement si forte interférence
          if (intensity > 0.5 && Math.random() < 0.3) {
            this.jumpMarker(layer);
          }
        }
      });
    },

    /**
     * Applique effet de corruption de données sur l'UI
     */
    corruptUI(intensity) {
      const elements = document.querySelectorAll('.unit-info, .marker-label, .unit-card');
      elements.forEach(el => {
        if (Math.random() < intensity) {
          el.classList.add('atak-corrupted');
          
          setTimeout(() => {
            el.classList.remove('atak-corrupted');
          }, 500);
        }
      });
    }
  };

  // Affiche écran cassé / éteint
  window.AtakRoleplayEffects.showBrokenScreen = function(type) {
    let overlay = document.querySelector('.atak-broken-screen');
    
    if (!overlay) {
      overlay = document.createElement('div');
      overlay.className = 'atak-broken-screen';
      document.body.appendChild(overlay);
    }
    
    if (type === 'destroyed') {
      // Écran détruit (fissures)
      overlay.innerHTML = `
        <div class="atak-broken-content">
          <div class="atak-screen-cracks"></div>
          <div class="atak-broken-text">
            <h2>ÉCRAN ENDOMMAGÉ</h2>
            <p>Connexion maintenue</p>
            <small>Toolkit ACE requis pour réparer</small>
          </div>
        </div>
      `;
      overlay.classList.add('atak-screen-destroyed');
    } else if (type === 'powered_off') {
      // ATAK éteint
      overlay.innerHTML = `
        <div class="atak-broken-content">
          <div class="atak-broken-text">
            <h2>ATAK ÉTEINT</h2>
            <p>Appuyez pour rallumer</p>
          </div>
        </div>
      `;
      overlay.classList.remove('atak-screen-destroyed');
      overlay.onclick = () => {
        // Tenter de rallumer
        window.AtakRoleplayEffects.hideBrokenScreen();
      };
    }
    
    overlay.style.display = 'flex';
  };
  
  window.AtakRoleplayEffects.hideBrokenScreen = function() {
    const overlay = document.querySelector('.atak-broken-screen');
    if (overlay) {
      overlay.style.display = 'none';
    }
  };

  // Auto-init : polling de l'état roleplay depuis Arma
  setInterval(() => {
    // L'état est injecté par fn_injectRoleplayEffectsInBrowser
    // On peut aussi faire un fetch vers l'API
    fetch(atakApiUrl('/api/atak/roleplay-stats'), {
      credentials: 'include',
      cache: 'no-store'
    })
    .then(r => r.json())
    .then(data => {
      if (data.measured_packet_loss) {
        window.AtakRoleplayEffects.updateNetworkQualityIndicators(
          null,
          null,
          data.measured_packet_loss.packet_loss_percent
        );
        
        // Appliquer effets selon le packet loss
        const loss = data.measured_packet_loss.packet_loss_percent;
        if (loss > 10) {
          window.AtakRoleplayEffects.applyGlitchEffect(0.3);
          window.AtakRoleplayEffects.applyMapInterference(0.4);
          window.AtakRoleplayEffects.degradeAllMarkers(0.6);
        } else if (loss > 5) {
          window.AtakRoleplayEffects.applyMapInterference(0.2);
          window.AtakRoleplayEffects.degradeAllMarkers(0.3);
        }
      }
    })
    .catch(() => {
      // Ignorer erreurs silencieusement
    });
  }, 2000); // Toutes les 2 secondes

  console.log('[COMSPEC] Effets roleplay ATAK/cTab chargés');

})();
