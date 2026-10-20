<?php
/**
 * Plugin Name: Voe Cases
 * Description: Cadastro dos cases da Voe Agência. Os cases aparecem na seção "Cases" da home.
 * Version: 1.0.0
 * Author: Voe Agência
 * Text Domain: voe-cases
 */

if (!defined('ABSPATH')) {
    exit;
}

const VOE_CASES_TYPE = 'voe_case';

/* ---------- Tipo de conteúdo "Case" ---------- */
add_action('init', 'voe_cases_register_type');

function voe_cases_register_type()
{
    register_post_type(VOE_CASES_TYPE, [
        'labels' => [
            'name'                  => 'Cases',
            'singular_name'         => 'Case',
            'menu_name'             => 'Cases',
            'add_new'               => 'Adicionar case',
            'add_new_item'          => 'Adicionar novo case',
            'edit_item'             => 'Editar case',
            'new_item'              => 'Novo case',
            'all_items'             => 'Todos os cases',
            'search_items'          => 'Buscar cases',
            'not_found'             => 'Nenhum case cadastrado.',
            'not_found_in_trash'    => 'Nenhum case na lixeira.',
            'featured_image'        => 'Imagem do case',
            'set_featured_image'    => 'Definir imagem do case',
            'remove_featured_image' => 'Remover imagem do case',
            'use_featured_image'    => 'Usar como imagem do case',
        ],
        'public'        => false,
        'show_ui'       => true,
        'show_in_menu'  => true,
        'menu_position' => 20,
        'menu_icon'     => 'dashicons-portfolio',
        'supports'      => ['title', 'thumbnail', 'page-attributes'],
        'show_in_rest'  => false,
    ]);
}

add_filter('enter_title_here', function ($text, $post) {
    return $post->post_type === VOE_CASES_TYPE ? 'Nome do cliente' : $text;
}, 10, 2);

/* ---------- Campos: segmento e link ---------- */
add_action('add_meta_boxes', function () {
    add_meta_box('voe_case_info', 'Informações do case', 'voe_cases_render_box', VOE_CASES_TYPE, 'normal', 'high');
});

function voe_cases_render_box($post)
{
    wp_nonce_field('voe_case_save', 'voe_case_nonce');
    $segmento = get_post_meta($post->ID, 'voe_segmento', true);
    $link     = get_post_meta($post->ID, 'voe_link', true);
    ?>
    <p>
        <label for="voe_segmento"><strong>Segmento</strong></label><br>
        <input type="text" id="voe_segmento" name="voe_segmento" class="widefat"
               value="<?php echo esc_attr($segmento); ?>" placeholder="Ex.: Saúde · Pediatria">
    </p>
    <p>
        <label for="voe_link"><strong>Link do site</strong></label><br>
        <input type="url" id="voe_link" name="voe_link" class="widefat"
               value="<?php echo esc_attr($link); ?>" placeholder="https://www.cliente.com.br">
    </p>
    <p class="description">
        A imagem do case é a <strong>Imagem do case</strong> (coluna da direita). Tamanho ideal: 1600 × 1000 px.
        Para mudar a ordem na página, use o campo <strong>Ordem</strong> em "Atributos" (números menores aparecem primeiro).
    </p>
    <?php
}

add_action('save_post_' . VOE_CASES_TYPE, function ($post_id) {
    if (!isset($_POST['voe_case_nonce']) || !wp_verify_nonce($_POST['voe_case_nonce'], 'voe_case_save')) {
        return;
    }
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }
    if (isset($_POST['voe_segmento'])) {
        update_post_meta($post_id, 'voe_segmento', sanitize_text_field(wp_unslash($_POST['voe_segmento'])));
    }
    if (isset($_POST['voe_link'])) {
        update_post_meta($post_id, 'voe_link', esc_url_raw(wp_unslash($_POST['voe_link'])));
    }
});

/* ---------- Colunas na listagem do painel ---------- */
add_filter('manage_' . VOE_CASES_TYPE . '_posts_columns', function ($cols) {
    return [
        'cb'           => $cols['cb'],
        'voe_imagem'   => 'Imagem',
        'title'        => 'Cliente',
        'voe_segmento' => 'Segmento',
        'voe_ordem'    => 'Ordem',
        'date'         => $cols['date'],
    ];
});

add_action('manage_' . VOE_CASES_TYPE . '_posts_custom_column', function ($col, $post_id) {
    if ($col === 'voe_imagem') {
        echo get_the_post_thumbnail($post_id, [90, 56], ['style' => 'border-radius:6px;object-fit:cover;width:90px;height:56px']);
    } elseif ($col === 'voe_segmento') {
        echo esc_html(get_post_meta($post_id, 'voe_segmento', true));
    } elseif ($col === 'voe_ordem') {
        echo (int) get_post_field('menu_order', $post_id);
    }
}, 10, 2);

/* ---------- API lida pela home: /wp-json/voe/v1/cases ---------- */
add_action('rest_api_init', function () {
    register_rest_route('voe/v1', '/cases', [
        'methods'             => 'GET',
        'permission_callback' => '__return_true',
        'callback'            => function () {
            $posts = get_posts([
                'post_type'      => VOE_CASES_TYPE,
                'post_status'    => 'publish',
                'posts_per_page' => -1,
                'orderby'        => ['menu_order' => 'ASC', 'date' => 'DESC'],
            ]);
            return array_map(function ($p) {
                return [
                    'nome'     => html_entity_decode(get_the_title($p), ENT_QUOTES, 'UTF-8'),
                    'segmento' => get_post_meta($p->ID, 'voe_segmento', true),
                    'link'     => get_post_meta($p->ID, 'voe_link', true),
                    'imagem'   => get_the_post_thumbnail_url($p, 'large') ?: '',
                ];
            }, $posts);
        },
    ]);
});

/* ---------- Na ativação, cadastra os 6 cases atuais ---------- */
register_activation_hook(__FILE__, function () {
    voe_cases_register_type();
    if (get_posts(['post_type' => VOE_CASES_TYPE, 'post_status' => 'any', 'posts_per_page' => 1, 'fields' => 'ids'])) {
        return;
    }
    $uploads = 'https://voeagencia.com.br/wp-content/uploads/2026/10/';
    $cases = [
        ['Ponto da Obra', 'Locação de equipamentos', 'https://pontodaobra.com.br', 'Ponto-DaObra.png'],
        ['Móveis Nascimento', 'Varejo · Móveis', 'https://moveisnascimento.com.br', 'Moveis-Nascimento.png'],
        ['Vista Motors', 'Automotivo · Seminovos', 'https://vistamotors.com.br', 'VistaMotors.png'],
        ['Clínica Brincar', 'Saúde · Pediatria', 'https://clinicabrincar.com.br', 'Brincar.png'],
        ['A3P Transporte', 'Fretamento corporativo', 'https://a3ptransporte.com.br', 'A3P.png'],
        ['CMW Transportes', 'Transporte · Fretamento', 'https://www.cmwtransportes.com.br', 'CMW.png'],
    ];
    foreach ($cases as $i => [$nome, $segmento, $link, $arquivo]) {
        $id = wp_insert_post([
            'post_type'   => VOE_CASES_TYPE,
            'post_status' => 'publish',
            'post_title'  => $nome,
            'menu_order'  => $i + 1,
        ]);
        if (!$id || is_wp_error($id)) {
            continue;
        }
        update_post_meta($id, 'voe_segmento', $segmento);
        update_post_meta($id, 'voe_link', $link);
        $imagem = attachment_url_to_postid($uploads . $arquivo);
        if ($imagem) {
            set_post_thumbnail($id, $imagem);
        }
    }
});
