<?php
/**
 * Dashboard View
 */

if (!defined('ABSPATH')) {
    exit;
}

// Add inline critical CSS to ensure styles load
add_action('admin_head', function() {
    ?>
    <style id="rmsautoblog-critical-css">
        /* Critical CSS Variables */
        :root {
            --rms-bg-primary: #0f0f23;
            --rms-bg-secondary: #1a1a2e;
            --rms-bg-card: #16213e;
            --rms-bg-card-hover: #1f3460;
            --rms-accent-primary: #667eea;
            --rms-accent-secondary: #764ba2;
            --rms-accent-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --rms-accent-glow: rgba(102, 126, 234, 0.3);
            --rms-success: #10b981;
            --rms-error: #ef4444;
            --rms-warning: #f59e0b;
            --rms-text-primary: #ffffff;
            --rms-text-secondary: #94a3b8;
            --rms-text-muted: #64748b;
            --rms-border: rgba(255, 255, 255, 0.1);
            --rms-radius-sm: 8px;
            --rms-radius-md: 12px;
            --rms-radius-lg: 16px;
            --rms-radius-xl: 24px;
            --rms-shadow-sm: 0 2px 8px rgba(0, 0, 0, 0.2);
            --rms-shadow-md: 0 4px 20px rgba(0, 0, 0, 0.3);
            --rms-shadow-lg: 0 8px 40px rgba(0, 0, 0, 0.4);
            --rms-shadow-glow: 0 0 30px var(--rms-accent-glow);
            --rms-transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        /* Main wrapper - force dark theme */
        .rmsautoblog-autoblog-wrap {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif !important;
            max-width: 100% !important;
            margin: 0 !important;
            padding: 24px !important;
            background: #0f0f23 !important;
            min-height: calc(100vh - 32px) !important;
            margin-left: -20px !important;
            margin-right: -20px !important;
            margin-top: -20px !important;
            box-sizing: border-box !important;
        }

        /* Header with gradient */
        .rmsautoblog-autoblog-wrap .rmsautoblog-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
            border-radius: 24px !important;
            padding: 48px 40px !important;
            margin-bottom: 32px !important;
            position: relative !important;
            overflow: hidden !important;
            box-shadow: 0 0 30px rgba(102, 126, 234, 0.3) !important;
        }

        .rmsautoblog-autoblog-wrap .rmsautoblog-header h1 {
            margin: 0 0 12px 0 !important;
            font-size: 32px !important;
            font-weight: 700 !important;
            color: #ffffff !important;
            display: flex !important;
            align-items: center !important;
            gap: 16px !important;
            padding: 0 !important;
        }

        .rmsautoblog-autoblog-wrap .rmsautoblog-header .description {
            margin: 0 !important;
            font-size: 16px !important;
            color: rgba(255, 255, 255, 0.85) !important;
        }

        /* Controls section */
        .rmsautoblog-autoblog-wrap .rmsautoblog-controls {
            display: flex !important;
            justify-content: space-between !important;
            align-items: center !important;
            background: #16213e !important;
            padding: 24px 28px !important;
            border-radius: 16px !important;
            margin-bottom: 32px !important;
            border: 1px solid rgba(255, 255, 255, 0.1) !important;
            flex-wrap: wrap !important;
            gap: 20px !important;
        }

        .rmsautoblog-autoblog-wrap .rmsautoblog-filters label {
            font-weight: 600 !important;
            color: #ffffff !important;
        }

        .rmsautoblog-autoblog-wrap .rmsautoblog-select {
            padding: 12px 16px !important;
            background: #1a1a2e !important;
            border: 1px solid rgba(255, 255, 255, 0.1) !important;
            border-radius: 8px !important;
            color: #ffffff !important;
            min-width: 220px !important;
        }

        /* Trends grid */
        .rmsautoblog-autoblog-wrap .rmsautoblog-trends-container {
            display: grid !important;
            grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)) !important;
            gap: 24px !important;
        }

        /* Trend cards */
        .rmsautoblog-autoblog-wrap .rmsautoblog-trend-card {
            background: #16213e !important;
            border-radius: 16px !important;
            padding: 24px !important;
            border: 1px solid rgba(255, 255, 255, 0.1) !important;
            display: flex !important;
            flex-direction: column !important;
        }

        .rmsautoblog-autoblog-wrap .rmsautoblog-trend-card:hover {
            background: #1f3460 !important;
            border-color: #667eea !important;
            transform: translateY(-4px);
        }

        .rmsautoblog-autoblog-wrap .trend-title {
            font-size: 17px !important;
            font-weight: 600 !important;
            color: #ffffff !important;
            margin: 0 0 10px 0 !important;
        }

        .rmsautoblog-autoblog-wrap .trend-description {
            font-size: 14px !important;
            color: #94a3b8 !important;
            margin: 0 0 16px 0 !important;
        }

        .rmsautoblog-autoblog-wrap .trend-meta {
            display: flex !important;
            gap: 20px !important;
            font-size: 12px !important;
            color: #64748b !important;
            margin-top: auto !important;
            padding-top: 16px !important;
            border-top: 1px solid rgba(255, 255, 255, 0.1) !important;
            margin-bottom: 20px !important;
        }

        /* Empty state */
        .rmsautoblog-autoblog-wrap .rmsautoblog-empty-state {
            grid-column: 1 / -1 !important;
            text-align: center !important;
            padding: 80px 40px !important;
            background: #16213e !important;
            border-radius: 16px !important;
            border: 2px dashed rgba(255, 255, 255, 0.1) !important;
        }

        .rmsautoblog-autoblog-wrap .rmsautoblog-empty-state h3 {
            color: #ffffff !important;
            font-size: 22px !important;
        }

        .rmsautoblog-autoblog-wrap .rmsautoblog-empty-state p {
            color: #94a3b8 !important;
        }

        /* Category badges */
        .category-badge {
            display: inline-flex !important;
            padding: 6px 14px !important;
            border-radius: 20px !important;
            font-size: 11px !important;
            font-weight: 700 !important;
            text-transform: uppercase !important;
        }

        .category-seo {
            background: rgba(59, 130, 246, 0.2) !important;
            color: #60a5fa !important;
            border: 1px solid rgba(59, 130, 246, 0.3) !important;
        }

        .category-general {
            background: rgba(100, 116, 139, 0.2) !important;
            color: #94a3b8 !important;
            border: 1px solid rgba(100, 116, 139, 0.3) !important;
        }

        .category-digital-marketing,
        .category-marketing {
            background: rgba(168, 85, 247, 0.2) !important;
            color: #c084fc !important;
            border: 1px solid rgba(168, 85, 247, 0.3) !important;
        }

        .category-web-development {
            background: rgba(34, 197, 94, 0.2) !important;
            color: #4ade80 !important;
            border: 1px solid rgba(34, 197, 94, 0.3) !important;
        }

        /* Buttons */
        .rmsautoblog-autoblog-wrap .button-hero {
            display: inline-flex !important;
            align-items: center !important;
            gap: 10px !important;
            padding: 14px 28px !important;
            font-size: 15px !important;
            font-weight: 600 !important;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
            border: none !important;
            border-radius: 12px !important;
            color: white !important;
            cursor: pointer !important;
        }

        .rmsautoblog-autoblog-wrap .button-hero:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 4px 20px rgba(102, 126, 234, 0.4) !important;
        }

        .rmsautoblog-autoblog-wrap .rmsautoblog-create-btn {
            width: 100% !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            gap: 8px !important;
            padding: 12px 20px !important;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
            border: none !important;
            border-radius: 8px !important;
            color: white !important;
            font-weight: 600 !important;
        }

        .trend-source {
            font-size: 12px !important;
            color: #64748b !important;
        }

        .trend-link {
            color: #667eea !important;
            text-decoration: none !important;
        }

        /* Actions layout */
        .rmsautoblog-actions {
            display: flex !important;
            gap: 12px !important;
            flex-wrap: wrap !important;
        }
    </style>
    <?php
}, 1);

