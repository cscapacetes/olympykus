
function togglePurchaseSummary() {
    const summaryBody = document.getElementById('purchase-summary__body');
    const collapseToggles = document.querySelectorAll('.collapse-toggle');
    
    if (summaryBody) {
        const isVisible = summaryBody.style.display !== 'none' && summaryBody.style.display !== '';
        
        if (isVisible) {
            // Minimizar - esconder o corpo do resumo com animação
            summaryBody.style.maxHeight = summaryBody.scrollHeight + 'px';
            setTimeout(() => {
                summaryBody.style.maxHeight = '0';
                summaryBody.style.opacity = '0';
            }, 10);
            
            setTimeout(() => {
                summaryBody.style.display = 'none';
            }, 300);
            
            // Mostrar elementos collapse-toggle
            collapseToggles.forEach(element => {
                element.style.display = 'block';
            });
        } else {
            // Expandir - mostrar o corpo do resumo com animação
            summaryBody.style.display = 'block';
            summaryBody.style.maxHeight = '0';
            summaryBody.style.opacity = '0';
            
            setTimeout(() => {
                summaryBody.style.maxHeight = summaryBody.scrollHeight + 'px';
                summaryBody.style.opacity = '1';
            }, 10);
            
            setTimeout(() => {
                summaryBody.style.maxHeight = 'none';
            }, 300);
            
            // Esconder elementos collapse-toggle
            collapseToggles.forEach(element => {
                element.style.display = 'none';
            });
        }
    }
}

// 🌐 VARIÁVEIS GLOBAIS (fora do DOMContentLoaded para serem acessíveis em todo o código)
let precoUnitario = null; // Valor padrão, será atualizado via API
let nomeProduto = null; // Nome padrão, será atualizado via API

// 💰 Função para formatar valores monetários com separador de milhares
function formatarValorBrasileiro(valor) {
    // Converter para número se for string
    const numero = typeof valor === 'string' ? parseFloat(valor.replace(/\./g, '').replace(',', '.')) : valor;
    
    // Formatar com 2 casas decimais
    const valorFormatado = numero.toFixed(2);
    
    // Separar parte inteira e decimal
    const [parteInteira, parteDecimal] = valorFormatado.split('.');
    
    // Adicionar separador de milhares
    const parteInteiraFormatada = parteInteira.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    
    // Retornar no formato brasileiro (1.234,56)
    return `${parteInteiraFormatada},${parteDecimal}`;
}

