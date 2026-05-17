/**
 * TikTok Tracking - Captura e Persistência de ttclid
 * 
 * Este script captura o parâmetro ttclid da URL e salva
 * para uso posterior no rastreamento server-side.
 */

(function() {
    'use strict';
    
    // ========== CONFIGURAÇÃO ==========
    const TTCLID_COOKIE_NAME = 'ttclid';
    const TTCLID_STORAGE_KEY = 'tiktok_click_id';
    const TTCLID_EXPIRY_DAYS = 7; // TikTok usa janela de 7 dias
    
    /**
     * Captura ttclid da URL
     */
    function getTtclidFromUrl() {
        const urlParams = new URLSearchParams(window.location.search);
        return urlParams.get('ttclid');
    }
    
    /**
     * Salva ttclid em cookie
     */
    function saveTtclidToCookie(ttclid) {
        const expiryDate = new Date();
        expiryDate.setDate(expiryDate.getDate() + TTCLID_EXPIRY_DAYS);
        
        document.cookie = `${TTCLID_COOKIE_NAME}=${ttclid}; ` +
                         `expires=${expiryDate.toUTCString()}; ` +
                         `path=/; ` +
                         `SameSite=Lax`;
        
        console.log('✅ ttclid salvo em cookie:', ttclid);
    }
    
    /**
     * Salva ttclid em localStorage (backup)
     */
    function saveTtclidToStorage(ttclid) {
        try {
            const data = {
                ttclid: ttclid,
                timestamp: Date.now(),
                expiry: Date.now() + (TTCLID_EXPIRY_DAYS * 24 * 60 * 60 * 1000)
            };
            localStorage.setItem(TTCLID_STORAGE_KEY, JSON.stringify(data));
            console.log('✅ ttclid salvo em localStorage:', ttclid);
        } catch (e) {
            console.warn('⚠️ Erro ao salvar ttclid em localStorage:', e);
        }
    }
    
    /**
     * Recupera ttclid salvo
     */
    function getSavedTtclid() {
        // Tenta cookie primeiro
        const cookies = document.cookie.split(';');
        for (let cookie of cookies) {
            const [name, value] = cookie.trim().split('=');
            if (name === TTCLID_COOKIE_NAME) {
                return value;
            }
        }
        
        // Tenta localStorage como backup
        try {
            const stored = localStorage.getItem(TTCLID_STORAGE_KEY);
            if (stored) {
                const data = JSON.parse(stored);
                // Verifica se não expirou
                if (data.expiry > Date.now()) {
                    return data.ttclid;
                } else {
                    // Remove se expirou
                    localStorage.removeItem(TTCLID_STORAGE_KEY);
                }
            }
        } catch (e) {
            console.warn('⚠️ Erro ao ler ttclid do localStorage:', e);
        }
        
        return null;
    }
    
    /**
     * Captura e salva UTM parameters
     */
    function captureUtmParams() {
        const urlParams = new URLSearchParams(window.location.search);
        const utmParams = {};
        
        const utmKeys = [
            'utm_source',
            'utm_medium',
            'utm_campaign',
            'utm_content',
            'utm_term'
        ];
        
        utmKeys.forEach(key => {
            const value = urlParams.get(key);
            if (value) {
                utmParams[key] = value;
            }
        });
        
        if (Object.keys(utmParams).length > 0) {
            try {
                localStorage.setItem('utm_params', JSON.stringify(utmParams));
                console.log('✅ UTM params salvos:', utmParams);
            } catch (e) {
                console.warn('⚠️ Erro ao salvar UTM params:', e);
            }
        }
    }
    
    /**
     * Adiciona ttclid a todos os links internos
     */
    function propagateTtclid(ttclid) {
        if (!ttclid) return;
        
        // Adiciona a links de checkout/compra
        const links = document.querySelectorAll('a[href*="checkout"], a[href*="comprar"]');
        links.forEach(link => {
            try {
                const url = new URL(link.href, window.location.origin);
                if (!url.searchParams.has('ttclid')) {
                    url.searchParams.set('ttclid', ttclid);
                    link.href = url.toString();
                }
            } catch (e) {
                // Ignora erros de URL inválida
            }
        });
    }
    
    /**
     * Envia evento server-side
     */
    function sendServerSideEvent(eventData) {
        const ttclid = getSavedTtclid();
        
        if (!ttclid) {
            console.warn('⚠️ ttclid não encontrado. Evento server-side pode ter atribuição limitada.');
        }
        
        const payload = {
            event: eventData.event,
            event_id: eventData.event_id,
            timestamp: Math.floor(Date.now() / 1000),
            ttclid: ttclid,
            page_url: window.location.href,
            properties: eventData.properties || {}
        };
        
        // Só adiciona user se tiver email ou phone válidos
        if (eventData.user) {
            const hasEmail = eventData.user.email && eventData.user.email.trim() !== '';
            const hasPhone = eventData.user.phone && eventData.user.phone.trim() !== '';
            
            if (hasEmail || hasPhone) {
                payload.user = {};
                if (hasEmail) payload.user.email = eventData.user.email;
                if (hasPhone) payload.user.phone = eventData.user.phone;
            }
        }
        
        console.log('📤 Enviando evento server-side:', payload);
        console.log('📊 Payload completo:', JSON.stringify(payload, null, 2));
        
        // Envia via fetch (caminho relativo para funcionar em qualquer domínio)
        fetch('./tiktok-events-api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(payload)
        })
        .then(response => response.json())
        .then(data => {
            console.log('📥 Resposta:', data);
            if (data.success) {
                console.log('✅ Evento server-side enviado com sucesso!');
            } else {
                console.error('❌ Erro ao enviar evento server-side:', data.message || data.error);
                if (data.response) {
                    console.error('📋 Detalhes:', data.response);
                }
            }
        })
        .catch(error => {
            console.error('❌ Erro na requisição server-side:', error);
        });
    }
    
    /**
     * Inicialização
     */
    function init() {
        // Captura ttclid da URL
        const ttclidFromUrl = getTtclidFromUrl();
        
        if (ttclidFromUrl) {
            console.log('🎯 ttclid detectado na URL:', ttclidFromUrl);
            saveTtclidToCookie(ttclidFromUrl);
            saveTtclidToStorage(ttclidFromUrl);
            propagateTtclid(ttclidFromUrl);
        } else {
            // Verifica se já tem salvo
            const savedTtclid = getSavedTtclid();
            if (savedTtclid) {
                console.log('ℹ️ ttclid recuperado:', savedTtclid);
                propagateTtclid(savedTtclid);
            } else {
                console.log('ℹ️ Nenhum ttclid encontrado (tráfego orgânico ou direto)');
            }
        }
        
        // Captura UTM params
        captureUtmParams();
    }
    
    // Expõe funções globalmente
    window.TikTokTracking = {
        getTtclid: getSavedTtclid,
        sendServerSideEvent: sendServerSideEvent,
        init: init
    };
    
    // Inicializa automaticamente
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
    
})();
