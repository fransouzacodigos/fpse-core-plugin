# 🚩 Bandeiras dos Estados

Este diretório contém as bandeiras oficiais dos estados brasileiros.

## 📋 Arquivos Necessários

Cada estado precisa ter sua bandeira em formato PNG:

- `ac.png` - Acre
- `al.png` - Alagoas
- `ap.png` - Amapá
- `am.png` - Amazonas
- `ba.png` - Bahia
- `ce.png` - Ceará
- `df.png` - Distrito Federal
- `es.png` - Espírito Santo
- `go.png` - Goiás
- `ma.png` - Maranhão
- `mt.png` - Mato Grosso
- `ms.png` - Mato Grosso do Sul
- `mg.png` - Minas Gerais
- `pa.png` - Pará
- `pb.png` - Paraíba
- `pr.png` - Paraná
- `pe.png` - Pernambuco
- `pi.png` - Piauí
- `rj.png` - Rio de Janeiro
- `rn.png` - Rio Grande do Norte
- `rs.png` - Rio Grande do Sul
- `ro.png` - Rondônia
- `rr.png` - Roraima
- `sc.png` - Santa Catarina
- `sp.png` - São Paulo
- `se.png` - Sergipe
- `to.png` - Tocantins

**Total: 27 arquivos** (26 estados + DF)

## 📐 Especificações

- **Formato:** PNG
- **Tamanho recomendado:** 200x200px ou 300x300px
- **Nome do arquivo:** Código da UF em minúsculas (ex: `sp.png`, `rj.png`)

## 🔍 Como Obter as Bandeiras

As bandeiras oficiais dos estados brasileiros podem ser encontradas em:

1. **Governo Federal** - Sites oficiais dos estados
2. **Wikimedia Commons** - Domínio público
3. **SVG para PNG** - Converta SVGs oficiais para PNG

**Importante:** Use apenas bandeiras oficiais e de domínio público.

## ✅ Verificação

O plugin procura os arquivos em:
```
fpse-core/assets/flags/{uf}.png
```

Exemplo:
- `fpse-core/assets/flags/sp.png` para São Paulo
- `fpse-core/assets/flags/rj.png` para Rio de Janeiro

Se o arquivo não for encontrado, o grupo será criado sem avatar.

## 🎨 Uso

As bandeiras são automaticamente:
1. Copiadas para o diretório de avatares do BuddyBoss
2. Atribuídas aos grupos estaduais como avatar
3. Exibidas no frontend do BuddyBoss

## 📝 Nota

Se as bandeiras não estiverem disponíveis inicialmente:
- Os grupos ainda serão criados (sem avatar)
- Você pode adicionar as bandeiras depois
- O plugin tentará atualizar os avatares na próxima ativação
