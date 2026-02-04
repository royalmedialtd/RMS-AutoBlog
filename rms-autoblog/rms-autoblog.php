<?php
/**
 * Plugin Name: RMS AutoBlog
 * Plugin URI: https://www.royalmedia.us
 * Description: Automatically generate SEO-optimized blog posts from trending topics, RSS feeds, or custom keywords with AI-powered content creation.
 * Version: 2.0.0
 * Author: Royal Media Services
 * Author URI: https://www.royalmedia.us
 * License: GPL v2 or later
 * Text Domain: rms-autoblog
 */

if (!defined('ABSPATH')) {
    exit;
}

define('RMSAUTOBLOG_VERSION', '2.0.0');
define('RMSAUTOBLOG_PATH', plugin_dir_path(__FILE__));
define('RMSAUTOBLOG_URL', plugin_dir_url(__FILE__));


// Include required files
require_once RMSAUTOBLOG_PATH . 'includes/class-trending-fetcher.php';
require_once RMSAUTOBLOG_PATH . 'includes/class-content-generator.php';
require_once RMSAUTOBLOG_PATH . 'includes/class-post-creator.php';
require_once RMSAUTOBLOG_PATH . 'includes/class-scheduler.php';

class RMS_Autoblog {
    
    private static $instance = null;
    private $trending_fetcher;
    private $content_generator;
    private $post_creator;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->trending_fetcher = new rmsautoblog_Trending_Fetcher();
        $this->content_generator = new rmsautoblog_Content_Generator();
        $this->post_creator = new rmsautoblog_Post_Creator();
        
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('admin_head', array($this, 'admin_menu_icon_styles'));
        add_action('wp_ajax_rmsautoblog_fetch_trends', array($this, 'ajax_fetch_trends'));
        add_action('wp_ajax_rmsautoblog_create_post', array($this, 'ajax_create_post'));
        add_action('wp_ajax_rmsautoblog_test_api', array($this, 'ajax_test_api'));
        add_action('wp_ajax_rmsautoblog_get_suggestions', array($this, 'ajax_get_suggestions'));
        add_action('wp_ajax_rmsautoblog_generate_image', array($this, 'ajax_generate_image'));
        add_action('wp_ajax_rmsautoblog_create_custom_post', array($this, 'ajax_create_custom_post'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_init', array($this, 'migrate_settings'));
    }
    
    public function ajax_test_api() {
        check_ajax_referer('RMS_Autoblog_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'rms-autoblog')));
        }

        $api_key = isset($_POST['api_key']) ? sanitize_text_field($_POST['api_key']) : '';

        if (empty($api_key)) {
            wp_send_json_error(array('message' => __('API key is required', 'rms-autoblog')));
        }

        // Test the API
        $url = add_query_arg(array(
            'q' => 'technology',
            'pageSize' => 1,
            'apiKey' => $api_key
        ), 'https://newsapi.org/v2/everything');

        $response = wp_remote_get($url, array(
            'timeout' => 10,
            'headers' => array(
                'User-Agent' => 'rms-autoblog/' . RMSAUTOBLOG_VERSION . ' (WordPress Plugin)'
            )
        ));

        if (is_wp_error($response)) {
            wp_send_json_error(array('message' => $response->get_error_message()));
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        // Handle specific error codes
        if ($code === 426) {
            wp_send_json_error(array(
                'message' => __('NewsAPI free/developer plan only works from localhost. Your API key is valid, but you need to upgrade to a paid plan for production use. RSS feeds will still work as a fallback.', 'rms-autoblog'),
                'code' => 'upgrade_required'
            ));
        }

        if ($code === 401) {
            wp_send_json_error(array(
                'message' => __('Invalid API key. Please verify your NewsAPI key is correct.', 'rms-autoblog'),
                'code' => 'unauthorized'
            ));
        }

        if ($code === 429) {
            wp_send_json_error(array(
                'message' => __('Rate limit exceeded. NewsAPI limits requests on the free tier. Please wait a moment and try again.', 'rms-autoblog'),
                'code' => 'rate_limit'
            ));
        }

        if ($code !== 200) {
            wp_send_json_error(array(
                'message' => $body['message'] ?? __('API request failed', 'rms-autoblog') . ' (HTTP ' . $code . ')',
                'code' => 'api_error'
            ));
        }

        wp_send_json_success(array('message' => __('Connection successful! NewsAPI is working.', 'rms-autoblog')));
    }
    
    /**
     * Add custom styles for admin menu icon
     */
    public function admin_menu_icon_styles() {
        ?>
        <style>
            /* RMS AutoBlog Menu Icon Styling */
            #adminmenu .toplevel_page_rms-autoblog .wp-menu-image img {
                padding: 6px 0 0 0;
                opacity: 0.6;
                filter: brightness(0) invert(1);
                width: 20px;
                height: 20px;
            }
            #adminmenu .toplevel_page_rms-autoblog:hover .wp-menu-image img,
            #adminmenu .toplevel_page_rms-autoblog.current .wp-menu-image img,
            #adminmenu .toplevel_page_rms-autoblog.wp-has-current-submenu .wp-menu-image img {
                opacity: 1;
            }
        </style>
        <?php
    }

