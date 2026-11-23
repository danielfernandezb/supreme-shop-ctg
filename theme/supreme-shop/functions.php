<?php
if (!defined('ABSPATH')) { exit; }

add_action('after_setup_theme', function () {
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('woocommerce');
});

function supreme_seed() {
    return json_decode(file_get_contents(get_template_directory() . '/catalog.json'), true) ?: array();
}

function supreme_category_url($slug) {
    $term = get_term_by('slug', $slug, 'product_cat');
    if ($term) {
        $url = get_term_link($term);
        if (!is_wp_error($url)) { return $url; }
    }
    return home_url('/?marca=' . rawurlencode($slug) . '#catalogo');
}

function supreme_card($product) {
    $terms = get_the_terms($product->get_id(), 'product_cat');
    $brand = (!is_wp_error($terms) && $terms) ? $terms[0]->name : '';
    $collection = $brand;
    $brand = $product->get_attribute('Marca') ?: $brand;
    $size = $product->get_attribute('Talla EU');
    $price = $product->get_price() === '' ? 'Precio por confirmar' : $product->get_price_html();
    $url = esc_url($product->get_permalink());
    ob_start(); ?>
    <article class="card" data-brand="<?php echo esc_attr($collection); ?>">
        <a class="imagewrap" href="<?php echo $url; ?>" aria-label="<?php echo esc_attr('Ver ' . $product->get_name()); ?>">
            <span class="cardlabel">SKU <?php echo esc_html($product->get_sku()); ?></span>
            <?php echo $product->get_image('large', array('loading' => 'lazy')); ?>
        </a>
        <div class="cardinfo"><div>
            <small><?php echo esc_html($brand . ' / SKU ' . $product->get_sku()); ?></small>
            <h3><?php echo esc_html($product->get_name()); ?></h3>
            <p><?php echo $size ? esc_html('EU ' . $size) . ' · ' : ''; ?><?php echo wp_kses_post($price); ?></p><p class="stock-note"><?php echo $product->is_in_stock() ? 'Disponible' : 'Agotado'; ?></p>
        </div><a class="roundarrow" href="<?php echo $url; ?>" aria-label="<?php echo esc_attr('Ver ' . $product->get_name()); ?>">↗</a></div>
    </article>
    <?php return ob_get_clean();
}

function supreme_cards($category = '', $newest = false, $limit = 0) {
    if (!function_exists('wc_get_products')) { return ''; }
    $args = array('status' => 'publish', 'limit' => -1, 'orderby' => $newest ? 'date' : 'menu_order', 'order' => $newest ? 'DESC' : 'ASC');
    if ($category) { $args['category'] = array($category); }
    $cards = array();
    foreach (wc_get_products($args) as $index => $product) {
        $card = supreme_card($product);
        if ($limit && $index >= $limit) { $card = preg_replace('/<article /', '<article hidden ', $card, 1); }
        $cards[] = $card;
    }
    return implode('', $cards);
}

