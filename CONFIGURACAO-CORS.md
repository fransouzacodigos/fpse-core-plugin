# ⚙️ Configuração de CORS via WordPress Admin

O plugin FPSE Core agora permite configurar os domínios CORS diretamente pelo WordPress Admin, sem precisar editar código!

## 📍 Como Acessar

1. Acesse o WordPress Admin (`/wp-admin`)
2. No menu lateral, vá em **Configurações** → **FPSE Core**
3. Configure os domínios permitidos no campo **Origens Permitidas (CORS)**

## ✏️ Como Configurar

### Formato

- **Um domínio por linha**
- Use protocolo completo: `https://exemplo.com` ou `http://localhost:5173`
- Sem barra final (`/`)

### Exemplos

```
http://localhost:5173
http://localhost:3000
https://cadastro.fortalecepse.com.br
https://app.exemplo.com
```

### Desenvolvimento Local

Para desenvolvimento, adicione:
```
http://localhost:5173
http://localhost:3000
http://127.0.0.1:5173
http://127.0.0.1:3000
```

### Produção

Para produção, adicione apenas os domínios reais:
```
https://cadastro.fortalecepse.com.br
https://app.fortalecepse.com.br
```

## ✅ Passos

1. Acesse **Configurações** → **FPSE Core**
2. No campo **Origens Permitidas (CORS)**, adicione os domínios (um por linha)
3. Clique em **Salvar Configurações**
4. Pronto! Os domínios já estarão configurados

## 🔒 Segurança

⚠️ **Importante:**
- Liste apenas domínios que você controla
- **Não use** `*` (wildcard) em produção
- Use `https://` em produção (não `http://`)
- Remova domínios de desenvolvimento quando não precisar mais

## 🔄 Como Funciona

1. As configurações são salvas no banco de dados WordPress (option `fpse_cors_origins`)
2. O plugin lê essas configurações automaticamente ao processar requisições CORS
3. Se não houver configuração, usa padrões de desenvolvimento
4. **Não precisa editar código** nem reativar o plugin

## 📝 Notas

- As configurações são **imediatas** (não precisa reativar plugin)
- O campo aceita **vários domínios** (um por linha)
- A validação **remove duplicatas** automaticamente
- Domínios inválidos são **ignorados** (com aviso no log, se debug estiver ativo)

## 🐛 Troubleshooting

### Configuração não está funcionando

1. Verifique se salvou as configurações corretamente
2. Limpe cache do WordPress (se usar plugin de cache)
3. Verifique se os domínios estão no formato correto
4. Verifique logs do WordPress se debug estiver ativo

### Domínio não está sendo aceito

1. Verifique se o domínio está exatamente como aparece no navegador (com `https://`)
2. Verifique se não há espaços extras ou caracteres especiais
3. Verifique se o domínio está listado nas configurações

## 🎯 Vantagens

✅ **Sem editar código** - Tudo via interface web  
✅ **Fácil de atualizar** - Adicione/remova domínios quando precisar  
✅ **Seguro** - Validação automática de URLs  
✅ **Imediato** - Mudanças aplicadas na hora  

## 📚 Alternativa (Código)

Se preferir editar código (não recomendado em produção), você ainda pode editar:

`fpse-core/config/permissions.php` → `cors_allowed_origins`

Mas a **prioridade é**:
1. Configuração do Admin (banco de dados) ← **Recomendado**
2. Arquivo de configuração (`config/permissions.php`)
3. Padrões de desenvolvimento
