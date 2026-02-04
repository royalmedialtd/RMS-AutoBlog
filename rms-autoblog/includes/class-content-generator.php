<?php
/**
 * Content Generator - Enhanced with OpenAI integration
 * Generates blog post content using AI with customizable settings
 */

if (!defined('ABSPATH')) {
    exit;
}

class rmsautoblog_Content_Generator {
    
    private $length_tokens = array(
        'short' => 1000,
        'medium' => 2000,
        'long' => 3000,
        'comprehensive' => 4000
    );
    
    private $style_prompts = array(
        'professional' => 'Write in a professional, authoritative tone. Use industry terminology appropriately and maintain a formal but accessible voice.',
        'conversational' => 'Write in a friendly, conversational tone as if talking to a colleague. Use "you" to address the reader directly.',
        'educational' => 'Write in an educational, tutorial-like style. Break down complex concepts into simple steps and explain everything clearly.',
        'news' => 'Write in a journalistic news style. Lead with the most important information, use active voice, and be factual and objective.',
        'casual' => 'Write in a casual, engaging tone. Be relatable, use humor where appropriate, and keep the reader entertained.'
    );
    
    /**
     * Generate content for a topic
     */
    public function generate($topic, $category = 'general', $use_ai = true, $custom_structure = '') {
        // Check if OpenAI is configured and AI is requested
        $openai_key = get_option('rmsautoblog_openai_key', '');
        
        if ($use_ai && !empty($openai_key)) {
            $ai_content = $this->generate_with_ai($topic, $category, $custom_structure);
            if (!is_wp_error($ai_content) && !empty($ai_content['content'])) {
                return $ai_content;
            }
            // If AI failed, return the error instead of falling back silently
            if (is_wp_error($ai_content)) {
                return $ai_content;
            }
        }
        
        // Fallback to template-based generation
        return $this->generate_template($topic, $category, $custom_structure);
    }
    