function supreme_template($file, $category = null) {
    $allowed = array('home', 'adidas', 'new-balance', 'nike', 'puma', 'mizuno');
    if (!in_array($file, $allowed, true)) { $file = 'home'; }
    $home_novelties = $file === 'home' && !$category && is_front_page();
    $html = file_get_contents(get_template_directory() . '/templates/' . $file . '.html');
    if (function_exists('wc_get_products') && get_option('supreme_catalog_complete')) {
        $html = preg_replace_callback('/<div class="grid">.*?<\/article><\/div>/s', function () use ($category, $home_novelties) {
            return '<div class="grid">' . supreme_cards($category ?: '', $home_novelties, $home_novelties ? 10 : 0) . '</div>';
        }, $html, 1);
    }
    $html = preg_replace_callback('/(?:\.\/)?index\.html/', function () { return esc_url(home_url('/')); }, $html);
    $html = preg_replace_callback('/coleccion-([a-z-]+)\.html/', function ($match) {
        return esc_url(supreme_category_url($match[1]));
    }, $html);
    $html = preg_replace_callback('/producto-(\d+)\.html/', function ($match) {
        $id = function_exists('wc_get_product_id_by_sku') ? wc_get_product_id_by_sku($match[1]) : 0;
        return esc_url($id ? get_permalink($id) : home_url('/#catalogo'));
    }, $html);
    $html = str_replace('assets/', esc_url(get_template_directory_uri() . '/assets/'), $html);
    $html = str_replace('/assets/supreme.js"', '/assets/supreme.js?v=' . filemtime(get_template_directory() . '/assets/supreme.js') . '"', $html);
    if (function_exists('wc_get_products')) {
        $collections = get_terms(array('taxonomy' => 'product_cat', 'hide_empty' => true));
        $links = '';
        $filters = '<button class="filter" data-filter="Todos" aria-pressed="true">Todos</button>';
        if (!is_wp_error($collections)) {
            foreach ($collections as $collection) {
                if ($collection->slug === 'uncategorized') { continue; }
                $links .= '<a href="' . esc_url(supreme_category_url($collection->slug)) . '">' . esc_html($collection->name) . '</a>';
                $filters .= '<button class="filter" data-filter="' . esc_attr($collection->name) . '" aria-pressed="false">' . esc_html($collection->name) . '</button>';
            }
        }
        $html = preg_replace_callback('/<div class="collectionlinks">.*?<\/div>/s', function () use ($links) { return '<div class="collectionlinks">' . $links . '</div>'; }, $html, 1);
        $html = preg_replace_callback('/<div class="filters"[^>]*>.*?<\/div>/s', function () use ($filters) { return '<div class="filters" aria-label="Filtrar por colección">' . $filters . '</div>'; }, $html, 1);
        $html = str_replace('13 referencias', count(wc_get_products(array('status' => 'publish', 'limit' => -1, 'return' => 'ids'))) . ' referencias', $html);
        if ($category && !in_array($category, array('adidas', 'new-balance', 'nike', 'puma', 'mizuno'), true)) {
            $term = get_term_by('slug', $category, 'product_cat');
            $label = $term ? $term->name : 'Colección';
            $banner = supreme_collection_banner($category, $label);
            $html = preg_replace_callback('/<main id="main">.*?<\/main>/s', function () use ($category, $label, $banner) {
                return '<main id="main"><section class="section"><a href="' . esc_url(home_url('/#catalogo')) . '">← Volver al catálogo</a>' . $banner . '<div class="sectionhead"><div><span class="kicker">Colección</span><h2>' . esc_html($label) . '</h2></div></div><div class="grid">' . supreme_cards($category) . '</div></section></main>';
            }, $html, 1);
        } elseif ($file === 'home' && !$category) {
            $extra = '';
            foreach (array('asics' => 'Asics', 'skechers' => 'Skechers', 'exclusivos' => 'Exclusivos') as $slug => $label) {
                $banner = supreme_collection_banner($slug, $label);
                if ($banner && strpos($html, 'id="coleccion-' . $slug . '"') === false) { $extra .= $banner; }
            }
            $html = preg_replace_callback('/(<\/section>\s*<\/main>)/', function ($match) use ($extra) { return $extra . $match[1]; }, $html, 1);
            $html = preg_replace_callback('/(<div class="quickbrands-grid">)(.*?)(<\/div>)/s', function ($match) {
                if (strpos($match[2], '>Asics<') !== false) { return $match[0]; }
                $shortcuts = '';
                foreach (array('asics' => 'Asics', 'skechers' => 'Skechers', 'exclusivos' => 'Exclusivos') as $slug => $label) {
                    $products = wc_get_products(array('status' => 'publish', 'category' => array($slug), 'limit' => 1));
                    if ($products) { $shortcuts .= '<a class="quickbrand" href="' . esc_url(supreme_category_url($slug)) . '">' . $products[0]->get_image('thumbnail', array('alt' => '', 'loading' => 'lazy')) . '<span>' . esc_html($label) . '</span><span aria-hidden="true">↗</span></a>'; }
                }
                return $match[1] . $match[2] . $shortcuts . $match[3];
            }, $html, 1);
            $html = str_replace('</head>', '<style>.quickbrands-grid{grid-template-columns:repeat(4,minmax(0,1fr))}</style></head>', $html);
        }
    }
    if ($home_novelties) {
        $html = str_replace(array('01 / Encuentra tu par', 'Elige cómo pisas.', 'Clásicos de la calle y siluetas deportivas.<br>¿Cuál va con tu estilo?'), array('01 / Recién llegados', 'Novedades', 'Descubre los últimos modelos de Supreme Shop.'), $html);
        $html = str_replace('id="catalogo" data-catalog>', 'id="catalogo" data-catalog data-limit="10">', $html);
        $shop_url = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/?post_type=product');
        if (!$shop_url || untrailingslashit($shop_url) === untrailingslashit(home_url('/'))) { $shop_url = home_url('/?post_type=product'); }
        $html = str_replace('href="catalogo.html"', 'href="' . esc_url($shop_url) . '"', $html);
        if (strpos($html, 'data-view-all') === false) {
            $html = preg_replace_callback('/<p class="catalogfoot"><span data-count>.*?<\/p>/s', function () use ($shop_url) {
                return '<p class="catalogfoot"><span data-count>10 productos</span></p><div class="actions"><a class="btn" data-view-all href="' . esc_url($shop_url) . '">Ver todos los productos ↗</a></div>';
            }, $html, 1);
        }
        $html = str_replace('</head>', '<style>#catalogo[data-limit="10"] .grid{grid-template-columns:repeat(5,minmax(0,1fr));gap:16px}#catalogo[data-limit="10"] .cardinfo{padding:14px;gap:8px}#catalogo[data-limit="10"] .card h3{font-size:14px;line-height:1.3}@media(max-width:1024px){#catalogo[data-limit="10"] .grid{grid-template-columns:repeat(3,minmax(0,1fr))}}@media(max-width:600px){#catalogo[data-limit="10"] .grid{grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}#catalogo[data-limit="10"] .cardinfo{padding:12px}}</style></head>', $html);
    }
    return $html;
}


