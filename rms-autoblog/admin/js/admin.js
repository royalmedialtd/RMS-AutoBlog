/**
 * RMS AutoBlog Admin JavaScript
 */

(function ($) {
    'use strict';

    var RMSAutoblogApp = {

        // Current data
        trends: [],
        currentTopic: null,

        /**
         * Initialize
         */
        init: function () {
            this.bindEvents();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function () {
            var self = this;

            // Fetch trends button
            $('#rmsautoblog-fetch-btn').on('click', function () {
                self.fetchTrends();
            });

            // Category filter change
            $('#rmsautoblog-category').on('change', function () {
                self.filterTrends($(this).val());
            });

            // Create post button (delegated)
            $(document).on('click', '.rmsautoblog-create-btn', function () {
                var $card = $(this).closest('.rmsautoblog-trend-card');
                self.openModal($card.data('topic'), $card.data('category'));
            });

            // Custom post button
            $('#rmsautoblog-custom-post-btn').on('click', function () {
                self.openCustomModal();
            });

            // AI suggestion button
            $('#rmsautoblog-suggest-btn').on('click', function () {
                self.getAISuggestions();
            });

            // Generate image button
            $('#rmsautoblog-generate-image-btn').on('click', function () {
                self.generateImage();
            });

            // Custom post create button
            $('#rmsautoblog-custom-create-btn').on('click', function () {
                self.createCustomPost();
            });

            // Modal close
            $('.rmsautoblog-modal-close, .rmsautoblog-modal-cancel, .rmsautoblog-modal-overlay').on('click', function () {
                self.closeModal();
            });

            // Create post submit
            $('#rmsautoblog-create-post-btn').on('click', function () {
                self.createPost();
            });

            // ESC key closes modal
            $(document).on('keydown', function (e) {
                if (e.key === 'Escape') {
                    self.closeModal();
                }
            });
        },

        /**
         * Fetch trending topics
         */
        fetchTrends: function () {
            var self = this;
            var category = $('#rmsautoblog-category').val();

            console.log('TechNews: Fetching trends...', { category: category });
            this.showStatus(rmsautoblogSettings.strings.fetching);
            $('#rmsautoblog-fetch-btn').prop('disabled', true);

            $.ajax({
                url: rmsautoblogSettings.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'rmsautoblog_fetch_trends',
                    nonce: rmsautoblogSettings.nonce,
                    category: category
                },
                success: function (response) {
                    console.log('TechNews: Response received', response);
                    if (response.success) {
                        self.trends = response.data.trends;
                        console.log('TechNews: Trends loaded', self.trends.length, 'items');
                        self.renderTrends(self.trends);
                        self.hideStatus();
                    } else {
                        console.error('TechNews: Error', response.data);
                        self.showError(response.data.message || 'Unknown error occurred');
                    }
                },
                error: function (xhr, status, error) {
                    console.error('TechNews: AJAX Error', { xhr: xhr, status: status, error: error });
                    self.showError(rmsautoblogSettings.strings.error + ' (Check console for details)');
                },
                complete: function () {
                    $('#rmsautoblog-fetch-btn').prop('disabled', false);
                }
            });
        },

        /**
         * Render trends
         */
        renderTrends: function (trends) {
            var $container = $('#rmsautoblog-trends-container');
            var template = wp.template('rmsautoblog-trend-card');

            $container.empty();

            if (trends.length === 0) {
                $container.html(
                    '<div class="rmsautoblog-empty-state">' +
                    '<span class="dashicons dashicons-info"></span>' +
                    '<h3>No trends found</h3>' +
                    '<p>Try selecting a different category or check your API configuration.</p>' +
                    '</div>'
                );
                return;
            }

            trends.forEach(function (trend) {
                // Format date
                if (trend.published_at) {
                    var date = new Date(trend.published_at);
                    trend.published_at = date.toLocaleDateString();
                }

                $container.append(template(trend));
            });
        },

        /**
         * Filter trends by category
         */
        filterTrends: function (category) {
            if (!category) {
                this.renderTrends(this.trends);
                return;
            }

            var filtered = this.trends.filter(function (trend) {
                return trend.category === category;
            });

            this.renderTrends(filtered);
        },

        /**
         * Open create post modal
         */
        openModal: function (topic, category) {
            this.currentTopic = topic;

            $('#rmsautoblog-post-title').val(topic);
            $('#rmsautoblog-post-category').val(category || 'seo');

            $('#rmsautoblog-modal').fadeIn(200);
            $('#rmsautoblog-post-title').focus();
        },

        /**
         * Close modal
         */
        closeModal: function () {
            $('#rmsautoblog-modal').fadeOut(200);
            this.currentTopic = null;
        },

        /**
         * Create post
         */
        createPost: function () {
            var self = this;
            var topic = $('#rmsautoblog-post-title').val();
            var category = $('#rmsautoblog-post-category').val();
            var useAI = $('#rmsautoblog-use-ai').is(':checked');

            if (!topic) {
                alert('Please enter a post title');
                return;
            }

            $('#rmsautoblog-create-post-btn').prop('disabled', true)
                .html('<span class="spinner is-active" style="float:none;margin:0 8px 0 0;"></span> ' + rmsautoblogSettings.strings.creating);

            $.ajax({
                url: rmsautoblogSettings.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'rmsautoblog_create_post',
                    nonce: rmsautoblogSettings.nonce,
                    topic: topic,
                    category: category,
                    use_ai: useAI ? 1 : 0
                },
                success: function (response) {
                    if (response.success) {
                        self.closeModal();
                        self.showSuccess(
                            response.data.message +
                            ' <a href="' + response.data.edit_url + '" class="button button-small">' +
                            'Edit Post</a>'
                        );
                    } else {
                        self.showError(response.data.message);
                    }
                },
                error: function () {
                    self.showError(rmsautoblogSettings.strings.error);
                },
                complete: function () {
                    $('#rmsautoblog-create-post-btn').prop('disabled', false)
                        .html('<span class="dashicons dashicons-edit"></span> Create Draft Post');
                }
            });
        },

        /**
         * Show status message
         */
        showStatus: function (message) {
            $('#rmsautoblog-status .status-text').text(message);
            $('#rmsautoblog-status').slideDown(200);
            $('#rmsautoblog-error, #rmsautoblog-success').slideUp(200);
        },

        /**
         * Open custom post modal
         */
        openCustomModal: function () {
            // Reset form
            $('#rmsautoblog-custom-keyword').val('');
            $('#rmsautoblog-custom-title').val('');
            $('#rmsautoblog-custom-structure').val('');
            $('#rmsautoblog-generated-image').hide();
            $('#rmsautoblog-image-path').val('');

            // Show modal
            $('#rmsautoblog-custom-modal').fadeIn(200);
        },

        /**
         * Get AI suggestions for title and structure
         */
        getAISuggestions: function () {
            var self = this;
            var keyword = $('#rmsautoblog-custom-keyword').val().trim();

            if (!keyword) {
                alert('Please enter a keyword first');
                return;
            }

            $('#rmsautoblog-suggest-btn').prop('disabled', true)
                .html('<span class="spinner is-active" style="float:none;margin:0 8px 0 0;"></span> Getting suggestions...');

            $.ajax({
                url: rmsautoblogSettings.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'rmsautoblog_get_suggestions',
                    nonce: rmsautoblogSettings.nonce,
                    keyword: keyword
                },
                success: function (response) {
                    if (response.success && response.data) {
                        if (response.data.title) {
                            $('#rmsautoblog-custom-title').val(response.data.title);
                        }
                        if (response.data.structure) {
                            $('#rmsautoblog-custom-structure').val(response.data.structure);
                        }
                    } else {
                        alert(response.data.message || 'Failed to get suggestions');
                    }
                },
                error: function () {
                    alert('Error getting AI suggestions');
                },
                complete: function () {
                    $('#rmsautoblog-suggest-btn').prop('disabled', false)
                        .html('<span class="dashicons dashicons-lightbulb"></span> Get AI Suggestions');
                }
            });
        },

        /**
         * Generate AI image
         */
        generateImage: function () {
            var self = this;
            var keyword = $('#rmsautoblog-custom-keyword').val().trim();
            var title = $('#rmsautoblog-custom-title').val().trim();
            var prompt = title || keyword;

            if (!prompt) {
                alert('Please enter a keyword or title first');
                return;
            }

            $('#rmsautoblog-generate-image-btn').prop('disabled', true)
                .html('<span class="spinner is-active" style="float:none;margin:0 8px 0 0;"></span> Generating image...');

            $.ajax({
                url: rmsautoblogSettings.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'rmsautoblog_generate_image',
                    nonce: rmsautoblogSettings.nonce,
                    prompt: 'Professional blog header image for: ' + prompt
                },
                success: function (response) {
                    if (response.success && response.data.image_url) {
                        $('#rmsautoblog-generated-image img').attr('src', response.data.image_url);
                        $('#rmsautoblog-image-path').val(response.data.image_path);
                        $('#rmsautoblog-generated-image').fadeIn(200);
                    } else {
                        alert(response.data.message || 'Failed to generate image');
                    }
                },
                error: function () {
                    alert('Error generating image');
                },
                complete: function () {
                    $('#rmsautoblog-generate-image-btn').prop('disabled', false)
                        .html('<span class="dashicons dashicons-format-image"></span> Generate AI Image');
                }
            });
        },

        /**
         * Create custom post
         */
        createCustomPost: function () {
            var self = this;
            var keyword = $('#rmsautoblog-custom-keyword').val().trim();
            var title = $('#rmsautoblog-custom-title').val().trim();
            var structure = $('#rmsautoblog-custom-structure').val().trim();
            var category = $('#rmsautoblog-custom-category').val();
            var useAI = $('#rmsautoblog-custom-use-ai').is(':checked');
            var imagePath = $('#rmsautoblog-image-path').val();

            if (!keyword) {
                alert('Please enter a keyword');
                return;
            }

            $('#rmsautoblog-custom-create-btn').prop('disabled', true)
                .html('<span class="spinner is-active" style="float:none;margin:0 8px 0 0;"></span> Creating post...');

            $.ajax({
                url: rmsautoblogSettings.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'rmsautoblog_create_custom_post',
                    nonce: rmsautoblogSettings.nonce,
                    keyword: keyword,
                    title: title,
                    structure: structure,
                    category: category,
                    use_ai: useAI ? '1' : '0',
                    image_path: imagePath
                },
                success: function (response) {
                    if (response.success) {
                        self.showSuccess(response.data.message + ' <a href="' + response.data.edit_url + '">Edit Post</a>');
                        $('#rmsautoblog-custom-modal').fadeOut(200);
                    } else {
                        alert(response.data.message || 'Failed to create post');
                    }
                },
                error: function () {
                    alert('Error creating post');
                },
                complete: function () {
                    $('#rmsautoblog-custom-create-btn').prop('disabled', false)
                        .html('<span class="dashicons dashicons-yes"></span> Create Draft Post');
                }
            });
        },

        /**
         * Hide status messages
         */
        hideStatus: function () {
            $('#rmsautoblog-status').slideUp(200);
        },

        /**
         * Show error message
         */
        showError: function (message) {
            this.hideStatus();
            $('#rmsautoblog-error p').html(message);
            $('#rmsautoblog-error').slideDown(200);
            $('#rmsautoblog-success').slideUp(200);

            setTimeout(function () {
                $('#rmsautoblog-error').slideUp(200);
            }, 5000);
        },

        /**
         * Show success message
         */
        showSuccess: function (message) {
            this.hideStatus();
            $('#rmsautoblog-success p').html(message);
            $('#rmsautoblog-success').slideDown(200);
            $('#rmsautoblog-error').slideUp(200);
        }
    };

    // Initialize on document ready
    $(document).ready(function () {
        RMSAutoblogApp.init();
    });

})(jQuery);



