/**
 * TechNews Autoblog Admin JavaScript
 */

(function ($) {
    'use strict';

    var TechNewsAutoblog = {

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
            $('#technews-fetch-btn').on('click', function () {
                self.fetchTrends();
            });

            // Category filter change
            $('#technews-category').on('change', function () {
                self.filterTrends($(this).val());
            });

            // Create post button (delegated)
            $(document).on('click', '.technews-create-btn', function () {
                var $card = $(this).closest('.technews-trend-card');
                self.openModal($card.data('topic'), $card.data('category'));
            });

            // Modal close
            $('.technews-modal-close, .technews-modal-cancel, .technews-modal-overlay').on('click', function () {
                self.closeModal();
            });

            // Create post submit
            $('#technews-create-post-btn').on('click', function () {
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
            var category = $('#technews-category').val();

            console.log('TechNews: Fetching trends...', { category: category });
            this.showStatus(technewsAutoblog.strings.fetching);
            $('#technews-fetch-btn').prop('disabled', true);

            $.ajax({
                url: technewsAutoblog.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'technews_fetch_trends',
                    nonce: technewsAutoblog.nonce,
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
                    self.showError(technewsAutoblog.strings.error + ' (Check console for details)');
                },
                complete: function () {
                    $('#technews-fetch-btn').prop('disabled', false);
                }
            });
        },

        /**
         * Render trends
         */
        renderTrends: function (trends) {
            var $container = $('#technews-trends-container');
            var template = wp.template('technews-trend-card');

            $container.empty();

            if (trends.length === 0) {
                $container.html(
                    '<div class="technews-empty-state">' +
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

            $('#technews-post-title').val(topic);
            $('#technews-post-category').val(category || 'seo');

            $('#technews-modal').fadeIn(200);
            $('#technews-post-title').focus();
        },

        /**
         * Close modal
         */
        closeModal: function () {
            $('#technews-modal').fadeOut(200);
            this.currentTopic = null;
        },

        /**
         * Create post
         */
        createPost: function () {
            var self = this;
            var topic = $('#technews-post-title').val();
            var category = $('#technews-post-category').val();
            var useAI = $('#technews-use-ai').is(':checked');

            if (!topic) {
                alert('Please enter a post title');
                return;
            }

            $('#technews-create-post-btn').prop('disabled', true)
                .html('<span class="spinner is-active" style="float:none;margin:0 8px 0 0;"></span> ' + technewsAutoblog.strings.creating);

            $.ajax({
                url: technewsAutoblog.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'technews_create_post',
                    nonce: technewsAutoblog.nonce,
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
                    self.showError(technewsAutoblog.strings.error);
                },
                complete: function () {
                    $('#technews-create-post-btn').prop('disabled', false)
                        .html('<span class="dashicons dashicons-edit"></span> Create Draft Post');
                }
            });
        },

        /**
         * Show status message
         */
        showStatus: function (message) {
            $('#technews-status .status-text').text(message);
            $('#technews-status').slideDown(200);
            $('#technews-error, #technews-success').slideUp(200);
        },

        /**
         * Hide status message
         */
        hideStatus: function () {
            $('#technews-status').slideUp(200);
        },

        /**
         * Show error message
         */
        showError: function (message) {
            this.hideStatus();
            $('#technews-error p').html(message);
            $('#technews-error').slideDown(200);
            $('#technews-success').slideUp(200);

            setTimeout(function () {
                $('#technews-error').slideUp(200);
            }, 5000);
        },

        /**
         * Show success message
         */
        showSuccess: function (message) {
            this.hideStatus();
            $('#technews-success p').html(message);
            $('#technews-success').slideDown(200);
            $('#technews-error').slideUp(200);
        }
    };

    // Initialize on document ready
    $(document).ready(function () {
        TechNewsAutoblog.init();
    });

})(jQuery);
