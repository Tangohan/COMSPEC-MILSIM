/**
 * Script de diagnostic ATAK Overwatch Beta
 * 
 * Ce script teste :
 * 1. Le formulaire de chat et ses éléments
 * 2. Les données ATAK disponibles dans l'API
 * 3. Les champs affichés vs champs disponibles
 */

(function() {
  'use strict';

  console.log('='.repeat(80));
  console.log('DIAGNOSTIC ATAK OVERWATCH BETA');
  console.log('='.repeat(80));

  // ============================================================================
  // TEST 1: FORMULAIRE DE CHAT
  // ============================================================================
  
  function testChatForm() {
    console.log('\n--- TEST 1: FORMULAIRE DE CHAT ---');
    
    const form = document.getElementById('ow-chat-form');
    const input = document.getElementById('ow-chat-input');
    const submitBtn = form ? form.querySelector('button[type="submit"]') : null;
    
    console.log('Form #ow-chat-form:', form);
    console.log('  - Existe:', !!form);
    console.log('  - Visible:', form && form.offsetParent !== null);
    console.log('  - Disabled:', form && form.hasAttribute('disabled'));
    
    console.log('Input #ow-chat-input:', input);
    console.log('  - Existe:', !!input);
    console.log('  - Visible:', input && input.offsetParent !== null);
    console.log('  - Disabled:', input && input.disabled);
    console.log('  - ReadOnly:', input && input.readOnly);
    console.log('  - Value:', input ? input.value : 'N/A');
    console.log('  - Placeholder:', input ? input.placeholder : 'N/A');
    
    console.log('Bouton submit:', submitBtn);
    console.log('  - Existe:', !!submitBtn);
    console.log('  - Disabled:', submitBtn && submitBtn.disabled);
    
    // Vérifier les variables globales
    console.log('\nVariables globales:');
    console.log('  - window.activeChannel:', window.activeChannel || 'NON DÉFINI');
    console.log('  - window.authorName:', window.authorName || 'NON DÉFINI');
    console.log('  - window.ATAK_USER:', window.ATAK_USER);
    console.log('  - window.ATAK_API_BASE:', window.ATAK_API_BASE);
    
    // Test d'écriture dans l'input
    if (input && !input.disabled && !input.readOnly) {
      console.log('\n✅ Input accessible pour l\'écriture');
      try {
        input.value = 'TEST';
        console.log('✅ Écriture test OK, valeur:', input.value);
        input.value = '';
      } catch (e) {
        console.error('❌ Erreur lors de l\'écriture:', e);
      }
    } else {
      console.error('❌ Input inaccessible pour l\'écriture');
    }
    
    // Vérifier event listeners
    const listeners = getEventListeners ? getEventListeners(form) : null;
    console.log('\nEvent listeners sur le form:', listeners);
    
    return {
      formExists: !!form,
      inputExists: !!input,
      inputAccessible: input && !input.disabled && !input.readOnly
    };
  }

  // ============================================================================
  // TEST 2: API ET DONNÉES ATAK
  // ============================================================================
  
  function testAtakAPI() {
    console.log('\n--- TEST 2: API ET DONNÉES ATAK ---');
    
    const apiBase = window.ATAK_API_BASE || '';
    const mapId = window.ATAK_DEFAULT_MAP_ID || 1;
    const token = window.ATAK_TOKEN || window.ATAK_CSRF_TOKEN || '';
    
    console.log('Configuration API:');
    console.log('  - Base URL:', apiBase);
    console.log('  - Map ID:', mapId);
    console.log('  - Token:', token ? token.substring(0, 10) + '...' : 'NON DÉFINI');
    
    // Test API units
    const unitsUrl = apiBase + '/api/atak/units?mapId=' + mapId;
    console.log('\nTest GET', unitsUrl);
    
    fetch(unitsUrl, {
      headers: token ? { 'X-CSRF-Token': token } : {},
      credentials: 'include'
    })
    .then(response => {
      console.log('Réponse API units:');
      console.log('  - Status:', response.status, response.statusText);
      console.log('  - OK:', response.ok);
      return response.json();
    })
    .then(data => {
      console.log('  - Données reçues:', data);
      
      const units = data.units || data.rows || [];
      console.log('  - Nombre d\'unités:', units.length);
      
      if (units.length > 0) {
        console.log('\n📋 STRUCTURE D\'UNE UNITÉ (premier exemple):');
        const unit = units[0];
        console.log(JSON.stringify(unit, null, 2));
        
        console.log('\n🔍 TOUS LES CHAMPS DISPONIBLES:');
        Object.keys(unit).sort().forEach(key => {
          const value = unit[key];
          const type = typeof value;
          const preview = type === 'object' ? JSON.stringify(value) : String(value);
          const display = preview.length > 50 ? preview.substring(0, 50) + '...' : preview;
          console.log(`  - ${key} (${type}): ${display}`);
        });
      } else {
        console.warn('⚠️ Aucune unité trouvée dans la réponse');
      }
    })
    .catch(error => {
      console.error('❌ Erreur API units:', error);
    });
    
    // Test API chat
    const chatUrl = apiBase + '/api/chat?mapId=' + mapId + '&channel=general&limit=5';
    console.log('\nTest GET', chatUrl);
    
    fetch(chatUrl, {
      headers: token ? { 'X-CSRF-Token': token } : {},
      credentials: 'include'
    })
    .then(response => {
      console.log('Réponse API chat:');
      console.log('  - Status:', response.status, response.statusText);
      console.log('  - OK:', response.ok);
      return response.json();
    })
    .then(data => {
      console.log('  - Données reçues:', data);
      const messages = data.messages || data.rows || [];
      console.log('  - Nombre de messages:', messages.length);
      if (messages.length > 0) {
        console.log('  - Exemple message:', messages[0]);
      }
    })
    .catch(error => {
      console.error('❌ Erreur API chat:', error);
    });
  }

  // ============================================================================
  // TEST 3: PANNEAU DÉTAILLÉ CONTACT
  // ============================================================================
  
  function testContactPanel() {
    console.log('\n--- TEST 3: PANNEAU DÉTAILLÉ CONTACT ---');
    
    console.log('window.OverwatchV3:', window.OverwatchV3);
    console.log('  - Existe:', !!window.OverwatchV3);
    
    if (window.OverwatchV3) {
      console.log('  - showDetailedContactPanel:', typeof window.OverwatchV3.showDetailedContactPanel);
      
      // Créer un contact de test
      const testUnit = {
        uid: 'TEST-001',
        callsign: 'ALPHA-1',
        status: 'online',
        lat: 48.8566,
        lng: 2.3522,
        alt: 100,
        heading: 90,
        speed: 5.5,
        group: 'Alpha Squad',
        role: 'Team Leader',
        side: 'friendly',
        updated_at: new Date().toISOString(),
        // Données téléphone potentielles
        battery: 85,
        signal_strength: -70,
        gps_accuracy: 5,
        device_id: 'DEVICE-TEST-001',
        atak_version: '4.10.0',
        screen_on: true
      };
      
      console.log('\n🧪 Test avec un contact fictif:');
      try {
        const html = window.OverwatchV3.showDetailedContactPanel(testUnit);
        console.log('✅ Génération HTML OK');
        console.log('Longueur HTML:', html.length, 'caractères');
        
        // Vérifier quels champs sont affichés
        console.log('\n🔍 Champs affichés dans le HTML:');
        const displayedFields = [];
        Object.keys(testUnit).forEach(key => {
          const pattern = new RegExp(key.replace(/_/g, '[\\s_-]'), 'i');
          if (pattern.test(html)) {
            displayedFields.push(key);
          }
        });
        console.log('  - Affichés:', displayedFields);
        
        const notDisplayed = Object.keys(testUnit).filter(k => !displayedFields.includes(k));
        if (notDisplayed.length > 0) {
          console.warn('  - NON affichés:', notDisplayed);
        }
      } catch (e) {
        console.error('❌ Erreur génération panneau:', e);
      }
    } else {
      console.error('❌ window.OverwatchV3 non disponible');
    }
  }

  // ============================================================================
  // EXÉCUTION
  // ============================================================================
  
  // Attendre que le DOM soit prêt
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() {
      setTimeout(function() {
        testChatForm();
        testAtakAPI();
        testContactPanel();
        console.log('\n' + '='.repeat(80));
        console.log('FIN DU DIAGNOSTIC');
        console.log('='.repeat(80));
      }, 1000);
    });
  } else {
    setTimeout(function() {
      testChatForm();
      testAtakAPI();
      testContactPanel();
      console.log('\n' + '='.repeat(80));
      console.log('FIN DU DIAGNOSTIC');
      console.log('='.repeat(80));
    }, 1000);
  }

})();
