<?php
/**
 * Plugin Name: Blum Blog
 * Description: Configurable, Elementor-free blog grids for Gutenberg pages.
 * Version: 0.1.0
 * Author: Cyril Blum
 * License: GPL-2.0-or-later
 */
if (!defined('ABSPATH')) exit;

function blum_blog_defaults() { return ['columns'=>3,'posts'=>9,'category'=>'','tag'=>'','title_length'=>70,'excerpt_length'=>140,'read_more'=>'Read more','image_height'=>240,'show_date'=>true,'show_excerpt'=>true]; }
function blum_blog_settings($id) { return wp_parse_args(get_post_meta($id,'_blum_blog_settings',true), blum_blog_defaults()); }
function blum_blog_category_options($categories, $parent = 0, $depth = 0) {
    $html = '';
    foreach ($categories as $category) {
        if ((int) $category->parent !== (int) $parent) continue;
        $html .= '<option value="'.esc_attr($category->slug).'" '.selected(blum_blog_settings(get_the_ID())['category'],$category->slug,false).'>'.esc_html(str_repeat('— ', $depth).$category->name).'</option>';
        $html .= blum_blog_category_options($categories, $category->term_id, $depth + 1);
    }
    return $html;
}

add_action('admin_menu', function () {
    add_menu_page('Blum Blog', 'Blum Blog', 'edit_pages', 'blum-blog', 'blum_blog_admin_home', 'dashicons-welcome-write-blog', 31);
});

function blum_blog_admin_home() {
    $pages = array_filter(get_posts(['post_type'=>'page', 'post_status'=>['publish','draft','private'], 'posts_per_page'=>-1]), function($page) { return has_shortcode((string) $page->post_content, 'blum_blog'); });
    echo '<div class="wrap"><h1>Blum Blog</h1><p>Create and manage Elementor-independent blog grids with <code>[blum_blog]</code>. Open a page below to edit all grid settings, including the category dropdown, columns, post count, image height, excerpts, dates, and read-more text.</p><p><a class="button button-primary" href="'.esc_url(admin_url('post-new.php?post_type=page')).'">Create a new blog page</a></p><h2>Blog grid pages</h2>';
    if (!$pages) {
        echo '<p>No pages with <code>[blum_blog]</code> found yet.</p>';
    } else {
        echo '<table class="widefat striped"><thead><tr><th>Page</th><th>Category</th><th>Tag</th><th>Posts</th><th>Status</th><th>Actions</th></tr></thead><tbody>';
        foreach ($pages as $page) {
            $settings = blum_blog_settings($page->ID);
            $category = $settings['category'] ? get_category_by_slug($settings['category']) : null;
            $tag = $settings['tag'] ? get_term_by('slug', $settings['tag'], 'post_tag') : null;
            echo '<tr><td><strong>'.esc_html($page->post_title ?: '(Untitled)').'</strong></td><td>'.esc_html($category ? $category->name : ($settings['category'] ?: 'All categories')).'</td><td>'.esc_html($tag ? $tag->name : ($settings['tag'] ?: 'All tags')).'</td><td>'.esc_html($settings['posts'] ? $settings['posts'] : 'All').'</td><td>'.esc_html($page->post_status).'</td><td><a href="'.esc_url(get_edit_post_link($page->ID)).'">Edit settings</a> · <a href="'.esc_url(get_permalink($page->ID)).'" target="_blank">View</a></td></tr>';
        }
        echo '</tbody></table>';
    }
    echo '<h2>How it works</h2><ol><li>Create or edit a page.</li><li>Add <code>[blum_blog]</code> to its content.</li><li>Use the <strong>Blum Blog Grid</strong> panel to configure the grid.</li><li>Use <strong>posts = 0</strong> to show all matching posts.</li></ol></div>';
}