// Get custom categories from settings
$custom_categories_raw = get_option('rmsautoblog_custom_categories', "SEO\nDigital Marketing\nWeb Development\nMobile Development");
$custom_category_lines = array_filter(array_map('trim', explode("\n", $custom_categories_raw)));

// Build categories array
$categories = array('' => __('All Categories', 'rms-autoblog'));
foreach ($custom_category_lines as $cat) {
    $slug = sanitize_title($cat);
    $categories[$slug] = $cat;
}

// Check if OpenAI is configured
$has_openai = !empty(get_option('rmsautoblog_openai_key', ''));
?>

<div class="wrap rmsautoblog-autoblog-wrap">
    <div class="rmsautoblog-header">
        <h1>
            <span class="dashicons dashicons-trending-up"></span>
            <?php _e('RMS AutoBlog', 'rms-autoblog'); ?>
        </h1>
        <p class="description"><?php _e('Discover trending topics and create blog posts with one click.', 'rms-autoblog'); ?></p>
    </div>
    
    <div class="rmsautoblog-controls">
        <div class="rmsautoblog-filters">
            <label for="rmsautoblog-category"><?php _e('Category:', 'rms-autoblog'); ?></label>
            <select id="rmsautoblog-category" class="rmsautoblog-select">
                <?php foreach ($categories as $value => $label): ?>
                    <option value="<?php echo esc_attr($value); ?>"><?php echo esc_html($label); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="rmsautoblog-actions">
            <button type="button" id="rmsautoblog-fetch-btn" class="button button-primary button-hero">
                <span class="dashicons dashicons-update"></span>
                <?php _e('Fetch Trending Topics', 'rms-autoblog'); ?>
            </button>
            <button type="button" id="rmsautoblog-custom-post-btn" class="button button-secondary button-hero">
                <span class="dashicons dashicons-edit"></span>
                <?php _e('Create Custom Post', 'rms-autoblog'); ?>
            </button>
        </div>
    </div>
    
    <div id="rmsautoblog-status" class="rmsautoblog-status" style="display: none;">
        <span class="spinner is-active"></span>
        <span class="status-text"></span>
    </div>
    
    <div id="rmsautoblog-error" class="notice notice-error" style="display: none;">
        <p></p>
    </div>
    
    <div id="rmsautoblog-success" class="notice notice-success" style="display: none;">
        <p></p>
    </div>
    
    <div id="rmsautoblog-trends-container" class="rmsautoblog-trends-container">
        <div class="rmsautoblog-empty-state">
            <span class="dashicons dashicons-search"></span>
            <h3><?php _e('No trends loaded yet', 'rms-autoblog'); ?></h3>
            <p><?php _e('Click "Fetch Trending Topics" to discover the latest trends in your niches.', 'rms-autoblog'); ?></p>
        </div>
    </div>
    
    <!-- Post Creation Modal -->
    <div id="rmsautoblog-modal" class="rmsautoblog-modal" style="display: none;">
        <div class="rmsautoblog-modal-overlay"></div>
        <div class="rmsautoblog-modal-content">
            <div class="rmsautoblog-modal-header">
                <h2><?php _e('Create Blog Post', 'rms-autoblog'); ?></h2>
                <button type="button" class="rmsautoblog-modal-close">&times;</button>
            </div>
            <div class="rmsautoblog-modal-body">
                <div class="rmsautoblog-form-group">
                    <label for="rmsautoblog-post-title"><?php _e('Post Title', 'rms-autoblog'); ?></label>
                    <input type="text" id="rmsautoblog-post-title" class="regular-text" />
                </div>
                <div class="rmsautoblog-form-group">
                    <label for="rmsautoblog-post-category"><?php _e('Category', 'rms-autoblog'); ?></label>
                    <select id="rmsautoblog-post-category" class="rmsautoblog-select">
                        <?php foreach ($categories as $value => $label): ?>
                            <?php if ($value): ?>
                                <option value="<?php echo esc_attr($value); ?>"><?php echo esc_html($label); ?></option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="rmsautoblog-form-group">
                    <label>
                        <input type="checkbox" id="rmsautoblog-use-ai" <?php echo $has_openai ? 'checked' : ''; ?> />
                        <?php _e('Use AI to generate content', 'rms-autoblog'); ?>
                        <?php if (!$has_openai): ?>
                            <em style="color: #d63638;">(<?php _e('OpenAI API key not configured', 'rms-autoblog'); ?>)</em>
                        <?php endif; ?>
                    </label>
                </div>
            </div>
            <div class="rmsautoblog-modal-footer">
                <button type="button" class="button rmsautoblog-modal-cancel"><?php _e('Cancel', 'rms-autoblog'); ?></button>
                <button type="button" id="rmsautoblog-create-post-btn" class="button button-primary">
                    <span class="dashicons dashicons-edit"></span>
                    <?php _e('Create Draft Post', 'rms-autoblog'); ?>
                </button>
            </div>
        </div>
    </div>
