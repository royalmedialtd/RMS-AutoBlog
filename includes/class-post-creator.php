<?php
/**
 * Post Creator - Enhanced to handle AI-generated content
 */

if (!defined('ABSPATH')) {
    exit;
}

class rmsautoblog_Post_Creator {
    
    /**
     * Create a draft post
     */
    public function create($topic, $content, $category = 'general') {
        // Build post content HTML
        $post_content = $this->build_post_content($content);
        
        // Prepare post data
        $post_data = array(
            'post_title' => $content['title'] ?? $this->sanitize_title($topic),
            'post_content' => $post_content,
            'post_status' => 'draft',
            'post_type' => 'post',
            'post_author' => get_current_user_id()
        );
        
        // Insert post
        $post_id = wp_insert_post($post_data);
        
        if (is_wp_error($post_id)) {
            return $post_id;
        }
        
        // Assign category
        $this->assign_category($post_id, $category);
        
        // Set SEO meta if plugins available
        $this->set_seo_meta($post_id, $content);
        
        // Assign tags from keywords
        $this->assign_tags($post_id, $content);
        
        // Process featured image
        $this->process_featured_image($post_id, $content, $topic);
        
        // Add post meta for tracking
        update_post_meta($post_id, '_rmsautoblog_autoblog_generated', true);
        update_post_meta($post_id, '_rmsautoblog_autoblog_topic', sanitize_text_field($topic));
        update_post_meta($post_id, '_rmsautoblog_autoblog_date', current_time('mysql'));
        update_post_meta($post_id, '_rmsautoblog_autoblog_ai', !empty($content['ai_generated']));
        
        return $post_id;
    }
    
    /**
     * Build HTML content from structured data
     */
    private function build_post_content($content) {
        // Check if this is AI-generated content (single string)
        if (!empty($content['ai_generated']) && !empty($content['content'])) {
            return $this->markdown_to_gutenberg($content['content']);
        }
        
        // Template-based content with sections
        $html = '';
        
        // Introduction
        if (!empty($content['intro'])) {
            $html .= "<!-- wp:paragraph -->\n<p>" . wp_kses_post($content['intro']) . "</p>\n<!-- /wp:paragraph -->\n\n";
        }
        
        // Table of Contents (only for template-based)
        if (!empty($content['sections']) && count($content['sections']) > 2) {
            $html .= "<!-- wp:heading -->\n<h2>Table of Contents</h2>\n<!-- /wp:heading -->\n\n";
            $html .= "<!-- wp:list -->\n<ul>\n";
            foreach ($content['sections'] as $section) {
                $anchor = sanitize_title($section['title']);
                $html .= sprintf('<li><a href="#%s">%s</a></li>' . "\n", $anchor, esc_html($section['title']));
            }
            $html .= "</ul>\n<!-- /wp:list -->\n\n";
        }
        
        // Sections
        if (!empty($content['sections'])) {
            foreach ($content['sections'] as $section) {
                $anchor = sanitize_title($section['title']);
                $html .= sprintf(
                    "<!-- wp:heading -->\n<h2 id=\"%s\">%s</h2>\n<!-- /wp:heading -->\n\n",
                    $anchor,
                    esc_html($section['title'])
                );
                
                if (!empty($section['content'])) {
                    $section_content = $this->markdown_to_gutenberg($section['content']);
                    $html .= $section_content . "\n\n";
                }
            }
        }
        
        // Keywords info as comment
        if (!empty($content['keywords'])) {
            $html .= "\n<!-- Focus Keywords: " . implode(', ', $content['keywords']) . " -->\n";
        }
        
        return $html;
    }
    