function supreme_collection_banner($slug, $label) {
    $products = wc_get_products(array('status' => 'publish', 'category' => array($slug), 'limit' => 1, 'orderby' => 'menu_order', 'order' => 'ASC'));
    if (!$products) { return ''; }
    $product = $products[0];
    $url = esc_url(supreme_category_url($slug));
    return '<article class="brandbanner" style="background:#e7dfd5;color:#211a30"><a class="brandvisual" href="' . $url . '" aria-label="Ver colección ' . esc_attr($label) . '">' . $product->get_image('large') . '</a><div class="brandcopy"><span class="kicker">Selección Supreme Shop</span><h2>' . esc_html($label) . '</h2><p>Encuentra tu próximo par.</p><a class="btn" href="' . $url . '">Ver colección ↗</a></div></article>';
}

function supreme_import_image($filename, $parent) {
    $existing = get_posts(array('post_type' => 'attachment', 'post_status' => 'inherit', 'numberposts' => 1,
        'meta_key' => '_supreme_source', 'meta_value' => $filename));
    if ($existing) { return $existing[0]->ID; }
    $source = get_template_directory() . '/assets/' . basename($filename);
    if (!is_readable($source)) { throw new Exception('Falta la foto ' . $filename); }
    $temp = wp_tempnam($filename);
    if (!$temp || !copy($source, $temp)) { throw new Exception('No se pudo preparar ' . $filename); }
    $id = media_handle_sideload(array('name' => basename($filename), 'tmp_name' => $temp), $parent);
    if (is_wp_error($id)) { @unlink($temp); throw new Exception($id->get_error_message()); }
    update_post_meta($id, '_supreme_source', $filename);
    update_post_meta($id, '_wp_attachment_image_alt', get_the_title($parent));
    return $id;
}

add_action('admin_menu', function () {
    add_theme_page('Supreme Shop', 'Supreme Shop', 'manage_options', 'supreme-setup', 'supreme_setup_page');
});