document.addEventListener('DOMContentLoaded', function() {
    console.log('Script iniciado');
    
    // Carregar configurações do produto do admin
    carregarConfiguracoesProduto();
    
    // Inicializar estado do resumo (expandido por padrão em todas as telas)
    const summaryBody = document.getElementById('purchase-summary__body');
    const collapseToggles = document.querySelectorAll('.collapse-toggle');
    const arrowIcon = document.querySelector('.arrow_down__icon');
    
    // Função para carregar configurações do produto do admin
    async function carregarConfiguracoesProduto() {
        try {
            console.log('🔧 Carregando configurações do produto...');
            
            // Carregar diretamente do arquivo JSON
            const response = await fetch('./checkout-config.json?v=' + Date.now());
            
            if (!response.ok) {
                throw new Error('Arquivo de configuração não encontrado');
            }
            
            const text = await response.text();
            console.log('📄 Texto bruto do arquivo:', text);
            
            const data = JSON.parse(text);
            console.log('📦 Dados carregados do JSON:', data);
            console.log('📢 TOPBAR DATA:', data.topbar);
            
            if (data.product_price && data.product_name) {
                // Atualizar variáveis globais
                // Converter formato brasileiro (1.234,56) para float
                const priceString = data.product_price.toString().replace(/\./g, '').replace(',', '.');
                precoUnitario = parseFloat(priceString);
                nomeProduto = data.product_name;
                
                console.log('✅ Configurações aplicadas:', {
                    preco: precoUnitario,
                    precoOriginal: data.product_price,
                    nome: nomeProduto,
                    isDigital: data.is_digital || false
                });
                
                // Verificar se é produto digital e ajustar o checkout
                if (data.is_digital === true) {
                    console.log('📱 Produto digital detectado - ajustando checkout');
                    ajustarCheckoutParaDigital();
                }
                
                // Controlar visibilidade dos depoimentos
                if (data.depoimentos_enabled !== undefined) {
                    const depoimentosSections = document.querySelectorAll('.depoimentos-section');
                    if (data.depoimentos_enabled === true) {
                        // Mostrar depoimentos
                        depoimentosSections.forEach(section => {
                            section.classList.remove('hidden');
                        });
                        console.log('⭐ Depoimentos ativados no carregamento');
                    } else {
                        // Ocultar depoimentos
                        depoimentosSections.forEach(section => {
                            section.classList.add('hidden');
                        });
                        console.log('🚫 Depoimentos desativados no carregamento');
                    }
                } else {
                    // Padrão: mostrar depoimentos
                    const depoimentosSections = document.querySelectorAll('.depoimentos-section');
                    depoimentosSections.forEach(section => {
                        section.classList.remove('hidden');
                    });
                    console.log('⭐ Depoimentos ativados (padrão)');
                }
                
                // Controlar visibilidade do badge de pagamento seguro
                const safeBadge = document.getElementById('safe-payment-badge');
                const safeBadgeImg = document.getElementById('safe-payment-badge-img');
                const safeBuySidebar = document.getElementById('safe-buy-sidebar');
                
                console.log('🔍 DEBUG Badge - Elementos encontrados:', {
                    safeBadge: !!safeBadge,
                    safeBadgeImg: !!safeBadgeImg,
                    safeBuySidebar: !!safeBuySidebar,
                    show_safe_badge: data.show_safe_badge,
                    tipo: typeof data.show_safe_badge
                });
                
                if (data.show_safe_badge !== undefined) {
                    if (data.show_safe_badge === false || data.show_safe_badge === 'false') {
                        if (safeBadge) {
                            safeBadge.classList.add('safe-badge-hidden');
                            console.log('🛡️ Classe adicionada ao safeBadge:', safeBadge.className);
                        }
                        if (safeBuySidebar) {
                            safeBuySidebar.classList.add('safe-badge-hidden');
                            console.log('🛡️ Classe adicionada ao safeBuySidebar:', safeBuySidebar.className);
                        }
                        console.log('🛡️ Badge seguro ocultado no carregamento');
                    } else {
                        if (safeBadge) safeBadge.classList.remove('safe-badge-hidden');
                        if (safeBuySidebar) safeBuySidebar.classList.remove('safe-badge-hidden');
                        console.log('🛡️ Badge seguro exibido no carregamento');
                    }
                } else {
                    // Padrão: mostrar badge
                    if (safeBadge) safeBadge.classList.remove('safe-badge-hidden');
                    if (safeBuySidebar) safeBuySidebar.classList.remove('safe-badge-hidden');
                    console.log('🛡️ Badge seguro exibido (padrão)');
                }
                
                // Atualizar imagem do badge seguro se fornecida
                if (data.safe_badge_image && data.safe_badge_image !== '' && safeBadgeImg) {
                    safeBadgeImg.src = data.safe_badge_image;
                    safeBadgeImg.onerror = function() {
                        this.src = 'https://pay.controledavida.fun/assets/img/safe-payment.svg?v=1773793484';
                    };
                    console.log('🛡️ Imagem do badge atualizada no carregamento para:', data.safe_badge_image);
                }
                
                // Controlar visibilidade da logo da empresa
                const companyLogos = document.querySelectorAll('.checkout-logo');
                
                console.log('🔍 DEBUG Logo - Elementos encontrados:', {
                    companyLogos: companyLogos.length,
                    show_company_logo: data.show_company_logo,
                    tipo: typeof data.show_company_logo
                });
                
                if (data.show_company_logo !== undefined) {
                    if (data.show_company_logo === false || data.show_company_logo === 'false') {
                        companyLogos.forEach(logo => {
                            logo.classList.add('company-logo-hidden');
                            console.log('🏪 Classe adicionada à logo:', logo.className);
                        });
                        console.log('🏪 Logo da empresa ocultada no carregamento');
                    } else {
                        companyLogos.forEach(logo => {
                            logo.classList.remove('company-logo-hidden');
                        });
                        console.log('🏪 Logo da empresa exibida no carregamento');
                    }
                } else {
                    // Padrão: mostrar logo
                    companyLogos.forEach(logo => {
                        logo.classList.remove('company-logo-hidden');
                    });
                    console.log('🏪 Logo da empresa exibida (padrão)');
                }
                
                // Aplicar cores personalizadas se existirem
                if (data.colors) {
                    console.log('🎨 Cores personalizadas encontradas no config');
                    aplicarCoresPersonalizadas(data.colors);
                }
                
                // Atualizar interface com os novos valores
                atualizarInterfaceProduto(data);
                
                // Recalcular valores com o novo preço
                atualizarValores(1);
                
                // NÃO carregar opções de frete automaticamente
                // As opções de frete só devem aparecer quando o usuário preencher o CEP
                console.log('🚚 Configurações de frete carregadas (serão exibidas após preencher CEP)');
                
                // Inicializar contador regressivo se configurado
                if (data.contador) {
                    inicializarContador(data.contador);
                }

                // Aplicar configurações do topbar (banner de aviso)
                // Se topbar existe no config, usar valores dele
                // Senão, aplicar fallback ao topbar padrão do HTML
                const topbar = document.querySelector('.checkout-topbar');
                
                if (topbar) {
                    console.log('🔍 Verificando topbar no config:', data.topbar);
                    
                    if (data.topbar) {
                        console.log('📢 Topbar encontrado no config:', data.topbar);
                        console.log('📢 Tipo de visible:', typeof data.topbar.visible, 'Valor:', data.topbar.visible);
                        
                        // Converter string booleana para booleano se necessário
                        let isVisible = data.topbar.visible;
                        if (typeof isVisible === 'string') {
                            isVisible = isVisible === 'true' || isVisible === '1';
                        }
                        
                        console.log('📢 isVisible após conversão:', isVisible);
                        
                        // Aplicar visibilidade
                        if (isVisible === false) {
                            topbar.style.display = 'none';
                            console.log('📢 Banner de aviso OCULTADO');
                        } else {
                            topbar.style.display = '';
                            console.log('📢 Banner de aviso EXIBIDO');
                            
                            // Aplicar cor de fundo
                            if (data.topbar.bg_color) {
                                topbar.style.backgroundColor = data.topbar.bg_color;
                                console.log('📢 Cor de fundo aplicada:', data.topbar.bg_color);
                            }
                            
                            // Aplicar texto se fornecido
                            if (data.topbar.text && data.topbar.text.trim() !== '') {
                                const containerDiv = topbar.querySelector('.container');
                                if (containerDiv) {
                                    containerDiv.innerHTML = data.topbar.text;
                                    console.log('📢 Texto do banner ATUALIZADO:', data.topbar.text.substring(0, 50) + '...');
                                }
                            } else {
                                console.log('📢 Texto do topbar vazio ou não fornecido - mantendo padrão');
                            }
                        }
                    } else {
                        console.log('📢 Topbar NÃO encontrado no config - mantendo HTML padrão');
                    }
                } else {
                    console.warn('⚠️ Elemento .checkout-topbar não encontrado no DOM');
                }
                
                // Controlar visibilidade dos campos de formulário
                atualizarVisibilidadeCampos(data);
                
            } else {
                console.warn('⚠️ Dados inválidos no arquivo de configuração');
            }
            
        } catch (error) {
            console.error('❌ Erro ao carregar configurações:', error.message);
            console.log('📝 Usando valores padrão:', {
                preco: precoUnitario,
                nome: nomeProduto
            });
        }
    }
    
    // Função para atualizar interface com dados do produto
    window.atualizarInterfaceProduto = function(config = {}) {
        // Atualizar nome do produto em todos os lugares
        const nomeElements = document.querySelectorAll('.name_product_card');
        nomeElements.forEach(element => {
            element.textContent = nomeProduto;
        });
        
        // Atualizar descrição do produto se fornecida
        if (config.product_description) {
            const descElements = document.querySelectorAll('.info-small');
            descElements.forEach(element => {
                element.textContent = config.product_description;
            });
        }
        
        // Atualizar imagem do produto se fornecida
        if (config.product_image) {
            const imgElements = document.querySelectorAll('.product-img');
            imgElements.forEach(element => {
                element.src = config.product_image;
            });
        }
        
        // Atualizar logo da empresa se fornecido
        if (config.company_logo) {
            const logoElements = document.querySelectorAll('.checkout-logo');
            logoElements.forEach(element => {
                element.src = config.company_logo;
            });
        }
        
        // Atualizar nome da empresa se fornecido
        if (config.company_name) {
            const companyElements = document.querySelectorAll('footer p');
            companyElements.forEach(element => {
                if (element.textContent.includes('©')) {
                    element.textContent = `© 2026 ${config.company_name}`;
                }
            });
        }
        
        // Carregar ofertas se fornecidas
        if (config.offers) {
            atualizarOfertas(config.offers);
        }
        
        // Carregar depoimentos (sempre chamar, mesmo se vazio, para limpar)
        atualizarDepoimentos(config.depoimentos || []);
        
        // Atualizar valores iniciais na interface
        const valorFormatado = formatarValorBrasileiro(precoUnitario);
        
        // Atualizar subtotal
        const subtotalElements = document.querySelectorAll('.subtotal-value');
        subtotalElements.forEach(element => {
            element.textContent = ` ${valorFormatado} `;
        });
        
        // Atualizar todos os elementos de total
        const totalElements = document.querySelectorAll('.valor_total');
        totalElements.forEach(element => {
            element.textContent = `R$ ${valorFormatado}`;
        });
        
        // Atualizar no localStorage também
        localStorage.setItem('checkout_product_title', nomeProduto);
        
        console.log('🔄 Interface do produto atualizada com:', {
            nome: nomeProduto,
            preco: `R$ ${valorFormatado}`,
            descricao: config.product_description || 'Não alterada',
            imagem: config.product_image || 'Não alterada',
            empresa: config.company_name || 'Não alterada',
            ofertas: config.offers ? `${config.offers.items?.length || 0} ofertas` : 'Não alteradas'
        });
    }

    // Verificar se é mobile (largura menor que 992px)
    function isMobile() {
        return window.innerWidth < 992;
    }
    
    // Inicializar estado baseado no tamanho da tela
    function initializeSummaryState() {
        // Sempre começar expandido (tanto mobile quanto desktop)
        if (summaryBody) summaryBody.style.display = 'block';
        collapseToggles.forEach(element => {
            element.style.display = 'none';
        });
        // Arrow icon permanece fixo apontando para baixo (sem rotação)
    }
    
    // Função para atualizar todos os valores
    window.atualizarValores = function(quantidade) {
        console.log('Atualizando valores para quantidade:', quantidade);
        
        // Atualizar todos os inputs de quantidade
        const inputs = document.querySelectorAll('.input-number input[type="number"]');
        inputs.forEach(input => {
            input.value = quantidade;
        });
        
        // Atualizar indicadores de quantidade
        const qtdeSpans = document.querySelectorAll('.qtde');
        qtdeSpans.forEach(span => {
            span.textContent = quantidade;
        });
        
        // Usar a função que considera as ofertas
        atualizarTotalComOfertas();
        
        // Salvar dados atualizados no localStorage
        salvarDadosLocalStorage();
        
        console.log('Valores atualizados');
    }
    
    // VALIDAÇÕES E MÁSCARAS
    
    // Função para validar email
    function validarEmail(email) {
        const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return regex.test(email);
    }
    
    // Função para validar CPF
    function validarCPF(cpf) {
        cpf = cpf.replace(/[^\d]/g, '');
        
        if (cpf.length !== 11 || /^(\d)\1{10}$/.test(cpf)) {
            return false;
        }
        
        // Validação do primeiro dígito
        let soma = 0;
        for (let i = 0; i < 9; i++) {
            soma += parseInt(cpf.charAt(i)) * (10 - i);
        }
        let resto = 11 - (soma % 11);
        if (resto === 10 || resto === 11) resto = 0;
        if (resto !== parseInt(cpf.charAt(9))) return false;
        
        // Validação do segundo dígito
        soma = 0;
        for (let i = 0; i < 10; i++) {
            soma += parseInt(cpf.charAt(i)) * (11 - i);
        }
        resto = 11 - (soma % 11);
        if (resto === 10 || resto === 11) resto = 0;
        if (resto !== parseInt(cpf.charAt(10))) return false;
        
        return true;
    }
    
    // Função para validar nome (pelo menos 2 palavras)
    function validarNome(nome) {
        const palavras = nome.trim().split(/\s+/);
        return palavras.length >= 2 && palavras.every(palavra => palavra.length >= 2);
    }
    
    // Função para validar telefone
    function validarTelefone(telefone) {
        const numeroLimpo = telefone.replace(/[^\d]/g, '');
        return numeroLimpo.length >= 10;
    }
    
    // Função para aplicar máscara de telefone
    function mascaraTelefone(value) {
        value = value.replace(/\D/g, '');
        value = value.replace(/^(\d{2})(\d)/g, '($1) $2');
        value = value.replace(/(\d)(\d{4})$/, '$1-$2');
        return value;
    }
    
    // Função para aplicar máscara de CPF
    function mascaraCPF(value) {
        value = value.replace(/\D/g, '');
        value = value.replace(/(\d{3})(\d)/, '$1.$2');
        value = value.replace(/(\d{3})(\d)/, '$1.$2');
        value = value.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
        return value;
    }
    
    // Função para aplicar máscara de CEP
    function mascaraCEP(value) {
        value = value.replace(/\D/g, '');
        value = value.replace(/(\d{5})(\d)/, '$1-$2');
        return value;
    }
    
    // Função para validar CEP
    function validarCEP(cep) {
        const cepLimpo = cep.replace(/[^\d]/g, '');
        return cepLimpo.length === 8;
    }
    
    // Função para buscar CEP na API
    async function buscarCEP(cep) {
        const cepLimpo = cep.replace(/[^\d]/g, '');
        
        if (cepLimpo.length !== 8) {
            return null;
        }
        
        try {
            console.log('🔍 Buscando CEP:', cepLimpo);
            
            // Mostrar spinner de loading
            const spinner = document.getElementById('zip-code-spinner');
            if (spinner) spinner.style.display = 'block';
            
            const response = await fetch(`https://viacep.com.br/ws/${cepLimpo}/json/`);
            const data = await response.json();
            
            // Esconder spinner
            if (spinner) spinner.style.display = 'none';
            
            if (data.erro) {
                console.log('❌ CEP não encontrado');
                return null;
            }
            
            console.log('✅ CEP encontrado:', data);
            return data;
            
        } catch (error) {
            console.error('❌ Erro ao buscar CEP:', error);
            
            // Esconder spinner em caso de erro
            const spinner = document.getElementById('zip-code-spinner');
            if (spinner) spinner.style.display = 'none';
            
            return null;
        }
    }
    
    // Função para preencher campos de endereço
    function preencherEndereco(dadosCEP) {
        const ruaInput = document.getElementById('street');
        const bairroInput = document.getElementById('neighborhood');
        const cidadeInput = document.getElementById('city');
        const estadoSelect = document.getElementById('state');
        
        if (ruaInput && dadosCEP.logradouro) {
            ruaInput.value = dadosCEP.logradouro;
            esconderErro(ruaInput);
        }
        
        if (bairroInput && dadosCEP.bairro) {
            bairroInput.value = dadosCEP.bairro;
            esconderErro(bairroInput);
        }
        
        if (cidadeInput && dadosCEP.localidade) {
            cidadeInput.value = dadosCEP.localidade;
            esconderErro(cidadeInput);
        }
        
        if (estadoSelect && dadosCEP.uf) {
            estadoSelect.value = dadosCEP.uf;
            esconderErro(estadoSelect);
        }
        
        // Focar no campo número após preencher
        const numeroInput = document.getElementById('number');
        if (numeroInput) {
            numeroInput.focus();
        }
        
        // Mostrar opções de frete após preencher endereço
        mostrarOpcoesFreteSimuladas();
        
        // Salvar dados automaticamente
        salvarDadosEntrega();
        
        console.log('📍 Endereço preenchido automaticamente');
    }
    
    // Função para mostrar opções de frete simuladas
    async function mostrarOpcoesFreteSimuladas() {
        const radiosContainer = document.querySelector('.col-12.radios.px-0.mb-2');
        
        if (!radiosContainer) return;
        
        // Esconder mensagem de CEP vazio
        const emptyShipping = radiosContainer.querySelector('.emptyShipping');
        if (emptyShipping) {
            emptyShipping.style.display = 'none';
        }
        
        // Tentar carregar opções de frete do JSON primeiro
        try {
            console.log('🔍 Tentando carregar frete do JSON...');
            const response = await fetch('./checkout-config.json?v=' + Date.now());
            console.log('📡 Response status:', response.status, response.ok);
            
            if (response.ok) {
                const data = await response.json();
                console.log('📦 Dados carregados:', data);
                console.log('🚚 Frete no JSON:', data.frete);
                
                // Se existem opções de frete salvas, usar elas
                if (data.frete && data.frete.opcoes && data.frete.opcoes.length > 0) {
                    console.log('✅ Usando opções de frete do JSON:', data.frete.opcoes);
                    atualizarOpcoesFretePreview(data.frete.opcoes);
                    return; // Sair da função, não usar hardcoded
                } else {
                    console.log('⚠️ Nenhuma opção de frete encontrada no JSON');
                }
            } else {
                console.log('❌ Erro ao carregar JSON, status:', response.status);
            }
        } catch (error) {
            console.log('❌ Erro ao carregar frete do JSON:', error);
        }
        
        // Se não há opções salvas, usar HTML hardcoded padrão
        console.log('📦 Usando opções de frete padrão (hardcoded)');
        
        const freteOptionsHTML = `
            <div style="cursor: pointer; border: 1px solid rgb(221, 221, 221); filter: none;" class="radio-box d-flex flex-reverse m-top mb-2">
                <div class="radio-div">
                    <div class="radio-container">
                        <label for="radio1">
                            <input class="radio-frete" type="radio" data-shipping="mwK436O2LlZQ8bx" id="PACCorreios" value="mwK436O2LlZQ8bx" name="shipping">
                            <div class="custom-radio"><span></span></div>
                        </label>
                    </div>
                </div>
                <div style="margin-right:4px; margin-left:24px">
                    <label for="PAC Correios">
                        <strong class="shipping-name">PAC Correios<span class="price" id="mwK436O2LlZQ8bx"><small>R$</small>14,64</span></strong>
                        <p class="desc-frete" style="margin-top:7px">4 a 12 dias</p>
                    </label>
                </div>
            </div>
            
            <div style="cursor: pointer; border: 1px solid rgb(221, 221, 221); filter: none;" class="radio-box d-flex flex-reverse m-top mb-2">
                <div class="radio-div">
                    <div class="radio-container">
                        <label for="radio1">
                            <input class="radio-frete" type="radio" data-shipping="BNjzgPOqJrGM78R" id="CorreiosSedex" value="BNjzgPOqJrGM78R" name="shipping">
                            <div class="custom-radio"><span></span></div>
                        </label>
                    </div>
                </div>
                <div style="margin-right:4px; margin-left:24px">
                    <label for="Correios Sedex">
                        <strong class="shipping-name">Correios Sedex<span class="price" id="BNjzgPOqJrGM78R"><small>R$</small>19,74</span></strong>
                        <p class="desc-frete" style="margin-top:7px">entrega em até 5 dias úteis</p>
                    </label>
                </div>
            </div>
            
            <div style="cursor: pointer; border: 1px solid rgb(46, 133, 236); filter: drop-shadow(rgba(46, 133, 236, 0.3) 0px 0px 7px);" class="radio-box d-flex flex-reverse m-top mb-2">
                <div class="radio-div">
                    <div class="radio-container">
                        <label for="radio1">
                            <input class="radio-frete" type="radio" data-shipping="ODAK3LQ2ExZE6Vz" id="FreteFull - Correios" value="ODAK3LQ2ExZE6Vz" name="shipping" checked>
                            <div class="custom-radio"><span></span></div>
                        </label>
                    </div>
                </div>
                <div style="margin-right:4px; margin-left:24px">
                    <label for="Frete Full - Correios">
                        <strong class="shipping-name">Frete Full - Correios<span class="price" id="ODAK3LQ2ExZE6Vz"><small>R$</small>26,73</span></strong>
                        <p class="desc-frete" style="margin-top:7px">Entrega de 12h à 14h</p>
                    </label>
                </div>
            </div>
        `;
        
        // Adicionar as opções de frete
        radiosContainer.innerHTML = freteOptionsHTML;
        
        // Adicionar event listeners para as opções de frete
        const radioBoxes = radiosContainer.querySelectorAll('.radio-box');
        radioBoxes.forEach(radioBox => {
            radioBox.addEventListener('click', function() {
                selecionarFrete(this);
            });
        });
        
        // Atualizar valor do frete automaticamente (selecionar o terceiro por padrão)
        atualizarValorFrete('26,73');
        
        console.log('📦 Opções de frete exibidas');
    }
    
    // Função para atualizar opções de frete do preview
    function atualizarOpcoesFretePreview(freteOpcoes) {
        const radiosContainer = document.querySelector('.col-12.radios.px-0.mb-2');
        
        if (!radiosContainer || !freteOpcoes || freteOpcoes.length === 0) {
            console.log('⚠️ Container de frete não encontrado ou sem opções');
            return;
        }
        
        // Esconder mensagem de CEP vazio
        const emptyShipping = radiosContainer.querySelector('.emptyShipping');
        if (emptyShipping) {
            emptyShipping.style.display = 'none';
        }
        
        // Limpar container
        radiosContainer.innerHTML = '';
        
        // Criar HTML para cada opção de frete
        freteOpcoes.forEach((frete, index) => {
            const freteId = `frete-preview-${index}`;
            const isDestaque = frete.destaque || false;
            const isSelected = frete.selected || false;
            
            // Estilo do border baseado em destaque
            const borderStyle = isDestaque 
                ? 'border: 1px solid rgb(46, 133, 236); filter: drop-shadow(rgba(46, 133, 236, 0.3) 0px 0px 7px);'
                : 'border: 1px solid rgb(221, 221, 221); filter: none;';
            
            const freteHTML = `
                <div style="cursor: pointer; ${borderStyle}" class="radio-box d-flex flex-reverse m-top mb-2">
                    <div class="radio-div">
                        <div class="radio-container">
                            <label for="${freteId}">
                                <input class="radio-frete" type="radio" data-shipping="${freteId}" id="${freteId}" value="${freteId}" name="shipping" ${isSelected ? 'checked' : ''}>
                                <div class="custom-radio"><span></span></div>
                            </label>
                        </div>
                    </div>
                    <div style="margin-right:4px; margin-left:24px">
                        <label for="${frete.name || 'Frete'}">
                            <strong class="shipping-name">${frete.name || 'Frete'}<span class="price" id="${freteId}-price"><small>R$</small>${frete.price || '0,00'}</span></strong>
                            <p class="desc-frete" style="margin-top:7px">${frete.description || 'Entrega padrão'}</p>
                        </label>
                    </div>
                </div>
            `;
            
            radiosContainer.innerHTML += freteHTML;
        });
        
        // Adicionar event listeners para as opções de frete
        const radioBoxes = radiosContainer.querySelectorAll('.radio-box');
        radioBoxes.forEach(radioBox => {
            radioBox.addEventListener('click', function() {
                selecionarFrete(this);
            });
        });
        
        // Atualizar valor do frete com a opção selecionada
        const opcaoSelecionada = freteOpcoes.find(f => f.selected);
        if (opcaoSelecionada && opcaoSelecionada.price) {
            atualizarValorFrete(opcaoSelecionada.price);
        } else if (freteOpcoes.length > 0 && freteOpcoes[0].price) {
            atualizarValorFrete(freteOpcoes[0].price);
        }
        
        console.log(`🚚 ${freteOpcoes.length} opções de frete atualizadas no preview`);
    }
    
    // Função para selecionar frete
    function selecionarFrete(radioBox) {
        console.log('🚚 Função selecionarFrete chamada');
        
        // Remover seleção de todos os radio boxes
        const allRadioBoxes = document.querySelectorAll('.radio-box');
        allRadioBoxes.forEach(box => {
            box.classList.remove('selected');
            box.style.border = '1px solid rgb(221, 221, 221)';
            box.style.filter = 'none';
            
            // Desmarcar radio button
            const radio = box.querySelector('input[type="radio"]');
            if (radio) radio.checked = false;
        });
        
        // Selecionar o radio box clicado
        radioBox.classList.add('selected');
        radioBox.style.border = '1px solid rgb(46, 133, 236)';
        radioBox.style.filter = 'drop-shadow(rgba(46, 133, 236, 0.3) 0px 0px 7px)';
        
        // Marcar radio button
        const radio = radioBox.querySelector('input[type="radio"]');
        if (radio) {
            radio.checked = true;
            
            // Buscar o preço do frete selecionado
            const priceSpan = radioBox.querySelector('.price');
            if (priceSpan) {
                // Extrair apenas o valor numérico (ex: "R$14,64" -> "14,64")
                const precoTexto = priceSpan.textContent;
                const precoMatch = precoTexto.match(/R?\$?(\d+,\d+)/);
                
                if (precoMatch) {
                    const valorFrete = precoMatch[1];
                    atualizarValorFrete(valorFrete);
                    console.log('✅ Frete selecionado:', radio.value, 'Valor:', valorFrete);
                }
            }
            
            // Salvar dados
            salvarDadosEntrega();
        }
    }
    
    // Função para esconder opções de frete
    function esconderOpcoesFreteSimuladas() {
        const radiosContainer = document.querySelector('.col-12.radios.px-0.mb-2');
        
        if (radiosContainer) {
            // Mostrar mensagem de CEP vazio
            radiosContainer.innerHTML = `
                <div class="emptyShipping">
                    <span style="font-family: 'Inter'; font-style: normal; font-weight: 500; font-size: 16px; line-height: 19px; letter-spacing: -0.02em; color: #585858;">
                        Preencha seu CEP para encontrar o melhor frete
                    </span>
                    <div style="height: 4px"></div>
                    <span style="font-family: 'Inter'; font-style: normal; font-weight: 500; font-size: 12px; line-height: 15px; color: #999999;">
                        Após preenchido, encontraremos as melhores opções pra você
                    </span>
                    <input type="hidden" name="shipping_id" value="" />
                </div>
            `;
            
            // Resetar valor do frete
            const freteSpans = document.querySelectorAll('.valor_frete');
            freteSpans.forEach(span => {
                span.textContent = ' - ';
            });
            
            // Atualizar valor total (sem frete)
            const quantidade = parseInt(document.querySelector('.input-number input[type="number"]')?.value || '1');
            const subtotal = precoUnitario * quantidade;
            const total = formatarValorBrasileiro(subtotal);
            
            const valorTotalSpans = document.querySelectorAll('.valor_total');
            valorTotalSpans.forEach(span => {
                span.textContent = `R$ ${total}`;
            });
            
            console.log('📦 Opções de frete escondidas');
        }
    }
    
    // Função para atualizar valor do frete
    function atualizarValorFrete(valorFrete) {
        const freteSpans = document.querySelectorAll('.valor_frete');
        freteSpans.forEach(span => {
            span.textContent = `R$ ${valorFrete}`;
        });
        
        // Usar a função que considera as ofertas
        atualizarTotalComOfertas();
        
        console.log('💰 Valor do frete atualizado:', valorFrete);
    }
    
    // Função para calcular valor total real (incluindo ofertas e frete)
    function calcularValorTotalReal() {
        const quantidade = parseInt(document.querySelector('.input-number input[type="number"]')?.value || '1');
        let subtotal = precoUnitario * quantidade;
        
        // Somar valores das ofertas selecionadas
        const ofertasSelecionadas = document.querySelectorAll('.ob-product.checked');
        let totalOfertas = 0;
        
        ofertasSelecionadas.forEach(oferta => {
            const priceValue = parseFloat(oferta.querySelector('.ob-price-value').value);
            totalOfertas += priceValue;
        });
        
        // Calcular subtotal com ofertas
        const novoSubtotal = subtotal + totalOfertas;
        
        // Adicionar frete se selecionado
        let total = novoSubtotal;
        const freteSpan = document.querySelector('.valor_frete');
        if (freteSpan && freteSpan.textContent !== ' - ') {
            const freteTexto = freteSpan.textContent;
            const freteMatch = freteTexto.match(/R?\$?(\d+,\d+)/);
            if (freteMatch) {
                const valorFrete = parseFloat(freteMatch[1].replace(',', '.'));
                total += valorFrete;
            }
        }
        
        return {
            subtotal: novoSubtotal,
            total: total,
            ofertas: totalOfertas,
            frete: total - novoSubtotal
        };
    }

    // Função para salvar dados no localStorage
    window.salvarDadosLocalStorage = function() {
        const emailInput = document.getElementById('email');
        const telefoneInput = document.getElementById('telephone');
        const nomeInput = document.getElementById('name');
        const documentInput = document.getElementById('document');
        
        // Dados do formulário de identificação
        const checkoutFormData = {
            name: nomeInput ? nomeInput.value.trim() : '',
            email: emailInput ? emailInput.value.trim() : '',
            phone: telefoneInput ? telefoneInput.value.trim() : '',
            cpf: documentInput ? documentInput.value.trim() : ''
        };
        
        // Salva dados consolidados do checkout
        localStorage.setItem('checkout_form_data', JSON.stringify(checkoutFormData));
        
        // Salva dados individuais (compatibilidade com código legado)
        if (checkoutFormData.name) {
            const addressData = {
                nome: checkoutFormData.name,
                email: checkoutFormData.email,
                telefone: checkoutFormData.phone
            };
            localStorage.setItem('userAddress', JSON.stringify(addressData));
        }
        
        if (checkoutFormData.cpf) {
            localStorage.setItem('userCPF', checkoutFormData.cpf);
        }
        
        // Calcular valores reais (incluindo ofertas e frete)
        const valoresReais = calcularValorTotalReal();
        const quantidade = parseInt(document.querySelector('.input-number input[type="number"]')?.value || '1');
        
        // Salvar valor total REAL no localStorage
        localStorage.setItem('checkout_total', valoresReais.total.toString());
        localStorage.setItem('checkout_subtotal', valoresReais.subtotal.toString());
        localStorage.setItem('checkout_ofertas_total', valoresReais.ofertas.toString());
        localStorage.setItem('checkout_frete_total', valoresReais.frete.toString());
        localStorage.setItem('checkout_product_title', nomeProduto);
        localStorage.setItem('cartQuantity', quantidade.toString());
        
        // Cria dados do carrinho no formato esperado (com valor total real)
        const cartItems = [{
            name: nomeProduto,
            price: Math.round(valoresReais.total * 100), // Converte valor total para centavos
            quantity: quantidade,
            product: {
                name: nomeProduto
            }
        }];
        
        localStorage.setItem('tikshop_cart', JSON.stringify(cartItems));
        localStorage.setItem('cart_items', JSON.stringify(cartItems));
        
        console.log('💾 Dados salvos no localStorage:', {
            checkoutFormData,
            valoresCalculados: valoresReais,
            quantidade,
            cartItems
        });
    }
    
    // Função para salvar dados de entrega no localStorage
    function salvarDadosEntrega() {
        const cepInput = document.getElementById('zip_code');
        const ruaInput = document.getElementById('street');
        const numeroInput = document.getElementById('number');
        const complementoInput = document.getElementById('complement');
        const bairroInput = document.getElementById('neighborhood');
        const cidadeInput = document.getElementById('city');
        const estadoSelect = document.getElementById('state');
        const receiverNameInput = document.getElementById('receiver_name');
        
        // Busca dados já salvos
        const checkoutFormData = JSON.parse(localStorage.getItem('checkout_form_data') || '{}');
        
        // Adiciona dados de entrega
        if (cepInput) checkoutFormData.cep = cepInput.value.trim();
        if (ruaInput) checkoutFormData.street = ruaInput.value.trim();
        if (numeroInput) checkoutFormData.number = numeroInput.value.trim();
        if (complementoInput) checkoutFormData.complement = complementoInput.value.trim();
        if (bairroInput) checkoutFormData.neighborhood = bairroInput.value.trim();
        if (cidadeInput) checkoutFormData.city = cidadeInput.value.trim();
        if (estadoSelect) checkoutFormData.state = estadoSelect.value;
        if (receiverNameInput && receiverNameInput.value.trim()) {
            checkoutFormData.receiver_name = receiverNameInput.value.trim();
        }
        
        // Atualiza dados consolidados
        localStorage.setItem('checkout_form_data', JSON.stringify(checkoutFormData));
        
        // Atualiza dados de endereço (formato legado)
        const addressData = {
            nome: checkoutFormData.receiver_name || checkoutFormData.name,
            email: checkoutFormData.email,
            telefone: checkoutFormData.phone,
            endereco: checkoutFormData.street,
            numero: checkoutFormData.number,
            complemento: checkoutFormData.complement,
            bairro: checkoutFormData.neighborhood,
            cidade: checkoutFormData.city,
            estado: checkoutFormData.state,
            cep: checkoutFormData.cep
        };
        
        localStorage.setItem('userAddress', JSON.stringify(addressData));
        
        // Calcular e salvar valores totais atualizados
        const valoresReais = calcularValorTotalReal();
        localStorage.setItem('checkout_total', valoresReais.total.toString());
        localStorage.setItem('checkout_subtotal', valoresReais.subtotal.toString());
        localStorage.setItem('checkout_ofertas_total', valoresReais.ofertas.toString());
        localStorage.setItem('checkout_frete_total', valoresReais.frete.toString());
        
        // Salva dados de frete se selecionado
        const freteRadio = document.querySelector('input[name="shipping"]:checked');
        if (freteRadio) {
            const freteContainer = freteRadio.closest('.radio-box');
            const freteNome = freteContainer?.querySelector('.shipping-name')?.textContent?.split('R$')[0]?.trim() || 'Frete';
            const fretePreco = freteContainer?.querySelector('.price')?.textContent?.replace('R$', '').replace(',', '.') || '0';
            
            localStorage.setItem('checkout_shipping_name', freteNome);
            localStorage.setItem('checkout_shipping_value', fretePreco);
            
            // Formato legado do frete
            const shippingData = {
                name: freteNome,
                price: Math.round(parseFloat(fretePreco) * 100) // Converte para centavos
            };
            localStorage.setItem('cart_shipping', JSON.stringify(shippingData));
        }
        
        console.log('📦 Dados de entrega salvos:', checkoutFormData);
        console.log('💰 Valores totais atualizados:', valoresReais);
    }
    
    // Função para validar todos os campos obrigatórios
    function validarTodosCampos() {
        const emailInput = document.getElementById('email');
        const telefoneInput = document.getElementById('telephone');
        const nomeInput = document.getElementById('name');
        const documentInput = document.getElementById('document');
        
        let todosValidos = true;
        
        // Validar email (apenas se estiver visível)
        if (emailInput && !emailInput.closest('.field-hidden') && emailInput.offsetParent !== null) {
            const valor = emailInput.value.trim();
            if (valor === '') {
                mostrarErro(emailInput, 'empty');
                todosValidos = false;
            } else if (!validarEmail(valor)) {
                mostrarErro(emailInput, 'invalid');
                todosValidos = false;
            } else {
                esconderErro(emailInput);
            }
        }
        
        // Validar telefone (apenas se estiver visível)
        if (telefoneInput && !telefoneInput.closest('.field-hidden') && telefoneInput.offsetParent !== null) {
            const valor = telefoneInput.value.trim();
            if (valor === '') {
                mostrarErro(telefoneInput, 'empty');
                todosValidos = false;
            } else if (!validarTelefone(valor)) {
                mostrarErro(telefoneInput, 'invalid');
                todosValidos = false;
            } else {
                esconderErro(telefoneInput);
            }
        }
        
        // Validar nome (sempre obrigatório)
        if (nomeInput) {
            const valor = nomeInput.value.trim();
            if (valor === '') {
                mostrarErro(nomeInput, 'empty');
                todosValidos = false;
            } else if (!validarNome(valor)) {
                mostrarErro(nomeInput, 'invalid');
                todosValidos = false;
            } else {
                esconderErro(nomeInput);
            }
        }
        
        // Validar CPF (apenas se estiver visível)
        if (documentInput && !documentInput.closest('.field-hidden') && documentInput.offsetParent !== null) {
            const valor = documentInput.value.trim();
            if (valor === '') {
                mostrarErro(documentInput, 'empty');
                todosValidos = false;
            } else if (!validarCPF(valor)) {
                mostrarErro(documentInput, 'invalid');
                todosValidos = false;
            } else {
                esconderErro(documentInput);
            }
        }
        
        return todosValidos;
    }
    
    // Função para fazer scroll suave para o card do produto
    function scrollParaSteps() {
        const cardProduto = document.querySelector('.card.produto.mb-4');
        if (cardProduto) {
            cardProduto.scrollIntoView({ 
                behavior: 'smooth', 
                block: 'start' 
            });
            console.log('📜 Scroll realizado para o card do produto');
        }
    }
    
    // Função para controlar visibilidade dos campos de formulário
    function atualizarVisibilidadeCampos(config) {
        console.log('📝 Controlando visibilidade dos campos:', {
            show_email_field: config.show_email_field,
            show_phone_field: config.show_phone_field,
            show_cpf_field: config.show_cpf_field
        });
        
        try {
            // Adicionar CSS global para campos ocultos
            if (!document.getElementById('field-visibility-css')) {
                const style = document.createElement('style');
                style.id = 'field-visibility-css';
                style.textContent = `
                    .field-hidden {
                        display: none !important;
                        visibility: hidden !important;
                        opacity: 0 !important;
                        height: 0 !important;
                        overflow: hidden !important;
                        margin: 0 !important;
                        padding: 0 !important;
                    }
                `;
                document.head.appendChild(style);
                console.log('🎨 CSS para campos ocultos adicionado');
            }
            
            // Campo de Email
            if (config.show_email_field !== undefined) {
                const emailField = document.getElementById('email');
                const emailContainer = emailField?.closest('.col-12.col-sm-6') || 
                                     emailField?.closest('.form-group') || 
                                     emailField?.parentElement?.parentElement;
                
                if (emailContainer) {
                    if (config.show_email_field === false || config.show_email_field === 'false') {
                        emailContainer.style.display = 'none !important';
                        emailContainer.style.visibility = 'hidden';
                        emailContainer.style.opacity = '0';
                        emailContainer.style.height = '0';
                        emailContainer.style.overflow = 'hidden';
                        emailContainer.classList.add('field-hidden');
                        console.log('📧 Campo de email ocultado');
                    } else {
                        emailContainer.style.display = '';
                        emailContainer.style.visibility = '';
                        emailContainer.style.opacity = '';
                        emailContainer.style.height = '';
                        emailContainer.style.overflow = '';
                        emailContainer.classList.remove('field-hidden');
                        console.log('📧 Campo de email exibido');
                    }
                }
            }
            
            // Campo de Telefone
            if (config.show_phone_field !== undefined) {
                const phoneField = document.getElementById('telephone');
                const phoneContainer = phoneField?.closest('.col-12.col-sm-6') || 
                                     phoneField?.closest('.form-group') || 
                                     phoneField?.parentElement?.parentElement;
                
                if (phoneContainer) {
                    if (config.show_phone_field === false || config.show_phone_field === 'false') {
                        phoneContainer.style.display = 'none !important';
                        phoneContainer.style.visibility = 'hidden';
                        phoneContainer.style.opacity = '0';
                        phoneContainer.style.height = '0';
                        phoneContainer.style.overflow = 'hidden';
                        phoneContainer.classList.add('field-hidden');
                        console.log('📱 Campo de telefone ocultado');
                    } else {
                        phoneContainer.style.display = '';
                        phoneContainer.style.visibility = '';
                        phoneContainer.style.opacity = '';
                        phoneContainer.style.height = '';
                        phoneContainer.style.overflow = '';
                        phoneContainer.classList.remove('field-hidden');
                        console.log('📱 Campo de telefone exibido');
                    }
                }
            }
            
            // Campo de CPF/Documento
            if (config.show_cpf_field !== undefined) {
                const cpfField = document.getElementById('document');
                const cpfContainer = cpfField?.closest('.col-12.col-md-6') || 
                                   cpfField?.closest('.form-group') || 
                                   cpfField?.parentElement?.parentElement;
                
                if (cpfContainer) {
                    if (config.show_cpf_field === false || config.show_cpf_field === 'false') {
                        cpfContainer.style.display = 'none !important';
                        cpfContainer.style.visibility = 'hidden';
                        cpfContainer.style.opacity = '0';
                        cpfContainer.style.height = '0';
                        cpfContainer.style.overflow = 'hidden';
                        cpfContainer.classList.add('field-hidden');
                        console.log('🆔 Campo de CPF ocultado');
                    } else {
                        cpfContainer.style.display = '';
                        cpfContainer.style.visibility = '';
                        cpfContainer.style.opacity = '';
                        cpfContainer.style.height = '';
                        cpfContainer.style.overflow = '';
                        cpfContainer.classList.remove('field-hidden');
                        console.log('🆔 Campo de CPF exibido');
                    }
                }
            }
            
            console.log('✅ Visibilidade dos campos atualizada');
        } catch (error) {
            console.error('❌ Erro ao atualizar visibilidade dos campos:', error);
        }
    }
    
    // Função para ajustar checkout para produto digital
    function ajustarCheckoutParaDigital() {
        // Ocultar step de entrega
        const deliveryStep = document.getElementById('delivery_data');
        if (deliveryStep) {
            deliveryStep.style.display = 'none';
        }
        
        // Ocultar linha do frete no resumo (ambos os locais: sidebar e mobile)
        const freteRows = document.querySelectorAll('.row.justify-content-between.p-0.mb-2');
        freteRows.forEach(row => {
            const freteLabel = row.querySelector('.subtotal');
            if (freteLabel && freteLabel.textContent.includes('Frete')) {
                row.style.display = 'none';
                console.log('✅ Linha do frete ocultada no checkout');
            }
        });
        
        // Ajustar numeração dos steps para produto digital
        const paymentStep = document.getElementById('payment_data');
        if (paymentStep) {
            const paymentNumber = paymentStep.querySelector('.step-number .number');
            if (paymentNumber) {
                paymentNumber.textContent = '2';
            }
        }
        
        // Adicionar classe para ajustar espaçamento (2 steps ao invés de 3)
        const stepsContainer = document.querySelector('.steps');
        if (stepsContainer) {
            stepsContainer.classList.add('steps2');
            stepsContainer.classList.remove('steps3');
        }
        
        // Ajustar o botão "IR PARA A ENTREGA" para ir direto ao pagamento
        const deliveryNextStepBtn = document.getElementById('delivery_next_step');
        if (deliveryNextStepBtn) {
            deliveryNextStepBtn.textContent = 'IR PARA O PAGAMENTO';
            
            // Remover event listener antigo e adicionar novo
            const newBtn = deliveryNextStepBtn.cloneNode(true);
            deliveryNextStepBtn.parentNode.replaceChild(newBtn, deliveryNextStepBtn);
            
            newBtn.addEventListener('click', function(e) {
                e.preventDefault();
                console.log('Botão IR PARA O PAGAMENTO clicado (produto digital)');
                
                if (validarTodosCampos()) {
                    console.log('Todos os campos são válidos, avançando para pagamento');
                    avancarParaPagamentoDigital();
                } else {
                    console.log('Existem campos inválidos');
                }
            });
        }
        
        console.log('✅ Checkout ajustado para produto digital');
    }
    
    // Função para avançar direto para pagamento (produto digital)
    function avancarParaPagamentoDigital() {
        // Salva dados no localStorage antes de avançar
        salvarDadosLocalStorage();
        
        // Esconder card-content-1 (identificação)
        const cardContent1 = document.querySelector('.card-content-1');
        if (cardContent1) {
            cardContent1.style.display = 'none';
        }
        
        // Mostrar card-content-3 (pagamento) - pular entrega
        const cardContent3 = document.querySelector('.card-content-3');
        if (cardContent3) {
            cardContent3.style.display = 'block';
        }
        
        // Atualizar os steps
        const contactData = document.getElementById('contact_data');
        const deliveryData = document.getElementById('delivery_data');
        const paymentData = document.getElementById('payment_data');
        
        if (contactData) {
            contactData.classList.remove('current');
            contactData.classList.add('done');
        }
        
        // Marcar entrega como done também (mesmo que oculta)
        if (deliveryData) {
            deliveryData.classList.add('done');
            deliveryData.classList.remove('current');
        }
        
        if (paymentData) {
            paymentData.classList.add('ativo', 'current');
        }
        
        // Scroll para os steps
        setTimeout(() => {
            scrollParaSteps();
        }, 100);
        
        console.log('✅ Avançado para pagamento (produto digital)');
    }
    
    // Função para avançar para a etapa de entrega
    function avancarParaEntrega() {
        // Salva dados no localStorage antes de avançar
        salvarDadosLocalStorage();
        
        // Esconder card-content-1 (identificação)
        const cardContent1 = document.querySelector('.card-content-1');
        if (cardContent1) {
            cardContent1.style.display = 'none';
        }
        
        // Mostrar card-content-2 (entrega)
        const cardContent2 = document.querySelector('.card-content-2');
        if (cardContent2) {
            cardContent2.style.display = 'block';
        }
        
        // Atualizar os steps
        const contactData = document.getElementById('contact_data');
        const deliveryData = document.getElementById('delivery_data');
        
        if (contactData) {
            contactData.classList.remove('current');
            contactData.classList.add('done');
        }
        
        if (deliveryData) {
            deliveryData.classList.add('ativo', 'current');
        }
        
        // Scroll para os steps
        setTimeout(() => {
            scrollParaSteps();
        }, 100);
    }
    function mostrarErro(input, tipoErro = 'invalid') {
        input.classList.add('is-invalid');
        input.classList.remove('is-valid');
        
        const feedback = input.parentElement.querySelector('.invalid-feedback');
        if (feedback) {
            const invalidSpan = feedback.querySelector('.invalid_data');
            const emptySpan = feedback.querySelector('.empty_data');
            
            if (tipoErro === 'empty') {
                if (invalidSpan) invalidSpan.style.display = 'none';
                if (emptySpan) emptySpan.style.display = 'block';
            } else {
                if (invalidSpan) invalidSpan.style.display = 'block';
                if (emptySpan) emptySpan.style.display = 'none';
            }
        }
    }
    
    // Função para esconder erro
    function esconderErro(input) {
        input.classList.remove('is-invalid');
        input.classList.add('is-valid');
        
        const feedback = input.parentElement.querySelector('.invalid-feedback');
        if (feedback) {
            const invalidSpan = feedback.querySelector('.invalid_data');
            const emptySpan = feedback.querySelector('.empty_data');
            
            if (invalidSpan) invalidSpan.style.display = 'none';
            if (emptySpan) emptySpan.style.display = 'none';
        }
    }
    
    // Aplicar máscaras e validações
    const emailInput = document.getElementById('email');
    const telefoneInput = document.getElementById('telephone');
    const nomeInput = document.getElementById('name');
    const documentInput = document.getElementById('document');
    
    // Validação de email
    if (emailInput) {
        emailInput.addEventListener('blur', function() {
            const valor = this.value.trim();
            if (valor === '') {
                mostrarErro(this, 'empty');
            } else if (!validarEmail(valor)) {
                mostrarErro(this, 'invalid');
            } else {
                esconderErro(this);
            }
        });
        
        emailInput.addEventListener('input', function() {
            if (this.classList.contains('is-invalid') && validarEmail(this.value.trim())) {
                esconderErro(this);
            }
        });
    }
    
    // Máscara e validação de telefone
    if (telefoneInput) {
        telefoneInput.addEventListener('input', function() {
            this.value = mascaraTelefone(this.value);
        });
        
        telefoneInput.addEventListener('blur', function() {
            const valor = this.value.trim();
            if (valor === '') {
                mostrarErro(this, 'empty');
            } else if (!validarTelefone(valor)) {
                mostrarErro(this, 'invalid');
            } else {
                esconderErro(this);
            }
        });
    }
    
    // Validação de nome
    if (nomeInput) {
        nomeInput.addEventListener('blur', function() {
            const valor = this.value.trim();
            if (valor === '') {
                mostrarErro(this, 'empty');
            } else if (!validarNome(valor)) {
                mostrarErro(this, 'invalid');
            } else {
                esconderErro(this);
            }
        });
        
        nomeInput.addEventListener('input', function() {
            if (this.classList.contains('is-invalid') && validarNome(this.value.trim())) {
                esconderErro(this);
            }
        });
    }
    
    // Máscara e validação de CPF
    if (documentInput) {
        documentInput.addEventListener('input', function() {
            this.value = mascaraCPF(this.value);
        });
        
        documentInput.addEventListener('blur', function() {
            const valor = this.value.trim();
            if (valor === '') {
                mostrarErro(this, 'empty');
            } else if (!validarCPF(valor)) {
                mostrarErro(this, 'invalid');
            } else {
                esconderErro(this);
            }
        });
    }
    
    // Máscara e validação de CEP
    const cepInput = document.getElementById('zip_code');
    if (cepInput) {
        cepInput.addEventListener('input', async function() {
            // Aplicar máscara
            this.value = mascaraCEP(this.value);
            
            // Se CEP estiver completo, buscar na API
            const cepLimpo = this.value.replace(/[^\d]/g, '');
            if (cepLimpo.length === 8) {
                const dadosCEP = await buscarCEP(this.value);
                
                if (dadosCEP) {
                    preencherEndereco(dadosCEP);
                    esconderErro(this);
                } else {
                    mostrarErro(this, 'invalid');
                    esconderOpcoesFreteSimuladas();
                }
            } else if (cepLimpo.length < 8) {
                // Se CEP incompleto, esconder opções de frete
                esconderOpcoesFreteSimuladas();
            }
        });
        
        cepInput.addEventListener('blur', function() {
            const valor = this.value.trim();
            if (valor === '') {
                mostrarErro(this, 'empty');
                esconderOpcoesFreteSimuladas();
            } else if (!validarCEP(valor)) {
                mostrarErro(this, 'invalid');
                esconderOpcoesFreteSimuladas();
            } else {
                esconderErro(this);
            }
        });
    }
    
    // Função para validar campos de entrega obrigatórios
    function validarCamposEntrega() {
        const cepInput = document.getElementById('zip_code');
        const ruaInput = document.getElementById('street');
        const numeroInput = document.getElementById('number');
        const bairroInput = document.getElementById('neighborhood');
        const cidadeInput = document.getElementById('city');
        const estadoSelect = document.getElementById('state');
        const noNumberCheckbox = document.getElementById('noNumber');
        
        let todosValidos = true;
        
        // Validar CEP
        if (cepInput) {
            const valor = cepInput.value.trim();
            if (valor === '') {
                mostrarErro(cepInput, 'empty');
                todosValidos = false;
            } else if (!validarCEP(valor)) {
                mostrarErro(cepInput, 'invalid');
                todosValidos = false;
            } else {
                esconderErro(cepInput);
            }
        }
        
        // Validar rua
        if (ruaInput) {
            const valor = ruaInput.value.trim();
            if (valor === '') {
                mostrarErro(ruaInput, 'empty');
                todosValidos = false;
            } else if (valor.length < 5) {
                mostrarErro(ruaInput, 'invalid');
                todosValidos = false;
            } else {
                esconderErro(ruaInput);
            }
        }
        
        // Validar número (se não marcou S/N)
        if (numeroInput) {
            const valor = numeroInput.value.trim();
            const semNumero = noNumberCheckbox && noNumberCheckbox.checked;
            
            if (!semNumero && valor === '') {
                mostrarErro(numeroInput, 'empty');
                todosValidos = false;
            } else if (!semNumero && valor !== '' && !/^\d+$/.test(valor)) {
                mostrarErro(numeroInput, 'invalid');
                todosValidos = false;
            } else {
                esconderErro(numeroInput);
            }
        }
        
        // Validar bairro
        if (bairroInput) {
            const valor = bairroInput.value.trim();
            if (valor === '') {
                mostrarErro(bairroInput, 'empty');
                todosValidos = false;
            } else if (valor.length < 2) {
                mostrarErro(bairroInput, 'invalid');
                todosValidos = false;
            } else {
                esconderErro(bairroInput);
            }
        }
        
        // Validar cidade
        if (cidadeInput) {
            const valor = cidadeInput.value.trim();
            if (valor === '') {
                mostrarErro(cidadeInput, 'empty');
                todosValidos = false;
            } else if (valor.length < 2) {
                mostrarErro(cidadeInput, 'invalid');
                todosValidos = false;
            } else {
                esconderErro(cidadeInput);
            }
        }
        
        // Validar estado
        if (estadoSelect) {
            const valor = estadoSelect.value;
            if (valor === '') {
                mostrarErro(estadoSelect, 'empty');
                todosValidos = false;
            } else {
                esconderErro(estadoSelect);
            }
        }
        
        return todosValidos;
    }
    
    // Função para avançar para a etapa de pagamento
    function avancarParaPagamento() {
        // Salva dados no localStorage antes de avançar
        salvarDadosEntrega();
        
        // Esconder card-content-2 (entrega)
        const cardContent2 = document.querySelector('.card-content-2');
        if (cardContent2) {
            cardContent2.style.display = 'none';
        }
        
        // Mostrar card-content-3 (pagamento)
        const cardContent3 = document.querySelector('.card-content-3');
        if (cardContent3) {
            cardContent3.style.display = 'block';
        }
        
        // Atualizar os steps
        const contactData = document.getElementById('contact_data');
        const deliveryData = document.getElementById('delivery_data');
        const paymentData = document.getElementById('payment_data');
        
        // Marcar identificação como concluída
        if (contactData) {
            contactData.classList.remove('current');
            contactData.classList.add('done');
        }
        
        // Marcar entrega como concluída
        if (deliveryData) {
            deliveryData.classList.remove('current');
            deliveryData.classList.add('done');
        }
        
        // Marcar pagamento como atual
        if (paymentData) {
            paymentData.classList.add('ativo', 'current');
        }
        
        // Scroll para os steps
        setTimeout(() => {
            scrollParaSteps();
        }, 100);
        
        console.log('✅ Avançado para a etapa de pagamento');
    }
    
    // Event listener para o botão "IR PARA O PAGAMENTO"
    const paymentNextStepBtn = document.getElementById('payment_next_step');
    if (paymentNextStepBtn) {
        paymentNextStepBtn.addEventListener('click', function(e) {
            e.preventDefault();
            console.log('Botão IR PARA O PAGAMENTO clicado');
            
            if (validarCamposEntrega()) {
                console.log('Todos os campos de entrega são válidos');
                avancarParaPagamento();
            } else {
                console.log('Existem campos de entrega inválidos');
            }
        });
    }
    
    // Event listener para o botão "IR PARA A ENTREGA"
    const deliveryNextStepBtn = document.getElementById('delivery_next_step');
    if (deliveryNextStepBtn) {
        deliveryNextStepBtn.addEventListener('click', function(e) {
            e.preventDefault();
            console.log('Botão IR PARA A ENTREGA clicado');
            
            if (validarTodosCampos()) {
                console.log('Todos os campos são válidos, avançando para entrega');
                avancarParaEntrega();
            } else {
                console.log('Existem campos inválidos');
            }
        });
    }
    
    // Event listener para o botão "Voltar" da tela de pagamento
    const backDeliveryBtn = document.getElementById('back-delivery');
    if (backDeliveryBtn) {
        backDeliveryBtn.addEventListener('click', function(e) {
            e.preventDefault();
            console.log('Botão Voltar (pagamento) clicado');
            
            // Verificar se é produto digital
            const stepsContainer = document.querySelector('.steps');
            const isProdutoDigital = stepsContainer && stepsContainer.classList.contains('steps2');
            
            if (isProdutoDigital) {
                console.log('📱 Produto digital detectado - voltando para identificação');
                
                // Esconder card-content-3 (pagamento)
                const cardContent3 = document.querySelector('.card-content-3');
                if (cardContent3) {
                    cardContent3.style.display = 'none';
                }
                
                // Mostrar card-content-1 (identificação) - pular entrega
                const cardContent1 = document.querySelector('.card-content-1');
                if (cardContent1) {
                    cardContent1.style.display = 'block';
                }
                
                // Atualizar os steps
                const contactData = document.getElementById('contact_data');
                const paymentData = document.getElementById('payment_data');
                
                // Marcar identificação como atual
                if (contactData) {
                    contactData.classList.add('ativo', 'current');
                    contactData.classList.remove('done');
                }
                
                // Desmarcar pagamento como atual
                if (paymentData) {
                    paymentData.classList.remove('ativo', 'current');
                }
                
                console.log('↩️ Voltou para a etapa de identificação (produto digital)');
            } else {
                console.log('📦 Produto físico - voltando para entrega');
                
                // Esconder card-content-3 (pagamento)
                const cardContent3 = document.querySelector('.card-content-3');
                if (cardContent3) {
                    cardContent3.style.display = 'none';
                }
                
                // Mostrar card-content-2 (entrega)
                const cardContent2 = document.querySelector('.card-content-2');
                if (cardContent2) {
                    cardContent2.style.display = 'block';
                }
                
                // Atualizar os steps
                const deliveryData = document.getElementById('delivery_data');
                const paymentData = document.getElementById('payment_data');
                
                // Marcar entrega como atual
                if (deliveryData) {
                    deliveryData.classList.add('ativo', 'current');
                    deliveryData.classList.remove('done');
                }
                
                // Desmarcar pagamento como atual
                if (paymentData) {
                    paymentData.classList.remove('ativo', 'current');
                }
                
                console.log('↩️ Voltou para a etapa de entrega');
            }
            
            // Scroll para os steps
            setTimeout(() => {
                const stepsElement = document.querySelector('.steps');
                if (stepsElement) {
                    stepsElement.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            }, 100);
        });
    }
    
    // Event listener para o botão "Voltar" da tela de entrega
    const backContactBtn = document.getElementById('back-contact');
    if (backContactBtn) {
        backContactBtn.addEventListener('click', function(e) {
            e.preventDefault();
            console.log('Botão Voltar (entrega) clicado');
            
            // Esconder card-content-2 (entrega)
            const cardContent2 = document.querySelector('.card-content-2');
            if (cardContent2) {
                cardContent2.style.display = 'none';
            }
            
            // Mostrar card-content-1 (identificação)
            const cardContent1 = document.querySelector('.card-content-1');
            if (cardContent1) {
                cardContent1.style.display = 'block';
            }
            
            // Atualizar os steps
            const contactData = document.getElementById('contact_data');
            const deliveryData = document.getElementById('delivery_data');
            
            // Marcar identificação como atual
            if (contactData) {
                contactData.classList.add('ativo', 'current');
                contactData.classList.remove('done');
            }
            
            // Desmarcar entrega como atual
            if (deliveryData) {
                deliveryData.classList.remove('ativo', 'current');
            }
            
            // Scroll para os steps
            setTimeout(() => {
                scrollParaSteps();
            }, 100);
            
            console.log('↩️ Voltou para a etapa de identificação');
        });
    }
    
    // Função para mostrar tela de loading
    function mostrarTelaLoading() {
        const ajaxLoader = document.querySelector('.ajax-loader');
        if (ajaxLoader) {
            ajaxLoader.style.visibility = 'visible';
            ajaxLoader.style.display = 'flex';
            
            // Desabilitar scroll da página
            document.body.style.overflow = 'hidden';
            
            console.log('⏳ Tela de loading exibida');
        }
    }
    
    // Função para esconder tela de loading
    function esconderTelaLoading() {
        const ajaxLoader = document.querySelector('.ajax-loader');
        if (ajaxLoader) {
            ajaxLoader.style.visibility = 'hidden';
            ajaxLoader.style.display = 'none';
            
            // Reabilitar scroll da página
            document.body.style.overflow = 'auto';
            
            console.log('✅ Tela de loading escondida');
        }
    }
    
    // Event listener para o botão "Gerar Pix"
    const finalizePixBtn = document.getElementById('finalize_pix_purchase');
    if (finalizePixBtn) {
        finalizePixBtn.addEventListener('click', function(e) {
            e.preventDefault();
            console.log('🎯 Botão Gerar Pix clicado');
            
            // Mostrar tela de loading
            mostrarTelaLoading();
            
            // Processar pagamento PIX
            processarPagamentoPix();
        });
    }
    
    // Função para processar pagamento PIX
    async function processarPagamentoPix() {
        try {
            // IMPORTANTE: Salvar dados atualizados antes de processar o pagamento
            salvarDadosLocalStorage();
            salvarDadosEntrega();
            
            // Coletar dados do formulário
            const dadosFormulario = coletarDadosFormulario();
            
            // Coletar parâmetros UTM da URL atual
            const parametrosUTM = coletarParametrosUTM();
            
            // Calcular valor total em centavos (usando a função que considera ofertas e frete)
            const valorTotal = calcularValorTotalCentavos();
            
            // Preparar dados para envio
            const dadosEnvio = {
                nome: dadosFormulario.nome,
                email: dadosFormulario.email,
                cpf: dadosFormulario.cpf,
                telefone: dadosFormulario.telefone,
                valor: valorTotal,
                ...parametrosUTM
            };
            
            console.log('📦 Dados preparados para envio:', dadosEnvio);
            
            // Enviar requisição para pagamento.php
            const response = await fetch('../checkout/pagamento.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(dadosEnvio)
            });
            
            const resultado = await response.json();
            console.log('📥 Resposta do pagamento.php:', resultado);
            
            // Esconder tela de loading
            esconderTelaLoading();
            
            if (resultado.success) {
                // Salvar dados no localStorage
                salvarDadosPagamento(resultado);
                
                // Redirecionar para payment.html com parâmetros UTM
                redirecionarParaPagamento(parametrosUTM);
            } else {
                throw new Error(resultado.message || 'Erro ao gerar PIX');
            }
            
        } catch (error) {
            console.error('❌ Erro ao processar pagamento:', error);
            esconderTelaLoading();
            alert('Erro ao gerar PIX: ' + error.message);
        }
    }
    
    // Função para coletar dados do formulário
    function coletarDadosFormulario() {
        const dadosCheckout = JSON.parse(localStorage.getItem('checkout_form_data') || '{}');
        
        return {
            nome: dadosCheckout.name || document.getElementById('name')?.value || '',
            email: dadosCheckout.email || document.getElementById('email')?.value || '',
            cpf: dadosCheckout.cpf || document.getElementById('document')?.value || '',
            telefone: dadosCheckout.phone || document.getElementById('telephone')?.value || ''
        };
    }
    
    // Função para coletar parâmetros UTM da URL atual
    function coletarParametrosUTM() {
        const urlParams = new URLSearchParams(window.location.search);
        
        return {
            utm_source: urlParams.get('utm_source'),
            utm_medium: urlParams.get('utm_medium'),
            utm_campaign: urlParams.get('utm_campaign'),
            utm_content: urlParams.get('utm_content'),
            utm_term: urlParams.get('utm_term'),
            utm_id: urlParams.get('utm_id'),
            xcod: urlParams.get('xcod'),
            sck: urlParams.get('sck'),
            src: urlParams.get('src')
        };
    }
    
    // Função para calcular valor total em centavos
    function calcularValorTotalCentavos() {
        const quantidade = parseInt(document.querySelector('.input-number input[type="number"]')?.value || '1');
        let subtotal = precoUnitario * quantidade;
        
        // Somar valores das ofertas selecionadas
        const ofertasSelecionadas = document.querySelectorAll('.ob-product.checked');
        let totalOfertas = 0;
        
        ofertasSelecionadas.forEach(oferta => {
            const priceValue = parseFloat(oferta.querySelector('.ob-price-value').value);
            totalOfertas += priceValue;
        });
        
        // Calcular subtotal com ofertas
        const novoSubtotal = subtotal + totalOfertas;
        
        // Adicionar frete se selecionado
        let total = novoSubtotal;
        const freteSpan = document.querySelector('.valor_frete');
        if (freteSpan && freteSpan.textContent !== ' - ') {
            const freteTexto = freteSpan.textContent;
            const freteMatch = freteTexto.match(/R?\$?(\d+,\d+)/);
            if (freteMatch) {
                const valorFrete = parseFloat(freteMatch[1].replace(',', '.'));
                total += valorFrete;
            }
        }
        
        // Converter para centavos
        const totalCentavos = Math.round(total * 100);
        
        console.log('💰 Cálculo do valor total:', {
            produto: precoUnitario * quantidade,
            ofertas: totalOfertas,
            subtotal: novoSubtotal,
            total: total,
            totalCentavos: totalCentavos
        });
        
        return totalCentavos;
    }
    
    // Função para salvar dados do pagamento no localStorage
    function salvarDadosPagamento(resultado) {
        const dadosPagamento = {
            success: resultado.success,
            token: resultado.token,
            pixCode: resultado.pixCode,
            qrCodeUrl: resultado.qrCodeUrl,
            valor: resultado.valor,
            timestamp: new Date().toISOString(),
            logs: resultado.logs
        };
        
        localStorage.setItem('payment_data', JSON.stringify(dadosPagamento));
        localStorage.setItem('payment_token', resultado.token);
        localStorage.setItem('pix_code', resultado.pixCode || '');
        localStorage.setItem('qr_code_url', resultado.qrCodeUrl || '');
        
        console.log('💾 Dados do pagamento salvos no localStorage:', dadosPagamento);
    }
    
    // Função para redirecionar para payment.html com parâmetros UTM
    function redirecionarParaPagamento(parametrosUTM) {
        // Pegar TODOS os parâmetros da URL atual
        const currentParams = new URLSearchParams(window.location.search);
        
        // Adicionar/sobrescrever com os parâmetros UTM fornecidos
        Object.entries(parametrosUTM).forEach(([key, value]) => {
            if (value && value.trim() !== '') {
                currentParams.set(key, value);
            }
        });
        
        // Construir URL final mantendo TODOS os parâmetros
        const urlBase = './payment.html';
        const urlCompleta = currentParams.toString() ? `${urlBase}?${currentParams.toString()}` : urlBase;
        
        console.log('🔄 Redirecionando para:', urlCompleta);
        console.log('📋 Parâmetros mantidos:', Object.fromEntries(currentParams.entries()));
        
        // Redirecionar
        window.location.href = urlCompleta;
    }
    
    // Event listeners para botões de adicionar
    document.addEventListener('click', function(e) {
        if (e.target.closest('.btn-add')) {
            e.preventDefault();
            console.log('Botão + clicado');
            
            const input = document.querySelector('.input-number input[type="number"]');
            let quantidade = parseInt(input.value, 10) || 1;
            
            if (quantidade < 99) {
                quantidade++;
                atualizarValores(quantidade);
            }
        }
        
        if (e.target.closest('.btn-sub')) {
            e.preventDefault();
            console.log('Botão - clicado');
            
            const input = document.querySelector('.input-number input[type="number"]');
            let quantidade = parseInt(input.value, 10) || 1;
            
            if (quantidade > 1) {
                quantidade--;
                atualizarValores(quantidade);
            }
        }
    });
    
    // Event listener para input direto
    document.addEventListener('input', function(e) {
        if (e.target.matches('.input-number input[type="number"]')) {
            console.log('Input alterado');
            
            let quantidade = parseInt(e.target.value, 10);
            if (isNaN(quantidade) || quantidade < 1) {
                quantidade = 1;
            } else if (quantidade > 99) {
                quantidade = 99;
            }
            
            atualizarValores(quantidade);
        }
    });
    
    // Event listeners para salvar dados automaticamente
    const camposParaSalvar = ['email', 'telephone', 'name', 'document'];
    camposParaSalvar.forEach(id => {
        const input = document.getElementById(id);
        if (input) {
            input.addEventListener('blur', function() {
                // Salva dados sempre que um campo perde o foco
                salvarDadosLocalStorage();
            });
        }
    });
    
    // Validações para campos de entrega obrigatórios
    const ruaInput = document.getElementById('street');
    const numeroInput = document.getElementById('number');
    const bairroInput = document.getElementById('neighborhood');
    const cidadeInput = document.getElementById('city');
    const estadoSelect = document.getElementById('state');
    
    // Validação de rua/endereço
    if (ruaInput) {
        ruaInput.addEventListener('blur', function() {
            const valor = this.value.trim();
            if (valor === '') {
                mostrarErro(this, 'empty');
            } else if (valor.length < 5) {
                mostrarErro(this, 'invalid');
            } else {
                esconderErro(this);
            }
        });
    }
    
    // Validação de número
    if (numeroInput) {
        const noNumberCheckbox = document.getElementById('noNumber');
        
        numeroInput.addEventListener('blur', function() {
            const valor = this.value.trim();
            const semNumero = noNumberCheckbox && noNumberCheckbox.checked;
            
            if (!semNumero && valor === '') {
                mostrarErro(this, 'empty');
            } else if (!semNumero && valor !== '' && !/^\d+$/.test(valor)) {
                mostrarErro(this, 'invalid');
            } else {
                esconderErro(this);
            }
        });
        
        // Event listener para checkbox "S/N"
        if (noNumberCheckbox) {
            noNumberCheckbox.addEventListener('change', function() {
                if (this.checked) {
                    numeroInput.value = 'S/N';
                    numeroInput.disabled = true;
                    esconderErro(numeroInput);
                } else {
                    numeroInput.value = '';
                    numeroInput.disabled = false;
                    numeroInput.focus();
                }
            });
        }
    }
    
    // Validação de bairro
    if (bairroInput) {
        bairroInput.addEventListener('blur', function() {
            const valor = this.value.trim();
            if (valor === '') {
                mostrarErro(this, 'empty');
            } else if (valor.length < 2) {
                mostrarErro(this, 'invalid');
            } else {
                esconderErro(this);
            }
        });
    }
    
    // Validação de cidade
    if (cidadeInput) {
        cidadeInput.addEventListener('blur', function() {
            const valor = this.value.trim();
            if (valor === '') {
                mostrarErro(this, 'empty');
            } else if (valor.length < 2) {
                mostrarErro(this, 'invalid');
            } else {
                esconderErro(this);
            }
        });
    }
    
    // Validação de estado
    if (estadoSelect) {
        estadoSelect.addEventListener('blur', function() {
            const valor = this.value;
            if (valor === '') {
                mostrarErro(this, 'empty');
            } else {
                esconderErro(this);
            }
        });
    }
    
    // Event listeners para campos de entrega (salvamento automático)
    const camposEntrega = ['zip_code', 'street', 'number', 'complement', 'neighborhood', 'city', 'state', 'receiver_name'];
    camposEntrega.forEach(id => {
        const input = document.getElementById(id);
        if (input) {
            input.addEventListener('blur', function() {
                // Salva dados de entrega sempre que um campo perde o foco
                salvarDadosEntrega();
            });
        }
    });
    
    // Event listener para seleção de frete
    document.addEventListener('change', function(e) {
        if (e.target.matches('input[name="shipping"]')) {
            console.log('Frete selecionado:', e.target.value);
            
            // Buscar o preço do frete selecionado
            const freteContainer = e.target.closest('.radio-box');
            if (freteContainer) {
                const priceSpan = freteContainer.querySelector('.price');
                if (priceSpan) {
                    // Extrair apenas o valor numérico (ex: "R$14,64" -> "14,64")
                    const precoTexto = priceSpan.textContent;
                    const precoMatch = precoTexto.match(/R?\$?(\d+,\d+)/);
                    
                    if (precoMatch) {
                        const valorFrete = precoMatch[1];
                        atualizarValorFrete(valorFrete);
                    }
                }
            }
            
            salvarDadosEntrega();
        }
    });
    
    // Event listener para redimensionamento da janela
    window.addEventListener('resize', function() {
        initializeSummaryState();
    });
    
    // Funcionalidade das ofertas
    function initializeOffers() {
        console.log('🎁 Inicializando ofertas...');
        
        const buttons = document.querySelectorAll('.ob-purchase');
        console.log(`📊 Encontrados ${buttons.length} botões de oferta`);
        
        // Event listeners para os botões "PEGAR OFERTA"
        buttons.forEach((button, index) => {
            console.log(`🔧 Configurando botão ${index + 1}`);
            
            // Desabilitar eventos do label e checkbox internos
            const label = button.querySelector('label');
            const checkbox = button.querySelector('input[type="checkbox"]');
            
            if (label) {
                label.style.pointerEvents = 'none';
            }
            if (checkbox) {
                checkbox.style.pointerEvents = 'none';
            }
            
            button.addEventListener('click', function(e) {
                console.log('🖱️ Botão clicado!', this);
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();
                
                const product = this.closest('.ob-product');
                console.log('📦 Produto encontrado:', product);
                
                if (product && !product.classList.contains('checked')) {
                    console.log('✅ Adicionando oferta...');
                    
                    // Adicionar classe checked
                    product.classList.add('checked');
                    console.log('✅ Classe checked adicionada');
                    
                    // Marcar checkbox
                    const checkbox = product.querySelector('input[type="checkbox"]');
                    if (checkbox) {
                        checkbox.checked = true;
                        console.log('✅ Checkbox marcado');
                    }
                    
                    // Remover o botão do DOM completamente
                    const buttonElement = this;
                    buttonElement.style.display = 'none';
                    setTimeout(() => {
                        buttonElement.remove();
                        console.log('✅ Botão removido do DOM');
                    }, 10);
                    
                    // Mostrar "OFERTA ADQUIRIDA"
                    const purchasedSpan = product.querySelector('.ob-purchased');
                    if (purchasedSpan) {
                        purchasedSpan.style.display = 'flex';
                        console.log('✅ "OFERTA ADQUIRIDA" exibido');
                    }
                    
                    // Mostrar ícone de lixeira
                    const trashIcon = product.querySelector('.ob-trash');
                    if (trashIcon) {
                        trashIcon.style.display = 'block';
                        console.log('✅ Ícone de lixeira exibido');
                    }
                    
                    // Adicionar oferta ao resumo da compra
                    adicionarOfertaAoResumo(product);
                    
                    console.log('✅ Oferta adquirida:', product.id);
                } else {
                    console.log('⚠️ Produto já está checked ou não encontrado');
                }
            }, true);
        });
        
        // Event listeners para os ícones de lixeira
        document.querySelectorAll('.ob-trash').forEach(trashIcon => {
            trashIcon.addEventListener('click', function(e) {
                console.log('🗑️ Lixeira clicada');
                e.preventDefault();
                e.stopPropagation();
                
                const product = this.closest('.ob-product');
                if (product && product.classList.contains('checked')) {
                    // Remover classe checked
                    product.classList.remove('checked');
                    
                    // Desmarcar checkbox
                    const checkbox = product.querySelector('input[type="checkbox"]');
                    if (checkbox) {
                        checkbox.checked = false;
                    }
                    
                    // Esconder "OFERTA ADQUIRIDA"
                    const purchasedSpan = product.querySelector('.ob-purchased');
                    if (purchasedSpan) {
                        purchasedSpan.style.display = 'none';
                    }
                    
                    // Esconder ícone de lixeira
                    this.style.display = 'none';
                    
                    // Recriar e mostrar botão "PEGAR OFERTA"
                    let purchaseButton = product.querySelector('.ob-purchase');
                    if (!purchaseButton) {
                        // Botão foi removido, precisamos recriá-lo
                        purchaseButton = document.createElement('button');
                        purchaseButton.type = 'button';
                        purchaseButton.className = 'ob-purchase';
                        purchaseButton.innerHTML = `
                            <label class="ob-checkbox">
                                <input type="checkbox">
                                <span class="checkmark"></span>
                            </label>
                            <span>PEGAR OFERTA</span>
                        `;
                        product.appendChild(purchaseButton);
                        
                        // Adicionar event listener ao novo botão
                        purchaseButton.addEventListener('click', function(e) {
                            e.preventDefault();
                            e.stopPropagation();
                            e.stopImmediatePropagation();
                            
                            if (!product.classList.contains('checked')) {
                                product.classList.add('checked');
                                
                                const checkbox = product.querySelector('input[type="checkbox"]');
                                if (checkbox) checkbox.checked = true;
                                
                                this.style.display = 'none';
                                this.remove();
                                
                                const purchasedSpan = product.querySelector('.ob-purchased');
                                if (purchasedSpan) purchasedSpan.style.display = 'flex';
                                
                                const trashIcon = product.querySelector('.ob-trash');
                                if (trashIcon) trashIcon.style.display = 'block';
                                
                                adicionarOfertaAoResumo(product);
                            }
                        }, true);
                    } else {
                        purchaseButton.style.display = 'block';
                    }
                    
                    // Remover oferta do resumo da compra
                    removerOfertaDoResumo(product);
                    
                    console.log('🗑️ Oferta removida:', product.id);
                }
            });
        });
        
        console.log('✅ Ofertas inicializadas');
    }
    
    // Função para adicionar oferta ao resumo da compra
    function adicionarOfertaAoResumo(product) {
        const productId = product.id;
        const title = product.querySelector('.ob-title').textContent;
        const price = product.querySelector('.ob-price').textContent;
        const priceValue = parseFloat(product.querySelector('.ob-price-value').value);
        
        // Mostrar o preview das ofertas
        const obPreview = document.querySelector('.ob-preview');
        if (obPreview) {
            obPreview.style.display = 'block';
        }
        
        // Adicionar item ao preview-content
        const previewContent = document.querySelector('.ob-preview-content');
        if (previewContent) {
            const offerHTML = `
                <div class="ob-info" data-offer-id="${productId}">
                    <div style="display: flex; align-items: center; justify-content: space-between;">
                        <div style="display: flex; align-items: center;">
                            <div style="margin-left: 8px;">
                                <div class="ob-title">${title}</div>
                                <div class="ob-description">Oferta especial</div>
                                <div class="ob-price-container">
                                    <span class="ob-price">${price}</span>
                                </div>
                            </div>
                        </div>
                        <a class="ob-trash" onclick="removerOfertaPorId('${productId}')" style="display: block; cursor: pointer;"></a>
                    </div>
                </div>
            `;
            previewContent.innerHTML += offerHTML;
        }
        
        // Atualizar valores totais
        atualizarTotalComOfertas();
        
        console.log('📦 Oferta adicionada ao resumo:', title, price);
    }
    
    // Função para remover oferta do resumo da compra
    function removerOfertaDoResumo(product) {
        const productId = product.id;
        
        // Remover item do preview-content
        const offerElement = document.querySelector(`[data-offer-id="${productId}"]`);
        if (offerElement) {
            offerElement.remove();
        }
        
        // Verificar se ainda há ofertas no preview
        const previewContent = document.querySelector('.ob-preview-content');
        const obPreview = document.querySelector('.ob-preview');
        
        if (previewContent && previewContent.children.length === 0 && obPreview) {
            obPreview.style.display = 'none';
        }
        
        // Atualizar valores totais
        atualizarTotalComOfertas();
        
        console.log('🗑️ Oferta removida do resumo:', productId);
    }
    
    // Função global para remover oferta por ID (chamada pelo onclick)
    window.removerOfertaPorId = function(productId) {
        const product = document.getElementById(productId);
        if (product && product.classList.contains('checked')) {
            // Simular clique no ícone de lixeira da oferta original
            const trashIcon = product.querySelector('.ob-trash');
            if (trashIcon) {
                trashIcon.click();
            }
        }
    };
    
    // Função para atualizar total com ofertas
    window.atualizarTotalComOfertas = function() {
        const quantidade = parseInt(document.querySelector('.input-number input[type="number"]')?.value || '1');
        let subtotal = precoUnitario * quantidade;
        
        // Somar valores das ofertas selecionadas
        const ofertasSelecionadas = document.querySelectorAll('.ob-product.checked');
        let totalOfertas = 0;
        
        ofertasSelecionadas.forEach(oferta => {
            const priceValue = parseFloat(oferta.querySelector('.ob-price-value').value);
            totalOfertas += priceValue;
        });
        
        // Calcular novo subtotal
        const novoSubtotal = subtotal + totalOfertas;
        
        // Atualizar subtotal
        const subtotalSpans = document.querySelectorAll('.subtotal-value');
        subtotalSpans.forEach(span => {
            span.textContent = formatarValorBrasileiro(novoSubtotal);
        });
        
        // Calcular total com frete
        let total = novoSubtotal;
        const freteSpan = document.querySelector('.valor_frete');
        if (freteSpan && freteSpan.textContent !== ' - ') {
            const freteTexto = freteSpan.textContent;
            const freteMatch = freteTexto.match(/R?\$?([\d.]+,\d+)/);
            if (freteMatch) {
                const valorFrete = parseFloat(freteMatch[1].replace(/\./g, '').replace(',', '.'));
                total += valorFrete;
            }
        }
        
        // Atualizar valor total
        const valorTotalSpans = document.querySelectorAll('.valor_total');
        valorTotalSpans.forEach(span => {
            span.textContent = `R$ ${formatarValorBrasileiro(total)}`;
        });
        
        // IMPORTANTE: Atualizar localStorage com o valor total correto
        localStorage.setItem('checkout_total', total.toString());
        localStorage.setItem('checkout_subtotal', novoSubtotal.toString());
        localStorage.setItem('checkout_ofertas_total', totalOfertas.toString());
        localStorage.setItem('checkout_frete_total', (total - novoSubtotal).toString());
        
        console.log('💰 Total atualizado:', {
            subtotal: novoSubtotal.toFixed(2),
            ofertas: totalOfertas.toFixed(2),
            total: total.toFixed(2)
        });
    }
    
    // Inicializar
    initializeSummaryState();
    atualizarValores(1);
    initializeOffers(); // Inicializar funcionalidade das ofertas
    
    // Inicializar suporte ao preview se estiver no modo preview
    if (window.location.search.includes('preview=1') || window.location.search.includes('preview=2')) {
        initializePreviewMode();
    }
    
    console.log('Script carregado completamente');
});

