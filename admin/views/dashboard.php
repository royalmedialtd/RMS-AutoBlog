<?php
/**
 * Dashboard View
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get custom categories from settings
$custom_categories_raw = get_option('technews_custom_categories', "SEO\nDigital Marketing\nWeb Development\nMobile Development");
$custom_category_lines = array_filter(array_map('trim', explode("\n", $custom_categories_raw)));

// Build categories array
$categories = array('' => __('All Categories', 'technews-autoblog'));
foreach ($custom_category_lines as $cat) {
    $slug = sanitize_title($cat);
    $categories[$slug] = $cat;
}

// Check if OpenAI is configured
$has_openai = !empty(get_option('technews_openai_key', ''));
?>

<div class="wrap technews-autoblog-wrap">
    <div class="technews-header">
        <h1>
            <span class="dashicons dashicons-trending-up"></span>
            <?php _e('TechNews Autoblog', 'technews-autoblog'); ?>
        </h1>
        <p class="description"><?php _e('Discover trending topics and create blog posts with one click.', 'technews-autoblog'); ?></p>
    </div>
    
    <div class="technews-controls">
        <div class="technews-filters">
            <label for="technews-category"><?php _e('Category:', 'technews-autoblog'); ?></label>
            <select id="technews-category" class="technews-select">
                <?php foreach ($categories as $value => $label): ?>
                    <option value="<?php echo esc_attr($value); ?>"><?php echo esc_html($label); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="technews-actions">
            <button type="button" id="technews-fetch-btn" class="button button-primary button-hero">
                <span class="dashicons dashicons-update"></span>
                <?php _e('Fetch Trending Topics', 'technews-autoblog'); ?>
            </button>
        </div>
    </div>
    
    <div id="technews-status" class="technews-status" style="display: none;">
        <span class="spinner is-active"></span>
        <span class="status-text"></span>
    </div>
    
    <div id="technews-error" class="notice notice-error" style="display: none;">
        <p></p>
    </div>
    
    <div id="technews-success" class="notice notice-success" style="display: none;">
        <p></p>
    </div>
    
    <div id="technews-trends-container" class="technews-trends-container">
        <div class="technews-empty-state">
            <span class="dashicons dashicons-search"></span>
            <h3><?php _e('No trends loaded yet', 'technews-autoblog'); ?></h3>
            <p><?php _e('Click "Fetch Trending Topics" to discover the latest trends in your niches.', 'technews-autoblog'); ?></p>
        </div>
    </div>
    
    <!-- Post Creation Modal -->
    <div id="technews-modal" class="technews-modal" style="display: none;">
        <div class="technews-modal-overlay"></div>
        <div class="technews-modal-content">
            <div class="technews-modal-header">
                <h2><?php _e('Create Blog Post', 'technews-autoblog'); ?></h2>
                <button type="button" class="technews-modal-close">&times;</button>
            </div>
            <div class="technews-modal-body">
                <div class="technews-form-group">
                    <label for="technews-post-title"><?php _e('Post Title', 'technews-autoblog'); ?></label>
                    <input type="text" id="technews-post-title" class="regular-text" />
                </div>
                <div class="technews-form-group">
                    <label for="technews-post-category"><?php _e('Category', 'technews-autoblog'); ?></label>
                    <select id="technews-post-category" class="technews-select">
                        <?php foreach ($categories as $value => $label): ?>
                            <?php if ($value): ?>
                                <option value="<?php echo esc_attr($value); ?>"><?php echo esc_html($label); ?></option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="technews-form-group">
                    <label>
                        <input type="checkbox" id="technews-use-ai" <?php echo $has_openai ? 'checked' : ''; ?> />
                        <?php _e('Use AI to generate content', 'technews-autoblog'); ?>
                        <?php if (!$has_openai): ?>
                            <em style="color: #d63638;">(<?php _e('OpenAI API key not configured', 'technews-autoblog'); ?>)</em>
                        <?php endif; ?>
                    </label>
                </div>
            </div>
            <div class="technews-modal-footer">
                <button type="button" class="button technews-modal-cancel"><?php _e('Cancel', 'technews-autoblog'); ?></button>
                <button type="button" id="technews-create-post-btn" class="button button-primary">
                    <span class="dashicons dashicons-edit"></span>
                    <?php _e('Create Draft Post', 'technews-autoblog'); ?>
                </button>
            </div>
        </div>
    </div>
</div>

<script type="text/html" id="tmpl-technews-trend-card">
    <div class="technews-trend-card" data-topic="{{ data.title }}" data-category="{{ data.category }}">
        <div class="trend-category">
            <span class="category-badge category-{{ data.category }}">{{ data.category }}</span>
            <span class="trend-source">{{ data.source_name }}</span>
        </div>
        <h3 class="trend-title">{{ data.title }}</h3>
        <# if (data.description) { #>
            <p class="trend-description">{{ data.description }}</p>
        <# } #>
        <div class="trend-meta">
            <# if (data.published_at) { #>
                <span class="trend-date">
                    <span class="dashicons dashicons-calendar-alt"></span>
                    {{ data.published_at }}
                </span>
            <# } #>
            <# if (data.url) { #>
                <a href="{{ data.url }}" target="_blank" class="trend-link">
                    <span class="dashicons dashicons-external"></span>
                    <?php _e('Source', 'technews-autoblog'); ?>
                </a>
            <# } #>
        </div>
        <button type="button" class="button button-primary technews-create-btn">
            <span class="dashicons dashicons-plus-alt"></span>
            <?php _e('Create Post', 'technews-autoblog'); ?>
        </button>
    </div>
</script>