    /**
     * Convert markdown to Gutenberg blocks
     */
    private function markdown_to_gutenberg($text) {
        $html = '';
        $lines = preg_split('/\n/', $text);
        $in_list = false;
        $list_type = '';
        $in_code_block = false;
        $code_content = '';
        $code_lang = '';
        $paragraph_buffer = '';
        
        foreach ($lines as $line) {
            // Code block start/end
            if (preg_match('/^```(\w*)/', $line, $matches)) {
                if (!$in_code_block) {
                    // Flush paragraph buffer
                    if (!empty($paragraph_buffer)) {
                        $html .= $this->wrap_paragraph($paragraph_buffer);
                        $paragraph_buffer = '';
                    }
                    $in_code_block = true;
                    $code_lang = $matches[1] ?: '';
                    continue;
                } else {
                    $in_code_block = false;
                    $html .= "<!-- wp:code -->\n<pre class=\"wp-block-code\"><code>" . esc_html(trim($code_content)) . "</code></pre>\n<!-- /wp:code -->\n\n";
                    $code_content = '';
                    continue;
                }
            }
            
            if ($in_code_block) {
                $code_content .= $line . "\n";
                continue;
            }
            
            // Headings
            if (preg_match('/^(#{1,6})\s+(.+)$/', $line, $matches)) {
                // Flush paragraph buffer
                if (!empty($paragraph_buffer)) {
                    $html .= $this->wrap_paragraph($paragraph_buffer);
                    $paragraph_buffer = '';
                }
                // Close any open list
                if ($in_list) {
                    $html .= $list_type === 'ul' ? "</ul>\n<!-- /wp:list -->\n\n" : "</ol>\n<!-- /wp:list -->\n\n";
                    $in_list = false;
                }
                
                $level = strlen($matches[1]);
                $heading_text = $this->parse_inline_markdown($matches[2]);
                $anchor = sanitize_title($matches[2]);
                $html .= "<!-- wp:heading {\"level\":$level} -->\n<h$level id=\"$anchor\">$heading_text</h$level>\n<!-- /wp:heading -->\n\n";
                continue;
            }
            
            // Unordered list items
            if (preg_match('/^[-*]\s+(.+)$/', $line, $matches)) {
                // Flush paragraph buffer
                if (!empty($paragraph_buffer)) {
                    $html .= $this->wrap_paragraph($paragraph_buffer);
                    $paragraph_buffer = '';
                }
                if (!$in_list || $list_type !== 'ul') {
                    if ($in_list) {
                        $html .= "</ol>\n<!-- /wp:list -->\n\n";
                    }
                    $html .= "<!-- wp:list -->\n<ul>\n";
                    $in_list = true;
                    $list_type = 'ul';
                }
                $item_text = $this->parse_inline_markdown($matches[1]);
                $html .= "<li>$item_text</li>\n";
                continue;
            }
            
            // Ordered list items
            if (preg_match('/^\d+\.\s+(.+)$/', $line, $matches)) {
                // Flush paragraph buffer
                if (!empty($paragraph_buffer)) {
                    $html .= $this->wrap_paragraph($paragraph_buffer);
                    $paragraph_buffer = '';
                }
                if (!$in_list || $list_type !== 'ol') {
                    if ($in_list) {
                        $html .= "</ul>\n<!-- /wp:list -->\n\n";
                    }
                    $html .= "<!-- wp:list {\"ordered\":true} -->\n<ol>\n";
                    $in_list = true;
                    $list_type = 'ol';
                }
                $item_text = $this->parse_inline_markdown($matches[1]);
                $html .= "<li>$item_text</li>\n";
                continue;
            }
            
            // Close list if we hit a non-list line
            if ($in_list && !empty(trim($line))) {
                $html .= $list_type === 'ul' ? "</ul>\n<!-- /wp:list -->\n\n" : "</ol>\n<!-- /wp:list -->\n\n";
                $in_list = false;
            }
            
            // Empty line - paragraph break
            if (empty(trim($line))) {
                if (!empty($paragraph_buffer)) {
                    $html .= $this->wrap_paragraph($paragraph_buffer);
                    $paragraph_buffer = '';
                }
                continue;
            }
            
            // Regular text - accumulate in paragraph buffer
            if (!empty($paragraph_buffer)) {
                $paragraph_buffer .= ' ';
            }
            $paragraph_buffer .= trim($line);
        }
        
        // Flush remaining content
        if (!empty($paragraph_buffer)) {
            $html .= $this->wrap_paragraph($paragraph_buffer);
        }
        if ($in_list) {
            $html .= $list_type === 'ul' ? "</ul>\n<!-- /wp:list -->\n\n" : "</ol>\n<!-- /wp:list -->\n\n";
        }
        
        return $html;
    }
    
