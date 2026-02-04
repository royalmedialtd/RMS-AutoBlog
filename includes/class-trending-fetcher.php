<?php
/**
 * Trending Topics Fetcher
 * Fetches trending topics from NewsAPI and Google Trends RSS
 */

if (!defined('ABSPATH')) {
    exit;
}

class rmsautoblog_Trending_Fetcher {
    
    private $newsapi_base = 'https://newsapi.org/v2/';
    private $google_trends_rss = 'https://trends.google.com/trends/trendingsearches/daily/rss';
    
    /**
     * Get category keywords from settings
     */
    private function get_category_keywords() {
        $custom_categories = get_option('rmsautoblog_custom_categories', "SEO\nDigital Marketing\nWeb Development\nMobile Development");
        $lines = array_filter(array_map('trim', explode("\n", $custom_categories)));
        
        $keywords = array();
        foreach ($lines as $line) {
            $slug = sanitize_title($line);
            // Use the category name as the primary keyword, plus variations
            $keywords[$slug] = array($line);
        }
        
        return $keywords;
    }
    
    /**
     * Fetch trends from all sources
     */
    public function fetch_trends($category = '') {
        $trends = array();
        
        // Fetch from custom RSS feeds (priority)
        $rss_trends = $this->fetch_from_custom_rss($category);
        if (!is_wp_error($rss_trends)) {
            $trends = array_merge($trends, $rss_trends);
        }
        
        // Fetch from NewsAPI
        $newsapi_trends = $this->fetch_from_newsapi($category);
        if (!is_wp_error($newsapi_trends)) {
            $trends = array_merge($trends, $newsapi_trends);
        }
        
        // Fetch from Google Trends RSS
        $google_trends = $this->fetch_from_google_trends($category);
        if (!is_wp_error($google_trends)) {
            $trends = array_merge($trends, $google_trends);
        }
        
        // Remove duplicates and sort by relevance
        $trends = $this->process_trends($trends);
        
        return $trends;
    }
    
    /**
     * Fetch from custom RSS feeds
     */
    private function fetch_from_custom_rss($category = '') {
        $feeds_raw = get_option('rmsautoblog_rss_feeds', '');
        if (empty($feeds_raw)) {
            return array();
        }
        
        $feeds = array_filter(array_map('trim', explode("\n", $feeds_raw)));
        $limit = (int) get_option('rmsautoblog_rss_limit', 5);
        $trends = array();
        
        foreach ($feeds as $feed_url) {
            if (empty($feed_url) || !filter_var($feed_url, FILTER_VALIDATE_URL)) {
                continue;
            }
            
            $feed_trends = $this->parse_rss_feed($feed_url, $limit, $category);
            if (!is_wp_error($feed_trends)) {
                $trends = array_merge($trends, $feed_trends);
            }
        }
        
        return $trends;
    }
    
    /**
     * Parse a single RSS feed
     */
    private function parse_rss_feed($url, $limit = 5, $category = '') {
        $response = wp_remote_get($url, array(
            'timeout' => 15,
            'headers' => array(
                'User-Agent' => 'TechNews-Autoblog/1.0 (WordPress Plugin)'
            )
        ));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $body = wp_remote_retrieve_body($response);
        if (empty($body)) {
            return new WP_Error('empty_feed', __('Empty RSS feed', 'rms-autoblog'));
        }
        
        // Parse XML
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($body);
        
        if ($xml === false) {
            return new WP_Error('invalid_xml', __('Invalid RSS feed format', 'rms-autoblog'));
        }
        
        $trends = array();
        $count = 0;
        
        // Get feed source name from URL
        $parsed_url = parse_url($url);
        $source_name = isset($parsed_url['host']) ? $parsed_url['host'] : 'Custom Feed';
        $source_name = str_replace('www.', '', $source_name);
        
        // Handle both RSS 2.0 and Atom feeds
        $items = array();
        if (isset($xml->channel->item)) {
            $items = $xml->channel->item;
        } elseif (isset($xml->entry)) {
            $items = $xml->entry;
        }
        
        foreach ($items as $item) {
            if ($count >= $limit) {
                break;
            }
            
            // Get title (RSS 2.0 or Atom)
            $title = isset($item->title) ? (string) $item->title : '';
            if (empty($title)) {
                continue;
            }
            
            // Get link (RSS 2.0 or Atom)
            $link = '';
            if (isset($item->link)) {
                if (is_string($item->link) || $item->link instanceof \SimpleXMLElement && count($item->link->attributes()) === 0) {
                    $link = (string) $item->link;
                } else {
                    // Atom format with href attribute
                    $link = (string) $item->link['href'];
                }
            }
            
            // Get description
            $description = '';
            if (isset($item->description)) {
                $description = strip_tags((string) $item->description);
            } elseif (isset($item->summary)) {
                $description = strip_tags((string) $item->summary);
            } elseif (isset($item->content)) {
                $description = strip_tags((string) $item->content);
            }
            $description = wp_trim_words($description, 30, '...');
            
            // Get publication date
            $pub_date = '';
            if (isset($item->pubDate)) {
                $pub_date = (string) $item->pubDate;
            } elseif (isset($item->published)) {
                $pub_date = (string) $item->published;
            } elseif (isset($item->updated)) {
                $pub_date = (string) $item->updated;
            }
            
            // Detect category from content
            $detected_category = $this->detect_category($title . ' ' . $description);
            
            // Filter by category if specified
            if (!empty($category) && $detected_category !== $category) {
                continue;
            }
            
            $trends[] = array(
                'title' => sanitize_text_field($title),
                'description' => sanitize_text_field($description),
                'source' => 'RSS Feed',
                'source_name' => $source_name,
                'url' => esc_url($link),
                'published_at' => $pub_date ? date('Y-m-d', strtotime($pub_date)) : '',
                'category' => $detected_category
            );
            
            $count++;
        }
        
        return $trends;
    }
    