// ========== SUPORTE AO PREVIEW EM TEMPO REAL ==========

// Inicializar modo preview
function initializePreviewMode() {
    console.log('🎭 Modo preview ativado');
    console.log('🔍 URL atual:', window.location.href);
    console.log('🔍 Parent window:', window.parent !== window ? 'Sim' : 'Não');
    
    // Escutar mensagens do painel admin
    window.addEventListener('message', function(event) {
        console.log('📨 Mensagem recebida:', event.data);
        if (event.data && event.data.type === 'updatePreview') {
            console.log('📡 Recebendo atualização do preview:', event.data.data);
            aplicarConfiguracoesPreview(event.data.data);
        }
    });
    
    // Notificar que o preview está pronto
    setTimeout(() => {
        if (window.parent !== window) {
            console.log('✅ Notificando parent que preview está pronto');
            window.parent.postMessage({
                type: 'previewReady'
            }, '*');
        }
    }, 1000);
}

// Aplicar configurações do preview em tempo real
function aplicarConfiguracoesPreview(config) {
    try {
        // 🧹 LIMPAR localStorage de valores antigos ao receber novo checkout
        localStorage.removeItem('checkout_total');
        localStorage.removeItem('checkout_subtotal');
        localStorage.removeItem('checkout_ofertas_total');
        localStorage.removeItem('checkout_frete_total');
        console.log('🧹 localStorage limpo para novo checkout');
        
        // Atualizar preço
        if (config.product_price && config.product_price !== '') {
            // Converter formato brasileiro (1.234,56) para float
            const priceString = config.product_price.toString().replace(/\./g, '').replace(',', '.');
            const novoPreco = parseFloat(priceString);
            if (!isNaN(novoPreco)) {
                precoUnitario = novoPreco;
                console.log('💰 Preço atualizado para:', novoPreco, '(original:', config.product_price, ')');
                
                // Atualizar valores na interface diretamente
                const valorFormatado = formatarValorBrasileiro(novoPreco);
                
                // Atualizar subtotal
                const subtotalElements = document.querySelectorAll('.subtotal-value');
                subtotalElements.forEach(element => {
                    element.textContent = ` ${valorFormatado} `;
                });
                
                // Atualizar total
                const totalElements = document.querySelectorAll('.valor_total');
                totalElements.forEach(element => {
                    element.textContent = `R$ ${valorFormatado}`;
                });
                
                // 🔄 Recalcular e salvar valores no localStorage com o novo preço
                // Calcular subtotal (preço * quantidade)
                const quantidade = parseInt(document.querySelector('.input-number input[type="number"]')?.value || '1');
                let subtotal = novoPreco * quantidade;
                
                // Somar ofertas selecionadas
                const ofertasSelecionadas = document.querySelectorAll('.ob-product.checked');
                let totalOfertas = 0;
                ofertasSelecionadas.forEach(oferta => {
                    const priceValue = parseFloat(oferta.querySelector('.ob-price-value')?.value || '0');
                    if (!isNaN(priceValue)) {
                        totalOfertas += priceValue;
                    }
                });
                
                // Calcular subtotal com ofertas
                const novoSubtotal = subtotal + totalOfertas;
                
                // Adicionar frete se selecionado
                let total = novoSubtotal;
                const freteSpan = document.querySelector('.valor_frete');
                if (freteSpan && freteSpan.textContent !== ' - ') {
                    const freteTexto = freteSpan.textContent;
                    const freteMatch = freteTexto.match(/R?\$?(\d+,\d+)/);
                    if (freteMatch) {
                        const valorFrete = parseFloat(freteMatch[1].replace(',', '.'));
                        if (!isNaN(valorFrete)) {
                            total += valorFrete;
                        }
                    }
                }
                
                // Salvar no localStorage
                localStorage.setItem('checkout_total', total.toString());
                localStorage.setItem('checkout_subtotal', novoSubtotal.toString());
                localStorage.setItem('checkout_ofertas_total', totalOfertas.toString());
                localStorage.setItem('checkout_frete_total', (total - novoSubtotal).toString());
                console.log('💾 Valores salvos no localStorage:', {
                    subtotal: novoSubtotal,
                    ofertas: totalOfertas,
                    frete: total - novoSubtotal,
                    total: total
                });
            }
        }
        
        // Atualizar nome do produto
        if (config.product_name && config.product_name !== '') {
            nomeProduto = config.product_name;
            const nomeElements = document.querySelectorAll('.name_product_card');
            nomeElements.forEach(element => {
                element.textContent = config.product_name;
            });
            localStorage.setItem('checkout_product_title', config.product_name);
            console.log('📝 Nome atualizado para:', config.product_name);
        }
        
        // Atualizar descrição do produto
        if (config.product_description && config.product_description !== '') {
            const descElements = document.querySelectorAll('.info-small');
            descElements.forEach(element => {
                element.textContent = config.product_description;
            });
            console.log('📄 Descrição atualizada para:', config.product_description);
        }
        
        // Atualizar imagem do produto
        if (config.product_image && config.product_image !== '') {
            const imgElements = document.querySelectorAll('.product-img');
            imgElements.forEach(element => {
                element.src = config.product_image;
                element.onerror = function() {
                    this.src = 'https://cloudfox-files.s3.amazonaws.com/produto.svg';
                };
            });
            console.log('🖼️ Imagem atualizada para:', config.product_image);
        }
        
        // Atualizar logo da empresa
        if (config.company_logo && config.company_logo !== '') {
            const logoElements = document.querySelectorAll('.checkout-logo');
            logoElements.forEach(element => {
                element.src = config.company_logo;
                element.onerror = function() {
                    this.src = 'https://cloudfox-digital-products.s3.amazonaws.com/uploads/user/7YL9jZDV96gp4qm/public/stores/nQ7kZ7nLVD30eJL/logo/MtvVsDb1gO3z97UxLtcWCwJjT6PMBUY582wIGX7d.png';
                };
            });
            console.log('🏢 Logo atualizado para:', config.company_logo);
        }
        
        // Atualizar nome da empresa no footer
        if (config.company_name && config.company_name !== '') {
            const footerElements = document.querySelectorAll('footer p');
            footerElements.forEach(element => {
                if (element.textContent.includes('©')) {
                    element.textContent = `© 2026 ${config.company_name}`;
                }
            });
            console.log('🏪 Nome da empresa atualizado para:', config.company_name);
        }
        
        // Atualizar badge de pagamento seguro
        const safeBadge = document.getElementById('safe-payment-badge');
        const safeBadgeImg = document.getElementById('safe-payment-badge-img');
        const safeBuySidebar = document.getElementById('safe-buy-sidebar');
        
        console.log('🛡️ DEBUG - Atualizando badge no preview:', {
            show_safe_badge: config.show_safe_badge,
            show_safe_badge_type: typeof config.show_safe_badge,
            safe_badge_image: config.safe_badge_image,
            safeBadge: !!safeBadge,
            safeBadgeCurrentClasses: safeBadge?.className,
            safeBuySidebar: !!safeBuySidebar,
            safeBuySidebarCurrentClasses: safeBuySidebar?.className
        });
        
        if (config.show_safe_badge !== undefined) {
            if (config.show_safe_badge === false || config.show_safe_badge === 'false') {
                if (safeBadge) {
                    safeBadge.classList.add('safe-badge-hidden');
                    console.log('🛡️ Classe adicionada ao safeBadge:', safeBadge.className);
                }
                if (safeBuySidebar) {
                    safeBuySidebar.classList.add('safe-badge-hidden');
                    console.log('🛡️ Classe adicionada ao safeBuySidebar:', safeBuySidebar.className);
                }
                console.log('🛡️ Badge seguro ocultado no preview');
            } else {
                if (safeBadge) {
                    safeBadge.classList.remove('safe-badge-hidden');
                    console.log('🛡️ Classe removida do safeBadge:', safeBadge.className);
                }
                if (safeBuySidebar) {
                    safeBuySidebar.classList.remove('safe-badge-hidden');
                    console.log('🛡️ Classe removida do safeBuySidebar:', safeBuySidebar.className);
                }
                console.log('🛡️ Badge seguro exibido no preview');
            }
        }
        
        // Atualizar imagem do badge seguro
        if (config.safe_badge_image && config.safe_badge_image !== '' && safeBadgeImg) {
            console.log('🛡️ Atualizando imagem do badge para:', config.safe_badge_image);
            safeBadgeImg.src = config.safe_badge_image;
            safeBadgeImg.onerror = function() {
                console.log('❌ Erro ao carregar imagem do badge, usando fallback');
                this.src = 'https://pay.controledavida.fun/assets/img/safe-payment.svg?v=1773793484';
            };
            console.log('🛡️ Imagem do badge atualizada no preview');
        }
        
        // Controlar visibilidade da logo da empresa
        const companyLogos = document.querySelectorAll('.checkout-logo');
        
        console.log('🏪 DEBUG - Atualizando logo no preview:', {
            show_company_logo: config.show_company_logo,
            show_company_logo_type: typeof config.show_company_logo,
            companyLogos: companyLogos.length
        });
        
        if (config.show_company_logo !== undefined) {
            if (config.show_company_logo === false || config.show_company_logo === 'false') {
                companyLogos.forEach(logo => {
                    logo.classList.add('company-logo-hidden');
                    console.log('🏪 Classe adicionada à logo:', logo.className);
                });
                console.log('🏪 Logo da empresa ocultada no preview');
            } else {
                companyLogos.forEach(logo => {
                    logo.classList.remove('company-logo-hidden');
                    console.log('🏪 Classe removida da logo:', logo.className);
                });
                console.log('🏪 Logo da empresa exibida no preview');
            }
        }
        
        // Aplicar cores personalizadas
        if (config.colors) {
            aplicarCoresPersonalizadas(config.colors);
        }
        
        // Atualizar ofertas
        if (config.offers) {
            atualizarOfertas(config.offers);
        }
        
        // Atualizar opções de frete - EXIBIR NO PREVIEW
        if (config.frete && config.frete.enabled && config.frete.opcoes && config.frete.opcoes.length > 0) {
            // Armazenar configurações de frete globalmente para uso posterior
            window.freteConfigPreview = config.frete.opcoes;
            console.log('🚚 Configurações de frete recebidas:', config.frete.opcoes);
            
            // Exibir opções de frete no preview
            atualizarOpcoesFretePreview(config.frete.opcoes);
        } else {
            // Se não houver opções de frete ou frete desativado, limpar
            window.freteConfigPreview = [];
            const radiosContainer = document.querySelector('.col-12.radios.px-0.mb-2');
            if (radiosContainer) {
                radiosContainer.innerHTML = '';
                console.log('🧹 Opções de frete limpas');
            }
        }
        
        // Garantir que o valor do frete está como " - " inicialmente
        const freteSpans = document.querySelectorAll('.valor_frete');
        freteSpans.forEach(span => {
            span.textContent = ' - ';
        });
        console.log('💰 Valor do frete resetado para " - "');
        
        // Atualizar depoimentos (sempre chamar, mesmo se vazio, para limpar)
        atualizarDepoimentos(config.depoimentos || []);
        
        // Verificar se é produto digital
        if (config.is_digital === true) {
            console.log('📱 Produto digital detectado no preview');
            ajustarCheckoutParaDigital();
        } else {
            console.log('📦 Produto físico detectado no preview - restaurando modo normal');
            
            // Se não for digital, garantir que está no modo normal (3 steps)
            const deliveryStep = document.getElementById('delivery_data');
            if (deliveryStep) {
                deliveryStep.style.display = 'block';
                console.log('✅ Step de entrega exibido');
            }
            
            // NÃO mostrar o card-content-2 automaticamente - ele só deve aparecer ao clicar no botão
            // Apenas garantir que ele não está com display:none forçado
            const cardContent2 = document.querySelector('.card-content-2');
            if (cardContent2 && cardContent2.style.display === 'block') {
                // Se já estava visível (usuário já avançou), manter
                console.log('✅ Formulário de entrega já estava visível');
            }
            
            // Mostrar linha do frete no resumo (ambos os locais: sidebar e mobile)
            const freteRows = document.querySelectorAll('.row.justify-content-between.p-0.mb-2');
            freteRows.forEach(row => {
                const freteLabel = row.querySelector('.subtotal');
                if (freteLabel && freteLabel.textContent.includes('Frete')) {
                    row.style.display = 'flex';
                    console.log('✅ Linha do frete exibida');
                }
            });
            
            const paymentStep = document.getElementById('payment_data');
            if (paymentStep) {
                const paymentNumber = paymentStep.querySelector('.step-number .number');
                if (paymentNumber) {
                    paymentNumber.textContent = '3';
                    console.log('✅ Numeração do step de pagamento ajustada para 3');
                }
            }
            
            const stepsContainer = document.querySelector('.steps');
            if (stepsContainer) {
                stepsContainer.classList.add('steps3');
                stepsContainer.classList.remove('steps2');
                console.log('✅ Classe steps3 adicionada');
            }
            
            // Restaurar texto do botão
            const deliveryNextStepBtn = document.getElementById('delivery_next_step');
            if (deliveryNextStepBtn) {
                deliveryNextStepBtn.textContent = 'IR PARA A ENTREGA';
                console.log('✅ Texto do botão restaurado para "IR PARA A ENTREGA"');
                
                // IMPORTANTE: Restaurar o comportamento original do botão
                // Remover o event listener de produto digital e restaurar o padrão
                const newBtn = deliveryNextStepBtn.cloneNode(true);
                deliveryNextStepBtn.parentNode.replaceChild(newBtn, deliveryNextStepBtn);
                
                // Adicionar o event listener padrão (ir para entrega)
                newBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    console.log('Botão IR PARA A ENTREGA clicado (produto físico)');
                    
                    // Validação simples dos campos obrigatórios
                    const emailInput = document.getElementById('email');
                    const telefoneInput = document.getElementById('telephone');
                    const nomeInput = document.getElementById('name');
                    const documentInput = document.getElementById('document');
                    
                    let camposValidos = true;
                    
                    if (!emailInput || !emailInput.value.trim()) {
                        camposValidos = false;
                        console.log('Email não preenchido');
                    }
                    if (!telefoneInput || !telefoneInput.value.trim()) {
                        camposValidos = false;
                        console.log('Telefone não preenchido');
                    }
                    if (!nomeInput || !nomeInput.value.trim()) {
                        camposValidos = false;
                        console.log('Nome não preenchido');
                    }
                    if (!documentInput || !documentInput.value.trim()) {
                        camposValidos = false;
                        console.log('Documento não preenchido');
                    }
                    
                    if (camposValidos) {
                        console.log('Todos os campos são válidos, avançando para entrega');
                        // Comportamento padrão: ir para a tela de entrega
                        const cardContent1 = document.querySelector('.card-content-1');
                        const cardContent2 = document.querySelector('.card-content-2');
                        
                        if (cardContent1) cardContent1.style.display = 'none';
                        if (cardContent2) cardContent2.style.display = 'block';
                        
                        // Atualizar steps
                        const contactData = document.getElementById('contact_data');
                        const deliveryData = document.getElementById('delivery_data');
                        
                        if (contactData) {
                            contactData.classList.remove('current');
                            contactData.classList.add('done');
                        }
                        
                        if (deliveryData) {
                            deliveryData.classList.add('ativo', 'current');
                        }
                        
                        // Scroll suave para os steps
                        const stepsElement = document.querySelector('.steps');
                        if (stepsElement) {
                            stepsElement.scrollIntoView({ behavior: 'smooth', block: 'start' });
                        }
                    } else {
                        console.log('Existem campos inválidos');
                    }
                });
                
                console.log('✅ Comportamento do botão restaurado para modo físico');
            }
        }
        
        // Controlar visibilidade dos depoimentos
        if (config.depoimentos_enabled !== undefined) {
            const depoimentosSections = document.querySelectorAll('.depoimentos-section');
            if (config.depoimentos_enabled === true) {
                // Mostrar depoimentos
                depoimentosSections.forEach(section => {
                    section.classList.remove('hidden');
                });
                console.log('⭐ Depoimentos ativados');
            } else {
                // Ocultar depoimentos
                depoimentosSections.forEach(section => {
                    section.classList.add('hidden');
                });
                console.log('🚫 Depoimentos desativados');
            }
        }
        
        console.log('✅ Preview atualizado com sucesso');
        
    } catch (error) {
        console.error('❌ Erro ao aplicar configurações do preview:', error);
    }
}

