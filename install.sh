#!/bin/bash

#################################################################################
# FPSE Core Plugin - Automatic Installation Script
#
# Use este script para instalar automaticamente o plugin FPSE Core.
# Funciona com ou sem Composer.
#
# Uso: ./install.sh
#      ./install.sh /path/to/wordpress
#################################################################################

# Cores para output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Detectar o caminho do WordPress
if [ -z "$1" ]; then
    WP_PATH="."
    echo -e "${YELLOW}Usando diretório atual como WordPress path${NC}"
else
    WP_PATH="$1"
fi

# Verificar se é um WordPress válido
if [ ! -f "$WP_PATH/wp-config.php" ]; then
    echo -e "${RED}❌ Erro: wp-config.php não encontrado em $WP_PATH${NC}"
    echo "Uso: ./install.sh /path/to/wordpress"
    exit 1
fi

PLUGINS_PATH="$WP_PATH/wp-content/plugins"

echo -e "${GREEN}═══════════════════════════════════════════════════${NC}"
echo -e "${GREEN}FPSE Core Plugin - Instalação Automática${NC}"
echo -e "${GREEN}═══════════════════════════════════════════════════${NC}"
echo ""

# Passo 1: Copiar plugin
echo -e "${YELLOW}[1/5]${NC} Copiando plugin para wp-content/plugins..."
if [ -d "$PLUGINS_PATH/fpse-core" ]; then
    echo -e "${YELLOW}⚠️  Plugin já existe. Atualizando...${NC}"
    rm -rf "$PLUGINS_PATH/fpse-core"
fi

cp -r . "$PLUGINS_PATH/fpse-core"
if [ $? -eq 0 ]; then
    echo -e "${GREEN}✅ Plugin copiado com sucesso${NC}"
else
    echo -e "${RED}❌ Erro ao copiar plugin${NC}"
    exit 1
fi

# Passo 2: Verificar Composer
echo ""
echo -e "${YELLOW}[2/5]${NC} Verificando Composer..."
if [ -f "$PLUGINS_PATH/fpse-core/composer.json" ]; then
    if command -v composer &> /dev/null; then
        echo -e "${YELLOW}Composer encontrado. Instalando dependências...${NC}"
        cd "$PLUGINS_PATH/fpse-core"
        composer install
        if [ $? -eq 0 ]; then
            echo -e "${GREEN}✅ Dependências instaladas com sucesso${NC}"
        else
            echo -e "${YELLOW}⚠️  Aviso: Falha ao instalar composer. Usando autoload manual.${NC}"
        fi
        cd - > /dev/null
    else
        echo -e "${YELLOW}ℹ️  Composer não encontrado. Usando autoload manual.${NC}"
    fi
else
    echo -e "${YELLOW}ℹ️  Nenhum composer.json encontrado.${NC}"
fi

# Passo 3: Verificar permissões
echo ""
echo -e "${YELLOW}[3/5]${NC} Configurando permissões..."
chmod -R 755 "$PLUGINS_PATH/fpse-core"
chmod -R 755 "$PLUGINS_PATH/fpse-core/src"
chmod -R 755 "$PLUGINS_PATH/fpse-core/config"
echo -e "${GREEN}✅ Permissões configuradas${NC}"

# Passo 4: Verificar se WP CLI está disponível
echo ""
echo -e "${YELLOW}[4/5]${NC} Ativando plugin..."
if command -v wp &> /dev/null; then
    wp plugin activate fpse-core --allow-root 2>/dev/null || wp plugin activate fpse-core
    if [ $? -eq 0 ]; then
        echo -e "${GREEN}✅ Plugin ativado com sucesso via WP CLI${NC}"
    else
        echo -e "${YELLOW}ℹ️  Ativação via WP CLI falhou. Ative manualmente:${NC}"
        echo -e "   Dashboard WordPress > Plugins > FPSE Core > Ativar"
    fi
else
    echo -e "${YELLOW}ℹ️  WP CLI não encontrado. Ative manualmente:${NC}"
    echo -e "   Dashboard WordPress > Plugins > FPSE Core > Ativar"
fi

# Passo 5: Verificar instalação
echo ""
echo -e "${YELLOW}[5/5]${NC} Verificando instalação..."

# Verificar se arquivos essenciais existem
if [ -f "$PLUGINS_PATH/fpse-core/fpse-core.php" ] && \
   [ -f "$PLUGINS_PATH/fpse-core/autoload.php" ] && \
   [ -d "$PLUGINS_PATH/fpse-core/src" ]; then
    echo -e "${GREEN}✅ Arquivos essenciais encontrados${NC}"
else
    echo -e "${RED}❌ Arquivos essenciais não encontrados${NC}"
    exit 1
fi

# Resumo final
echo ""
echo -e "${GREEN}═══════════════════════════════════════════════════${NC}"
echo -e "${GREEN}✅ Instalação Concluída!${NC}"
echo -e "${GREEN}═══════════════════════════════════════════════════${NC}"
echo ""
echo "Plugin instalado em: $PLUGINS_PATH/fpse-core"
echo ""
echo "Próximos passos:"
echo "1. Ative o plugin no WordPress:"
echo "   wp plugin activate fpse-core"
echo "   OU"
echo "   Dashboard > Plugins > FPSE Core > Ativar"
echo ""
echo "2. Verifique a instalação:"
echo "   wp db query \"SHOW TABLES LIKE '%fpse_events%';\""
echo ""
echo "3. Teste a API:"
echo "   curl http://localhost/wp-json/fpse/v1/nonce"
echo ""
echo "4. Consulte a documentação:"
echo "   - README.md: Recursos e configuração"
echo "   - QUICK_START.md: Guia rápido"
echo "   - API.md: Referência da API"
echo "   - INSTALACAO-SEM-COMPOSER.md: Troubleshooting"
echo ""
echo -e "${YELLOW}Documentação: $PLUGINS_PATH/fpse-core/README.md${NC}"
echo ""
