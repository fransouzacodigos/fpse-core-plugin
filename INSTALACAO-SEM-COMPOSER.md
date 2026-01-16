# Instalação do Plugin - Sem Composer

Se o servidor WordPress **não tiver Composer instalado**, existem 3 opções:

## ✅ OPÇÃO 1: Usar Autoload Manual (RECOMENDADO)

### Como funciona?
O arquivo `autoload.php` já carrega automaticamente todas as classes sem precisar do Composer.

### Instalação (2 passos):

**1. Copiar a pasta do plugin:**
```bash
cp -r fpse-core /var/www/html/wp-content/plugins/
# ou
scp -r fpse-core user@server:/home/user/public_html/wp-content/plugins/
```

**2. Ativar no WordPress:**
```bash
# Via SSH/Terminal:
wp plugin activate fpse-core

# Ou via admin WordPress:
# Dashboard > Plugins > FPSE Core > Ativar
```

### Pronto! ✅
Não precisa fazer mais nada. O autoload.php carrega tudo automaticamente.

---

## ✅ OPÇÃO 2: Instalar Composer Localmente e Upload

Se você quer usar o `composer.json` (para melhor gerenciamento):

### No seu computador local:

```bash
# 1. Instalar dependências
cd fpse-core
composer install

# 2. Verificar se foi criada a pasta vendor/
ls -la vendor/

# 3. Compactar tudo
zip -r fpse-core-with-vendor.zip .

# 4. Upload do arquivo compactado
# Upload via FTP ou terminal
```

### No servidor:

```bash
# 1. Descompactar
unzip fpse-core-with-vendor.zip -d wp-content/plugins/

# 2. Ativar plugin
wp plugin activate fpse-core
```

### Vantagens:
- ✅ Gerenciamento de dependências
- ✅ Atualizar composer.lock para produção
- ✅ Mais seguro para produção

---

## ✅ OPÇÃO 3: Usar Autoload.php + Composer no Dev

Melhor abordagem para desenvolvimento com versionamento:

### Estrutura:

```
fpse-core/
├── .gitignore          # Exclui vendor/
├── composer.json       # (opcional, para dev)
├── autoload.php        # Autoload para produção
├── fpse-core.php       # Plugin entry point
├── config/
├── src/
└── vendor/             # (NUNCA commitar no git)
```

### No .gitignore, adicione:
```
/vendor/
/composer.lock
```

### Para dev local (com composer):
```bash
composer install
```

### Para produção (sem composer):
```bash
# Não incluir vendor/ no git
# Servidor usa autoload.php automaticamente
```

---

## 📋 Checklist de Instalação

### Opção 1 (Autoload Manual) - Simples e Rápido

- [ ] Copiar pasta `fpse-core` para `wp-content/plugins/`
- [ ] Ir para WordPress admin
- [ ] Ir em Plugins
- [ ] Clicar "Ativar" no FPSE Core
- [ ] Verificar se aparece mensagem de sucesso
- [ ] Verificar se tabela `wp_fpse_events` foi criada: `wp db table list`

### Opção 2 (Com Vendor Local) - Mais Seguro

- [ ] Instalar Composer no seu computador local
- [ ] Rodar `composer install` na pasta do plugin
- [ ] Compactar tudo (incluindo vendor/)
- [ ] Upload do arquivo compactado para o servidor
- [ ] Descompactar no servidor
- [ ] Ativar plugin no WordPress

---

## 🧪 Testar a Instalação

### Verificar se as classes estão carregando:

```bash
# Via terminal do servidor:
wp shell

# Dentro do shell:
$plugin = FortaleceePSE\Core\Plugin::getInstance();
echo "Plugin version: " . $plugin->getVersion();
exit;
```

### Ou criar arquivo de teste:

Criar arquivo: `wp-content/plugins/fpse-core/test-autoload.php`

```php
<?php
require_once 'autoload.php';

try {
    $plugin = \FortaleceePSE\Core\Plugin::getInstance();
    echo "✅ Plugin carregado com sucesso!<br>";
    echo "Version: " . $plugin->getVersion() . "<br>";
    
    // Testar config
    $states = $plugin->getConfig('states');
    echo "Estados carregados: " . count($states) . "<br>";
    
    // Testar profiles
    $profiles = $plugin->getConfig('profiles');
    echo "Perfis carregados: " . count($profiles) . "<br>";
    
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage();
}
?>
```

Acessar: `http://localhost/wp-content/plugins/fpse-core/test-autoload.php`

Se aparecer `✅ Plugin carregado com sucesso!`, tudo está funcionando.

---

## ❓ Qual Opção Escolher?

| Opção | Melhor Para | Pros | Contras |
|-------|-----------|------|---------|
| **Autoload Manual (1)** | Produção simples | Não precisa Composer, leve | Sem gerenciamento de deps |
| **Com Vendor (2)** | Produção profissional | Seguro, versionado | Mais pesado, git mais cuidado |
| **Dev + Autoload (3)** | Desenvolvimento | Melhor de ambos mundos | Requer git ignore correto |

**Recomendação**: **Opção 1 para produção**, **Opção 3 para desenvolvimento**.

---

## 🔍 Verificar Qual Usar

### Seu servidor tem Composer?

```bash
which composer
# Se aparecer /usr/bin/composer ou similar: Tem Composer ✅
# Se não aparecer nada: Não tem Composer ❌
```

### Seu servidor é compartilhado (shared hosting)?

- **SIM**: Use Opção 1 (Autoload Manual)
- **NÃO** (VPS/Dedicated): Pode usar Opção 2 ou 3

### Você trabalha em equipe com git?

- **SIM**: Use Opção 3
- **NÃO**: Use Opção 1

---

## 🚀 Quick Start - Opção 1 (Mais Rápida)

```bash
# 1. Copiar plugin para WordPress
cp -r fpse-core /var/www/html/wp-content/plugins/

# 2. Ativar plugin
wp plugin activate fpse-core

# 3. Verificar se funcionou
wp option get fpse_activated

# 4. Testar API
curl http://localhost/wp-json/fpse/v1/nonce
```

Pronto! 🎉

---

## 📞 Troubleshooting

### Erro: "Class not found"

```
Fatal error: Uncaught Error: Class "FortaleceePSE\Core\Plugin" not found
```

**Solução:**
- [ ] Verificar se `autoload.php` existe em `fpse-core/`
- [ ] Verificar se `fpse-core.php` importa `autoload.php`
- [ ] Verificar se a estrutura de diretórios está correta:
  ```
  fpse-core/src/Plugin.php ✅
  fpse-core/src/Services/UserService.php ✅
  ```

### Erro: "Permission denied"

**Solução:**
```bash
chmod -R 755 wp-content/plugins/fpse-core/
chmod -R 755 wp-content/plugins/fpse-core/src/
```

### Erro: "Cannot write to logs"

**Solução:**
```bash
chmod -R 777 wp-content/plugins/fpse-core/
# Ou configure pasta de logs específica em config/debug.php
```

---

## ✅ Resumo

- **Composer não obrigatório** ✅
- **Autoload manual já incluído** ✅
- **Funciona em shared hosting** ✅
- **Compatível com WordPress** ✅
- **PSR-4 compliant** ✅

**Status: Pronto para usar! 🚀**
