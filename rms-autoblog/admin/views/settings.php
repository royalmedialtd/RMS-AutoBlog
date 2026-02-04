<?php
/**
 * Settings View - Enhanced with customization options
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get saved settings
$saved_categories = get_option('rmsautoblog_custom_categories', "SEO\nDigital Marketing\nWeb Development\nMobile Development");
$brand_voice = get_option('rmsautoblog_brand_voice', '');
$content_length = get_option('rmsautoblog_content_length', 'medium');
$writing_style = get_option('rmsautoblog_writing_style', 'professional');
$target_audience = get_option('rmsautoblog_target_audience', '');
$include_examples = get_option('rmsautoblog_include_examples', '1');
$include_stats = get_option('rmsautoblog_include_stats', '1');
?>

<div class="wrap rmsautoblog-autoblog-wrap">
    <div class="rmsautoblog-header">
        <h1>
            <span class="dashicons dashicons-admin-settings"></span>
            <?php _e('RMS AutoBlog Settings', 'rms-autoblog'); ?>
        </h1>
    </div>
    
    <form method="post" action="options.php" class="rmsautoblog-settings-form">
        <?php settings_fields('rmsautoblog_settings'); ?>
        
        <!-- API Configuration -->
        <div class="rmsautoblog-settings-card">
            <h2><?php _e('API Configuration', 'rms-autoblog'); ?></h2>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="rmsautoblog_newsapi_key"><?php _e('NewsAPI Key', 'rms-autoblog'); ?></label>
                    </th>
                    <td>
                        <input type="password" 
                               id="rmsautoblog_newsapi_key" 
                               name="rmsautoblog_newsapi_key" 
                               value="<?php echo esc_attr(get_option('rmsautoblog_newsapi_key', '')); ?>" 
                               class="regular-text" />
                        <button type="button" id="rmsautoblog-toggle-newsapi" class="button">
                            <span class="dashicons dashicons-visibility"></span>
                        </button>
                        <button type="button" id="rmsautoblog-test-newsapi" class="button">
                            <?php _e('Test Connection', 'rms-autoblog'); ?>
                        </button>
                        <p class="description">
                            <?php printf(
                                __('Get your API key from %s', 'rms-autoblog'),
                                '<a href="https://newsapi.org/register" target="_blank">newsapi.org</a>'
                            ); ?>
                            <br>
                            <strong style="color: var(--rms-warning);"><?php _e('Note:', 'rms-autoblog'); ?></strong>
                            <?php _e('NewsAPI free/developer plan only works from localhost. For production servers, you need a paid plan ($449+/month) or rely on RSS feeds which work everywhere.', 'rms-autoblog'); ?>
                        </p>
                        <div id="rmsautoblog-newsapi-result" class="rmsautoblog-api-result" style="display: none;"></div>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="rmsautoblog_openai_key"><?php _e('OpenAI API Key', 'rms-autoblog'); ?></label>
                    </th>
                    <td>
                        <input type="password" 
                               id="rmsautoblog_openai_key" 
                               name="rmsautoblog_openai_key" 
                               value="<?php echo esc_attr(get_option('rmsautoblog_openai_key', '')); ?>" 
                               class="regular-text" />
                        <button type="button" id="rmsautoblog-toggle-openai" class="button">
                            <span class="dashicons dashicons-visibility"></span>
                        </button>
                        <p class="description">
                            <?php printf(
                                __('Required for AI content generation. Get your API key from %s', 'rms-autoblog'),
                                '<a href="https://platform.openai.com/api-keys" target="_blank">OpenAI</a>'
                            ); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="rmsautoblog_openai_model"><?php _e('OpenAI Model', 'rms-autoblog'); ?></label>
                    </th>
                    <td>
                        <select id="rmsautoblog_openai_model" name="rmsautoblog_openai_model" class="rmsautoblog-select">
                            <?php $current_model = get_option('rmsautoblog_openai_model', 'gpt-4o-mini'); ?>
                            <option value="gpt-4o-mini" <?php selected($current_model, 'gpt-4o-mini'); ?>>GPT-4o Mini (Fast, Affordable)</option>
                            <option value="gpt-4o" <?php selected($current_model, 'gpt-4o'); ?>>GPT-4o (Best Quality)</option>
                            <option value="gpt-3.5-turbo" <?php selected($current_model, 'gpt-3.5-turbo'); ?>>GPT-3.5 Turbo (Legacy)</option>
                        </select>
                        <p class="description"><?php _e('Select the AI model for content generation.', 'rms-autoblog'); ?></p>
                    </td>
                </tr>
            </table>
        </div>
        
        <!-- Search Categories -->
        <div class="rmsautoblog-settings-card">
            <h2><?php _e('Search Categories & Keywords', 'rms-autoblog'); ?></h2>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="rmsautoblog_custom_categories"><?php _e('Categories to Search', 'rms-autoblog'); ?></label>
                    </th>
                    <td>
                        <textarea id="rmsautoblog_custom_categories" 
                                  name="rmsautoblog_custom_categories" 
                                  rows="6" 
                                  class="large-text code"><?php echo esc_textarea($saved_categories); ?></textarea>
                        <p class="description">
                            <?php _e('Enter one category/topic per line. These will be used to search for trending content.', 'rms-autoblog'); ?>
                            <br>
                            <?php _e('Examples: SEO, Content Marketing, React Development, AI Tools, WordPress Plugins', 'rms-autoblog'); ?>
                        </p>
                    </td>
                </tr>
            </table>
        </div>
        
        <!-- RSS Feed Sources -->
        <div class="rmsautoblog-settings-card">
            <h2><?php _e('RSS Feed Sources', 'rms-autoblog'); ?> <span style="color: var(--rms-success); font-size: 14px; font-weight: normal;"><?php _e('(Recommended)', 'rms-autoblog'); ?></span></h2>
            <p class="description" style="margin-bottom: 15px;">
                <?php _e('RSS feeds are the most reliable way to fetch trending content. They work on all servers without API restrictions.', 'rms-autoblog'); ?>
            </p>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="rmsautoblog_rss_feeds"><?php _e('RSS Feed URLs', 'rms-autoblog'); ?></label>
                    </th>
                    <td>
                        <?php 
                        $default_feeds = "https://seranking.com/blog/feed/\nhttps://moz.com/blog/feed\nhttps://www.searchenginejournal.com/feed/\nhttps://searchengineland.com/feed\nhttps://blog.hubspot.com/marketing/rss.xml\nhttps://contentmarketinginstitute.com/feed/";
                        $saved_feeds = get_option('rmsautoblog_rss_feeds', $default_feeds);
                        ?>
                        <textarea id="rmsautoblog_rss_feeds" 
                                  name="rmsautoblog_rss_feeds" 
                                  rows="8" 
                                  class="large-text code"
                                  placeholder="<?php esc_attr_e('https://example.com/blog/feed/', 'rms-autoblog'); ?>"><?php echo esc_textarea($saved_feeds); ?></textarea>
                        <p class="description">
                            <?php _e('Enter one RSS feed URL per line. Most blogs have a feed at /feed/ or /rss/', 'rms-autoblog'); ?>
                            <br><br>
                            <strong><?php _e('Suggested feeds:', 'rms-autoblog'); ?></strong><br>
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
                        <label for="rmsautoblog_rss_limit"><?php _e('Articles per Feed', 'rms-autoblog'); ?></label>
                    </th>
                    <td>
                        <select id="rmsautoblog_rss_limit" name="rmsautoblog_rss_limit" class="rmsautoblog-select">
                            <?php $rss_limit = get_option('rmsautoblog_rss_limit', '5'); ?>
                            <option value="3" <?php selected($rss_limit, '3'); ?>>3 articles</option>
                            <option value="5" <?php selected($rss_limit, '5'); ?>>5 articles</option>
                            <option value="10" <?php selected($rss_limit, '10'); ?>>10 articles</option>
                            <option value="15" <?php selected($rss_limit, '15'); ?>>15 articles</option>
                        </select>
                        <p class="description"><?php _e('How many recent articles to fetch from each feed.', 'rms-autoblog'); ?></p>
                    </td>
                </tr>
            </table>
        </div>
        
        <!-- Content Generation Settings -->
        <div class="rmsautoblog-settings-card">
            <h2><?php _e('Content Generation Settings', 'rms-autoblog'); ?></h2>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="rmsautoblog_brand_voice"><?php _e('Brand Voice & Personality', 'rms-autoblog'); ?></label>
                    </th>
                    <td>
                        <textarea id="rmsautoblog_brand_voice" 
                                  name="rmsautoblog_brand_voice" 
                                  rows="4" 
                                  class="large-text"
                                  placeholder="<?php esc_attr_e('Example: We are TechNews, a tech blog that explains complex topics in simple terms. Our tone is friendly, informative, and slightly playful. We avoid jargon and always provide actionable takeaways.', 'rms-autoblog'); ?>"><?php echo esc_textarea($brand_voice); ?></textarea>
                        <p class="description">
                            <?php _e('Describe your brand voice and personality. This helps the AI match your writing style.', 'rms-autoblog'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="rmsautoblog_writing_style"><?php _e('Writing Style', 'rms-autoblog'); ?></label>
                    </th>
                    <td>
                        <select id="rmsautoblog_writing_style" name="rmsautoblog_writing_style" class="rmsautoblog-select">
                            <option value="professional" <?php selected($writing_style, 'professional'); ?>><?php _e('Professional & Authoritative', 'rms-autoblog'); ?></option>
                            <option value="conversational" <?php selected($writing_style, 'conversational'); ?>><?php _e('Conversational & Friendly', 'rms-autoblog'); ?></option>
                            <option value="educational" <?php selected($writing_style, 'educational'); ?>><?php _e('Educational & Tutorial-like', 'rms-autoblog'); ?></option>
                            <option value="news" <?php selected($writing_style, 'news'); ?>><?php _e('News & Journalism Style', 'rms-autoblog'); ?></option>
                            <option value="casual" <?php selected($writing_style, 'casual'); ?>><?php _e('Casual & Engaging', 'rms-autoblog'); ?></option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="rmsautoblog_content_length"><?php _e('Content Length', 'rms-autoblog'); ?></label>
                    </th>
                    <td>
                        <select id="rmsautoblog_content_length" name="rmsautoblog_content_length" class="rmsautoblog-select">
                            <option value="short" <?php selected($content_length, 'short'); ?>><?php _e('Short (~500 words)', 'rms-autoblog'); ?></option>
                            <option value="medium" <?php selected($content_length, 'medium'); ?>><?php _e('Medium (~1000 words)', 'rms-autoblog'); ?></option>
                            <option value="long" <?php selected($content_length, 'long'); ?>><?php _e('Long (~1500 words)', 'rms-autoblog'); ?></option>
                            <option value="comprehensive" <?php selected($content_length, 'comprehensive'); ?>><?php _e('Comprehensive (~2000+ words)', 'rms-autoblog'); ?></option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="rmsautoblog_target_audience"><?php _e('Target Audience', 'rms-autoblog'); ?></label>
                    </th>
                    <td>
                        <input type="text" 
                               id="rmsautoblog_target_audience" 
                               name="rmsautoblog_target_audience" 
                               value="<?php echo esc_attr($target_audience); ?>" 
                               class="regular-text"
                               placeholder="<?php esc_attr_e('e.g., Small business owners, Web developers, Marketing professionals', 'rms-autoblog'); ?>" />
                        <p class="description">
                            <?php _e('Describe your target audience to tailor the content appropriately.', 'rms-autoblog'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Content Features', 'rms-autoblog'); ?></th>
                    <td>
                        <fieldset>
                            <label>
                                <input type="checkbox" name="rmsautoblog_include_examples" value="1" <?php checked($include_examples, '1'); ?> />
                                <?php _e('Include practical examples and use cases', 'rms-autoblog'); ?>
                            </label><br>
                            <label>
                                <input type="checkbox" name="rmsautoblog_include_stats" value="1" <?php checked($include_stats, '1'); ?> />
                                <?php _e('Include statistics and data points when relevant', 'rms-autoblog'); ?>
                            </label><br>
                            <label>
                                <input type="checkbox" name="rmsautoblog_include_cta" value="1" <?php checked(get_option('rmsautoblog_include_cta', '1'), '1'); ?> />
                                <?php _e('Include call-to-action at the end', 'rms-autoblog'); ?>
                            </label>
                        </fieldset>
                    </td>
                </tr>
            </table>
        </div>
        
        <!-- Content Performance Analytics -->
        <div class="rmsautoblog-settings-card">
            <h2><?php _e('Content Performance Analytics', 'rms-autoblog'); ?></h2>
            <p class="description" style="margin-bottom: 15px;">
                <?php _e('Track and monitor how your autoblog content performs over time.', 'rms-autoblog'); ?>
            </p>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('Performance Tracking', 'rms-autoblog'); ?></th>
                    <td>
                        <fieldset>
                            <label>
                                <input type="checkbox" name="rmsautoblog_track_performance" value="1" <?php checked(get_option('rmsautoblog_track_performance', '1'), '1'); ?> />
                                <?php _e('Track content performance metrics', 'rms-autoblog'); ?>
                            </label><br>
                            <p class="description">
                                <?php _e('Monitor how your autoblog content performs in search rankings, traffic, and engagement.', 'rms-autoblog'); ?>
                            </p>
                        </fieldset>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Keyword Tracking', 'rms-autoblog'); ?></th>
                    <td>
                        <fieldset>
                            <label>
                                <input type="checkbox" name="rmsautoblog_track_keywords" value="1" <?php checked(get_option('rmsautoblog_track_keywords', '1'), '1'); ?> />
                                <?php _e('Track focus keyword rankings', 'rms-autoblog'); ?>
                            </label><br>
                            <p class="description">
                                <?php _e('Monitor how your posts rank for their target keywords over time (requires compatible SEO plugin).', 'rms-autoblog'); ?>
                            </p>
                        </fieldset>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('AI Content Tracking', 'rms-autoblog'); ?></th>
                    <td>
                        <fieldset>
                            <label>
                                <input type="checkbox" name="rmsautoblog_track_ai_content" value="1" <?php checked(get_option('rmsautoblog_track_ai_content', '1'), '1'); ?> />
                                <?php _e('Track AI vs. template-generated content performance', 'rms-autoblog'); ?>
                            </label><br>
                            <p class="description">
                                <?php _e('Compare the performance of AI-generated content versus template-based content.', 'rms-autoblog'); ?>
                            </p>
                        </fieldset>
                    </td>
                </tr>
            </table>
        </div>
        
        <!-- Usage Statistics -->
        <div class="rmsautoblog-settings-card">
            <h2><?php _e('Usage Statistics', 'rms-autoblog'); ?></h2>
            
            <div class="rmsautoblog-stats">
                <?php
                $posts_created = get_posts(array(
                    'post_type' => 'post',
                    'post_status' => 'any',
                    'meta_key' => '_rmsautoblog_autoblog_generated',
                    'meta_value' => true,
                    'posts_per_page' => -1
                ));
                ?>
                <div class="stat-box">
                    <span class="stat-number"><?php echo count($posts_created); ?></span>
                    <span class="stat-label"><?php _e('Posts Created', 'rms-autoblog'); ?></span>
                </div>
            </div>
        </div>
        
        <?php submit_button(__('Save Settings', 'rms-autoblog')); ?>
    </form>
</div>

<script>
jQuery(document).ready(function($) {
    // Toggle password visibility
    $('#rmsautoblog-toggle-newsapi').on('click', function() {
        var input = $('#rmsautoblog_newsapi_key');
        input.attr('type', input.attr('type') === 'password' ? 'text' : 'password');
    });
    
    $('#rmsautoblog-toggle-openai').on('click', function() {
        var input = $('#rmsautoblog_openai_key');
        input.attr('type', input.attr('type') === 'password' ? 'text' : 'password');
    });
    
    // Test NewsAPI connection
    $('#rmsautoblog-test-newsapi').on('click', function() {
        var $btn = $(this);
        var $result = $('#rmsautoblog-newsapi-result');
        
        $btn.prop('disabled', true).text('<?php _e('Testing...', 'rms-autoblog'); ?>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'rmsautoblog_test_api',
                nonce: '<?php echo wp_create_nonce('RMS_Autoblog_nonce'); ?>',
                api_key: $('#rmsautoblog_newsapi_key').val()
            },
            success: function(response) {
                if (response.success) {
                    $result.removeClass('error').addClass('success').html('<span class="dashicons dashicons-yes"></span> <?php _e('Connection successful!', 'rms-autoblog'); ?>').show();
                } else {
                    $result.removeClass('success').addClass('error').html('<span class="dashicons dashicons-no"></span> ' + response.data.message).show();
                }
            },
            error: function() {
                $result.removeClass('success').addClass('error').html('<span class="dashicons dashicons-no"></span> <?php _e('Connection failed', 'rms-autoblog'); ?>').show();
            },
            complete: function() {
                $btn.prop('disabled', false).text('<?php _e('Test Connection', 'rms-autoblog'); ?>');
            }
        });
    });
});
</script>