    /**
     * Fetch from NewsAPI
     */
    private function fetch_from_newsapi($category = '') {
        $api_key = get_option('rmsautoblog_newsapi_key', '');

        if (empty($api_key)) {
            return new WP_Error('no_api_key', __('NewsAPI key is not configured', 'rms-autoblog'));
        }

        $keywords = $this->get_keywords_for_category($category);
        $query = implode(' OR ', array_slice($keywords, 0, 5));

        $url = add_query_arg(array(
            'q' => urlencode($query),
            'language' => 'en',
            'sortBy' => 'publishedAt',
            'pageSize' => 20,
            'apiKey' => $api_key
        ), $this->newsapi_base . 'everything');

        $response = wp_remote_get($url, array(
            'timeout' => 15,
            'headers' => array(
                'User-Agent' => 'rms-autoblog/2.0.0 (WordPress Plugin)'
            )
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        // Handle various NewsAPI error responses
        if ($code === 426) {
            // This error occurs when using free tier from non-localhost
            return new WP_Error(
                'newsapi_upgrade_required',
                __('NewsAPI free plan only works from localhost. Upgrade to a paid plan for production use, or rely on RSS feeds.', 'rms-autoblog')
            );
        }

        if ($code === 401) {
            return new WP_Error(
                'newsapi_unauthorized',
                __('NewsAPI key is invalid or expired. Please check your API key in Settings.', 'rms-autoblog')
            );
        }

        if ($code === 429) {
            return new WP_Error(
                'newsapi_rate_limit',
                __('NewsAPI rate limit exceeded. Please wait before trying again.', 'rms-autoblog')
            );
        }

        if ($code !== 200) {
            $error_message = isset($data['message']) ? $data['message'] : __('NewsAPI request failed', 'rms-autoblog');
            return new WP_Error('newsapi_error', $error_message . ' (HTTP ' . $code . ')');
        }

        if (isset($data['status']) && $data['status'] === 'error') {
            $error_message = isset($data['message']) ? $data['message'] : __('NewsAPI returned an error', 'rms-autoblog');
            return new WP_Error('newsapi_error', $error_message);
        }

        if (empty($data['articles'])) {
            return array();
        }

        $trends = array();
        foreach ($data['articles'] as $article) {
            // Skip articles with "[Removed]" title (NewsAPI placeholder for deleted content)
            if (isset($article['title']) && $article['title'] === '[Removed]') {
                continue;
            }

            $trends[] = array(
                'title' => sanitize_text_field($article['title']),
                'description' => sanitize_text_field($article['description'] ?? ''),
                'source' => 'NewsAPI',
                'source_name' => sanitize_text_field($article['source']['name'] ?? ''),
                'url' => esc_url($article['url'] ?? ''),
                'published_at' => sanitize_text_field($article['publishedAt'] ?? ''),
                'category' => $category ?: $this->detect_category($article['title'] . ' ' . ($article['description'] ?? ''))
            );
        }

        return $trends;
    }
    
    /**
     * Fetch from Google Trends RSS
     */
    private function fetch_from_google_trends($category = '') {
        $response = wp_remote_get($this->google_trends_rss . '?geo=US', array(
            'timeout' => 15,
            'headers' => array(
                'User-Agent' => 'TechNews-Autoblog/1.0 (WordPress Plugin)'
            )
        ));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $body = wp_remote_retrieve_body($response);
        
        // Parse RSS
        $xml = simplexml_load_string($body);
        if ($xml === false) {
            return new WP_Error('parse_error', __('Failed to parse Google Trends RSS', 'rms-autoblog'));
        }
        
        $trends = array();
        $keywords = $this->get_keywords_for_category($category);
        
        foreach ($xml->channel->item as $item) {
            $title = (string) $item->title;
            
            // Filter by category keywords if specified
            if (!empty($category)) {
                $matches = false;
                foreach ($keywords as $keyword) {
                    if (stripos($title, $keyword) !== false) {
                        $matches = true;
                        break;
                    }
                }
                if (!$matches) {
                    continue;
                }
            }
            
            $trends[] = array(
                'title' => sanitize_text_field($title),
                'description' => sanitize_text_field((string) ($item->description ?? '')),
                'source' => 'Google Trends',
                'source_name' => 'Google Trends',
                'url' => esc_url((string) ($item->link ?? '')),
                'published_at' => sanitize_text_field((string) ($item->pubDate ?? '')),
                'category' => $category ?: $this->detect_category($title)
            );
        }
        
        return $trends;
    }
    
    /**
     * Get keywords for a specific category
     */
    private function get_keywords_for_category($category) {
        $category_keywords = $this->get_category_keywords();
        
        if (empty($category) || !isset($category_keywords[$category])) {
            // Return all keywords
            $all_keywords = array();
            foreach ($category_keywords as $cat_keywords) {
                $all_keywords = array_merge($all_keywords, $cat_keywords);
            }
            return array_unique($all_keywords);
        }
        
        return $category_keywords[$category];
    }
    
    /**
     * Detect category from text
     */
    private function detect_category($text) {
        $text = strtolower($text);
        $category_keywords = $this->get_category_keywords();
        
        foreach ($category_keywords as $category => $keywords) {
            foreach ($keywords as $keyword) {
                if (stripos($text, strtolower($keyword)) !== false) {
                    return $category;
                }
            }
        }
        
        return 'general';
    }
    
    /**
     * Process and deduplicate trends
     */
    private function process_trends($trends) {
        // Remove duplicates based on title similarity
        $unique = array();
        $seen_titles = array();
        
        foreach ($trends as $trend) {
            $normalized = strtolower(preg_replace('/[^a-z0-9]/', '', $trend['title']));
            
            $is_duplicate = false;
            foreach ($seen_titles as $seen) {
                if (similar_text($normalized, $seen) > (strlen($normalized) * 0.7)) {
                    $is_duplicate = true;
                    break;
                }
            }
            
            if (!$is_duplicate) {
                $seen_titles[] = $normalized;
                $unique[] = $trend;
            }
        }
        
        // Sort by date (newest first)
        usort($unique, function($a, $b) {
            return strtotime($b['published_at'] ?? 0) - strtotime($a['published_at'] ?? 0);
        });
        
        return array_slice($unique, 0, 50);
    }
    
    /**
     * Test API connection
     */
    public function test_connection() {
        $api_key = get_option('rmsautoblog_newsapi_key', '');

        if (empty($api_key)) {
            return new WP_Error('no_api_key', __('API key not configured', 'rms-autoblog'));
        }

        $url = add_query_arg(array(
            'q' => 'technology',
            'pageSize' => 1,
            'apiKey' => $api_key
        ), $this->newsapi_base . 'everything');

        $response = wp_remote_get($url, array(
            'timeout' => 10,
            'headers' => array(
                'User-Agent' => 'rms-autoblog/2.0.0 (WordPress Plugin)'
            )
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($code === 426) {
            return new WP_Error(
                'upgrade_required',
                __('NewsAPI free plan only works from localhost. Your API key is valid, but you need a paid plan for production servers. RSS feeds will still work.', 'rms-autoblog')
            );
        }

        if ($code === 401) {
            return new WP_Error('unauthorized', __('Invalid API key. Please check your NewsAPI key.', 'rms-autoblog'));
        }

        if ($code === 429) {
            return new WP_Error('rate_limit', __('Rate limit exceeded. Please wait before testing again.', 'rms-autoblog'));
        }

        if ($code !== 200) {
            return new WP_Error('api_error', $body['message'] ?? __('API request failed (HTTP ' . $code . ')', 'rms-autoblog'));
        }

        return true;
    }
}

