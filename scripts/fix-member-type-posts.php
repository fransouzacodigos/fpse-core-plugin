<?php
/**
 * Script de Correção: Criar Posts bp-member-type para Usuários Existentes
 * 
 * Este script cria os posts do tipo 'bp-member-type' para usuários que já têm
 * o term na taxonomy mas não têm o post correspondente.
 * 
 * Executar via WP-CLI:
 * wp eval-file fpse-core/scripts/fix-member-type-posts.php
 * 
 * Ou via admin (criar página temporária):
 * require_once 'fpse-core/scripts/fix-member-type-posts.php';
 */

// Verificar se está no contexto WordPress
if (!defined('ABSPATH')) {
    die('Este script deve ser executado no contexto do WordPress');
}

echo "=== Fix Member Type Posts ===\n";
echo "Data: " . date('Y-m-d H:i:s') . "\n\n";

// Obter configuração de perfis
$plugin = \FortaleceePSE\Core\Plugin::getInstance();
$profiles = $plugin->getConfig('profiles', []);

if (empty($profiles)) {
    echo "❌ ERRO: Nenhum perfil encontrado na configuração\n";
    exit(1);
}

echo "Perfis configurados: " . count($profiles) . "\n";
foreach ($profiles as $profileId => $profileData) {
    echo "  - {$profileId}: {$profileData['label']}\n";
}
echo "\n";

// Para cada perfil, verificar/criar post bp-member-type
$created = 0;
$existing = 0;
$errors = [];

foreach ($profiles as $profileId => $profileData) {
    $memberType = 'fpse_' . str_replace('-', '_', strtolower($profileId));
    $label = $profileData['label'] ?? ucfirst(str_replace('-', ' ', $profileId));
    
    echo "Processando: {$profileId} ({$memberType})...\n";
    
    // Verificar se post já existe
    $existingPost = get_posts([
        'post_type' => 'bp-member-type',
        'post_status' => 'any',
        'meta_key' => '_bp_member_type_key',
        'meta_value' => $memberType,
        'posts_per_page' => 1,
    ]);
    
    if (!empty($existingPost)) {
        echo "  ✓ Post já existe (ID: {$existingPost[0]->ID})\n";
        $existing++;
        continue;
    }
    
    // Criar post
    $postId = wp_insert_post([
        'post_title' => $label,
        'post_name' => $memberType,
        'post_status' => 'publish',
        'post_type' => 'bp-member-type',
        'post_content' => $profileData['description'] ?? '',
    ]);
    
    if (is_wp_error($postId)) {
        $error = "Falha ao criar post para {$profileId}: " . $postId->get_error_message();
        echo "  ✗ {$error}\n";
        $errors[] = $error;
        continue;
    }
    
    if ($postId <= 0) {
        $error = "Falha ao criar post para {$profileId}: ID inválido";
        echo "  ✗ {$error}\n";
        $errors[] = $error;
        continue;
    }
    
    // Definir meta fields
    update_post_meta($postId, '_bp_member_type_key', $memberType);
    update_post_meta($postId, '_bp_member_type_label_singular', $label);
    update_post_meta($postId, '_bp_member_type_label_plural', $label);
    update_post_meta($postId, '_bp_member_type_has_directory', '1');
    update_post_meta($postId, '_bp_member_type_show_in_loop', '0');
    
    echo "  ✓ Post criado (ID: {$postId})\n";
    $created++;
    
    // Verificar se term existe na taxonomy
    $term = get_term_by('slug', $memberType, 'bp_member_type');
    if (!$term || is_wp_error($term)) {
        echo "  ⚠ Term não existe na taxonomy - criando...\n";
        
        $termResult = wp_insert_term(
            $label,
            'bp_member_type',
            [
                'slug' => $memberType,
                'description' => $profileData['description'] ?? ''
            ]
        );
        
        if (is_wp_error($termResult)) {
            echo "  ✗ Falha ao criar term: " . $termResult->get_error_message() . "\n";
        } else {
            echo "  ✓ Term criado (ID: {$termResult['term_id']})\n";
        }
    } else {
        echo "  ✓ Term já existe (ID: {$term->term_id})\n";
    }
}

echo "\n=== Resumo ===\n";
echo "Posts criados: {$created}\n";
echo "Posts já existentes: {$existing}\n";
echo "Erros: " . count($errors) . "\n";

if (!empty($errors)) {
    echo "\nDetalhes dos erros:\n";
    foreach ($errors as $error) {
        echo "  - {$error}\n";
    }
}

echo "\n=== Verificação Final ===\n";

// Listar todos os posts bp-member-type
global $wpdb;
$posts = $wpdb->get_results("
    SELECT p.ID, p.post_title, p.post_name, pm.meta_value as member_type_key
    FROM {$wpdb->posts} p
    LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_bp_member_type_key'
    WHERE p.post_type = 'bp-member-type'
    ORDER BY p.post_title
");

echo "Total de posts bp-member-type no banco: " . count($posts) . "\n\n";

if (!empty($posts)) {
    echo "Posts encontrados:\n";
    foreach ($posts as $post) {
        echo "  - ID: {$post->ID} | {$post->post_title} | Slug: {$post->post_name} | Key: {$post->member_type_key}\n";
    }
} else {
    echo "⚠ Nenhum post bp-member-type encontrado!\n";
}

echo "\n=== Concluído ===\n";
