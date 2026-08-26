<?php
/**
 * Plugin Name: Blum Gallery
 * Description: A responsive, Gutenberg-friendly photography gallery with Media Library editing, filters, captions, and an accessible lightbox.
 * Version: 0.2.0
 * Author: Cyril Blum
 * License: GPL-2.0-or-later
 * Text Domain: blum-gallery
 */
/**
 * Cyril Gallery: theme-integrated gallery shortcode with a Media Library editor.
 * Add [cyril_gallery] to any page. Configure images in the page's Gallery Images box.
 */
if (!defined('ABSPATH')) exit;

add_action('admin_menu', function () {
    add_menu_page('Blum Gallery', 'Blum Gallery', 'edit_pages', 'blum-gallery', 'blum_gallery_admin_home', 'dashicons-format-gallery', 30);
});

function blum_gallery_admin_home() {
    $pages = get_posts(['post_type' => 'page', 'post_status' => ['publish', 'draft'], 'posts_per_page' => -1, 's' => '[blum_gallery]']);
    echo '<div class="wrap"><h1>Blum Gallery</h1><p>Create a gallery on any WordPress page by adding <code>[blum_gallery]</code> to the page content. Then use the <strong>Blum Gallery Images</strong> panel below the editor to choose, reorder, tag, and caption its images.</p><p><a class="button button-primary" href="' . esc_url(admin_url('post-new.php?post_type=page')) . '">Create a new gallery page</a> <a class="button" href="' . esc_url(admin_url('upload.php')) . '">Open Media Library</a></p><h2>Gallery pages</h2>';
    if (!$pages) { echo '<p>No pages with <code>[blum_gallery]</code> found yet.</p>'; } else { echo '<table class="widefat striped"><thead><tr><th>Page</th><th>Status</th><th>Actions</th></tr></thead><tbody>'; foreach ($pages as $page) echo '<tr><td>' . esc_html($page->post_title ?: '(Untitled)') . '</td><td>' . esc_html($page->post_status) . '</td><td><a href="' . esc_url(get_edit_post_link($page->ID)) . '">Edit gallery</a> · <a href="' . esc_url(get_permalink($page->ID)) . '" target="_blank">View</a></td></tr>'; echo '</tbody></table>'; }
    echo '<h2>How it works</h2><ol><li>Create or edit a WordPress page.</li><li>Add <code>[blum_gallery]</code> in the content editor.</li><li>Save the page.</li><li>Use the Blum Gallery Images panel to manage that page’s images and layout.</li></ol></div>';
}

function cyril_gallery_default_items() {
    $items = [];
    $data = [];
    foreach ([3324, 3312, 3378, 3045, 3318, 4256] as $source_id) {
        $raw = get_post_meta($source_id, '_elementor_data', true);
        $source = is_string($raw) ? json_decode($raw, true) : $raw;
        if (is_array($source)) $data = array_merge($data, $source);
    }
    $walk = function ($nodes, $category = 'Selected') use (&$walk, &$items) {
        if (!is_array($nodes)) return;
        foreach ($nodes as $node) {
            if (!is_array($node)) continue;
            $settings = $node['settings'] ?? [];
            foreach (($settings['galleries'] ?? []) as $group) {
                $name = $group['gallery_title'] ?? $category;
                foreach (($group['multiple_gallery'] ?? []) as $image) {
                    if (!empty($image['url'])) { $url = esc_url_raw($image['url']); $attachment = attachment_url_to_postid($url); $items[] = ['url' => $url, 'title' => $attachment ? get_the_title($attachment) : $name, 'tags' => [$name]]; }
                }
            }
            $walk($node['elements'] ?? [], $category);
        }
    };
    $walk($data);
    return $items;
}

function cyril_gallery_items($post_id) {
    $items = get_post_meta($post_id, '_cyril_gallery_items', true);
    if (!empty($items) && is_array($items)) return $items;
    return cyril_gallery_default_items();
}

