# 👥 Roles e Perfis do WordPress

O plugin FPSE Core cria automaticamente **roles (perfis) de usuário WordPress** baseados na configuração de `config/profiles.php`.

## 📋 Como Funciona

### Na Ativação do Plugin

Quando você ativa o plugin, ele:

1. **Lê `config/profiles.php`** (lista de todos os perfis disponíveis)
2. **Cria um role WordPress para cada perfil**
3. **Atribui capabilities básicas** a cada role

### Roles Criados

Baseado em `config/profiles.php`, os seguintes roles são criados:

#### EAA (Educação de Adolescentes e Adultos)
- `fpse_estudante_eaa` - Estudante - EAA
- `fpse_professor_eaa` - Professor - EAA
- `fpse_gestor_eaa` - Gestor - EAA

#### IES (Instituições de Ensino Superior)
- `fpse_estudante_ies` - Estudante - IES
- `fpse_professor_ies` - Professor - IES
- `fpse_pesquisador` - Pesquisador

#### NAP (Núcleo de Acessibilidade Pedagógica)
- `fpse_gestor_nap` - Gestor - NAP
- `fpse_assistente_nap` - Assistente - NAP

#### GTI (Gestão Tecnológica Inclusiva)
- `fpse_gestor_gti` - Gestor - GTI
- `fpse_tecnico_gti` - Técnico - GTI

#### Governance
- `fpse_coordenador_institucional` - Coordenador Institucional
- `fpse_monitor_programa` - Monitor do Programa

**Total:** 12 roles criados automaticamente

## 🔄 Como Atribuir Roles

### Automaticamente no Registro

Quando um usuário se registra via API (`/wp-json/fpse/v1/register`):

1. O campo `perfil_usuario` define qual perfil o usuário terá
2. O plugin automaticamente atribui o role correspondente
3. Exemplo: Se `perfil_usuario = 'estudante-eaa'`, o role `fpse_estudante_eaa` é atribuído

### Manualmente (Admin WordPress)

Você também pode atribuir roles manualmente:

1. Acesse **Usuários** → **Todos os Usuários**
2. Edite um usuário
3. Em **Função**, você verá todos os roles FPSE criados
4. Selecione o role apropriado e salve

## ✨ Capabilities (Permissões)

Todos os roles FPSE recebem as seguintes capabilities:

- ✅ `read` - Pode ler conteúdo do WordPress
- ✅ `view_fpse_registrations` - Pode ver registros do FPSE (próprios)

### Capabilities Especiais

Os roles admin (definidos em `config/permissions.php`) recebem capabilities adicionais:

- `manage_fpse_registrations` - Gerenciar registros
- `view_fpse_reports` - Ver relatórios
- `export_fpse_reports` - Exportar relatórios

## 🔍 Verificar Roles Criados

### Via WordPress Admin

1. Acesse **Usuários** → **Funções**
2. Você verá todos os roles FPSE listados

### Via Código

```php
use FortaleceePSE\Core\Utils\RoleCreator;
use FortaleceePSE\Core\Plugin;

$plugin = Plugin::getInstance();
$roleCreator = new RoleCreator($plugin);

// Verificar se role existe
$exists = $roleCreator->roleExistsForProfile('estudante-eaa');

// Obter nome do role
$roleName = RoleCreator::getRoleNameForProfile('estudante-eaa');
// Retorna: 'fpse_estudante_eaa'
```

### Via WP-CLI

```bash
# Listar todos os roles
wp role list

# Verificar um role específico
wp role list --format=table | grep fpse

# Ver capabilities de um role
wp role get fpse_estudante_eaa
```

## 🔧 Adicionar Novos Perfis

Para adicionar um novo perfil e criar o role correspondente:

1. **Edite `config/profiles.php`**:

```php
'meu-novo-perfil' => [
    'label' => 'Meu Novo Perfil',
    'category' => 'Custom',
    'description' => 'Descrição do novo perfil',
    'specific_fields' => ['campo1', 'campo2'],
],
```

2. **Reative o plugin** para criar o role:

```bash
wp plugin deactivate fpse-core
wp plugin activate fpse-core
```

Ou via WordPress Admin: **Plugins** → **Desativar FPSE Core** → **Ativar FPSE Core**

3. **Pronto!** O role `fpse_meu_novo_perfil` será criado automaticamente.

## 🗑️ Remover Roles

### Remover Todos os Roles FPSE

```php
use FortaleceePSE\Core\Utils\RoleCreator;
use FortaleceePSE\Core\Plugin;

$plugin = Plugin::getInstance();
$roleCreator = new RoleCreator($plugin);
$removed = $roleCreator->removeAllRoles();
```

**⚠️ Atenção:** Isso remove os roles, mas **não remove os usuários**. Os usuários perderão o role e precisarão ter outro role atribuído.

### Remover Um Role Específico

```bash
wp role delete fpse_estudante_eaa
```

## 📝 Convenções de Nomeação

- **Profile ID** (config): `estudante-eaa` (kebab-case)
- **Role Name** (WordPress): `fpse_estudante_eaa` (snake_case com prefixo `fpse_`)
- **Role Display Name**: `Estudante - EAA` (label do config)

O prefixo `fpse_` evita conflitos com outros plugins.

## 🎯 Uso Prático

### Filtrar Usuários por Perfil

```php
// Buscar todos os usuários com perfil de estudante EAA
$users = get_users([
    'role' => 'fpse_estudante_eaa',
]);
```

### Verificar Permissão

```php
// Verificar se usuário tem perfil específico
$user = wp_get_current_user();
if (in_array('fpse_estudante_eaa', $user->roles)) {
    // Usuário é estudante EAA
}
```

### Atribuir Role Programaticamente

```php
use FortaleceePSE\Core\Utils\RoleCreator;
use FortaleceePSE\Core\Plugin;

$plugin = Plugin::getInstance();
$roleCreator = new RoleCreator($plugin);

// Atribuir role baseado em perfil
$roleCreator->assignRoleByProfile($userId, 'estudante-eaa');
```

## 🔄 Sincronização

Os roles são criados/atualizados automaticamente:

- ✅ **Na ativação do plugin** - Cria todos os roles
- ✅ **No registro de usuário** - Atribui role automaticamente
- ✅ **Na atualização de perfil** - Atualiza role do usuário

Se você adicionar um novo perfil em `config/profiles.php`, **reative o plugin** para criar o role correspondente.

## 🐛 Troubleshooting

### Roles não foram criados

**Causa:** Plugin foi ativado antes da implementação desta funcionalidade.

**Solução:**
1. Desative o plugin
2. Ative novamente
3. Os roles serão criados automaticamente

### Role não existe para um perfil

**Causa:** Perfil foi adicionado ao config, mas plugin não foi reativado.

**Solução:**
1. Reative o plugin (desative → ative)
2. O role será criado automaticamente

### Usuário não tem role atribuído

**Causa:** Usuário foi criado antes da implementação ou role foi removido.

**Solução:**
1. Edite o usuário no WordPress Admin
2. Selecione o role apropriado
3. Ou use `RoleCreator::assignRoleByProfile()` programaticamente

## 📚 Referências

- [WordPress Roles and Capabilities](https://wordpress.org/support/article/roles-and-capabilities/)
- `config/profiles.php` - Configuração de perfis
- `src/Utils/RoleCreator.php` - Classe que cria roles
- `src/Plugin.php` - Método `createProfileRoles()`
