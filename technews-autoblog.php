<?php
/**
 * Plugin Name: TechNews Autoblog
 * Plugin URI: https://technews.page
 * Description: Automatically discover trending topics in SEO, Marketing, Web Development, and Mobile Development, then generate structured blog post drafts.
 * Version: 1.0.0
 * Author: TechNews
 * Author URI: https://technews.page
 * License: GPL v2 or later
 * Text Domain: technews-autoblog
 */

if (!defined('ABSPATH')) {
    exit;
}

define('TECHNEWS_AUTOBLOG_VERSION', '1.0.0');
define('TECHNEWS_AUTOBLOG_PATH', plugin_dir_path(__FILE__));
define('TECHNEWS_AUTOBLOG_URL', plugin_dir_url(__FILE__));

// Include required files
require_once TECHNEWS_AUTOBLOG_PATH . 'includes/class-trending-fetcher.php';
require_once TECHNEWS_AUTOBLOG_PATH . 'includes/class-content-generator.php';
require_once TECHNEWS_AUTOBLOG_PATH . 'includes/class-post-creator.php';

class TechNews_Autoblog {
    
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
        $this->trending_fetcher = new TechNews_Trending_Fetcher();
        $this->content_generator = new TechNews_Content_Generator();
        $this->post_creator = new TechNews_Post_Creator();
        
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('wp_ajax_technews_fetch_trends', array($this, 'ajax_fetch_trends'));
        add_action('wp_ajax_technews_create_post', array($this, 'ajax_create_post'));
        add_action('wp_ajax_technews_test_api', array($this, 'ajax_test_api'));
        add_action('admin_init', array($this, 'register_settings'));
    }
    
    public function ajax_test_api() {
        check_ajax_referer('technews_autoblog_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'technews-autoblog')));
        }
        
        $api_key = isset($_POST['api_key']) ? sanitize_text_field($_POST['api_key']) : '';
        
        if (empty($api_key)) {
            wp_send_json_error(array('message' => __('API key is required', 'technews-autoblog')));
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
                'User-Agent' => 'TechNews-Autoblog/' . TECHNEWS_AUTOBLOG_VERSION . ' (WordPress Plugin)'
            )
        ));
        
        if (is_wp_error($response)) {
            wp_send_json_error(array('message' => $response->get_error_message()));
        }
        
        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if ($code !== 200) {
            wp_send_json_error(array('message' => $body['message'] ?? __('API request failed', 'technews-autoblog')));
        }
        
        wp_send_json_success(array('message' => __('Connection successful!', 'technews-autoblog')));
    }
    
    public function add_admin_menu() {
        add_menu_page(
            __('TechNews Autoblog', 'technews-autoblog'),
            __('TechNews Autoblog', 'technews-autoblog'),
            'manage_options',
            'technews-autoblog',
            array($this, 'render_dashboard'),
            'dashicons-trending-up',
            30
        );
        
        add_submenu_page(
            'technews-autoblog',
            __('Settings', 'technews-autoblog'),
            __('Settings', 'technews-autoblog'),
            'manage_options',
            'technews-autoblog-settings',
            array($this, 'render_settings')
        );
    }
    
    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'technews-autoblog') === false) {
            return;
        }
        
        wp_enqueue_style(
            'technews-autoblog-admin',
            TECHNEWS_AUTOBLOG_URL . 'admin/css/admin.css',
            array(),
            TECHNEWS_AUTOBLOG_VERSION
        );
        
        wp_enqueue_script(
            'technews-autoblog-admin',
            TECHNEWS_AUTOBLOG_URL . 'admin/js/admin.js',
            array('jquery', 'wp-util'),
            TECHNEWS_AUTOBLOG_VERSION,
            true
        );
        
        wp_localize_script('technews-autoblog-admin', 'technewsAutoblog', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('technews_autoblog_nonce'),
            'strings' => array(
                'fetching' => __('Fetching trends...', 'technews-autoblog'),
                'creating' => __('Creating post...', 'technews-autoblog'),
                'success' => __('Post created successfully!', 'technews-autoblog'),
                'error' => __('An error occurred. Please try again.', 'technews-autoblog'),
            )
        ));
    }
    
    public function register_settings() {
        // API Keys
        register_setting('technews_autoblog_settings', 'technews_newsapi_key');
        register_setting('technews_autoblog_settings', 'technews_openai_key');
        register_setting('technews_autoblog_settings', 'technews_openai_model');
        
        // Custom categories
        register_setting('technews_autoblog_settings', 'technews_custom_categories');
        
        // Content settings
        register_setting('technews_autoblog_settings', 'technews_brand_voice');
        register_setting('technews_autoblog_settings', 'technews_writing_style');
        register_setting('technews_autoblog_settings', 'technews_content_length');
        register_setting('technews_autoblog_settings', 'technews_target_audience');
        register_setting('technews_autoblog_settings', 'technews_include_examples');
        register_setting('technews_autoblog_settings', 'technews_include_stats');
        register_setting('technews_autoblog_settings', 'technews_include_cta');
        
        // RSS feed sources
        register_setting('technews_autoblog_settings', 'technews_rss_feeds');
        register_setting('technews_autoblog_settings', 'technews_rss_limit');
        
        // Legacy
        register_setting('technews_autoblog_settings', 'technews_categories', array(
            'default' => array('seo', 'marketing', 'web-development', 'mobile-development')
        ));
    }
    
    public function render_dashboard() {
        include TECHNEWS_AUTOBLOG_PATH . 'admin/views/dashboard.php';
    }
    
    public function render_settings() {
        include TECHNEWS_AUTOBLOG_PATH . 'admin/views/settings.php';
    }
    
    public function ajax_fetch_trends() {
        check_ajax_referer('technews_autoblog_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'technews-autoblog')));
        }
        
        $category = isset($_POST['category']) ? sanitize_text_field($_POST['category']) : '';
        $trends = $this->trending_fetcher->fetch_trends($category);
        
        if (is_wp_error($trends)) {
            wp_send_json_error(array('message' => $trends->get_error_message()));
        }
        
        wp_send_json_success(array('trends' => $trends));
    }
    
    public function ajax_create_post() {
        check_ajax_referer('technews_autoblog_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('Permission denied', 'technews-autoblog')));
        }
        
        $topic = isset($_POST['topic']) ? sanitize_text_field($_POST['topic']) : '';
        $category = isset($_POST['category']) ? sanitize_text_field($_POST['category']) : '';
        
        if (empty($topic)) {
            wp_send_json_error(array('message' => __('Topic is required', 'technews-autoblog')));
        }
        
        // Generate content structure
        $content = $this->content_generator->generate($topic, $category);
        
        // Create draft post
        $post_id = $this->post_creator->create($topic, $content, $category);
        
        if (is_wp_error($post_id)) {
            wp_send_json_error(array('message' => $post_id->get_error_message()));
        }
        
        wp_send_json_success(array(
            'post_id' => $post_id,
            'edit_url' => get_edit_post_link($post_id, 'raw'),
            'message' => __('Post draft created successfully!', 'technews-autoblog')
        ));
    }
}

// Initialize plugin
add_action('plugins_loaded', array('TechNews_Autoblog', 'get_instance'));

// Activation hook
register_activation_hook(__FILE__, function() {
    // Create default options
    if (!get_option('technews_categories')) {
        update_option('technews_categories', array('seo', 'marketing', 'web-development', 'mobile-development'));
    }
});

// Deactivation hook
register_deactivation_hook(__FILE__, function() {
    // Cleanup if needed
});
