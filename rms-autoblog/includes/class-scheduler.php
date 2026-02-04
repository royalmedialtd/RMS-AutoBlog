<?php
/**
 * Content Scheduler for TechNews Autoblog
 */

if (!defined('ABSPATH')) {
    exit;
}

class rmsautoblog_Scheduler {
    
    public static function init() {
        add_action('rmsautoblog_autoblog_schedule_check', array(__CLASS__, 'check_scheduled_posts'));
        add_action('wp', array(__CLASS__, 'schedule_cron_job'));
        add_action('add_meta_boxes', array(__CLASS__, 'add_scheduling_meta_box'));
        add_action('save_post', array(__CLASS__, 'save_scheduling_data'));
    }
    
    public static function schedule_cron_job() {
        if (!wp_next_scheduled('rmsautoblog_autoblog_schedule_check')) {
            wp_schedule_event(time(), 'hourly', 'rmsautoblog_autoblog_schedule_check');
        }
    }
    
    public static function check_scheduled_posts() {
        $scheduled_posts = get_posts(array(
            'post_type' => 'post',
            'post_status' => 'draft',
            'meta_key' => '_rmsautoblog_autoblog_scheduled',
            'meta_value' => '1',
            'posts_per_page' => 5,
            'meta_query' => array(
                array(
                    'key' => '_rmsautoblog_autoblog_publish_time',
                    'value' => current_time('timestamp'),
                    'compare' => '<=',
                    'type' => 'NUMERIC'
                )
            )
        ));
        
        foreach ($scheduled_posts as $post) {
            // Publish the post
            wp_update_post(array(
                'ID' => $post->ID,
                'post_status' => 'publish'
            ));
            
            // Remove scheduling metadata
            delete_post_meta($post->ID, '_rmsautoblog_autoblog_scheduled');
            delete_post_meta($post->ID, '_rmsautoblog_autoblog_publish_time');
            delete_post_meta($post->ID, '_rmsautoblog_autoblog_publish_date');
            
            // Log the publishing
            update_post_meta($post->ID, '_rmsautoblog_autoblog_published_at', current_time('mysql'));
        }
    }
    
    public static function add_scheduling_meta_box() {
        add_meta_box(
            'technews-scheduling',
            'TechNews Autoblog Scheduling',
            array(__CLASS__, 'render_scheduling_meta_box'),
            'post',
            'side',
            'default'
        );
    }
    
    public static function render_scheduling_meta_box($post) {
        $scheduled = get_post_meta($post->ID, '_rmsautoblog_autoblog_scheduled', true);
        $publish_date = get_post_meta($post->ID, '_rmsautoblog_autoblog_publish_date', true);
        
        wp_nonce_field('rmsautoblog_scheduler_nonce', 'rmsautoblog_scheduler_nonce');
        ?>
        <p>
            <label>
                <input type="checkbox" name="rmsautoblog_schedule_post" <?php checked($scheduled, '1'); ?> />
                Schedule this post for automatic publishing
            </label>
        </p>
        
        <p>
            <label for="rmsautoblog_publish_date">Publish Date & Time:</label>
            <input type="datetime-local" 
                   id="rmsautoblog_publish_date" 
                   name="rmsautoblog_publish_date" 
                   value="<?php echo esc_attr($publish_date); ?>" 
                   style="width: 100%;" />
        </p>
        <?php
    }
    
    public static function save_scheduling_data($post_id) {
        // Verify nonce
        if (!isset($_POST['rmsautoblog_scheduler_nonce']) || 
            !wp_verify_nonce($_POST['rmsautoblog_scheduler_nonce'], 'rmsautoblog_scheduler_nonce')) {
            return;
        }
        
        // Check if user has permission
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Save scheduling data
        if (isset($_POST['rmsautoblog_schedule_post'])) {
            update_post_meta($post_id, '_rmsautoblog_autoblog_scheduled', '1');
            
            if (!empty($_POST['rmsautoblog_publish_date'])) {
                $publish_date = sanitize_text_field($_POST['rmsautoblog_publish_date']);
                update_post_meta($post_id, '_rmsautoblog_autoblog_publish_date', $publish_date);
                
                // Convert to timestamp for comparison
                $timestamp = strtotime($publish_date);
                update_post_meta($post_id, '_rmsautoblog_autoblog_publish_time', $timestamp);
            }
        } else {
            delete_post_meta($post_id, '_rmsautoblog_autoblog_scheduled');
            delete_post_meta($post_id, '_rmsautoblog_autoblog_publish_date');
            delete_post_meta($post_id, '_rmsautoblog_autoblog_publish_time');
        }
    }
}

// Initialize the scheduler
rmsautoblog_Scheduler::init();