add_action('add_meta_boxes', function(){ add_meta_box('blum_blog_settings','Blum Blog Grid','blum_blog_meta_box','page','normal','high'); });
function blum_blog_meta_box($post) {
    wp_nonce_field('blum_blog_save','blum_blog_nonce'); $s=blum_blog_settings($post->ID);
    echo '<p>Configure the blog grid used by <code>[blum_blog]</code>. Category and tag filters are optional; leave them empty to show all posts.</p><div class="blum-blog-settings">';
    $fields=[['columns','Columns','2, 3, or 4'],['posts','Number of posts','0 = all matching posts'],['title_length','Title max characters','0 = unlimited'],['excerpt_length','Excerpt max characters','0 = hide/none'],['read_more','Read-more text',''],['image_height','Image height (px)','0 = natural height']];
    foreach($fields as $f) echo '<p><label><strong>'.esc_html($f[1]).'</strong><br><input name="blum_blog_'.$f[0].'" type="'.($f[0]==='read_more'?'text':'number').'" value="'.esc_attr($s[$f[0]]).'" class="widefat" placeholder="'.esc_attr($f[2]).'"></label></p>';
    echo '<p><label><strong>Category / subcategory</strong><br><select name="blum_blog_category" class="widefat"><option value="">All categories</option>'.blum_blog_category_options(get_categories(['hide_empty'=>false])).'</select></label></p><p><label><strong>Post tag</strong><br><select name="blum_blog_tag" class="widefat"><option value="">All tags</option>'; foreach(get_tags(['hide_empty'=>false]) as $tag) echo '<option value="'.esc_attr($tag->slug).'" '.selected($s['tag'],$tag->slug,false).'>'.esc_html($tag->name).'</option>'; echo '</select></label></p>';
    echo '<p><label><input type="checkbox" name="blum_blog_show_date" value="1" '.checked($s['show_date'],true,false).'> Show publication date</label><br><label><input type="checkbox" name="blum_blog_show_excerpt" value="1" '.checked($s['show_excerpt'],true,false).'> Show excerpt</label></p></div>';
}
add_action('save_post_page', function($id){
    if(!isset($_POST['blum_blog_nonce'])||!wp_verify_nonce($_POST['blum_blog_nonce'],'blum_blog_save')||defined('DOING_AUTOSAVE')||!current_user_can('edit_page',$id)) return;
    $s=blum_blog_defaults(); foreach(['columns','posts','title_length','excerpt_length','image_height'] as $k) $s[$k]=absint($_POST['blum_blog_'.$k]??$s[$k]); $s['columns']=min(4,max(2,$s['columns'])); $s['posts']=min(30,max(0,$s['posts'])); $s['category']=sanitize_title($_POST['blum_blog_category']??''); $s['tag']=sanitize_title($_POST['blum_blog_tag']??''); $s['read_more']=sanitize_text_field($_POST['blum_blog_read_more']??'Read more'); $s['show_date']=!empty($_POST['blum_blog_show_date']); $s['show_excerpt']=!empty($_POST['blum_blog_show_excerpt']); update_post_meta($id,'_blum_blog_settings',$s);
});
function blum_blog_trim($text,$length){ $text=wp_strip_all_tags($text); if(!$length||mb_strlen($text)<= $length)return $text; return rtrim(mb_substr($text,0,$length)).'…'; }
function blum_blog_shortcode($atts=[]){
    $s=blum_blog_settings(get_the_ID()); $a=shortcode_atts(['category'=>$s['category'],'tag'=>$s['tag'],'posts'=>$s['posts']],$atts,'blum_blog'); $requested_posts=absint($a['posts']); $q=['post_type'=>'post','posts_per_page'=>$requested_posts===0?-1:min(30,max(1,$requested_posts)),'post_status'=>'publish']; if($a['category'])$q['category_name']=sanitize_title($a['category']); if($a['tag'])$q['tag']=sanitize_title($a['tag']); $posts=get_posts($q); ob_start();
    echo '<section class="blum-blog-grid" style="--blum-blog-columns:'.esc_attr($s['columns']).';--blum-blog-image-height:'.esc_attr($s['image_height']).'px">';
    foreach($posts as $p){ $title=blum_blog_trim(get_the_title($p),$s['title_length']); $excerpt=blum_blog_trim(get_the_excerpt($p),$s['excerpt_length']); $categories=get_the_category($p->ID); $child_categories=array_filter($categories,function($category){ return !empty($category->parent); }); $category_label=implode(', ',array_map(function($category){ return $category->name; },$child_categories ?: $categories)); echo '<article class="blum-blog-card">'; if(has_post_thumbnail($p)) echo '<a class="blum-blog-image" href="'.esc_url(get_permalink($p)).'"><img src="'.esc_url(get_the_post_thumbnail_url($p,'large')).'" alt="'.esc_attr(get_the_title($p)).'">'.($category_label?'<span class="blum-blog-category">'.esc_html($category_label).'</span>':'').'</a>'; echo '<div class="blum-blog-content">'; if($s['show_date']) echo '<time datetime="'.esc_attr(get_the_date('c',$p)).'">'.esc_html(get_the_date('', $p)).'</time>'; echo '<h2><a href="'.esc_url(get_permalink($p)).'">'.esc_html($title).'</a></h2>'; if($s['show_excerpt']&&$excerpt) echo '<p>'.esc_html($excerpt).'</p>'; echo '<a class="blum-blog-read-more" href="'.esc_url(get_permalink($p)).'">'.esc_html($s['read_more']).' <span aria-hidden="true">→</span></a></div></article>'; }
    if(!$posts) echo '<p>No posts found.</p>'; echo '</section><style>.blum-blog-grid{display:grid;grid-template-columns:repeat(var(--blum-blog-columns),minmax(0,1fr));gap:28px;margin:clamp(30px,6vw,80px) auto;max-width:1400px}.blum-blog-card{background:#f5f3ed;border:1px solid #deddd5;display:flex;flex-direction:column;overflow:hidden}.blum-blog-image{display:block;overflow:hidden;background:#ddd}.blum-blog-image img{display:block;width:100%;height:var(--blum-blog-image-height);object-fit:cover}.blum-blog-content{padding:22px 22px 25px}.blum-blog-content time{font-size:11px;letter-spacing:.12em;text-transform:uppercase;color:#687267}.blum-blog-content h2{font:clamp(22px,2.3vw,32px)/1.12 Georgia,serif;font-weight:400;margin:10px 0}.blum-blog-content h2 a{color:inherit;text-decoration:none}.blum-blog-content p{line-height:1.6;margin:0 0 18px}.blum-blog-read-more{color:#335c43;text-decoration:none;font-size:13px}.blum-blog-read-more span{margin-left:6px}@media(max-width:800px){.blum-blog-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:520px){.blum-blog-grid{grid-template-columns:1fr}}
    .blum-blog-image{position:relative}.blum-blog-category{position:absolute;top:12px;right:12px;max-width:calc(100% - 24px);padding:6px 9px;background:rgba(23,32,25,.88);color:#f2f0e9;font-size:10px;letter-spacing:.1em;line-height:1.2;text-transform:uppercase;text-align:right}
    </style>'; return ob_get_clean();
}
add_shortcode('blum_blog','blum_blog_shortcode');
