<?php
if (!defined('ABSPATH')) { exit; }
$page = 'home';
$category = null;
if (function_exists('is_product_category') && is_product_category()) {
    $category = get_queried_object()->slug;
    $page = $category;
}
$html = supreme_template($page, $category);
if (function_exists('is_product') && is_product()) {
    $product = wc_get_product(get_queried_object_id());
    $terms = get_the_terms($product->get_id(), 'product_cat');
    $brand = (!is_wp_error($terms) && $terms) ? $terms[0]->name : '';
    $brand = $product->get_attribute('Marca') ?: $brand;
    $size = $product->get_attribute('Talla EU');
    $images = array_filter(array_merge(array($product->get_image_id()), $product->get_gallery_image_ids()));
    ob_start(); ?>
    <section class="section product">
      <div class="gallery"><?php foreach ($images as $image) {
          echo wp_get_attachment_image($image, 'large', false, array('alt' => $product->get_name()));
      } ?></div>
      <div>
        <a class="kicker" href="<?php echo esc_url(home_url('/#catalogo')); ?>">← Volver al catálogo</a>
        <p class="sku">SKU <?php echo esc_html($product->get_sku()); ?></p>
        <h1><?php echo esc_html($product->get_name()); ?></h1>
        <p>Marca <?php echo esc_html($brand); ?></p>
        <div class="size-notice"><strong>Usamos talla europea (EU)</strong>
          <?php if ($size) { echo 'Talla de este producto <b>EU ' . esc_html($size) . '</b><br>'; } ?>
          <button type="button" class="guide-open" data-size-open data-brand="<?php echo esc_attr($brand); ?>">Guía de tallas · Cómo medir tu pie ↗</button>
        </div>
        <p class="price"><?php echo $product->get_price() === '' ? 'Precio por confirmar' : wp_kses_post($product->get_price_html()); ?></p>
        <div class="productdesc"><?php echo wp_kses_post(wpautop($product->get_description())); ?></div>
        <?php if ($product->is_purchasable() && $product->is_in_stock()) {
            $GLOBALS['product'] = $product;
            woocommerce_template_single_add_to_cart();
        } ?>
        <?php if (!$product->is_purchasable() || !$product->is_in_stock()) { ?><p class="catalogfoot"><?php echo $product->is_in_stock() ? 'Disponible · ' . esc_html($product->get_stock_quantity()) . ' unidad(es)' : 'Agotado'; ?></p><?php } ?>
        <div class="actions"><a class="btn acid" href="https://wa.link/17k3kw" target="_blank" rel="noopener">Consultar por WhatsApp ↗</a></div>
      </div>
    </section>
    <?php
    $body = ob_get_clean();
    $html = preg_replace_callback('/<main id="main">.*?<\/main>/s', function () use ($body) {
        return '<main id="main">' . $body . '</main>';
    }, $html);
} elseif (function_exists('is_woocommerce') && is_woocommerce() && !$category) {
    $body = '<section class="section" data-catalog><h1>Todos los tenis</h1><div class="grid">' . supreme_cards() . '</div></section>';
    $html = preg_replace_callback('/<main id="main">.*?<\/main>/s', function () use ($body) {
        return '<main id="main">' . $body . '</main>';
    }, $html);
} elseif (is_page() && !is_front_page()) {
    ob_start();
    while (have_posts()) { the_post(); echo '<section class="section"><h1>' . esc_html(get_the_title()) . '</h1>'; the_content(); echo '</section>'; }
    $body = ob_get_clean();
    $html = preg_replace_callback('/<main id="main">.*?<\/main>/s', function () use ($body) {
        return '<main id="main">' . $body . '</main>';
    }, $html);
}
ob_start(); wp_head(); $head = ob_get_clean();
ob_start(); wp_footer(); $foot = ob_get_clean();
$html = preg_replace('/<title>.*?<\/title>/s', '', $html);
$html = str_replace('</head>', $head . '</head>', $html);
$html = str_replace('</body>', $foot . '</body>', $html);
echo $html;
