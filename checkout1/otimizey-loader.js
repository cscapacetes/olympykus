/**
 * OTIMIZEY TRACKING SCRIPT LOADER
 * Carrega dinamicamente o script de tracking da Otimizey
 */
(function() {
    // Encontra o script atual através do atributo src
    const currentScript = document.currentScript || (function() {
        const scripts = document.getElementsByTagName('script');
        return scripts[scripts.length - 1];
    })();
    
    // Pega o caminho do script
    let scriptPath = currentScript.src;
    let configPath;
    
    if (scriptPath) {
        // Remove o nome do arquivo para pegar só o diretório
        const scriptDir = scriptPath.substring(0, scriptPath.lastIndexOf('/'));
        configPath = `${scriptDir}/otimizey-config.json`;
    } else {
        // Fallback: tenta na raiz
        configPath = '/checkout/otimizey-config.json';
    }
    
    // Carregar configuração com cache buster
    const cacheBuster = new Date().getTime();
    fetch(`${configPath}?v=${cacheBuster}`)
        .then(response => response.json())
        .then(config => {
            if (config.tracking_script_id && config.tracking_script_id.trim() !== '') {
                // Criar e injetar o script
                const script = document.createElement('script');
                script.src = `https://api.otimizey.com.br/api/tracking/script/${config.tracking_script_id}.js`;
                script.async = true;
                
                // Adicionar ao head
                document.head.appendChild(script);
                
                console.log('✅ Otimizey tracking script carregado:', config.tracking_script_id);
            } else {
                console.warn('⚠️ Otimizey tracking script ID não configurado');
            }
        })
        .catch(error => {
            console.error('❌ Erro ao carregar configuração Otimizey:', error);
        });
})();
