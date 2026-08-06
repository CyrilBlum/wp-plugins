<?php
/**
 * Plugin Name: Blum Blog
 * Description: Configurable, Elementor-free blog grids for Gutenberg pages.
 * Version: 0.1.0
 * Author: Cyril Blum
 * License: GPL-2.0-or-later
 */
if (!defined('ABSPATH')) exit;

function blum_blog_defaults() { return ['columns'=>3,'posts'=>9,'category'=>'','title_length'=>70,'excerpt_length'=>140,'read_more'=>'Read more','image_height'=>240,'show_date'=>true,'show_excerpt'=>true]; }
function blum_blog_settings($id) { return wp_parse_args(get_post_meta($id,'_blum_blog_settings',true), blum_blog_defaults()); }

add_action('add_meta_boxes', function(){ add_meta_box('blum_blog_settings','Blum Blog Grid','blum_blog_meta_box','page','normal','high'); });
function blum_blog_meta_box($post) {
    wp_nonce_field('blum_blog_save','blum_blog_nonce'); $s=blum_blog_settings($post->ID);
    echo '<p>Configure the blog grid used by <code>[blum_blog]</code>. The category field accepts a category slug; leave empty to show all posts.</p><div class="blum-blog-settings">';
    $fields=[['columns','Columns','2, 3, or 4'],['posts','Number of posts',''],['category','Category slug','e.g. travel'],['title_length','Title max characters','0 = unlimited'],['excerpt_length','Excerpt max characters','0 = hide/none'],['read_more','Read-more text',''],['image_height','Image height (px)','0 = natural height']];
    foreach($fields as $f) { if($f[0]==='category') { echo '<p><label><strong>Category</strong><br><select name="blum_blog_category" class="widefat"><option value="">All categories</option>'; foreach(get_categories(['hide_empty'=>false]) as $cat) echo '<option value="'.esc_attr($cat->slug).'" '.selected($s['category'],$cat->slug,false).'>'.esc_html($cat->name).'</option>'; echo '</select></label></p>'; } else echo '<p><label><strong>'.esc_html($f[1]).'</strong><br><input name="blum_blog_'.$f[0].'" type="'.($f[0]==='read_more'?'text':'number').'" value="'.esc_attr($s[$f[0]]).'" class="widefat" placeholder="'.esc_attr($f[2]).'"></label></p>'; }
    echo '<p><label><input type="checkbox" name="blum_blog_show_date" value="1" '.checked($s['show_date'],true,false).'> Show publication date</label><br><label><input type="checkbox" name="blum_blog_show_excerpt" value="1" '.checked($s['show_excerpt'],true,false).'> Show excerpt</label></p></div>';
}
add_action('save_post_page', function($id){
    if(!isset($_POST['blum_blog_nonce'])||!wp_verify_nonce($_POST['blum_blog_nonce'],'blum_blog_save')||defined('DOING_AUTOSAVE')||!current_user_can('edit_page',$id)) return;
    $s=blum_blog_defaults(); foreach(['columns','posts','title_length','excerpt_length','image_height'] as $k) $s[$k]=absint($_POST['blum_blog_'.$k]??$s[$k]); $s['columns']=min(4,max(2,$s['columns'])); $s['posts']=min(30,max(1,$s['posts'])); $s['category']=sanitize_title($_POST['blum_blog_category']??''); $s['read_more']=sanitize_text_field($_POST['blum_blog_read_more']??'Read more'); $s['show_date']=!empty($_POST['blum_blog_show_date']); $s['show_excerpt']=!empty($_POST['blum_blog_show_excerpt']); update_post_meta($id,'_blum_blog_settings',$s);
});
function blum_blog_trim($text,$length){ $text=wp_strip_all_tags($text); if(!$length||mb_strlen($text)<= $length)return $text; return rtrim(mb_substr($text,0,$length)).'…'; }
function blum_blog_shortcode($atts=[]){
    $s=blum_blog_settings(get_the_ID()); $a=shortcode_atts(['category'=>$s['category'],'posts'=>$s['posts']],$atts,'blum_blog'); $q=['post_type'=>'post','posts_per_page'=>min(30,max(1,absint($a['posts']))),'post_status'=>'publish']; if($a['category'])$q['category_name']=sanitize_title($a['category']); $posts=get_posts($q); ob_start();
    echo '<section class="blum-blog-grid" style="--blum-blog-columns:'.esc_attr($s['columns']).';--blum-blog-image-height:'.esc_attr($s['image_height']).'px">';
    foreach($posts as $p){ $title=blum_blog_trim(get_the_title($p),$s['title_length']); $excerpt=blum_blog_trim(get_the_excerpt($p),$s['excerpt_length']); echo '<article class="blum-blog-card">'; if(has_post_thumbnail($p)) echo '<a class="blum-blog-image" href="'.esc_url(get_permalink($p)).'"><img src="'.esc_url(get_the_post_thumbnail_url($p,'large')).'" alt="'.esc_attr(get_the_title($p)).'"></a>'; echo '<div class="blum-blog-content">'; if($s['show_date']) echo '<time datetime="'.esc_attr(get_the_date('c',$p)).'">'.esc_html(get_the_date('', $p)).'</time>'; echo '<h2><a href="'.esc_url(get_permalink($p)).'">'.esc_html($title).'</a></h2>'; if($s['show_excerpt']&&$excerpt) echo '<p>'.esc_html($excerpt).'</p>'; echo '<a class="blum-blog-read-more" href="'.esc_url(get_permalink($p)).'">'.esc_html($s['read_more']).' <span aria-hidden="true">→</span></a></div></article>'; }
    if(!$posts) echo '<p>No posts found.</p>'; echo '</section><style>.blum-blog-grid{display:grid;grid-template-columns:repeat(var(--blum-blog-columns),minmax(0,1fr));gap:28px;margin:clamp(30px,6vw,80px) auto;max-width:1400px}.blum-blog-card{background:#f5f3ed;border:1px solid #deddd5;display:flex;flex-direction:column;overflow:hidden}.blum-blog-image{display:block;overflow:hidden;background:#ddd}.blum-blog-image img{display:block;width:100%;height:var(--blum-blog-image-height);object-fit:cover}.blum-blog-content{padding:22px 22px 25px}.blum-blog-content time{font-size:11px;letter-spacing:.12em;text-transform:uppercase;color:#687267}.blum-blog-content h2{font:clamp(22px,2.3vw,32px)/1.12 Georgia,serif;font-weight:400;margin:10px 0}.blum-blog-content h2 a{color:inherit;text-decoration:none}.blum-blog-content p{line-height:1.6;margin:0 0 18px}.blum-blog-read-more{color:#335c43;text-decoration:none;font-size:13px}.blum-blog-read-more span{margin-left:6px}@media(max-width:800px){.blum-blog-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:520px){.blum-blog-grid{grid-template-columns:1fr}}
    </style>'; return ob_get_clean();
}
add_shortcode('blum_blog','blum_blog_shortcode');