// Função para atualizar ofertas dinamicamente
function atualizarOfertas(offersConfig) {
    const obContainer = document.querySelector('.ob-container');
    
    if (!obContainer) {
        console.log('⚠️ Container de ofertas não encontrado');
        return;
    }
    
    // Se ofertas não devem ser visíveis, esconder container
    if (!offersConfig.visible || !offersConfig.items || offersConfig.items.length === 0) {
        obContainer.style.display = 'none';
        console.log('🎁 Ofertas ocultadas');
        return;
    }
    
    // Mostrar container
    obContainer.style.display = 'block';
    
    const obBody = obContainer.querySelector('.ob-body');
    const obHeader = obContainer.querySelector('.ob-header .ob-label .text-green');
    
    if (!obBody) {
        console.log('⚠️ Body de ofertas não encontrado');
        return;
    }
    
    // Atualizar contador de ofertas no header
    if (obHeader) {
        const count = offersConfig.items.length;
        obHeader.textContent = `${count} ${count === 1 ? 'oferta disponível' : 'ofertas disponíveis'}`;
    }
    
    // Limpar ofertas existentes
    obBody.innerHTML = '';
    
    // Criar novas ofertas
    offersConfig.items.forEach((offer, index) => {
        const offerId = `preview-offer-${index}`;
        const leftPosition = index * 100;
        
        // Função helper para formatar preço
        function formatPrice(price) {
            if (!price) return '0,00';
            
            // Converter para string e remover espaços
            let priceStr = String(price).trim();
            
            // Se já tem vírgula, está formatado
            if (priceStr.includes(',')) {
                return priceStr;
            }
            
            // Se não tem vírgula, precisa formatar
            // Remover pontos (separador de milhares se houver)
            priceStr = priceStr.replace(/\./g, '');
            
            // Converter para número e dividir por 100 para obter centavos
            const numValue = parseFloat(priceStr) / 100;
            
            // Formatar com 2 casas decimais e vírgula
            return numValue.toFixed(2).replace('.', ',');
        }
        
        // Limpar e converter preços
        const priceFormatted = formatPrice(offer.price);
        const oldPriceFormatted = offer.old_price || offer.oldPrice ? formatPrice(offer.old_price || offer.oldPrice) : '';
        
        // Converter para valor numérico (para cálculos)
        const priceValue = parseFloat(priceFormatted.replace(',', '.'));
        
        const offerHTML = `
            <div class="ob-product" id="${offerId}" style="left: ${leftPosition}%; height: 141px;">
                <input type="hidden" class="ob-rule-id" value="preview-rule">
                <input type="hidden" class="ob-plan-id" value="preview-plan-${index}">
                <input type="hidden" class="ob-price-value" value="${priceValue}">
                <input type="hidden" class="ob-plan-saved" value="${priceFormatted}">
                
                <span class="ob-purchased">
                    <span>OFERTA ADQUIRIDA</span>
                </span>
                
                <div class="ob-image">
                    <img src="${offer.image || 'https://cloudfox-files.s3.amazonaws.com/produto.svg'}" 
                         alt="${offer.title || offer.name || 'Oferta'}" 
                         style="width: 60px; height: 60px; object-fit: cover; border-radius: 8px;"
                         onerror="this.src='https://cloudfox-files.s3.amazonaws.com/produto.svg'">
                </div>
                
                <div class="ob-info">
                    <div class="ob-text">
                        <div class="ob-title">${offer.title || offer.name || 'Oferta Especial'}</div>
                        <div class="ob-description">${offer.description || 'Aproveite esta oferta'}</div>
                        ${oldPriceFormatted ? `<div class="ob-old-price">R$ ${oldPriceFormatted}</div>` : ''}
                        <div class="ob-price">R$ ${priceFormatted}</div>
                    </div>
                </div>
                
                <a class="ob-trash"></a>
                
                <button type="button" class="ob-purchase">
                    <label class="ob-checkbox">
                        <input type="checkbox">
                        <span class="checkmark"></span>
                    </label>
                    <span>PEGAR OFERTA</span>
                </button>
            </div>
        `;
        
        obBody.innerHTML += offerHTML;
    });
    
    // Atualizar altura do body baseado na primeira oferta
    const firstOffer = obBody.querySelector('.ob-product');
    if (firstOffer) {
        const offerHeight = firstOffer.offsetHeight;
        obBody.style.height = `${offerHeight + 40}px`;
    }
    
    // Reinicializar event listeners das ofertas
    reinicializarEventListenersOfertas();
    
    console.log(`🎁 ${offersConfig.items.length} ofertas atualizadas no preview`);
}

