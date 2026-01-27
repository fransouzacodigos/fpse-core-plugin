# Scripts de Manutenção FPSE Core

Scripts utilitários para manutenção e correção do sistema.

## fix-member-type-posts.php

**Propósito:** Criar posts do tipo `bp-member-type` para todos os perfis configurados.

**Quando usar:**
- Após atualizar o plugin e os usuários existentes não aparecem com "Tipo de Perfil" no admin
- Quando a coluna "Tipo de Perfil" está vazia na lista de usuários
- Após adicionar novos perfis na configuração

**Como executar:**

### Via PHP CLI (Mais Simples)

```bash
# Navegar até o diretório scripts
cd /www/wwwroot/seu-site.com/wp-content/plugins/fpse-core/scripts

# Executar o script
php fix-member-type-posts.php
```

⚠️ **ERRO COMUM:** Se você já está no diretório `scripts/`, NÃO use o caminho completo:
```bash
# ❌ ERRADO (se já está em scripts/)
php fpse-core/scripts/fix-member-type-posts.php

# ✅ CORRETO (se já está em scripts/)
php fix-member-type-posts.php
```

### Via WP-CLI (Alternativa)

```bash
wp eval-file fpse-core/scripts/fix-member-type-posts.php
```

### Via Admin WordPress

1. Criar arquivo temporário `fix-member-types.php` na raiz do WordPress:

```php
<?php
require_once 'wp-load.php';
require_once 'fpse-core/scripts/fix-member-type-posts.php';
```

2. Acessar via navegador: `https://seu-site.com/fix-member-types.php`
3. **IMPORTANTE:** Deletar o arquivo após executar!

### Via PHP direto

```php
// Em qualquer contexto WordPress (ex: functions.php temporário)
require_once WP_PLUGIN_DIR . '/fpse-core/scripts/fix-member-type-posts.php';
```

**O que o script faz:**

1. ✅ Lista todos os perfis configurados
2. ✅ Para cada perfil:
   - Verifica se o post `bp-member-type` existe
   - Se não existe, cria o post com todos os meta fields necessários
   - Verifica se o term na taxonomy existe
   - Se não existe, cria o term também
3. ✅ Exibe resumo com:
   - Quantidade de posts criados
   - Quantidade de posts já existentes
   - Lista de erros (se houver)
   - Verificação final de todos os posts no banco

**Saída esperada:**

```
=== Fix Member Type Posts ===
Data: 2026-01-27 10:30:00

Perfis configurados: 6
  - estudante_eaa: Estudante EAA
  - bolsista_ies: Bolsista IES
  - gestor_gti: Gestor GTI
  - gestor_nap: Gestor NAP
  - gestor_escola: Gestor Escola
  - gestor_rede: Gestor Rede

Processando: estudante_eaa (fpse_estudante_eaa)...
  ✓ Post criado (ID: 123)
  ✓ Term já existe (ID: 45)

Processando: bolsista_ies (fpse_bolsista_ies)...
  ✓ Post criado (ID: 124)
  ✓ Term criado (ID: 91)

...

=== Resumo ===
Posts criados: 6
Posts já existentes: 0
Erros: 0

=== Verificação Final ===
Total de posts bp-member-type no banco: 6

Posts encontrados:
  - ID: 123 | Estudante EAA | Slug: fpse_estudante_eaa | Key: fpse_estudante_eaa
  - ID: 124 | Bolsista IES | Slug: fpse_bolsista_ies | Key: fpse_bolsista_ies
  ...

=== Concluído ===
```

## Segurança

⚠️ **IMPORTANTE:**
- Execute scripts apenas em ambiente de desenvolvimento/staging primeiro
- Faça backup do banco antes de executar em produção
- Nunca deixe scripts de manutenção acessíveis publicamente
- Delete arquivos temporários após uso

## Troubleshooting

### "Nenhum perfil encontrado na configuração"

**Causa:** Arquivo `config/profiles.php` não está carregado ou está vazio.

**Solução:**
1. Verificar se o arquivo existe: `fpse-core/config/profiles.php`
2. Verificar se o plugin está ativo
3. Verificar logs do WordPress para erros de carregamento

### "Falha ao criar post: Invalid post type"

**Causa:** BuddyBoss não está ativo ou não registrou o post type `bp-member-type`.

**Solução:**
1. Verificar se BuddyBoss está ativo
2. Verificar se BuddyBoss Member Types está habilitado nas configurações
3. Tentar desativar e reativar o BuddyBoss

### "Post criado mas Tipo de Perfil ainda vazio"

**Causa:** Cache do WordPress ou do navegador.

**Solução:**
1. Limpar cache do WordPress (se usar plugin de cache)
2. Fazer hard refresh no navegador (Ctrl+Shift+R)
3. Verificar se o post foi realmente criado:
   ```sql
   SELECT * FROM wp_posts WHERE post_type = 'bp-member-type';
   ```

## Logs

O script usa `echo` para saída. Para salvar em arquivo:

```bash
wp eval-file fpse-core/scripts/fix-member-type-posts.php > fix-member-types.log 2>&1
```