    /**
     * Generate content using OpenAI
     */
    private function generate_with_ai($topic, $category) {
        $api_key = get_option('rmsautoblog_openai_key', '');
        $model = get_option('rmsautoblog_openai_model', 'gpt-4o-mini');
        
        // Get settings
        $brand_voice = get_option('rmsautoblog_brand_voice', '');
        $writing_style = get_option('rmsautoblog_writing_style', 'professional');
        $content_length = get_option('rmsautoblog_content_length', 'medium');
        $target_audience = get_option('rmsautoblog_target_audience', '');
        $include_examples = get_option('rmsautoblog_include_examples', '1');
        $include_stats = get_option('rmsautoblog_include_stats', '1');
        $include_cta = get_option('rmsautoblog_include_cta', '1');
        
        // Build the system prompt
        $system_prompt = $this->build_system_prompt($brand_voice, $writing_style, $target_audience);
        
        // Build the user prompt
        $user_prompt = $this->build_user_prompt($topic, $category, $content_length, $include_examples, $include_stats, $include_cta);
        
        // Get max tokens based on length
        $max_tokens = $this->length_tokens[$content_length] ?? 2000;
        
        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', array(
            'timeout' => 120,
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode(array(
                'model' => $model,
                'messages' => array(
                    array(
                        'role' => 'system',
                        'content' => $system_prompt
                    ),
                    array(
                        'role' => 'user',
                        'content' => $user_prompt
                    )
                ),
                'max_tokens' => $max_tokens,
                'temperature' => 0.7
            ))
        ));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if ($code !== 200) {
            $error_message = $body['error']['message'] ?? __('OpenAI API request failed', 'rms-autoblog');
            return new WP_Error('openai_error', $error_message);
        }
        
        if (empty($body['choices'][0]['message']['content'])) {
            return new WP_Error('openai_error', __('No content generated', 'rms-autoblog'));
        }
        
        $generated_content = $body['choices'][0]['message']['content'];
        
        return $this->parse_ai_content($generated_content, $topic, $category);
    }
    
    /**
     * Build system prompt based on settings
     */
    private function build_system_prompt($brand_voice, $writing_style, $target_audience) {
        $style_instruction = $this->style_prompts[$writing_style] ?? $this->style_prompts['professional'];
        
        $prompt = "You are an expert content writer for a technology blog. Your task is to write high-quality, SEO-optimized blog posts.\n\n";
        
        $prompt .= "WRITING STYLE:\n$style_instruction\n\n";
        
        if (!empty($brand_voice)) {
            $prompt .= "BRAND VOICE & PERSONALITY:\n$brand_voice\n\n";
        }
        
        if (!empty($target_audience)) {
            $prompt .= "TARGET AUDIENCE:\n$target_audience\n\n";
        }
        
        $prompt .= "FORMATTING REQUIREMENTS:\n";
        $prompt .= "- Use markdown formatting\n";
        $prompt .= "- Start with a compelling introduction (no heading needed)\n";
        $prompt .= "- Use ## for main section headings\n";
        $prompt .= "- Use ### for subsections if needed\n";
        $prompt .= "- Use bullet points and numbered lists where appropriate\n";
        $prompt .= "- Make content scannable with short paragraphs\n";
        $prompt .= "- Include a conclusion section\n";
        
        return $prompt;
    }
    
    /**
     * Build user prompt for content generation
     */
    private function build_user_prompt($topic, $category, $content_length, $include_examples, $include_stats, $include_cta) {
        $length_words = array(
            'short' => '500-700',
            'medium' => '1000-1200',
            'long' => '1500-1800',
            'comprehensive' => '2000-2500'
        );
        
        $word_count = $length_words[$content_length] ?? '1000-1200';
        
        $prompt = "Write a comprehensive blog post about:\n\n";
        $prompt .= "**Topic:** $topic\n";
        $prompt .= "**Category:** $category\n";
        $prompt .= "**Target Length:** $word_count words\n\n";
        
        $prompt .= "CONTENT REQUIREMENTS:\n";
        $prompt .= "1. Create an engaging, SEO-friendly title (start with: # Title)\n";
        $prompt .= "2. Write a compelling introduction that hooks the reader\n";
        $prompt .= "3. Cover the topic thoroughly with well-organized sections\n";
        $prompt .= "4. Provide actionable insights and takeaways\n";
        
        if ($include_examples === '1') {
            $prompt .= "5. Include practical examples and real-world use cases\n";
        }
        
        if ($include_stats === '1') {
            $prompt .= "6. Include relevant statistics, data, or research when applicable (note: use general industry knowledge)\n";
        }
        
        if ($include_cta === '1') {
            $prompt .= "7. End with a call-to-action that encourages engagement\n";
        }
        
        $prompt .= "\nIMPORTANT: Write the FULL article content. Do not use placeholders like [Write content here]. Generate complete, publication-ready content.";
        
        return $prompt;
    }
    
    /**
     * Parse AI-generated content
     */
    private function parse_ai_content($content, $topic, $category) {
        // Extract title if present
        $title = '';
        if (preg_match('/^#\s+(.+)$/m', $content, $matches)) {
            $title = trim($matches[1]);
            // Remove the title from content
            $content = preg_replace('/^#\s+.+\n*/m', '', $content, 1);
        } else {
            $title = $this->generate_title($topic);
        }
        
        // Generate meta description from intro
        $meta_description = $this->extract_meta_description($content);
        
        // Generate keywords
        $keywords = $this->generate_keywords($topic, $category);
        
        return array(
            'title' => $title,
            'content' => trim($content),
            'meta_description' => $meta_description,
            'keywords' => $keywords,
            'category' => $category,
            'ai_generated' => true
        );
    }
    
    /**
     * Extract meta description from content
     */
    private function extract_meta_description($content) {
        // Get first paragraph
        $paragraphs = preg_split('/\n\n+/', $content);
        foreach ($paragraphs as $para) {
            // Skip headings
            if (strpos(trim($para), '#') === 0) {
                continue;
            }
            $para = strip_tags($para);
            $para = preg_replace('/\s+/', ' ', trim($para));
            if (strlen($para) > 50) {
                return substr($para, 0, 155) . '...';
            }
        }
        return '';
    }
    
    /**
     * Generate SEO-optimized title
     */
    private function generate_title($topic) {
        $formats = array(
            '%s: Complete Guide for %d',
            'How to Master %s in %d',
            'The Ultimate Guide to %s',
            '%s: Everything You Need to Know',
            '%s Best Practices and Tips for %d',
        );
        
        $format = $formats[array_rand($formats)];
        $year = date('Y');
        
        return sprintf($format, $topic, $year);
    }
    
    /**
     * Generate focus keywords with LSI (Latent Semantic Indexing) keywords
     */
    private function generate_keywords($topic, $category) {
        return $this->generate_lsi_keywords($topic, $category);
    }
    
    /**
     * Generate LSI keywords for better SEO
     */
    private function generate_lsi_keywords($topic, $category) {
        $lsi_keywords = array();
        
        // Base keywords from topic
        $base_keywords = array_map('trim', explode(' ', strtolower($topic)));
        
        // Add category-specific keywords
        $category_keywords = array(
            'seo' => array('search engine optimization', 'SEO strategy', 'keyword research', 'backlinks', 'SERP'),
            'marketing' => array('digital marketing', 'content marketing', 'social media', 'campaign', 'conversion'),
            'digital-marketing' => array('digital marketing', 'online marketing', 'content strategy', 'social media marketing', 'email marketing'),
            'web-development' => array('web development', 'frontend', 'backend', 'framework', 'responsive design'),
            'mobile-development' => array('mobile app', 'iOS development', 'Android development', 'app store', 'cross-platform')
        );
        
        // Add base LSI keywords
        $lsi_keywords[] = $topic;
        $lsi_keywords[] = strtolower($topic);
        
        if (!empty($category) && isset($category_keywords[$category])) {
            $lsi_keywords = array_merge($lsi_keywords, $category_keywords[$category]);
        }
        
        // Add time-based keywords
        $lsi_keywords[] = $topic . ' ' . date('Y');
        $lsi_keywords[] = $topic . ' guide';
        $lsi_keywords[] = 'how to ' . strtolower($topic);
        $lsi_keywords[] = 'best ' . strtolower($topic);
        
        // Add semantic variations
        $lsi_keywords[] = strtolower($topic) . ' tips';
        $lsi_keywords[] = strtolower($topic) . ' best practices';
        
        return array_slice(array_unique($lsi_keywords), 0, 10);
    }
    
    /**
     * Fallback template-based generation
     */
    private function generate_template($topic, $category) {
        $templates = array(
            'intro' => "In today's rapidly evolving landscape, understanding $topic is more important than ever. This comprehensive guide will walk you through everything you need to know.",
            'sections' => array(
                array(
                    'title' => 'What is ' . $topic . '?',
                    'content' => "Before diving deep, let's establish a clear understanding of what $topic means and why it matters.\n\n[AI content generation is not configured. Please add your OpenAI API key in Settings to generate complete content automatically.]"
                ),
                array(
                    'title' => 'Key Benefits',
                    'content' => "Understanding the benefits of $topic can help you make informed decisions.\n\n[Add your OpenAI API key to generate detailed content for this section.]"
                ),
                array(
                    'title' => 'How to Get Started',
                    'content' => "Getting started with $topic doesn't have to be complicated.\n\n[Configure OpenAI in Settings for AI-powered content generation.]"
                ),
                array(
                    'title' => 'Best Practices',
                    'content' => "Follow these best practices to maximize your success with $topic.\n\n[Enable AI content generation for comprehensive best practices.]"
                ),
                array(
                    'title' => 'Conclusion',
                    'content' => "$topic represents an important opportunity for those willing to invest the time to understand it.\n\n[For complete, publication-ready content, please configure your OpenAI API key in the plugin settings.]"
                )
            )
        );
        
        return array(
            'title' => $this->generate_title($topic),
            'intro' => $templates['intro'],
            'sections' => $templates['sections'],
            'meta_description' => "Learn everything about $topic with our comprehensive guide. Discover best practices, tips, and strategies for success.",
            'keywords' => $this->generate_keywords($topic, $category),
            'category' => $category,
            'ai_generated' => false
        );
    }
}