</div>

<script type="text/html" id="tmpl-rmsautoblog-trend-card">
    <div class="rmsautoblog-trend-card" data-topic="{{ data.title }}" data-category="{{ data.category }}">
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
                    <?php _e('Source', 'rms-autoblog'); ?>
                </a>
            <# } #>
        </div>
        <button type="button" class="button button-primary rmsautoblog-create-btn">
            <span class="dashicons dashicons-plus-alt"></span>
            <?php _e('Create Post', 'rms-autoblog'); ?>
        </button>
    </div>
</script>

<!-- Custom Post Creation Modal -->
<div id="rmsautoblog-custom-modal" class="rmsautoblog-modal" style="display: none;">
    <div class="rmsautoblog-modal-overlay"></div>
    <div class="rmsautoblog-modal-content">
        <div class="rmsautoblog-modal-header">
            <h2><?php _e('Create Custom Post', 'rms-autoblog'); ?></h2>
            <button type="button" class="rmsautoblog-modal-close" aria-label="Close">
                <span class="dashicons dashicons-no-alt"></span>
            </button>
        </div>
        
        <div class="rmsautoblog-modal-body">
            <div class="rmsautoblog-form-group">
                <label for="rmsautoblog-custom-keyword">
                    <?php _e('Keyword/Topic', 'rms-autoblog'); ?> *
                </label>
                <input type="text" 
                       id="rmsautoblog-custom-keyword" 
                       placeholder="<?php _e('e.g., WordPress SEO optimization', 'rms-autoblog'); ?>"
                       class="rmsautoblog-input" />
                <p class="description"><?php _e('Enter your main keyword or topic for the post', 'rms-autoblog'); ?></p>
            </div>
            
            <?php if ($has_openai): ?>
            <div class="rmsautoblog-form-group">
                <button type="button" id="rmsautoblog-suggest-btn" class="button button-secondary" style="width: 100%;">
                    <span class="dashicons dashicons-lightbulb"></span>
                    <?php _e('Get AI Suggestions', 'rms-autoblog'); ?>
                </button>
                <p class="description"><?php _e('Click to get AI-suggested title and structure', 'rms-autoblog'); ?></p>
            </div>
            <?php endif; ?>
            
            <div class="rmsautoblog-form-group">
                <label for="rmsautoblog-custom-title">
                    <?php _e('Post Title (Optional)', 'rms-autoblog'); ?>
                </label>
                <input type="text" 
                       id="rmsautoblog-custom-title" 
                       placeholder="<?php _e('Leave empty for AI to suggest', 'rms-autoblog'); ?>"
                       class="rmsautoblog-input" />
            </div>
            
            <div class="rmsautoblog-form-group">
                <label for="rmsautoblog-custom-structure">
                    <?php _e('Content Structure (Optional)', 'rms-autoblog'); ?>
                </label>
                <textarea id="rmsautoblog-custom-structure" 
                          rows="4" 
                          placeholder="<?php _e('e.g., Introduction, Benefits, How-to Guide, Conclusion', 'rms-autoblog'); ?>"
                          class="rmsautoblog-textarea"></textarea>
                <p class="description"><?php _e('Specify main sections (one per line), or leave empty for AI to suggest', 'rms-autoblog'); ?></p>
            </div>
            
            <div class="rmsautoblog-form-group">
                <label for="rmsautoblog-custom-category">
                    <?php _e('Category', 'rms-autoblog'); ?>
                </label>
                <select id="rmsautoblog-custom-category" class="rmsautoblog-select">
                    <?php foreach ($categories as $value => $label): ?>
                        <option value="<?php echo esc_attr($value); ?>"><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="rmsautoblog-form-group">
                <label><?php _e('Featured Image', 'rms-autoblog'); ?></label>
                <button type="button" id="rmsautoblog-generate-image-btn" class="button button-secondary" style="width: 100%; margin-bottom: 8px;">
                    <span class="dashicons dashicons-format-image"></span>
                    <?php _e('Generate AI Image', 'rms-autoblog'); ?>
                </button>
                <div id="rmsautoblog-generated-image" style="display: none; margin-top: 10px;">
                    <img src="" alt="Generated image" style="max-width: 100%; border-radius: 8px; border: 2px solid var(--rms-border);" />
                    <input type="hidden" id="rmsautoblog-image-path" value="" />
                </div>
            </div>
            
            <?php if ($has_openai): ?>
            <div class="rmsautoblog-form-group">
                <label>
                    <input type="checkbox" id="rmsautoblog-custom-use-ai" checked />
                    <?php _e('Use AI to generate content', 'rms-autoblog'); ?>
                </label>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="rmsautoblog-modal-footer">
            <button type="button" class="button rmsautoblog-modal-cancel">
                <?php _e('Cancel', 'rms-autoblog'); ?>
            </button>
            <button type="button" id="rmsautoblog-custom-create-btn" class="button button-primary">
                <span class="dashicons dashicons-yes"></span>
                <?php _e('Create Draft Post', 'rms-autoblog'); ?>
            </button>
        </div>
    </div>
</div>