    public function add_admin_menu() {
        // Custom SVG icon for the menu (base64 encoded) - trending up arrow
        $icon_svg = 'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#a7aaad" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"></polyline><polyline points="17 6 23 6 23 12"></polyline></svg>');

        add_menu_page(
            __('RMS AutoBlog', 'rms-autoblog'),
            __('RMS AutoBlog', 'rms-autoblog'),
            'manage_options',
            'rms-autoblog',
            array($this, 'render_dashboard'),
            $icon_svg,
            30
        );
        
        add_submenu_page(
            'rms-autoblog',
            __('Settings', 'rms-autoblog'),
            __('Settings', 'rms-autoblog'),
            'manage_options',
            'rms-autoblog-settings',
            array($this, 'render_settings')
        );
    }
    
    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'rms-autoblog') === false) {
            return;
        }

        // Enqueue Google Fonts for Inter
        wp_enqueue_style(
            'rms-autoblog-google-fonts',
            'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap',
            array(),
            null
        );

        wp_enqueue_style(
            'rms-autoblog-admin',
            RMSAUTOBLOG_URL . 'admin/css/admin.css',
            array('rms-autoblog-google-fonts'),
            RMSAUTOBLOG_VERSION . '.' . time() // Cache busting for development
        );
        
        wp_enqueue_script(
            'rms-autoblog-admin',
            RMSAUTOBLOG_URL . 'admin/js/admin.js',
            array('jquery', 'wp-util'),
            RMSAUTOBLOG_VERSION,
            true
        );
        
        wp_localize_script('rms-autoblog-admin', 'rmsautoblogSettings', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('RMS_Autoblog_nonce'),
            'strings' => array(
                'fetching' => __('Fetching trends...', 'rms-autoblog'),
                'creating' => __('Creating post...', 'rms-autoblog'),
                'success' => __('Post created successfully!', 'rms-autoblog'),
                'error' => __('An error occurred. Please try again.', 'rms-autoblog'),
            )
        ));
    }
    
    public function migrate_settings() {
        // Migrate API keys from old prefix
        if (get_option('technews_newsapi_key') && !get_option('rmsautoblog_newsapi_key')) {
            update_option('rmsautoblog_newsapi_key', get_option('technews_newsapi_key'));
        }
        if (get_option('technews_openai_key') && !get_option('rmsautoblog_openai_key')) {
            update_option('rmsautoblog_openai_key', get_option('technews_openai_key'));
        }
        if (get_option('technews_openai_model') && !get_option('rmsautoblog_openai_model')) {
            update_option('rmsautoblog_openai_model', get_option('technews_openai_model'));
        }
        
        // Migrate feed sources
        if (get_option('technews_rss_feeds') && !get_option('rmsautoblog_rss_feeds')) {
            update_option('rmsautoblog_rss_feeds', get_option('technews_rss_feeds'));
        }
    }
    
    public function register_settings() {
        // API Keys
        register_setting('rmsautoblog_settings', 'rmsautoblog_newsapi_key');
        register_setting('rmsautoblog_settings', 'rmsautoblog_openai_key');
        register_setting('rmsautoblog_settings', 'rmsautoblog_openai_model');
        
        // Custom categories
        register_setting('rmsautoblog_settings', 'rmsautoblog_custom_categories');
        
        // Content settings
        register_setting('rmsautoblog_settings', 'rmsautoblog_brand_voice');
        register_setting('rmsautoblog_settings', 'rmsautoblog_writing_style');
        register_setting('rmsautoblog_settings', 'rmsautoblog_content_length');
        register_setting('rmsautoblog_settings', 'rmsautoblog_target_audience');
        register_setting('rmsautoblog_settings', 'rmsautoblog_include_examples');
        register_setting('rmsautoblog_settings', 'rmsautoblog_include_stats');
        register_setting('rmsautoblog_settings', 'rmsautoblog_include_cta');
        
        // RSS feed sources
        register_setting('rmsautoblog_settings', 'rmsautoblog_rss_feeds');
        register_setting('rmsautoblog_settings', 'rmsautoblog_rss_limit');
        
        // Analytics tracking
        register_setting('rmsautoblog_settings', 'rmsautoblog_track_performance');
        register_setting('rmsautoblog_settings', 'rmsautoblog_track_keywords');
        register_setting('rmsautoblog_settings', 'rmsautoblog_track_ai_content');
        
        // Legacy
        register_setting('rmsautoblog_settings', 'rmsautoblog_categories', array(
            'default' => array('seo', 'marketing', 'web-development', 'mobile-development')
        ));
    }
    
    public function render_dashboard() {
        include RMSAUTOBLOG_PATH . 'admin/views/dashboard.php';
    }
    
    public function render_settings() {
        include RMSAUTOBLOG_PATH . 'admin/views/settings.php';
    }
    
    public function ajax_fetch_trends() {
        check_ajax_referer('RMS_Autoblog_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'rms-autoblog')));
        }
        
        $category = isset($_POST['category']) ? sanitize_text_field($_POST['category']) : '';
        $trends = $this->trending_fetcher->fetch_trends($category);
        
        if (is_wp_error($trends)) {
            wp_send_json_error(array('message' => $trends->get_error_message()));
        }
        
        wp_send_json_success(array('trends' => $trends));
    }
    
    public function ajax_create_post() {
        check_ajax_referer('RMS_Autoblog_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('Permission denied', 'rms-autoblog')));
        }
        
        $topic = isset($_POST['topic']) ? sanitize_text_field($_POST['topic']) : '';
        $category = isset($_POST['category']) ? sanitize_text_field($_POST['category']) : '';
        $use_ai = isset($_POST['use_ai']) && $_POST['use_ai'] == '1';
        
        if (empty($topic)) {
            wp_send_json_error(array('message' => __('Topic is required', 'rms-autoblog')));
        }
        
        // Check if AI is requested but not configured
        $openai_key = get_option('rmsautoblog_openai_key', '');
        if ($use_ai && empty($openai_key)) {
            wp_send_json_error(array('message' => __('OpenAI API key is not configured. Please add your API key in Settings.', 'rms-autoblog')));
        }
        
        // Generate content structure
        $content = $this->content_generator->generate($topic, $category, $use_ai);
        
        // Check for generation errors
        if (is_wp_error($content)) {
            wp_send_json_error(array('message' => $content->get_error_message()));
        }
        
        // Create draft post
        $post_id = $this->post_creator->create($topic, $content, $category);
        
        if (is_wp_error($post_id)) {
            wp_send_json_error(array('message' => $post_id->get_error_message()));
        }
        
        $ai_note = !empty($content['ai_generated']) ? ' (AI-generated)' : ' (Template)';
        
        wp_send_json_success(array(
            'post_id' => $post_id,
            'edit_url' => get_edit_post_link($post_id, 'raw'),
            'message' => __('Post draft created successfully!', 'rms-autoblog') . $ai_note
        ));
    }
    
    public function ajax_get_suggestions() {
        check_ajax_referer('RMS_Autoblog_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('Permission denied', 'rms-autoblog')));
        }
        
        $keyword = isset($_POST['keyword']) ? sanitize_text_field($_POST['keyword']) : '';
        
        if (empty($keyword)) {
            wp_send_json_error(array('message' => __('Keyword is required', 'rms-autoblog')));
        }
        
        // Check OpenAI
        $openai_key = get_option('rmsautoblog_openai_key', '');
        if (empty($openai_key)) {
            wp_send_json_error(array('message' => __('OpenAI API key not configured', 'rms-autoblog')));
        }
        
        // Create simple prompt for title and structure
        $prompt = "For the topic '{$keyword}', suggest:\n1. An SEO-friendly blog post title\n2. A content structure with 4-6 main sections (one per line)\n\nFormat:\nTITLE: [your title]\nSTRUCTURE:\n- Section 1\n- Section 2\n...";
        
        $model = get_option('rmsautoblog_openai_model', 'gpt-4o-mini');
        
        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', array(
            'timeout' => 30,
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $openai_key
            ),
            'body' => json_encode(array(
                'model' => $model,
                'messages' => array(
                    array('role' => 'user', 'content' => $prompt)
                ),
                'temperature' => 0.7,
                'max_tokens' => 300
            ))
        ));
        
        if (is_wp_error($response)) {
            wp_send_json_error(array('message' => $response->get_error_message()));
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['error'])) {
            wp_send_json_error(array('message' => $body['error']['message'] ?? __('OpenAI API error', 'rms-autoblog')));
        }
        
        $suggestion_text = $body['choices'][0]['message']['content'] ?? '';
        
        // Parse the response
        $title = '';
        $structure = '';
        
        if (preg_match('/TITLE:\s*(.+?)(?:\n|$)/i', $suggestion_text, $matches)) {
            $title = trim($matches[1]);
        }
        
        if (preg_match('/STRUCTURE:\s*\n((?:[-*]\s*.+\n?)+)/i', $suggestion_text, $matches)) {
            $structure = trim($matches[1]);
            // Clean up the structure
            $structure = preg_replace('/^[-*]\s*/m', '', $structure);
        }
        
        wp_send_json_success(array(
            'title' => $title,
            'structure' => $structure
        ));
    }
    
    public function ajax_generate_image() {
        check_ajax_referer('RMS_Autoblog_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('Permission denied', 'rms-autoblog')));
        }
        
        $prompt = isset($_POST['prompt']) ? sanitize_text_field($_POST['prompt']) : '';
        
        if (empty($prompt)) {
            wp_send_json_error(array('message' => __('Prompt is required', 'rms-autoblog')));
        }
        
        wp_send_json_error(array('message' => __('Image generation requires external API integration. Please manually upload an image for now.', 'rms-autoblog')));
    }
    
    public function ajax_create_custom_post() {
        check_ajax_referer('RMS_Autoblog_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('Permission denied', 'rms-autoblog')));
        }
        
        $keyword = isset($_POST['keyword']) ? sanitize_text_field($_POST['keyword']) : '';
        $title = isset($_POST['title']) ? sanitize_text_field($_POST['title']) : '';
        $structure = isset($_POST['structure']) ? sanitize_textarea_field($_POST['structure']) : '';
        $category = isset($_POST['category']) ? sanitize_text_field($_POST['category']) : 'general';
        $use_ai = isset($_POST['use_ai']) && $_POST['use_ai'] == '1';
        $image_path = isset($_POST['image_path']) ? sanitize_text_field($_POST['image_path']) : '';
        
        if (empty($keyword)) {
            wp_send_json_error(array('message' => __('Keyword is required', 'rms-autoblog')));
        }
        
        // Use custom title if provided, otherwise use keyword
        $topic = !empty($title) ? $title : $keyword;
        
        // Check if AI is requested but not configured
        $openai_key = get_option('rmsautoblog_openai_key', '');
        if ($use_ai && empty($openai_key)) {
            wp_send_json_error(array('message' => __('OpenAI API key not configured. Please add it in Settings or uncheck "Use AI".', 'rms-autoblog')));
        }
        
        try {
            // Generate content
            $content_data = $this->content_generator->generate($keyword, $category, $use_ai, $structure);
            
            // Override title if custom title was provided
            if (!empty($title)) {
                $content_data['title'] = $title;
            }
            
            // Create post
            $post_id = $this->post_creator->create($keyword, $content_data, $category);
            
            if (is_wp_error($post_id)) {
                wp_send_json_error(array('message' => $post_id->get_error_message()));
            }
            
            // Handle custom image if provided
            if (!empty($image_path) && file_exists($image_path)) {
                $attachment_id = $this->upload_custom_image($post_id, $image_path);
                if ($attachment_id) {
                    set_post_thumbnail($post_id, $attachment_id);
                }
            }
            
            $ai_note = $use_ai ? '' : ' ' . __('(Template-based content, edit and enhance it)', 'rms-autoblog');
            
            wp_send_json_success(array(
                'post_id' => $post_id,
                'edit_url' => get_edit_post_link($post_id, 'raw'),
                'message' => __('Custom post created successfully!', 'rms-autoblog') . $ai_note
            ));
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
    
    private function upload_custom_image($post_id, $image_path) {
        $upload_dir = wp_upload_dir();
        $filename = basename($image_path);
        $new_path = $upload_dir['path'] . '/' . $filename;
        
        // Copy file if it's not already in uploads
        if ($image_path !== $new_path) {
            @copy($image_path, $new_path);
        }
        
        $attachment = array(
            'guid' => $upload_dir['url'] . '/' . $filename,
            'post_mime_type' => wp_check_filetype($filename)['type'],
            'post_title' => preg_replace('/\.[^.]+$/', '', $filename),
            'post_content' => '',
            'post_status' => 'inherit'
        );
        
        $attach_id = wp_insert_attachment($attachment, $new_path, $post_id);
        
        if (!is_wp_error($attach_id)) {
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            $attach_data = wp_generate_attachment_metadata($attach_id, $new_path);
            wp_update_attachment_metadata($attach_id, $attach_data);
            return $attach_id;
        }
        
        return false;
    }
}

// Initialize plugin
add_action('plugins_loaded', array('RMS_Autoblog', 'get_instance'));

// Activation hook
register_activation_hook(__FILE__, function() {
    // Create default options
    if (!get_option('rmsautoblog_categories')) {
        update_option('rmsautoblog_categories', array('seo', 'marketing', 'web-development', 'mobile-development'));
    }
});

// Deactivation hook
register_deactivation_hook(__FILE__, function() {
    // Cleanup if needed
});



