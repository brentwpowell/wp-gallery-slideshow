<?php
/*
Plugin Name: jQuery Slide Show
Description: Makes use of the built in Galley system to show a slideshow
Version: 0.1.7
Author: Brent W. Powell
*/

add_action('init', 'register_gallery_ss_scripts');	

function register_gallery_ss_scripts() {
    if ( !is_admin() ) {
        wp_register_script('jquery_slides', plugins_url("slides.min.jquery.js", __FILE__), false);
        wp_enqueue_script('jquery_slides');
    }
}

add_shortcode('slideshow', 'slideshow_shortcode');

/**
 * The Gallery shortcode.
 *
 * This implements the functionality of the Gallery Shortcode for displaying
 * WordPress images on a post.
 *
 * @since 2.5.0
 *
 * @param array $attr Attributes of the shortcode.
 * @return string HTML content to display gallery.
 */
function slideshow_shortcode($attr) {
	
	if ( !is_admin() ) {
	    wp_register_script('slideshow_functions', plugins_url("plugin-functions.js", __FILE__), true);
	    wp_enqueue_script('slideshow_functions');
	    wp_register_style('slideshow_stylesheet', plugins_url("jq-slideshow.css", __FILE__));
            wp_enqueue_style( 'slideshow_stylesheet');
	}
        
        global $post;

	static $instance = 0;
	$instance++;

	// We're trusting author input, so let's at least make sure it looks like a valid orderby statement
	if ( isset( $attr['orderby'] ) ) {
		$attr['orderby'] = sanitize_sql_orderby( $attr['orderby'] );
		if ( !$attr['orderby'] )
			unset( $attr['orderby'] );
	}

	extract(shortcode_atts(array(
		'order'      => 'ASC',
		'orderby'    => 'menu_order ID',
		'id'         => $post->ID,
		'columns'    => 10,
		'size'       => 'large',
        'thumbsize'  => 'thumbnail',
		'include'    => '',
		'exclude'    => '',
		'align'	     => 'center',
		'aspect'     => '1.5'
	), $attr));
	
	$id = intval($id);
	if ( 'RAND' == $order )
		$orderby = 'none';

	if ( !empty($include) ) {
		$include = preg_replace( '/[^0-9,]+/', '', $include );
		$_attachments = get_posts( array('include' => $include, 'post_status' => 'inherit', 'post_type' => 'attachment', 'post_mime_type' => 'image', 'order' => $order, 'orderby' => $orderby) );

		$attachments = array();
		foreach ( $_attachments as $key => $val ) {
			$attachments[$val->ID] = $_attachments[$key];
		}
	} elseif ( !empty($exclude) ) {
		$exclude = preg_replace( '/[^0-9,]+/', '', $exclude );
		$attachments = get_children( array('post_parent' => $id, 'exclude' => $exclude, 'post_status' => 'inherit', 'post_type' => 'attachment', 'post_mime_type' => 'image', 'order' => $order, 'orderby' => $orderby) );
	} else {
		$attachments = get_children( array('post_parent' => $id, 'post_status' => 'inherit', 'post_type' => 'attachment', 'post_mime_type' => 'image', 'order' => $order, 'orderby' => $orderby) );
	}

	if ( empty($attachments) )
		return '';

	if ( is_feed() ) {
		$output = "\n";
		foreach ( $attachments as $att_id => $attachment )
			$output .= wp_get_attachment_link($att_id, $size, true) . "\n";
		return $output;
	}
	
	// Checking to see what the image size is, if height is set in settings->media, then determining height by $aspect (long-side/short-side).
	if ( $size == 'medium' ) {
	    $wp_image_height = get_option('medium_size_h');
	    $gallery_width = get_option('medium_size_w');
	    if ( $wp_image_height > 0 && $wp_image_height <= $gallery_width  )
		$gallery_height = $wp_image_height;
	    else
		$gallery_height = $gallery_width / $aspect;
	}
	if ( $size == 'large' ) 
	    $gallery_width = get_option('large_size_w');
	    $wp_image_height = get_option('large_size_h');
	    if ( $wp_image_height > 0 && $wp_image_height <= $gallery_width )
		$gallery_height = $wp_image_height;
	    else
		$gallery_height = $gallery_width / $aspect;
	;
	
	$itemtag = tag_escape($itemtag);
	$captiontag = tag_escape($captiontag);
	$columns = intval($columns);
	$itemwidth = $columns > 0 ? floor(100/$columns) : 100;
	$float = is_rtl() ? 'right' : 'left';

	$selector = "gallery-{$instance}";
	
	$navlocation = $gallery_height/2;

	$gallery_style = $gallery_div = '';
	if ( apply_filters( 'use_default_gallery_style', true ) )
		$gallery_style = "
		<style type='text/css'>
			#slideshow.slideshow {
			    max-width:{$gallery_width}px;
			}#slideshow .slideshow-nav img {
			    float: {$float};
			    width: {$itemwidth}%;
			    height: auto;
			}
			#slideshow.slideshow .panes {
			    height:{$gallery_height}px;
			}
                        #slideshow.slideshow .panes .pane {
                            max-height:{$gallery_height}px;
			    width:{$gallery_width}px;
			}
			#slideshow.slideshow .panes .pane img {
			    max-height:{$gallery_height}px;
			    max-width:{$gallery_width}px;
			}
			#slideshow .prev, #slideshow .next {
			    top: {$navlocation}px;
			}
		</style>";
	$size_class = sanitize_html_class( $size );
	$gallery_div = "<section id='slideshow' class='$selector slideshow size-{$size_class} align{$align}'><div class='slides_container panes gallery-columns-{$columns} gallery-size-{$size_class}'>";
	$output = apply_filters( 'gallery_style', $gallery_style . "\n\t\t" . $gallery_div );
	
	$i = 0;
        foreach ( $attachments as $id => $attachment ) {
		$image = isset($attr['link']) && 'file' == $attr['link'] ? wp_get_attachment_image($id, $size, false, false) : wp_get_attachment_image($id, $size, true, false);
                $link = get_attachment_link($id);
                
		$output .= "<div class='pane'>$image</div>";
	}
	$output .= "</div>";
	
	if ($columns > 0){
	    $output .= '<ul class="slideshow-nav pagination">';
	    foreach ( $attachments as $id => $attachment ) {
		    $thumb = wp_get_attachment_image($id, $thumbsize);
		    
		    $output .= "
				<li><a href='#'>$thumb</a></li>
			    ";
	    }
	}
        $output .= '</ul>';
        

	$output .= "
			<br style='clear: both;' />
			
		</section>\n";
	$gallery_width = null ;
	return $output;
}