function blum_gallery_settings($post_id) {
    $settings = get_post_meta($post_id, '_blum_gallery_settings', true);
    return wp_parse_args(is_array($settings) ? $settings : [], [
        'columns' => 3,
        'title' => 'Light, place & perspective.',
        'subtitle' => 'Selected photographs',
        'default_filter' => 'all',
        'show_filters' => true,
        'hide_page_title' => false,
        'taxonomy' => [
            'Food' => ['Fine Dining', 'Casual', 'My Kitchen'],
            'Architecture' => ['Concert Halls', 'Modern', 'New Gallery', 'Sacral', 'Traditional'],
            'Nature' => ['Flowers & Other', 'Landscapes', 'Trees & Gardens'],
            'People' => ['Portraits', 'Weddings']
        ]
    ]);
}

add_filter('hello_elementor_page_title', function ($show_title) {
    if (!is_singular('page')) return $show_title;
    return blum_gallery_settings(get_queried_object_id())['hide_page_title'] ? false : $show_title;
});

function blum_gallery_taxonomy($item, $mapping = []) {
    $tags = array_values(array_filter($item['tags'] ?? (!empty($item['category']) ? [$item['category']] : [])));
    $map = [];
    foreach ((array) $mapping as $parent => $children) foreach ((array) $children as $child) $map[(string) $child] = (string) $parent;
    $children = array_values(array_intersect($tags, array_keys($map)));
    $mapped_parent = $children ? $map[$children[0]] : null;
    return ['parent' => $mapped_parent ?: ($tags[0] ?? 'Selected'), 'children' => $children];
}

add_action('add_meta_boxes', function ($post_type, $post) {
    if ($post_type !== 'page' || !$post || (!has_shortcode((string) $post->post_content, 'blum_gallery') && !has_shortcode((string) $post->post_content, 'cyril_gallery'))) return;
    add_meta_box('cyril_gallery_images', 'Blum Gallery Images', 'cyril_gallery_meta_box', 'page', 'normal', 'high');
}, 10, 2);