// Função para reinicializar event listeners das ofertas
function reinicializarEventListenersOfertas() {
    const obProducts = document.querySelectorAll('.ob-product');
    
    obProducts.forEach(product => {
        const purchaseBtn = product.querySelector('.ob-purchase');
        const trashBtn = product.querySelector('.ob-trash');
        const checkbox = product.querySelector('input[type="checkbox"]');
        
        if (purchaseBtn && checkbox) {
            // Remover listeners antigos clonando o elemento
            const newPurchaseBtn = purchaseBtn.cloneNode(true);
            purchaseBtn.parentNode.replaceChild(newPurchaseBtn, purchaseBtn);
            
            // Adicionar novo listener
            newPurchaseBtn.addEventListener('click', function(e) {
                e.preventDefault();
                const newCheckbox = this.querySelector('input[type="checkbox"]');
                
                if (product.classList.contains('checked')) {
                    product.classList.remove('checked');
                    newCheckbox.checked = false;
                } else {
                    product.classList.add('checked');
                    newCheckbox.checked = true;
                }
                
                // Atualizar valores totais
                if (typeof atualizarValores === 'function') {
                    const quantidade = parseInt(document.querySelector('.input-number input[type="number"]')?.value || '1');
                    atualizarValores(quantidade);
                }
            });
        }
        
        if (trashBtn) {
            // Remover listeners antigos clonando o elemento
            const newTrashBtn = trashBtn.cloneNode(true);
            trashBtn.parentNode.replaceChild(newTrashBtn, trashBtn);
            
            // Adicionar novo listener
            newTrashBtn.addEventListener('click', function(e) {
                e.preventDefault();
                product.classList.remove('checked');
                if (checkbox) checkbox.checked = false;
                
                // Atualizar valores totais
                if (typeof atualizarValores === 'function') {
                    const quantidade = parseInt(document.querySelector('.input-number input[type="number"]')?.value || '1');
                    atualizarValores(quantidade);
                }
            });
        }
    });
    
    // Reinicializar navegação de ofertas
    const obPrev = document.querySelector('.ob-prev');
    const obNext = document.querySelector('.ob-next');
    
    if (obPrev && obNext) {
        let currentOfferIndex = 0;
        const totalOffers = obProducts.length;
        
        const updateOfferPosition = () => {
            obProducts.forEach((product, index) => {
                product.style.left = `${(index - currentOfferIndex) * 100}%`;
            });
            
            obPrev.disabled = currentOfferIndex === 0;
            obNext.disabled = currentOfferIndex >= totalOffers - 1;
        };
        
        // Remover listeners antigos
        const newObPrev = obPrev.cloneNode(true);
        const newObNext = obNext.cloneNode(true);
        obPrev.parentNode.replaceChild(newObPrev, obPrev);
        obNext.parentNode.replaceChild(newObNext, obNext);
        
        // Adicionar novos listeners
        newObPrev.addEventListener('click', () => {
            if (currentOfferIndex > 0) {
                currentOfferIndex--;
                updateOfferPosition();
            }
        });
        
        newObNext.addEventListener('click', () => {
            if (currentOfferIndex < totalOffers - 1) {
                currentOfferIndex++;
                updateOfferPosition();
            }
        });
        
        updateOfferPosition();
    }
}

