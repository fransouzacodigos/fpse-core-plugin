# 🚀 FPSE Core - Guia de Instalação Rápida (30 segundos)

## ⚡ Opção Mais Rápida - SEM Composer

### Passo 1: Copiar Arquivos (10 segundos)
```bash
# Linux/macOS
cp -r fpse-core wp-content/plugins/

# Ou via SFTP/FTP: Upload a pasta fpse-core para wp-content/plugins/
```

### Passo 2: Ativar Plugin (10 segundos)

**Via WordPress Admin:**
1. Acesse: `Dashboard > Plugins`
2. Procure por: "**FPSE Core**"
3. Clique em: "**Ativar**"

**Ou via Terminal:**
```bash
wp plugin activate fpse-core
```

### Passo 3: Verificar (10 segundos)
```bash
# Verificar se plugin está ativo
wp plugin list | grep fpse-core

# Verificar se tabela foi criada
wp db query "SELECT table_name FROM information_schema.tables WHERE table_name = 'wp_fpse_events';"
```

**Pronto! ✅ Plugin instalado e funcionando!**

---

## 🤖 Instalação Automática

### Linux/macOS:
```bash
cd fpse-core
chmod +x install.sh
./install.sh /path/to/wordpress
```

### Windows:
```cmd
cd fpse-core
install.bat C:\xampp\htdocs\wordpress
```

O script faz tudo automaticamente:
- ✅ Copia arquivos
- ✅ Instala Composer (se disponível)
- ✅ Configura permissões
- ✅ Ativa plugin

---

## 📋 Checklist de Instalação

- [ ] Copiar pasta `fpse-core` para `wp-content/plugins/`
- [ ] Acessar WordPress Admin
- [ ] Ir em: Plugins > FPSE Core
- [ ] Clicar: Ativar
- [ ] Verificar mensagem: "Plugin ativado"
- [ ] Testar API: `curl http://localhost/wp-json/fpse/v1/nonce`

---

## ✅ Sucesso = Veja Esta Mensagem

```
[Aviso] Plugin ativado.
```

Ou no terminal:
```
✓ The following plugins are now active:
  - fpse-core
```

---

## 🆘 Se Não Funcionar

### Erro: "Classe não encontrada"
```
Fatal error: Uncaught Error: Class "FortaleceePSE\Core\Plugin" not found
```

**Solução**: O arquivo `autoload.php` pode estar faltando.
- Verifique se existe: `fpse-core/autoload.php`
- Se não existir, crie via: `INSTALACAO-SEM-COMPOSER.md`

### Erro: "Permission denied"
```bash
chmod -R 755 wp-content/plugins/fpse-core
```

### Erro: "Cannot write to logs"
```bash
chmod -R 777 wp-content/plugins/fpse-core
```

---

## 🧪 Teste Rápido da API

```bash
# Obter nonce (funciona sempre)
curl http://localhost/wp-json/fpse/v1/nonce

# Esperado:
{
  "success": true,
  "nonce": "abc123...",
  "nonce_name": "fpse_nonce",
  "nonce_action": "fpse_register_action"
}
```

Se receber esta resposta, **o plugin está funcionando perfeitamente! ✅**

---

## 📚 Documentação Completa

- **INSTALACAO-SEM-COMPOSER.md** - 3 opções de instalação
- **README.md** - Recursos e configuração
- **QUICK_START.md** - Guia completo
- **API.md** - Referência da API
- **INTEGRATION.md** - Integração com React

---

## 💡 Dicas

### Servidor Compartilhado (Shared Hosting)?
Use esta opção sem Composer. É a mais simples e funciona em qualquer servidor.

### Servidor Próprio (VPS/Dedicated)?
Você pode usar Composer se quiser (veja INSTALACAO-SEM-COMPOSER.md).

### Trabalha com Git?
Exclua `vendor/` do git e use `autoload.php` em produção.

---

## 🎯 Próximos Passos

1. **✅ Plugin Instalado**
2. **Próximo**: Integrar com React (veja INTEGRATION.md)
3. **Depois**: Testar endpoints (veja API.md)
4. **Final**: Deploy para produção

---

## 🆔 Qual Opção Usar?

| Cenário | Opção |
|---------|-------|
| Shared Hosting | SEM Composer (está guia) |
| VPS + Composer | COM Composer (README.md) |
| Desenvolvimento | Ambas (gitignore vendor/) |

---

**Status: Tudo Pronto! 🚀**

Agora você pode:
- ✅ Registrar usuários via API
- ✅ Rastrear eventos
- ✅ Gerar relatórios
- ✅ Integrar com React

Dúvidas? Veja `INSTALACAO-SEM-COMPOSER.md` ou `README.md`.