    /**
     * Wrap text in paragraph block
     */
    private function wrap_paragraph($text) {
        $text = $this->parse_inline_markdown($text);
        return "<!-- wp:paragraph -->\n<p>" . wp_kses_post($text) . "</p>\n<!-- /wp:paragraph -->\n\n";
    }
    
    /**
     * Parse inline markdown (bold, italic, code, links)
     */
    private function parse_inline_markdown($text) {
        // Bold
        $text = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $text);
        $text = preg_replace('/__(.+?)__/', '<strong>$1</strong>', $text);
        
        // Italic
        $text = preg_replace('/\*(.+?)\*/', '<em>$1</em>', $text);
        $text = preg_replace('/_(.+?)_/', '<em>$1</em>', $text);
        
        // Inline code
        $text = preg_replace('/`(.+?)`/', '<code>$1</code>', $text);
        
        // Links
        $text = preg_replace('/\[(.+?)\]\((.+?)\)/', '<a href="$2">$1</a>', $text);
        
        return $text;
    }
    
    /**
     * Assign post to category
     */
    private function assign_category($post_id, $category_slug) {
        // Get custom categories from settings
        $custom_categories = get_option('rmsautoblog_custom_categories', '');
        $category_lines = array_filter(array_map('trim', explode("\n", $custom_categories)));
        
        // Try to find matching category
        $category_name = ucwords(str_replace('-', ' ', $category_slug));
        
        // If the slug matches one of the custom categories, use it
        foreach ($category_lines as $cat) {
            if (sanitize_title($cat) === $category_slug || strtolower($cat) === strtolower($category_slug)) {
                $category_name = $cat;
                break;
            }
        }
        
        // Get or create category
        $term = term_exists($category_name, 'category');
        
        if (!$term) {
            $term = wp_insert_term($category_name, 'category');
        }
        
        if (!is_wp_error($term)) {
            $term_id = is_array($term) ? $term['term_id'] : $term;
            wp_set_post_categories($post_id, array($term_id));
        }
    }
    
    /**
     * Assign tags from LSI keywords
     */
    private function assign_tags($post_id, $content) {
        if (empty($content['keywords']) || !is_array($content['keywords'])) {
            return;
        }
        
        // Use keywords as tags (skip first one as it's the focus keyword, use the rest as tags)
        $tags = array_slice($content['keywords'], 1, 8);
        
        if (!empty($tags)) {
            wp_set_post_tags($post_id, $tags, false);
        }
    }
    
    /**
     * Set SEO meta for popular SEO plugins (Yoast & AIOSEO)
     * Note: RankMath is intentionally not included to avoid interference
     */
    private function set_seo_meta($post_id, $content) {
        $focus_keyword = !empty($content['keywords']) ? $content['keywords'][0] : ''
;
        $meta_description = $content['meta_description'] ?? '';
        
        // Yoast SEO
        if (defined('WPSEO_VERSION')) {
            update_post_meta($post_id, '_yoast_wpseo_focuskw', $focus_keyword);
            update_post_meta($post_id, '_yoast_wpseo_metadesc', $meta_description);
            
            // Add additional LSI keywords if available
            if (!empty($content['keywords']) && count($content['keywords']) > 1) {
                update_post_meta($post_id, '_yoast_wpseo_focuskeywords', json_encode(array_slice($content['keywords'], 1, 4)));
            }
        }
        
        // All in One SEO
        if (class_exists('AIOSEO\\Plugin\\AIOSEO')) {
            update_post_meta($post_id, '_aioseo_keywords', $focus_keyword);
            update_post_meta($post_id, '_aioseo_description', $meta_description);
        }
    }
    
    /**
     * Sanitize topic for title
     */
    private function sanitize_title($topic) {
        return ucwords(strtolower(sanitize_text_field($topic)));
    }
    
    /**
     * Process and attach featured image to post
     */
    private function process_featured_image($post_id, $content, $topic) {
        // Try to find relevant image from RSS content or use placeholder
        $image_url = $this->find_relevant_image($content, $topic);
        
        if ($image_url) {
            $attachment_id = $this->download_and_attach_image($post_id, $image_url);
            if ($attachment_id) {
                set_post_thumbnail($post_id, $attachment_id);
                
                // Add image alt text for SEO
                $alt_text = $this->generate_alt_text($topic);
                update_post_meta($attachment_id, '_wp_attachment_image_alt', $alt_text);
                
                // Add image caption
                wp_update_post(array(
                    'ID' => $attachment_id,
                    'post_excerpt' => $alt_text
                ));
            }
        }
    }
    
    /**
     * Find relevant image from content or generate one
     */
    private function find_relevant_image($content, $topic) {
        // First, try to extract image from RSS content if available
        if (!empty($content['rss_images']) && is_array($content['rss_images'])) {
            foreach ($content['rss_images'] as $image_url) {
                if ($this->is_valid_image_url($image_url)) {
                    return $image_url;
                }
            }
        }
        
        // Check if content has embedded images
        if (!empty($content['content']) && is_string($content['content'])) {
            // Try to extract image URLs from markdown or HTML content
            preg_match_all('/\!\[.*?\]\((https?:\/\/[^\)]+\.(jpg|jpeg|png|gif|webp))\)/i', $content['content'], $matches);
            if (!empty($matches[1][0]) && $this->is_valid_image_url($matches[1][0])) {
                return $matches[1][0];
            }
        }
        
        // If no image found, return false (could integrate with Unsplash API or similar in future)
        return false;
    }
    
    /**
     * Validate image URL
     */
    private function is_valid_image_url($url) {
        if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }
        
        $image_extensions = array('.jpg', '.jpeg', '.png', '.gif', '.webp');
        $parsed_url = parse_url($url);
        
        if (!isset($parsed_url['path'])) {
            return false;
        }
        
        $path_lower = strtolower($parsed_url['path']);
        foreach ($image_extensions as $ext) {
            if (substr($path_lower, -strlen($ext)) === $ext) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Download and attach image to post
     */
    private function download_and_attach_image($post_id, $image_url) {
        // Download image
        $image_data = wp_remote_get($image_url, array(
            'timeout' => 15,
            'headers' => array(
                'User-Agent' => 'TechNews-Autoblog/1.0 (WordPress Plugin)'
            )
        ));
        
        if (is_wp_error($image_data)) {
            return false;
        }
        
        $image_body = wp_remote_retrieve_body($image_data);
        $image_type = wp_remote_retrieve_header($image_data, 'content-type');
        
        if (empty($image_body)) {
            return false;
        }
        
        // Determine file extension
        switch ($image_type) {
            case 'image/jpeg':
                $ext = '.jpg';
                break;
            case 'image/png':
                $ext = '.png';
                break;
            case 'image/gif':
                $ext = '.gif';
                break;
            case 'image/webp':
                $ext = '.webp';
                break;
            default:
                $ext = '.jpg';
        }
        
        // Create file name
        $filename = sanitize_file_name('technews-' . $post_id . '-' . time() . $ext);
        
        // Upload directory
        $upload_dir = wp_upload_dir();
        $file_path = $upload_dir['path'] . '/' . $filename;
        
        // Save file
        $file_saved = file_put_contents($file_path, $image_body);
        
        if ($file_saved === false) {
            return false;
        }
        
        // Create attachment
        $attachment = array(
            'guid'           => $upload_dir['url'] . '/' . $filename,
            'post_mime_type' => $image_type,
            'post_title'     => preg_replace('/\.[^.]+$/', '', $filename),
            'post_content'   => '',
            'post_status'    => 'inherit'
        );
        
        $attach_id = wp_insert_attachment($attachment, $file_path, $post_id);
        
        if (is_wp_error($attach_id)) {
            return false;
        }
        
        // Generate metadata
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        $attach_data = wp_generate_attachment_metadata($attach_id, $file_path);
        wp_update_attachment_metadata($attach_id, $attach_data);
        
        return $attach_id;
    }
    
    /**
     * Generate alt text for images
     */
    private function generate_alt_text($topic) {
        return 'Illustration related to ' . sanitize_text_field($topic);
    }
}

