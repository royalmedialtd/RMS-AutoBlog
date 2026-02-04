<?php
/**
 * Settings View - Enhanced with customization options
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get saved settings
$saved_categories = get_option('technews_custom_categories', "SEO\nDigital Marketing\nWeb Development\nMobile Development");
$brand_voice = get_option('technews_brand_voice', '');
$content_length = get_option('technews_content_length', 'medium');
$writing_style = get_option('technews_writing_style', 'professional');
$target_audience = get_option('technews_target_audience', '');
$include_examples = get_option('technews_include_examples', '1');
$include_stats = get_option('technews_include_stats', '1');
?>

<div class="wrap technews-autoblog-wrap">
    <div class="technews-header">
        <h1>
            <span class="dashicons dashicons-admin-settings"></span>
            <?php _e('TechNews Autoblog Settings', 'technews-autoblog'); ?>
        </h1>
    </div>
    
    <form method="post" action="options.php" class="technews-settings-form">
        <?php settings_fields('technews_autoblog_settings'); ?>
        
        <!-- API Configuration -->
        <div class="technews-settings-card">
            <h2><?php _e('API Configuration', 'technews-autoblog'); ?></h2>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="technews_newsapi_key"><?php _e('NewsAPI Key', 'technews-autoblog'); ?></label>
                    </th>
                    <td>
                        <input type="password" 
                               id="technews_newsapi_key" 
                               name="technews_newsapi_key" 
                               value="<?php echo esc_attr(get_option('technews_newsapi_key', '')); ?>" 
                               class="regular-text" />
                        <button type="button" id="technews-toggle-newsapi" class="button">
                            <span class="dashicons dashicons-visibility"></span>
                        </button>
                        <button type="button" id="technews-test-newsapi" class="button">
                            <?php _e('Test Connection', 'technews-autoblog'); ?>
                        </button>
                        <p class="description">
                            <?php printf(
                                __('Get your free API key from %s (100 requests/day free)', 'technews-autoblog'),
                                '<a href="https://newsapi.org/register" target="_blank">newsapi.org</a>'
                            ); ?>
                        </p>
                        <div id="technews-newsapi-result" class="technews-api-result" style="display: none;"></div>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="technews_openai_key"><?php _e('OpenAI API Key', 'technews-autoblog'); ?></label>
                    </th>
                    <td>
                        <input type="password" 
                               id="technews_openai_key" 
                               name="technews_openai_key" 
                               value="<?php echo esc_attr(get_option('technews_openai_key', '')); ?>" 
                               class="regular-text" />
                        <button type="button" id="technews-toggle-openai" class="button">
                            <span class="dashicons dashicons-visibility"></span>
                        </button>
                        <p class="description">
                            <?php printf(
                                __('Required for AI content generation. Get your API key from %s', 'technews-autoblog'),
                                '<a href="https://platform.openai.com/api-keys" target="_blank">OpenAI</a>'
                            ); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="technews_openai_model"><?php _e('OpenAI Model', 'technews-autoblog'); ?></label>
                    </th>
                    <td>
                        <select id="technews_openai_model" name="technews_openai_model" class="technews-select">
                            <?php $current_model = get_option('technews_openai_model', 'gpt-4o-mini'); ?>
                            <option value="gpt-4o-mini" <?php selected($current_model, 'gpt-4o-mini'); ?>>GPT-4o Mini (Fast, Affordable)</option>
                            <option value="gpt-4o" <?php selected($current_model, 'gpt-4o'); ?>>GPT-4o (Best Quality)</option>
                            <option value="gpt-3.5-turbo" <?php selected($current_model, 'gpt-3.5-turbo'); ?>>GPT-3.5 Turbo (Legacy)</option>
                        </select>
                        <p class="description"><?php _e('Select the AI model for content generation.', 'technews-autoblog'); ?></p>
                    </td>
                </tr>
            </table>
        </div>
        
        <!-- Search Categories -->
        <div class="technews-settings-card">
            <h2><?php _e('Search Categories & Keywords', 'technews-autoblog'); ?></h2>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="technews_custom_categories"><?php _e('Categories to Search', 'technews-autoblog'); ?></label>
                    </th>
                    <td>
                        <textarea id="technews_custom_categories" 
                                  name="technews_custom_categories" 
                                  rows="6" 
                                  class="large-text code"><?php echo esc_textarea($saved_categories); ?></textarea>
                        <p class="description">
                            <?php _e('Enter one category/topic per line. These will be used to search for trending content.', 'technews-autoblog'); ?>
                            <br>
                            <?php _e('Examples: SEO, Content Marketing, React Development, AI Tools, WordPress Plugins', 'technews-autoblog'); ?>
                        </p>
                    </td>
                </tr>
            </table>
        </div>
        
        <!-- RSS Feed Sources -->
        <div class="technews-settings-card">
            <h2><?php _e('RSS Feed Sources', 'technews-autoblog'); ?></h2>
            <p class="description" style="margin-bottom: 15px;">
                <?php _e('Add your favorite tech blogs and news sites. The plugin will fetch recent articles from these feeds.', 'technews-autoblog'); ?>
            </p>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="technews_rss_feeds"><?php _e('RSS Feed URLs', 'technews-autoblog'); ?></label>
                    </th>
                    <td>
                        <?php 
                        $default_feeds = "https://seranking.com/blog/feed/\nhttps://moz.com/blog/feed\nhttps://www.searchenginejournal.com/feed/\nhttps://searchengineland.com/feed\nhttps://blog.hubspot.com/marketing/rss.xml\nhttps://contentmarketinginstitute.com/feed/";
                        $saved_feeds = get_option('technews_rss_feeds', $default_feeds);
                        ?>
                        <textarea id="technews_rss_feeds" 
                                  name="technews_rss_feeds" 
                                  rows="8" 
                                  class="large-text code"
                                  placeholder="<?php esc_attr_e('https://example.com/blog/feed/', 'technews-autoblog'); ?>"><?php echo esc_textarea($saved_feeds); ?></textarea>
                        <p class="description">
                            <?php _e('Enter one RSS feed URL per line. Most blogs have a feed at /feed/ or /rss/', 'technews-autoblog'); ?>
                            <br><br>
                            <strong><?php _e('Suggested feeds:', 'technews-autoblog'); ?></strong><br>
                            <code>https://seranking.com/blog/feed/</code> - SE Ranking Blog<br>
                            <code>https://moz.com/blog/feed</code> - Moz Blog<br>
                            <code>https://www.searchenginejournal.com/feed/</code> - Search Engine Journal<br>
                            <code>https://searchengineland.com/feed</code> - Search Engine Land<br>
                            <code>https://blog.hubspot.com/marketing/rss.xml</code> - HubSpot Marketing<br>
                            <code>https://css-tricks.com/feed/</code> - CSS-Tricks<br>
                            <code>https://smashingmagazine.com/feed/</code> - Smashing Magazine
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="technews_rss_limit"><?php _e('Articles per Feed', 'technews-autoblog'); ?></label>
                    </th>
                    <td>
                        <select id="technews_rss_limit" name="technews_rss_limit" class="technews-select">
                            <?php $rss_limit = get_option('technews_rss_limit', '5'); ?>
                            <option value="3" <?php selected($rss_limit, '3'); ?>>3 articles</option>
                            <option value="5" <?php selected($rss_limit, '5'); ?>>5 articles</option>
                            <option value="10" <?php selected($rss_limit, '10'); ?>>10 articles</option>
                            <option value="15" <?php selected($rss_limit, '15'); ?>>15 articles</option>
                        </select>
                        <p class="description"><?php _e('How many recent articles to fetch from each feed.', 'technews-autoblog'); ?></p>
                    </td>
                </tr>
            </table>
        </div>
        
        <!-- Content Generation Settings -->
        <div class="technews-settings-card">
            <h2><?php _e('Content Generation Settings', 'technews-autoblog'); ?></h2>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="technews_brand_voice"><?php _e('Brand Voice & Personality', 'technews-autoblog'); ?></label>
                    </th>
                    <td>
                        <textarea id="technews_brand_voice" 
                                  name="technews_brand_voice" 
                                  rows="4" 
                                  class="large-text"
                                  placeholder="<?php esc_attr_e('Example: We are TechNews, a tech blog that explains complex topics in simple terms. Our tone is friendly, informative, and slightly playful. We avoid jargon and always provide actionable takeaways.', 'technews-autoblog'); ?>"><?php echo esc_textarea($brand_voice); ?></textarea>
                        <p class="description">
                            <?php _e('Describe your brand voice and personality. This helps the AI match your writing style.', 'technews-autoblog'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="technews_writing_style"><?php _e('Writing Style', 'technews-autoblog'); ?></label>
                    </th>
                    <td>
                        <select id="technews_writing_style" name="technews_writing_style" class="technews-select">
                            <option value="professional" <?php selected($writing_style, 'professional'); ?>><?php _e('Professional & Authoritative', 'technews-autoblog'); ?></option>
                            <option value="conversational" <?php selected($writing_style, 'conversational'); ?>><?php _e('Conversational & Friendly', 'technews-autoblog'); ?></option>
                            <option value="educational" <?php selected($writing_style, 'educational'); ?>><?php _e('Educational & Tutorial-like', 'technews-autoblog'); ?></option>
                            <option value="news" <?php selected($writing_style, 'news'); ?>><?php _e('News & Journalism Style', 'technews-autoblog'); ?></option>
                            <option value="casual" <?php selected($writing_style, 'casual'); ?>><?php _e('Casual & Engaging', 'technews-autoblog'); ?></option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="technews_content_length"><?php _e('Content Length', 'technews-autoblog'); ?></label>
                    </th>
                    <td>
                        <select id="technews_content_length" name="technews_content_length" class="technews-select">
                            <option value="short" <?php selected($content_length, 'short'); ?>><?php _e('Short (~500 words)', 'technews-autoblog'); ?></option>
                            <option value="medium" <?php selected($content_length, 'medium'); ?>><?php _e('Medium (~1000 words)', 'technews-autoblog'); ?></option>
                            <option value="long" <?php selected($content_length, 'long'); ?>><?php _e('Long (~1500 words)', 'technews-autoblog'); ?></option>
                            <option value="comprehensive" <?php selected($content_length, 'comprehensive'); ?>><?php _e('Comprehensive (~2000+ words)', 'technews-autoblog'); ?></option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="technews_target_audience"><?php _e('Target Audience', 'technews-autoblog'); ?></label>
                    </th>
                    <td>
                        <input type="text" 
                               id="technews_target_audience" 
                               name="technews_target_audience" 
                               value="<?php echo esc_attr($target_audience); ?>" 
                               class="regular-text"
                               placeholder="<?php esc_attr_e('e.g., Small business owners, Web developers, Marketing professionals', 'technews-autoblog'); ?>" />
                        <p class="description">
                            <?php _e('Describe your target audience to tailor the content appropriately.', 'technews-autoblog'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Content Features', 'technews-autoblog'); ?></th>
                    <td>
                        <fieldset>
                            <label>
                                <input type="checkbox" name="technews_include_examples" value="1" <?php checked($include_examples, '1'); ?> />
                                <?php _e('Include practical examples and use cases', 'technews-autoblog'); ?>
                            </label><br>
                            <label>
                                <input type="checkbox" name="technews_include_stats" value="1" <?php checked($include_stats, '1'); ?> />
                                <?php _e('Include statistics and data points when relevant', 'technews-autoblog'); ?>
                            </label><br>
                            <label>
                                <input type="checkbox" name="technews_include_cta" value="1" <?php checked(get_option('technews_include_cta', '1'), '1'); ?> />
                                <?php _e('Include call-to-action at the end', 'technews-autoblog'); ?>
                            </label>
                        </fieldset>
                    </td>
                </tr>
            </table>
        </div>
        
        <!-- Usage Statistics -->
        <div class="technews-settings-card">
            <h2><?php _e('Usage Statistics', 'technews-autoblog'); ?></h2>
            
            <div class="technews-stats">
                <?php
                $posts_created = get_posts(array(
                    'post_type' => 'post',
                    'post_status' => 'any',
                    'meta_key' => '_technews_autoblog_generated',
                    'meta_value' => true,
                    'posts_per_page' => -1
                ));
                ?>
                <div class="stat-box">
                    <span class="stat-number"><?php echo count($posts_created); ?></span>
                    <span class="stat-label"><?php _e('Posts Created', 'technews-autoblog'); ?></span>
                </div>
            </div>
        </div>
        
        <?php submit_button(__('Save Settings', 'technews-autoblog')); ?>
    </form>
</div>

<script>
jQuery(document).ready(function($) {
    // Toggle password visibility
    $('#technews-toggle-newsapi').on('click', function() {
        var input = $('#technews_newsapi_key');
        input.attr('type', input.attr('type') === 'password' ? 'text' : 'password');
    });
    
    $('#technews-toggle-openai').on('click', function() {
        var input = $('#technews_openai_key');
        input.attr('type', input.attr('type') === 'password' ? 'text' : 'password');
    });
    
    // Test NewsAPI connection
    $('#technews-test-newsapi').on('click', function() {
        var $btn = $(this);
        var $result = $('#technews-newsapi-result');
        
        $btn.prop('disabled', true).text('<?php _e('Testing...', 'technews-autoblog'); ?>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'technews_test_api',
                nonce: '<?php echo wp_create_nonce('technews_autoblog_nonce'); ?>',
                api_key: $('#technews_newsapi_key').val()
            },
            success: function(response) {
                if (response.success) {
                    $result.removeClass('error').addClass('success').html('<span class="dashicons dashicons-yes"></span> <?php _e('Connection successful!', 'technews-autoblog'); ?>').show();
                } else {
                    $result.removeClass('success').addClass('error').html('<span class="dashicons dashicons-no"></span> ' + response.data.message).show();
                }
            },
            error: function() {
                $result.removeClass('success').addClass('error').html('<span class="dashicons dashicons-no"></span> <?php _e('Connection failed', 'technews-autoblog'); ?>').show();
            },
            complete: function() {
                $btn.prop('disabled', false).text('<?php _e('Test Connection', 'technews-autoblog'); ?>');
            }
        });
    });
});
</script>