function cyril_gallery_meta_box($post) {
    wp_nonce_field('cyril_gallery_save', 'cyril_gallery_nonce');
    $items = cyril_gallery_items($post->ID);
    $settings = blum_gallery_settings($post->ID);
    echo '<div class="blum-gallery-settings"><p><label><span class="dashicons dashicons-info-outline" title="Parent categories and their child tags. Photos tagged with a child appear under its parent."></span> Category hierarchy <textarea name="blum_gallery_taxonomy" class="widefat" rows="7">' . esc_textarea(wp_json_encode($settings['taxonomy'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . '</textarea></label><small>JSON format: {"Food":["Fine Dining","Casual"]}. Rename a parent or child here, then update matching image tags below.</small></p></div>';
    echo '<p>Select images from the Media Library, then drag rows to reorder them. The title field defaults to the attachment’s WordPress <strong>Title</strong>; an entered value overrides it for this gallery only.</p><div class="blum-gallery-settings"><p><label><span class="dashicons dashicons-info-outline" title="Number of masonry columns on desktop. Mobile layouts adapt automatically."></span> Columns <select name="blum_gallery_columns"><option value="2" ' . selected($settings['columns'], 2, false) . '>2</option><option value="3" ' . selected($settings['columns'], 3, false) . '>3</option><option value="4" ' . selected($settings['columns'], 4, false) . '>4</option></select></label></p><p><label><input type="checkbox" name="blum_gallery_show_filters" value="1" ' . checked($settings['show_filters'] !== false, true, false) . '> <strong>Show category &amp; tag filters above gallery</strong></label></p><p><label>Default filter <select name="blum_gallery_default_filter"><option value="all" ' . selected($settings['default_filter'], 'all', false) . '>All</option><option value="Selected" ' . selected($settings['default_filter'], 'Selected', false) . '>Selected</option></select></label></p><p><label><input type="checkbox" name="blum_gallery_hide_page_title" value="1" ' . checked($settings['hide_page_title'], true, false) . '> Hide the WordPress page title</label></p><p><label><span class="dashicons dashicons-info-outline" title="Optional heading shown above the gallery. Clear it to show no title."></span> Title <input type="text" name="blum_gallery_title" value="' . esc_attr($settings['title']) . '" class="widefat"></label></p><p><label><span class="dashicons dashicons-info-outline" title="Optional small text shown above the title. Clear it to hide it."></span> Subtitle <input type="text" name="blum_gallery_subtitle" value="' . esc_attr($settings['subtitle']) . '" class="widefat"></label></p></div><div id="cyril-gallery-rows">';
    foreach ($items as $index => $item) cyril_gallery_admin_row($index, $item);
    echo '</div><p><button type="button" class="button" id="cyril-gallery-add">Add image</button></p><input type="hidden" id="cyril-gallery-json" name="cyril_gallery_json" value="' . esc_attr(wp_json_encode($items)) . '">';
}

function cyril_gallery_admin_row($index, $item) {
    $url = esc_url($item['url'] ?? '');
    $tags = implode(', ', $item['tags'] ?? (!empty($item['category']) ? [$item['category']] : []));
    if (empty($item['title']) && $url) { $attachment = attachment_url_to_postid($url); if ($attachment) $item['title'] = get_the_title($attachment); }
    echo '<div class="cyril-gallery-row" draggable="true"><span class="dashicons dashicons-menu drag" title="Drag to reorder"></span><img src="' . $url . '"><div class="fields"><label><span class="dashicons dashicons-info-outline" title="Defaults to the Media Library attachment Title. Enter text here only to override it for this gallery."></span><input class="image-title" type="text" placeholder="Media Library Title (override optional)" value="' . esc_attr($item['title'] ?? '') . '"></label><label><span class="dashicons dashicons-info-outline" title="Comma-separated tags. A photo can have multiple tags; each tag becomes a filter button."></span><input class="image-tags" type="text" placeholder="Tags, comma separated" value="' . esc_attr($tags) . '"></label><input class="image-url" type="hidden" value="' . esc_attr($url) . '"></div><button type="button" class="button choose-image">Choose image</button><button type="button" class="button-link-delete remove-image">Remove</button></div>';
}

add_action('save_post_page', function ($post_id) {
    if (!isset($_POST['cyril_gallery_nonce']) || !wp_verify_nonce($_POST['cyril_gallery_nonce'], 'cyril_gallery_save') || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || !current_user_can('edit_page', $post_id)) return;
    $items = json_decode(wp_unslash($_POST['cyril_gallery_json'] ?? '[]'), true);
    if (!is_array($items)) return;
    $clean = [];
    foreach ($items as $item) if (!empty($item['url'])) $clean[] = ['url' => esc_url_raw($item['url']), 'title' => sanitize_text_field($item['title'] ?? ''), 'tags' => array_values(array_filter(array_map('sanitize_text_field', array_map('trim', explode(',', $item['tags'] ?? '')))))];
    update_post_meta($post_id, '_cyril_gallery_items', $clean);
    $taxonomy = json_decode(wp_unslash($_POST['blum_gallery_taxonomy'] ?? '{}'), true);
    if (!is_array($taxonomy)) $taxonomy = [];
    $clean_taxonomy = [];
    foreach ($taxonomy as $parent => $children) {
        $parent = sanitize_text_field($parent);
        if (!$parent || !is_array($children)) continue;
        $children = array_values(array_filter(array_map('sanitize_text_field', $children)));
        if ($children) $clean_taxonomy[$parent] = $children;
    }
    update_post_meta($post_id, '_blum_gallery_settings', [
        'columns' => min(4, max(2, absint($_POST['blum_gallery_columns'] ?? 3))),
        'title' => sanitize_text_field($_POST['blum_gallery_title'] ?? ''),
        'subtitle' => sanitize_text_field($_POST['blum_gallery_subtitle'] ?? ''),
        'default_filter' => in_array($_POST['blum_gallery_default_filter'] ?? 'all', ['all', 'Selected'], true) ? $_POST['blum_gallery_default_filter'] : 'all',
        'show_filters' => !empty($_POST['blum_gallery_show_filters']),
        'hide_page_title' => !empty($_POST['blum_gallery_hide_page_title']),
        'taxonomy' => $clean_taxonomy
    ]);
});

add_action('admin_enqueue_scripts', function ($hook) {
    if (!in_array($hook, ['post.php', 'post-new.php'], true)) return;
    wp_enqueue_media();
    wp_add_inline_style('dashicons', '.cyril-gallery-row{display:flex;align-items:center;gap:10px;padding:10px 0;border-bottom:1px solid #ddd}.cyril-gallery-row img{width:72px;height:54px;object-fit:cover}.cyril-gallery-row .fields{display:flex;gap:6px;flex-wrap:wrap}.cyril-gallery-row input{max-width:500px}.cyril-gallery-row label{display:flex;align-items:center;gap:5px}.cyril-gallery-row .drag{cursor:grab;color:#777}.blum-gallery-settings{background:#f6f7f7;padding:10px 14px;max-width:700px;margin-bottom:12px}.blum-gallery-settings label{display:block;margin-bottom:8px}.blum-gallery-settings input[type="text"],.blum-gallery-settings select{margin-top:4px}');
    wp_enqueue_script('jquery-ui-sortable');
    wp_add_inline_script('media-editor', <<<'JS'
(function($){
    var frame;
    function sync(){
        var items=[];
        $('#cyril-gallery-rows .cyril-gallery-row').each(function(){
            var url=$(this).find('.image-url').val();
            if(url) items.push({
                url: url,
                title: $(this).find('.image-title').val(),
                tags: $(this).find('.image-tags').val().split(',').map(function(s){return s.trim();}).filter(Boolean)
            });
        });
        $('#cyril-gallery-json').val(JSON.stringify(items));
    }
    $('#cyril-gallery-rows').sortable({handle:'.drag',update:sync});
    $('#cyril-gallery-rows').on('input change','input',sync);
    $('#cyril-gallery-rows').on('click','.remove-image',function(){$(this).closest('.cyril-gallery-row').remove();sync();});
    $('#cyril-gallery-add').on('click',function(e){
        e.preventDefault();
        if(frame){frame.open();return;}
        frame=wp.media({title:'Select gallery images',button:{text:'Add to gallery'},multiple:true});
        frame.on('select',function(){
            var state=frame.state(), selection=state.get('selection');
            selection.each(function(att){
                var a=att.toJSON();
                var row=$('<div class="cyril-gallery-row" draggable="true"><span class="dashicons dashicons-menu drag"></span><img src="'+(a.sizes&&a.sizes.thumbnail?a.sizes.thumbnail.url:a.url)+'"><div class="fields"><label><span>Title:</span><input class="image-title" type="text" value="'+(a.title||'')+'"></label><label><span>Tags:</span><input class="image-tags" type="text" value="Selected"></label><input class="image-url" type="hidden" value="'+a.url+'"></div><button type="button" class="button-link-delete remove-image">Remove</button></div>');
                $('#cyril-gallery-rows').append(row);
            });
            sync();
        });
        frame.open();
    });
})(jQuery);
JS
    );
});

function blum_gallery_shortcode($atts = [], $content = '', $tag = '') {
    $post_id = get_the_ID();
    $items = cyril_gallery_items($post_id);
    $settings = blum_gallery_settings($post_id);
    $show_filters = !empty($settings['show_filters']);
    $tags = [];

    // Process tags and taxonomy
    foreach ($items as &$item) {
        $item['tags'] = array_values(array_filter($item['tags'] ?? (!empty($item['category']) ? [$item['category']] : [])));
        if (empty($item['title'])) {
            $attachment = attachment_url_to_postid($item['url']);
            $item['title'] = $attachment ? get_the_title($attachment) : '';
        }
        $tax = blum_gallery_taxonomy($item, $settings['taxonomy']);
        if (!empty($item['tags']) && !empty($tax['parent']) && $tax['parent'] !== 'Selected') {
            $item['tags'] = array_values(array_unique(array_merge($item['tags'], [$tax['parent']])));
        }
        $tags = array_merge($tags, $item['tags']);
    }
    unset($item);

    $tags = array_values(array_unique($tags));
    $default_filter = in_array($settings['default_filter'], array_merge(['all'], $tags), true) ? $settings['default_filter'] : 'all';

    ob_start();
    ?>
    <section class="cyril-gallery" aria-label="Photography gallery" style="margin-top:clamp(18px,3vw,42px)">
        <div class="cyril-gallery__head" style="margin-bottom:22px">
            <?php if ($settings['title'] || $settings['subtitle']): ?>
            <div>
                <?php if ($settings['subtitle']): ?><p class="cyril-gallery__eyebrow"><?php echo esc_html($settings['subtitle']); ?></p><?php endif; ?>
                <?php if ($settings['title']): ?><h2><?php echo esc_html($settings['title']); ?></h2><?php endif; ?>
            </div>
            <?php endif; ?>

            <?php if ($show_filters && count($tags) > 1): ?>
            <div class="cyril-gallery__filters">
                <button class="<?php echo $default_filter === 'all' ? 'is-active' : ''; ?>" data-filter="all">All</button>
                <?php foreach ($tags as $t): ?>
                <button class="<?php echo $default_filter === $t ? 'is-active' : ''; ?>" data-filter="<?php echo esc_attr($t); ?>"><?php echo esc_html($t); ?></button>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <div class="cyril-gallery__grid" style="--blum-gallery-columns:<?php echo esc_attr($settings['columns']); ?>">
            <?php foreach ($items as $item):
                $item_tags = implode('|', $item['tags']);
                // Determine initial visibility based on server-rendered default filter
                $is_hidden = false;
                if ($show_filters && $default_filter !== 'all' && count($tags) > 1) {
                    $is_hidden = !in_array($default_filter, $item['tags'], true);
                }
                // Try to resolve attachment metadata for width/height aspect ratio
                $att_id = attachment_url_to_postid($item['url']);
                $meta = $att_id ? wp_get_attachment_metadata($att_id) : null;
                $dim_attr = '';
                $aspect_style = '';
                if ($meta && !empty($meta['width']) && !empty($meta['height'])) {
                    $w = (int) $meta['width'];
                    $h = (int) $meta['height'];
                    $dim_attr = ' width="' . $w . '" height="' . $h . '"';
                    $aspect_style = ' style="aspect-ratio: ' . $w . ' / ' . $h . ';"';
                }
            ?>
            <figure data-tags="<?php echo esc_attr($item_tags); ?>"<?php echo $is_hidden ? ' hidden' : ''; ?>>
                <button class="cyril-gallery__photo" data-full="<?php echo esc_url($item['url']); ?>" data-caption="<?php echo esc_attr($item['title']); ?>"<?php echo $aspect_style; ?>>
                    <img src="<?php echo esc_url($item['url']); ?>" alt="<?php echo esc_attr($item['title']); ?>" loading="lazy" decoding="async"<?php echo $dim_attr; ?> onload="this.classList.add('is-loaded')">
                    <span><?php echo esc_html($item['title']); ?><?php if (!empty($item['tags'])): ?><small><?php echo esc_html(implode(' · ', $item['tags'])); ?></small><?php endif; ?></span>
                </button>
            </figure>
            <?php endforeach; ?>
        </div>

        <dialog class="cyril-gallery__lightbox">
            <button class="close" aria-label="Close">×</button>
            <button class="prev" aria-label="Previous">←</button>
            <div><img alt=""><p></p></div>
            <button class="next" aria-label="Next">→</button>
        </dialog>
    </section>

    <style>
    .cyril-gallery { margin: clamp(28px, 6vw, 90px) auto; max-width: 1400px; }
    .cyril-gallery__head { display: flex; justify-content: space-between; align-items: flex-end; gap: 28px; margin-bottom: 36px; }
    .cyril-gallery__eyebrow { color: #335c43; text-transform: uppercase; letter-spacing: .16em; font-size: 11px; margin-bottom: 4px; }
    .cyril-gallery h2 { font: clamp(34px, 5vw, 68px)/1 Georgia, serif; font-weight: 400; margin: 0; }
    .cyril-gallery h2 em { color: #335c43; }
    .cyril-gallery__filters { display: flex; gap: 6px; flex-wrap: wrap; }
    .cyril-gallery__filters button { border: 1px solid #b8b9b0; border-radius: 99px; background: transparent; padding: 8px 14px; cursor: pointer; font-size: 13px; transition: background .2s, color .2s; }
    .cyril-gallery__filters button.is-active, .cyril-gallery__filters button:hover { background: #172019; color: #f2f0e9; }
    
    /* Masonry Grid with smooth containment */
    .cyril-gallery__grid { columns: var(--blum-gallery-columns, 3); column-gap: 12px; }
    .cyril-gallery__grid figure { break-inside: avoid; margin: 0 0 12px; }
    .cyril-gallery__grid figure[hidden] { display: none !important; }
    
    /* Photo Container & Smooth Lazy Fade */
    .cyril-gallery__photo {
        display: block;
        position: relative;
        width: 100%;
        padding: 0;
        border: 0;
        background: #181d1a;
        cursor: zoom-in;
        text-align: left;
        overflow: hidden;
        border-radius: 4px;
    }
    .cyril-gallery__photo img {
        display: block;
        width: 100%;
        height: auto;
        opacity: 0;
        transition: opacity 0.35s ease-in-out, transform 0.6s ease;
    }
    .cyril-gallery__photo img.is-loaded {
        opacity: 1;
    }
    .cyril-gallery__photo:after {
        content: "";
        position: absolute;
        inset: 0;
        background: linear-gradient(transparent 50%, rgba(0, 0, 0, .72));
        opacity: 0;
        transition: opacity .3s;
    }
    .cyril-gallery__photo span {
        position: absolute;
        z-index: 1;
        bottom: 16px;
        left: 18px;
        color: #fff;
        font: 20px Georgia, serif;
        opacity: 0;
        transform: translateY(8px);
        transition: opacity .3s, transform .3s;
        max-width: calc(100% - 28px);
        line-height: 1.15;
        white-space: normal;
        overflow-wrap: anywhere;
    }
    .cyril-gallery__photo small {
        display: block;
        font: 10px Arial, sans-serif;
        letter-spacing: .12em;
        text-transform: uppercase;
        margin-top: 4px;
        line-height: 1.2;
        max-width: 100%;
    }
    .cyril-gallery__photo:hover img { transform: scale(1.025); }
    .cyril-gallery__photo:hover:after, .cyril-gallery__photo:hover span { opacity: 1; transform: none; }
    
    /* Lightbox Modal */
    .cyril-gallery__lightbox {
        width: 100vw;
        height: 100vh;
        max-width: none;
        max-height: none;
        background: rgba(8, 12, 9, .97);
        border: 0;
        color: #fff;
    }
    .cyril-gallery__lightbox[open] { display: grid; grid-template-columns: 70px 1fr 70px; align-items: center; text-align: center; }
    .cyril-gallery__lightbox img { max-width: 86vw; max-height: 84vh; border-radius: 2px; }
    .cyril-gallery__lightbox button { border: 0; background: none; color: #fff; font-size: 30px; cursor: pointer; }
    .cyril-gallery__lightbox .close { position: absolute; right: 18px; top: 8px; font-size: 38px; }
    
    @media (max-width: 700px) {
        .cyril-gallery__head { display: block; }
        .cyril-gallery__filters { margin-top: 20px; }
        .cyril-gallery__grid { columns: 2; }
    }
    @media (max-width: 480px) {
        .cyril-gallery__grid { columns: 1; }
    }
    </style>

    <script>
    (function() {
        document.querySelectorAll('.cyril-gallery').forEach(function(g) {
            var fs = [...g.querySelectorAll('figure')];
            var b = g.querySelector('.cyril-gallery__lightbox');
            var im = b ? b.querySelector('img') : null;
            var cp = b ? b.querySelector('p') : null;
            var v = fs.filter(function(f) { return !f.hidden; });
            var n = 0;

            // Handle cached images
            g.querySelectorAll('.cyril-gallery__photo img').forEach(function(img) {
                if (img.complete) { img.classList.add('is-loaded'); }
            });

            g.querySelectorAll('[data-filter]').forEach(function(btn) {
                btn.onclick = function() {
                    var filter = btn.dataset.filter;
                    g.querySelectorAll('[data-filter]').forEach(function(y) {
                        y.classList.toggle('is-active', y === btn);
                    });
                    fs.forEach(function(f) {
                        var tags = (f.dataset.tags || '').split('|');
                        f.hidden = (filter !== 'all' && tags.indexOf(filter) < 0);
                    });
                    v = fs.filter(function(f) { return !f.hidden; });
                };
            });

            function show(i) {
                if (!v.length || !b || !im) return;
                n = (i + v.length) % v.length;
                var p = v[n].querySelector('.cyril-gallery__photo');
                if (p) {
                    im.src = p.dataset.full;
                    if (cp) cp.textContent = p.dataset.caption;
                }
            }

            g.addEventListener('click', function(e) {
                var p = e.target.closest('.cyril-gallery__photo');
                if (p && b) {
                    var fig = p.closest('figure');
                    show(v.indexOf(fig));
                    b.showModal();
                }
            });

            if (b) {
                var closeBtn = b.querySelector('.close');
                var prevBtn = b.querySelector('.prev');
                var nextBtn = b.querySelector('.next');
                if (closeBtn) closeBtn.onclick = function() { b.close(); };
                if (prevBtn) prevBtn.onclick = function() { show(n - 1); };
                if (nextBtn) nextBtn.onclick = function() { show(n + 1); };
                b.onclick = function(e) { if (e.target === b) b.close(); };
                b.onkeydown = function(e) {
                    if (e.key === 'ArrowLeft') show(n - 1);
                    if (e.key === 'ArrowRight') show(n + 1);
                };
            }
        });
    })();
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode('cyril_gallery', 'blum_gallery_shortcode');
add_shortcode('blum_gallery', 'blum_gallery_shortcode');

add_action('wp_footer', function () {
    $post_id = get_the_ID();
    if (!is_singular() || (!has_shortcode((string) get_post_field('post_content', $post_id), 'blum_gallery') && !has_shortcode((string) get_post_field('post_content', $post_id), 'cyril_gallery'))) return;
    $settings = blum_gallery_settings($post_id);
    if (empty($settings['show_filters']) || empty($settings['taxonomy'])) return;

    echo '<style>
    .cyril-gallery__head > .cyril-gallery__filters:only-child { margin-inline: auto; justify-content: center; }
    .blum-sub-filters { display: none; order: 2; flex: 0 0 100%; width: 100%; gap: 6px; flex-wrap: wrap; margin-top: 10px; padding: 8px 0 2px; border-top: 1px solid #d7d8d0; justify-content: center; }
    .blum-sub-filters.is-visible { display: flex; }
    .blum-sub-filters button { font-size: .9em; }
    </style>
    <script>
    (function() {
        document.querySelectorAll(".cyril-gallery").forEach(function(g) {
            var groups = ' . wp_json_encode($settings['taxonomy']) . ';
            var filters = g.querySelector(".cyril-gallery__filters");
            if (!filters) return;
            var buttons = [...filters.querySelectorAll("button")];
            var figures = [...g.querySelectorAll("figure")];
            var open = null;

            function active(x) {
                filters.querySelectorAll("button").forEach(function(y) {
                    y.classList.toggle("is-active", y === x);
                });
            }

            Object.keys(groups).forEach(function(parent) {
                var children = buttons.filter(function(b) {
                    return groups[parent].indexOf(b.textContent.trim()) > -1;
                });
                if (!children.length) return;
                var old = buttons.find(function(b) { return b.textContent.trim() === parent; });
                if (old) old.remove();
                var sub = document.createElement("div");
                sub.className = "blum-sub-filters";
                children.forEach(function(b) { sub.appendChild(b); });
                filters.appendChild(sub);
                var main = document.createElement("button");
                main.textContent = parent;
                main.dataset.filter = parent;
                filters.insertBefore(main, sub);

                function apply(tag) {
                    figures.forEach(function(f) {
                        var tags = (f.dataset.tags || "").split("|");
                        f.hidden = tag !== "all" && ((tag === parent && children.every(function(c) { return tags.indexOf(c.textContent.trim()) < 0; })) || (tag !== parent && tags.indexOf(tag) < 0));
                    });
                }

                main.onclick = function() {
                    active(main);
                    if (open && open !== sub) open.classList.remove("is-visible");
                    open = sub;
                    sub.classList.add("is-visible");
                    apply(parent);
                };

                children.forEach(function(b) {
                    b.onclick = function() {
                        active(b);
                        if (open && open !== sub) open.classList.remove("is-visible");
                        open = sub;
                        sub.classList.add("is-visible");
                        apply(b.textContent.trim());
                    };
                });
            });

            var all = g.querySelector("[data-filter=all]");
            if (all) {
                all.onclick = function() {
                    active(all);
                    if (open) open.classList.remove("is-visible");
                    open = null;
                    figures.forEach(function(f) { f.hidden = false; });
                };
            }
        });
    })();
    </script>';
});
