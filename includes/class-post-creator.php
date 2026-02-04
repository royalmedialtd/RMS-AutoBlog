<?php
/**
 * Post Creator - Enhanced to handle AI-generated content
 */

if (!defined('ABSPATH')) {
    exit;
}

class TechNews_Post_Creator {
    
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
        
        // Add post meta for tracking
        update_post_meta($post_id, '_technews_autoblog_generated', true);
        update_post_meta($post_id, '_technews_autoblog_topic', sanitize_text_field($topic));
        update_post_meta($post_id, '_technews_autoblog_date', current_time('mysql'));
        update_post_meta($post_id, '_technews_autoblog_ai', !empty($content['ai_generated']));
        
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
        $custom_categories = get_option('technews_custom_categories', '');
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
     * Set SEO meta for popular SEO plugins
     */
    private function set_seo_meta($post_id, $content) {
        $focus_keyword = !empty($content['keywords']) ? $content['keywords'][0] : '';
        $meta_description = $content['meta_description'] ?? '';
        
        // RankMath
        if (class_exists('RankMath')) {
            update_post_meta($post_id, 'rank_math_focus_keyword', $focus_keyword);
            update_post_meta($post_id, 'rank_math_description', $meta_description);
        }
        
        // Yoast SEO
        if (defined('WPSEO_VERSION')) {
            update_post_meta($post_id, '_yoast_wpseo_focuskw', $focus_keyword);
            update_post_meta($post_id, '_yoast_wpseo_metadesc', $meta_description);
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
}