// Função para atualizar opções de frete do preview (GLOBAL)
function atualizarOpcoesFretePreview(freteOpcoes) {
    const radiosContainer = document.querySelector('.col-12.radios.px-0.mb-2');
    
    if (!radiosContainer || !freteOpcoes || freteOpcoes.length === 0) {
        console.log('⚠️ Container de frete não encontrado ou sem opções');
        return;
    }
    
    // Esconder mensagem de CEP vazio
    const emptyShipping = radiosContainer.querySelector('.emptyShipping');
    if (emptyShipping) {
        emptyShipping.style.display = 'none';
    }
    
    // Limpar container
    radiosContainer.innerHTML = '';
    
    // Criar HTML para cada opção de frete
    freteOpcoes.forEach((frete, index) => {
        const freteId = `frete-preview-${index}`;
        const isDestaque = frete.destaque || false;
        const isSelected = frete.selected || false;
        
        // Estilo do border baseado em destaque
        const borderStyle = isDestaque 
            ? 'border: 1px solid rgb(46, 133, 236); filter: drop-shadow(rgba(46, 133, 236, 0.3) 0px 0px 7px);'
            : 'border: 1px solid rgb(221, 221, 221); filter: none;';
        
        const freteHTML = `
            <div style="cursor: pointer; ${borderStyle}" class="radio-box d-flex flex-reverse m-top mb-2">
                <div class="radio-div">
                    <div class="radio-container">
                        <label for="${freteId}">
                            <input class="radio-frete" type="radio" data-shipping="${freteId}" id="${freteId}" value="${freteId}" name="shipping" ${isSelected ? 'checked' : ''}>
                            <div class="custom-radio"><span></span></div>
                        </label>
                    </div>
                </div>
                <div style="margin-right:4px; margin-left:24px">
                    <label for="${frete.name || 'Frete'}">
                        <strong class="shipping-name">${frete.name || 'Frete'}<span class="price" id="${freteId}-price"><small>R$</small>${frete.price || '0,00'}</span></strong>
                        <p class="desc-frete" style="margin-top:7px">${frete.description || 'Entrega padrão'}</p>
                    </label>
                </div>
            </div>
        `;
        
        radiosContainer.innerHTML += freteHTML;
    });
    
    // Adicionar event listeners para as opções de frete
    const radioBoxes = radiosContainer.querySelectorAll('.radio-box');
    radioBoxes.forEach(radioBox => {
        radioBox.addEventListener('click', function() {
            // Remover seleção de todos os radio boxes
            const allRadioBoxes = document.querySelectorAll('.radio-box');
            allRadioBoxes.forEach(box => {
                box.classList.remove('selected');
                box.style.border = '1px solid rgb(221, 221, 221)';
                box.style.filter = 'none';
                const radio = box.querySelector('input[type="radio"]');
                if (radio) radio.checked = false;
            });
            
            // Selecionar o radio box clicado
            this.classList.add('selected');
            this.style.border = '1px solid rgb(46, 133, 236)';
            this.style.filter = 'drop-shadow(rgba(46, 133, 236, 0.3) 0px 0px 7px)';
            
            const radio = this.querySelector('input[type="radio"]');
            if (radio) {
                radio.checked = true;
                const priceSpan = this.querySelector('.price');
                if (priceSpan) {
                    const precoTexto = priceSpan.textContent;
                    const precoMatch = precoTexto.match(/R?\$?(\d+,\d+)/);
                    if (precoMatch) {
                        const valorFrete = precoMatch[1];
                        const freteSpans = document.querySelectorAll('.valor_frete');
                        freteSpans.forEach(span => {
                            span.textContent = `R$ ${valorFrete}`;
                        });
                    }
                }
            }
        });
    });
    
    // Atualizar valor do frete com a opção selecionada
    const opcaoSelecionada = freteOpcoes.find(f => f.selected);
    if (opcaoSelecionada && opcaoSelecionada.price) {
        const freteSpans = document.querySelectorAll('.valor_frete');
        freteSpans.forEach(span => {
            span.textContent = `R$ ${opcaoSelecionada.price}`;
        });
    } else if (freteOpcoes.length > 0 && freteOpcoes[0].price) {
        const freteSpans = document.querySelectorAll('.valor_frete');
        freteSpans.forEach(span => {
            span.textContent = `R$ ${freteOpcoes[0].price}`;
        });
    }
    
    console.log(`🚚 ${freteOpcoes.length} opções de frete atualizadas no preview`);
}