function supreme_setup_page() {
    if (!current_user_can('manage_options')) { return; }
    echo '<div class="wrap"><h1>Supreme Shop CTG</h1>';
    if (!class_exists('WooCommerce')) {
        echo '<p>Activa WooCommerce para cargar el catálogo.</p></div>'; return;
    }
    if (get_option('supreme_catalog_complete')) {
        echo '<p>Catálogo importado. Los productos y sus imágenes están disponibles en WooCommerce.</p>';
        echo '<p><a class="button button-primary" href="' . esc_url(home_url('/')) . '">Ver Supreme Shop</a></p></div>'; return;
    }
    if (isset($_POST['supreme_import'])) {
        check_admin_referer('supreme_import_catalog');
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        $products = supreme_seed();
        $offset = max(0, (int) get_option('supreme_import_offset', 0));
        try {
            foreach (array_slice($products, $offset, 3) as $index => $row) {
                $id = wc_get_product_id_by_sku($row['sku']);
                if (!$id) {
                    $collection = isset($row['category']) ? $row['category'] : $row['brand'];
                    $term = term_exists($collection, 'product_cat');
                    if (!$term) { $term = wp_insert_term($collection, 'product_cat'); }
                    if (is_wp_error($term)) { throw new Exception($term->get_error_message()); }
                    $term_id = is_array($term) ? (int) $term['term_id'] : (int) $term;
                    $product = new WC_Product_Simple();
                    $product->set_name($row['name']);
                    $product->set_sku($row['sku']);
                    $product->set_status('draft');
                    $product->set_category_ids(array($term_id));
                    $product->set_manage_stock(true);
                    $product->set_stock_quantity((int) $row['quantity']);
                    if (isset($row['price'])) { $product->set_regular_price((string) $row['price']); }
                    $product->set_menu_order($offset + $index);
                    $attribute = new WC_Product_Attribute();
                    $attribute->set_name('Talla EU');
                    $attribute->set_options(array((string) $row['size']));
                    $attribute->set_visible(true);
                    $brand_attribute = new WC_Product_Attribute();
                    $brand_attribute->set_name('Marca');
                    $brand_attribute->set_options(array($row['brand']));
                    $brand_attribute->set_visible(true);
                    $product->set_attributes(array($attribute, $brand_attribute));
                    $id = $product->save();
                    update_post_meta($id, '_supreme_seed', 1);
                }
                $product = wc_get_product($id);
                if (get_post_meta($id, '_supreme_seed', true) && !get_post_meta($id, '_supreme_imported', true)) {
                    $images = array();
                    foreach ($row['images'] as $image) { $images[] = supreme_import_image($image, $id); }
                    $product->set_image_id($images[0]);
                    $product->set_gallery_image_ids(array_slice($images, 1));
                    $product->set_status('publish');
                    $product->save();
                    update_post_meta($id, '_supreme_imported', 1);
                }
                update_option('supreme_import_offset', $offset + $index + 1);
            }
            if ((int) get_option('supreme_import_offset') >= count($products)) {
                update_option('supreme_catalog_complete', true);
                update_option('blogname', 'Supreme Shop CTG');
                update_option('blogdescription', 'Tenis en Cartagena y envíos a Colombia');
                update_option('woocommerce_currency', 'COP');
                update_option('woocommerce_default_country', 'CO');
                // Keep the storefront accessible on its temporary address.
                update_option('woocommerce_coming_soon', 'no');
                echo '<div class="notice notice-success"><p>Catálogo completo. Productos y fotos cargados.</p></div>';
            }
        } catch (Exception $error) {
            echo '<div class="notice notice-error"><p>' . esc_html($error->getMessage()) . '</p></div>';
        }
    }
    $offset = (int) get_option('supreme_import_offset', 0);
    echo '<p>' . esc_html($offset) . ' de ' . count(supreme_seed()) . ' productos procesados.</p>';
    if (!get_option('supreme_catalog_complete')) {
        echo '<form method="post">'; wp_nonce_field('supreme_import_catalog');
        echo '<button class="button button-primary" name="supreme_import" value="1">Importar siguiente grupo</button></form>';
    } else { echo '<a class="button button-primary" href="' . esc_url(home_url('/')) . '">Ver Supreme Shop</a>'; }
    echo '</div>';
}

add_filter('template_include', function ($template) {
    if (function_exists('is_woocommerce') && is_woocommerce()) {
        return get_template_directory() . '/index.php';
    }
    return $template;
}, 99);

// Complete the gallery of the newly uploaded exclusive product once.
add_action('admin_init', function () {
    if (!current_user_can('edit_products') || get_option('supreme_lv_gallery_ready') || !function_exists('wc_get_product_id_by_sku')) { return; }
    $id = wc_get_product_id_by_sku('100014');
    if (!$id) { return; }
    $product = wc_get_product($id);
    $images = get_posts(array('post_type' => 'attachment', 'post_status' => 'inherit', 'numberposts' => 3, 'meta_query' => array(array('key' => '_wp_attached_file', 'value' => 'LV-deportivo-champagne-', 'compare' => 'LIKE'))));
    $gallery = array();
    foreach ($images as $image) {
        if ((int) $image->ID !== (int) $product->get_image_id() && strpos(get_attached_file($image->ID), 'LV-deportivo-champagne-') !== false) { $gallery[] = $image->ID; }
    }
    sort($gallery);
    if (count($gallery) === 2) { $product->set_gallery_image_ids($gallery); $product->save(); update_option('supreme_lv_gallery_ready', true); }
});
