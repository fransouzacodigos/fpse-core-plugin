# Como Executar o Script de Correção

## ❌ Erro Comum

```bash
root@mail:/www/wwwroot/.../fpse-core/scripts# php fpse-core/scripts/fix-member-type-posts.php
Could not open input file: fpse-core/scripts/fix-member-type-posts.php
```

**Problema**: Você já está DENTRO do diretório `scripts/`, então o caminho está errado.

---

## ✅ Forma Correta

### Opção 1: Executar do diretório scripts/ (RECOMENDADO)

```bash
cd /www/wwwroot/avab.fortalecepse.com.br/wp-content/plugins/fpse-core/scripts
php fix-member-type-posts.php
```

### Opção 2: Executar do diretório do plugin

```bash
cd /www/wwwroot/avab.fortalecepse.com.br/wp-content/plugins/fpse-core
php scripts/fix-member-type-posts.php
```

### Opção 3: Executar de qualquer lugar (caminho absoluto)

```bash
php /www/wwwroot/avab.fortalecepse.com.br/wp-content/plugins/fpse-core/scripts/fix-member-type-posts.php
```

---

## 📋 Passo a Passo Completo

```bash
# 1. Navegar até o diretório scripts
cd /www/wwwroot/avab.fortalecepse.com.br/wp-content/plugins/fpse-core/scripts

# 2. Verificar que o arquivo existe
ls -la fix-member-type-posts.php

# 3. Executar o script
php fix-member-type-posts.php

# 4. Verificar resultado
# O script mostrará quantos posts foram criados
```

---

## 🔍 Entendendo o Erro

Quando você está em `/www/.../fpse-core/scripts/` e executa:

```bash
php fpse-core/scripts/fix-member-type-posts.php
```

O PHP procura por: `/www/.../fpse-core/scripts/fpse-core/scripts/fix-member-type-posts.php`

Que não existe! Por isso o erro "Could not open input file".

---

## ✅ Solução Rápida

Se você já está no diretório correto (veja com `pwd`), simplesmente:

```bash
php fix-member-type-posts.php
```

Sem nenhum caminho adicional!