// Função para ajustar checkout para produto digital (GLOBAL)
function ajustarCheckoutParaDigital() {
    console.log('🔧 Ajustando checkout para produto digital...');
    
    // Ocultar step de entrega
    const deliveryStep = document.getElementById('delivery_data');
    if (deliveryStep) {
        deliveryStep.style.display = 'none';
        console.log('✅ Step de entrega ocultado');
    }
    
    // Ocultar o card-content-2 (formulário de entrega)
    const cardContent2 = document.querySelector('.card-content-2');
    if (cardContent2) {
        cardContent2.style.display = 'none';
        console.log('✅ Formulário de entrega ocultado');
    }
    
    // Ocultar linha do frete no resumo (ambos os locais: sidebar e mobile)
    const freteRows = document.querySelectorAll('.row.justify-content-between.p-0.mb-2');
    freteRows.forEach(row => {
        const freteLabel = row.querySelector('.subtotal');
        if (freteLabel && freteLabel.textContent.includes('Frete')) {
            row.style.display = 'none';
            console.log('✅ Linha do frete ocultada');
        }
    });
    
    // Ajustar numeração dos steps para produto digital
    const paymentStep = document.getElementById('payment_data');
    if (paymentStep) {
        const paymentNumber = paymentStep.querySelector('.step-number .number');
        if (paymentNumber) {
            paymentNumber.textContent = '2';
            console.log('✅ Numeração do step de pagamento ajustada para 2');
        }
    }
    
    // Adicionar classe para ajustar espaçamento (2 steps ao invés de 3)
    const stepsContainer = document.querySelector('.steps');
    if (stepsContainer) {
        stepsContainer.classList.add('steps2');
        stepsContainer.classList.remove('steps3');
        console.log('✅ Classe steps2 adicionada');
    }
    
    // Ajustar o botão "IR PARA A ENTREGA" para ir direto ao pagamento
    const deliveryNextStepBtn = document.getElementById('delivery_next_step');
    if (deliveryNextStepBtn) {
        deliveryNextStepBtn.textContent = 'IR PARA O PAGAMENTO';
        
        // Remover event listener antigo clonando o botão
        const newBtn = deliveryNextStepBtn.cloneNode(true);
        deliveryNextStepBtn.parentNode.replaceChild(newBtn, deliveryNextStepBtn);
        
        // Adicionar novo event listener que pula a entrega
        newBtn.addEventListener('click', function(e) {
            e.preventDefault();
            console.log('🚀 Botão IR PARA O PAGAMENTO clicado (produto digital)');
            
            // Validar apenas os campos de identificação
            const emailInput = document.getElementById('email');
            const telephoneInput = document.getElementById('telephone');
            const nameInput = document.getElementById('name');
            const documentInput = document.getElementById('document');
            
            let todosValidos = true;
            
            // Validar email (se não tiver checkbox "não tenho email" marcado)
            const noEmailCheckbox = document.getElementById('noEmail');
            if (!noEmailCheckbox || !noEmailCheckbox.checked) {
                if (!emailInput || !emailInput.value || !validarEmail(emailInput.value)) {
                    todosValidos = false;
                    if (emailInput) mostrarErro(emailInput);
                }
            }
            
            // Validar telefone
            if (!telephoneInput || !telephoneInput.value || !validarTelefone(telephoneInput.value)) {
                todosValidos = false;
                if (telephoneInput) mostrarErro(telephoneInput);
            }
            
            // Validar nome
            if (!nameInput || !nameInput.value || !validarNome(nameInput.value)) {
                todosValidos = false;
                if (nameInput) mostrarErro(nameInput);
            }
            
            // Validar CPF
            if (!documentInput || !documentInput.value || !validarCPF(documentInput.value)) {
                todosValidos = false;
                if (documentInput) mostrarErro(documentInput);
            }
            
            if (todosValidos) {
                console.log('✅ Campos válidos, avançando direto para pagamento');
                
                // Esconder card-content-1 (identificação)
                const cardContent1 = document.querySelector('.card-content-1');
                if (cardContent1) {
                    cardContent1.style.display = 'none';
                }
                
                // Mostrar card-content-3 (pagamento) - pular entrega
                const cardContent3 = document.querySelector('.card-content-3');
                if (cardContent3) {
                    cardContent3.style.display = 'block';
                }
                
                // Atualizar os steps
                const contactData = document.getElementById('contact_data');
                const deliveryData = document.getElementById('delivery_data');
                const paymentData = document.getElementById('payment_data');
                
                if (contactData) {
                    contactData.classList.remove('current');
                    contactData.classList.add('done');
                }
                
                // Marcar entrega como done também (mesmo que oculta)
                if (deliveryData) {
                    deliveryData.classList.add('done');
                    deliveryData.classList.remove('current');
                }
                
                if (paymentData) {
                    paymentData.classList.add('ativo', 'current');
                }
                
                // Scroll suave para o topo
                window.scrollTo({ top: 0, behavior: 'smooth' });
            } else {
                console.log('❌ Existem campos inválidos');
            }
        });
        
        console.log('✅ Texto do botão alterado para "IR PARA O PAGAMENTO" e event listener atualizado');
    }
    
    console.log('✅ Checkout ajustado para produto digital');
}

// Funções auxiliares de validação (GLOBAIS)
function validarEmail(email) {
    const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return regex.test(email);
}

function validarTelefone(telefone) {
    const numeroLimpo = telefone.replace(/[^\d]/g, '');
    return numeroLimpo.length >= 10;
}

function validarNome(nome) {
    const palavras = nome.trim().split(/\s+/);
    return palavras.length >= 2 && palavras.every(palavra => palavra.length >= 2);
}

function validarCPF(cpf) {
    cpf = cpf.replace(/[^\d]/g, '');
    
    if (cpf.length !== 11 || /^(\d)\1{10}$/.test(cpf)) {
        return false;
    }
    
    // Validação do primeiro dígito
    let soma = 0;
    for (let i = 0; i < 9; i++) {
        soma += parseInt(cpf.charAt(i)) * (10 - i);
    }
    let resto = 11 - (soma % 11);
    if (resto === 10 || resto === 11) resto = 0;
    if (resto !== parseInt(cpf.charAt(9))) return false;
    
    // Validação do segundo dígito
    soma = 0;
    for (let i = 0; i < 10; i++) {
        soma += parseInt(cpf.charAt(i)) * (11 - i);
    }
    resto = 11 - (soma % 11);
    if (resto === 10 || resto === 11) resto = 0;
    if (resto !== parseInt(cpf.charAt(10))) return false;
    
    return true;
}

function mostrarErro(input) {
    if (!input) return;
    
    input.classList.add('invalid');
    const feedback = input.parentElement.querySelector('.invalid-feedback');
    if (feedback) {
        feedback.style.display = 'flex';
        const emptyData = feedback.querySelector('.empty_data');
        if (emptyData) emptyData.style.display = 'inline';
    }
}

// Função para atualizar depoimentos dinamicamente
function atualizarDepoimentos(depoimentos) {
    // Atualizar depoimentos mobile
    const depoimentosMobile = document.querySelector('.d-block.d-lg-none.depoimentos-section .card');
    
    // Atualizar depoimentos desktop
    const depoimentosDesktop = document.querySelector('.d-none.d-lg-block.depoimentos-section .card');
    
    // Selecionar as seções de depoimentos
    const depoimentosSections = document.querySelectorAll('.depoimentos-section');
    
    if (!depoimentos || depoimentos.length === 0) {
        console.log('⚠️ Nenhum depoimento para exibir - limpando e ocultando seção');
        
        // Limpar depoimentos mobile
        if (depoimentosMobile) {
            depoimentosMobile.innerHTML = '';
        }
        
        // Limpar depoimentos desktop
        if (depoimentosDesktop) {
            depoimentosDesktop.innerHTML = '';
        }
        
        // Ocultar seções de depoimentos
        depoimentosSections.forEach(section => {
            section.style.display = 'none';
        });
        
        return;
    }
    
    // Mostrar seções de depoimentos se houver depoimentos
    depoimentosSections.forEach(section => {
        section.style.display = '';
    });
    
    if (depoimentosMobile) {
        depoimentosMobile.innerHTML = '';
        
        depoimentos.forEach((depoimento, index) => {
            const isLast = index === depoimentos.length - 1;
            const depoimentoHTML = `
                <div class="card-body">
                    <div class="row no-gutters">
                        <div class="col-8 d-flex">
                            <img
                                class="rounded-circle"
                                width="48"
                                height="48"
                                src="${depoimento.image || 'https://via.placeholder.com/48'}"
                                onerror="this.src='https://via.placeholder.com/48'"
                            />
                            <span class="text-ccblack pl-2 pt-1 d-inline-block" style="width: 80%">
                                ${depoimento.name || 'Cliente'}
                            </span>
                        </div>
                        <div class="d-none d-md-flex col-4 pt-1 text-right align-items-center justify-content-end">
                            <div class="stars d-flex" style="color: #f8ce1c">
                                <i class="fa fa-star"></i><i class="fa fa-star"></i><i class="fa fa-star"></i><i class="fa fa-star"></i><i class="fa fa-star"></i>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="review-desc col mt-2 text-left review-description">
                            ${depoimento.description || 'Depoimento do cliente'}
                        </div>
                    </div>
                    <div class="d-flex mt-4 d-md-none mt-4 align-items-center justify-content-start">
                        <div class="stars d-flex" style="color: #f8ce1c">
                            <i class="fa fa-star"></i><i class="fa fa-star"></i><i class="fa fa-star"></i><i class="fa fa-star"></i><i class="fa fa-star"></i>
                        </div>
                    </div>
                </div>
                ${!isLast ? '<hr />' : ''}
            `;
            
            depoimentosMobile.innerHTML += depoimentoHTML;
        });
    }
    
    
    if (depoimentosDesktop) {
        depoimentosDesktop.innerHTML = '';
        
        depoimentos.forEach((depoimento, index) => {
            const isLast = index === depoimentos.length - 1;
            const depoimentoHTML = `
                <div class="card-body">
                    <div class="row no-gutters">
                        <div class="col-8 d-flex">
                            <img
                                class="rounded-circle"
                                width="48"
                                height="48"
                                src="${depoimento.image || 'https://via.placeholder.com/48'}"
                                onerror="this.src='https://via.placeholder.com/48'"
                            />
                            <span class="text-ccblack pl-2 pt-1 d-inline-block" style="width: 80%">
                                ${depoimento.name || 'Cliente'}
                            </span>
                        </div>
                        <div class="d-none d-md-flex col-4 pt-1 text-right align-items-center justify-content-end">
                            <div class="stars d-flex" style="color: #f8ce1c">
                                <i class="fa fa-star"></i><i class="fa fa-star"></i><i class="fa fa-star"></i><i class="fa fa-star"></i><i class="fa fa-star"></i>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="review-desc col mt-2 text-left review-description">
                            ${depoimento.description || 'Depoimento do cliente'}
                        </div>
                    </div>
                    <div class="d-flex mt-4 d-md-none mt-4 align-items-center justify-content-start">
                        <div class="stars d-flex" style="color: #f8ce1c">
                            <i class="fa fa-star"></i><i class="fa fa-star"></i><i class="fa fa-star"></i><i class="fa fa-star"></i><i class="fa fa-star"></i>
                        </div>
                    </div>
                </div>
                ${!isLast ? '<hr />' : ''}
            `;
            
            depoimentosDesktop.innerHTML += depoimentoHTML;
        });
    }
    
    console.log(`⭐ ${depoimentos.length} depoimentos atualizados`);
}


// Função para aplicar cores personalizadas no checkout
function aplicarCoresPersonalizadas(colors) {
    console.log('🎨 Aplicando cores personalizadas:', colors);
    
    try {
        // Criar ou atualizar style tag para cores customizadas
        let styleTag = document.getElementById('custom-colors-style');
        if (!styleTag) {
            styleTag = document.createElement('style');
            styleTag.id = 'custom-colors-style';
            document.head.appendChild(styleTag);
        }
        
        const corPrincipal = colors.principal || '#262626';
        const corHover = colors.hover || '#222222';
        const corSecundaria = colors.secundaria || '#393939';
        
        // CSS com as cores personalizadas
        const customCSS = `
            /* Countdown */
            .countdown-timer-container {
                background-color: ${corPrincipal} !important;
            }
            
            /* Quantity Indicator */
            .qtde {
                background-color: ${corPrincipal} !important;
            }
            
            /* Three-step Checkout Tabs */
            .guide.current {
                border-color: ${corPrincipal} !important;
            }
            
            .guide.current .guide-text {
                color: ${corPrincipal} !important;
            }
            
            /* Coupon */
            .coupon-btn-link {
                color: ${corPrincipal} !important;
            }
            
            .coupon-btn-link:hover {
                color: ${corHover} !important;
            }
            
            /* Payment Flags */
            .jss809 .chk-payment-flags .chk-flag-option::before {
                border-top: 2px solid ${corPrincipal} !important;
                border-bottom: 2px solid ${corPrincipal} !important;
            }
            
            .jss809 .chk-payment-flags .chk-flag-option::after {
                border-left: 2px solid ${corPrincipal} !important;
                border-right: 2px solid ${corPrincipal} !important;
            }
            
            .jss809 .chk-payment-flags.selected .chk-flag-option.selected {
                box-shadow: inset 0 0 0 2px ${corPrincipal} !important;
            }
            
            .jss809 .chk-payment-flags.selected .chk-flag-option.selected p {
                color: ${corPrincipal} !important;
            }
            
            /* Buttons */
            .thanks-button {
                background-color: ${corPrincipal} !important;
            }
            
            .mini-button {
                background-color: ${corPrincipal} !important;
            }
            
            .mini-button:hover {
                background-color: ${corHover} !important;
            }
            
            .buy-btn {
                background-color: ${corPrincipal} !important;
            }
            
            .buy-btn:hover {
                background-color: ${corHover} !important;
            }
            
            .swiper-pagination-bullet-active {
                background-color: ${corPrincipal} !important;
            }
            
            .guide.current .guide-text .step-number {
                background-color: ${corPrincipal} !important;
            }
            
            /* Steps concluídos (com checkmark) */
            .done .guide-text .step-number {
                background-color: ${corSecundaria} !important;
            }
        `;
        
        styleTag.textContent = customCSS;
        console.log('✅ Cores aplicadas com sucesso!');
        
    } catch (error) {
        console.error('❌ Erro ao aplicar cores:', error);
    }
}

// Tornar função global para uso no preview
window.aplicarCoresPersonalizadas = aplicarCoresPersonalizadas;


// ============================================
// CONTADOR REGRESSIVO
// ============================================

let contadorInterval = null;
let contadorConfig = null;

// Função para inicializar o contador regressivo
function inicializarContador(config) {
    console.log('⏰ Inicializando contador regressivo:', config);
    
    contadorConfig = config;
    
    // Se o contador não está habilitado, esconder e sair
    if (!config.enabled) {
        const countdownTimer = document.getElementById('countdown-timer');
        if (countdownTimer) {
            countdownTimer.style.display = 'none';
        }
        console.log('⏰ Contador desabilitado');
        return;
    }
    
    // Aplicar estilos personalizados
    const countdownTimer = document.getElementById('countdown-timer');
    const countdownText = document.querySelector('.countdown-text');
    const countdownTime = document.querySelector('.countdown-time');
    
    if (countdownTimer) {
        countdownTimer.style.backgroundColor = config.bg_color || '#FF1A1A';
        countdownTimer.style.color = config.text_color || '#000000';
        countdownTimer.style.display = 'block';
    }
    
    if (countdownText) {
        countdownText.innerHTML = config.text || 'Esta oferta expira em';
        countdownText.style.color = config.text_color || '#000000';
    }
    
    if (countdownTime) {
        countdownTime.style.color = config.text_color || '#000000';
    }
    
    // Verificar se já existe um contador salvo no localStorage
    const savedCountdown = localStorage.getItem('countdown_state');
    let startTime, endTime;
    
    if (savedCountdown) {
        const savedData = JSON.parse(savedCountdown);
        startTime = savedData.startTime;
        endTime = savedData.endTime;
        
        // Verificar se o contador já expirou
        const now = Date.now();
        if (now >= endTime) {
            mostrarContadorExpirado();
            return;
        }
        
        console.log('⏰ Restaurando contador do localStorage');
    } else {
        // Criar novo contador
        startTime = Date.now();
        endTime = startTime + (config.minutes * 60 * 1000);
        
        localStorage.setItem('countdown_state', JSON.stringify({
            startTime: startTime,
            endTime: endTime
        }));
        
        console.log('⏰ Novo contador criado');
    }
    
    // Iniciar atualização do contador
    atualizarContador(endTime);
    
    // Limpar intervalo anterior se existir
    if (contadorInterval) {
        clearInterval(contadorInterval);
    }
    
    // Atualizar a cada segundo
    contadorInterval = setInterval(() => {
        atualizarContador(endTime);
    }, 1000);
}

// Função para atualizar o display do contador
function atualizarContador(endTime) {
    const now = Date.now();
    const timeLeft = endTime - now;
    
    if (timeLeft <= 0) {
        mostrarContadorExpirado();
        if (contadorInterval) {
            clearInterval(contadorInterval);
            contadorInterval = null;
        }
        return;
    }
    
    // Calcular minutos e segundos restantes
    const minutes = Math.floor(timeLeft / 60000);
    const seconds = Math.floor((timeLeft % 60000) / 1000);
    
    // Formatar para sempre ter 2 dígitos
    const formattedTime = `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
    
    // Atualizar display
    const countdownDisplay = document.getElementById('countdown-display');
    if (countdownDisplay) {
        countdownDisplay.textContent = formattedTime;
    }
}

// Função para mostrar mensagem de contador expirado
function mostrarContadorExpirado() {
    console.log('⏰ Contador expirado!');
    
    const countdownText = document.querySelector('.countdown-text');
    const countdownTime = document.querySelector('.countdown-time');
    
    if (countdownText && contadorConfig) {
        countdownText.innerHTML = contadorConfig.text_expired || 'Oferta expirada!';
    }
    
    if (countdownTime) {
        countdownTime.style.display = 'none';
    }
    
    // Opcional: adicionar efeito visual de expiração
    const countdownTimer = document.getElementById('countdown-timer');
    if (countdownTimer) {
        countdownTimer.style.opacity = '0.8';
    }
}

// Função para resetar o contador (útil para testes)
window.resetarContador = function() {
    localStorage.removeItem('countdown_state');
    if (contadorConfig) {
        inicializarContador(contadorConfig);
    }
    console.log('⏰ Contador resetado');
};

// Escutar mensagens do preview builder para atualizar contador em tempo real
window.addEventListener('message', function(event) {
    if (event.data.type === 'updatePreview' && event.data.data) {
        const data = event.data.data;
        
        // Atualizar contador se fornecido
        if (data.contador) {
            // Limpar intervalo anterior
            if (contadorInterval) {
                clearInterval(contadorInterval);
                contadorInterval = null;
            }
            
            // Reinicializar com nova configuração
            inicializarContador(data.contador);
        }

        // Atualizar topbar (banner) se fornecido
        if (data.topbar) {
            const topbar = document.querySelector('.checkout-topbar');
            if (topbar) {
                if (data.topbar.visible === false) {
                    topbar.style.display = 'none';
                    console.log('📢 Banner de aviso oculto');
                } else {
                    topbar.style.display = '';
                    topbar.style.backgroundColor = data.topbar.bg_color || '#02ad5b';
                    
                    // Atualizar o texto HTML do topbar
                    const containerDiv = topbar.querySelector('.container');
                    if (containerDiv && data.topbar.text) {
                        containerDiv.innerHTML = data.topbar.text;
                        console.log('📢 Banner de aviso atualizado:', {
                            visible: true,
                            text: data.topbar.text,
                            bg_color: data.topbar.bg_color
                        });
                    }
                }
            }
        }
    }
});


/**
 * Topbar Position Fix
 * Adjusts checkout-topbar position based on logo-header visibility
 */
(function initTopbarPositionFix() {
    'use strict';
    
    function adjustTopbarPosition() {
        const logoHeader = document.getElementById('logo-header');
        const topbar = document.querySelector('.checkout-topbar');
        
        if (!topbar || !logoHeader) {
            return;
        }
        
        // Check if logo-header is visible
        const logoElement = logoHeader.querySelector('.checkout-logo');
        const isLogoHidden = logoElement && logoElement.classList.contains('company-logo-hidden');
        const isLogoHeaderHidden = logoHeader.style.display === 'none' || 
            window.getComputedStyle(logoHeader).display === 'none';
        
        const isLogoVisible = !isLogoHidden && !isLogoHeaderHidden;
        
        // Move topbar in DOM based on logo visibility
        if (isLogoVisible) {
            // Logo is visible - topbar should be after logo-header
            if (logoHeader.nextElementSibling !== topbar) {
                logoHeader.parentNode.insertBefore(topbar, logoHeader.nextElementSibling);
            }
        } else {
            // Logo is hidden - topbar should be first element
            const content = document.querySelector('.content');
            if (content && content.firstElementChild !== topbar) {
                content.insertBefore(topbar, content.firstElementChild);
            }
        }
        
        console.log('📍 Topbar position adjusted:', {
            logoVisible: isLogoVisible,
            topbarPosition: isLogoVisible ? 'after logo-header' : 'first in content'
        });
    }
    
    // Run adjustment function
    function runAdjustment() {
        setTimeout(adjustTopbarPosition, 100);
    }
    
    // Run on page load
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', runAdjustment);
    } else {
        runAdjustment();
    }
    
    // Watch for changes to logo visibility
    const observer = new MutationObserver(function(mutations) {
        let shouldAdjust = false;
        
        mutations.forEach(function(mutation) {
            if (mutation.type === 'attributes' && 
                (mutation.attributeName === 'class' || 
                 mutation.attributeName === 'style')) {
                shouldAdjust = true;
            }
        });
        
        if (shouldAdjust) {
            adjustTopbarPosition();
        }
    });
    
    // Observe logo-header and its children
    setTimeout(function() {
        const logoHeader = document.getElementById('logo-header');
        if (logoHeader) {
            observer.observe(logoHeader, {
                attributes: true,
                attributeFilter: ['class', 'style'],
                subtree: true
            });
        }
        
        console.log('✅ Topbar position fix initialized');
    }, 200);
})();
 