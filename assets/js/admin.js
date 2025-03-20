/**
 * WordPress WebP & AVIF Optimizer admin scripts
 */
(function($) {
    'use strict';
    
    // Bulk conversion handler
    function initBulkConversion() {
        const $button = $('.wp-image-optimizer-bulk-convert');
        const $progress = $('.wp-image-optimizer-progress-wrapper');
        const $progressBar = $('.wp-image-optimizer-progress-bar');
        const $progressText = $('.wp-image-optimizer-progress-percentage');
        const $progressCount = $('.wp-image-optimizer-progress-count');
        
        let processId = '';
        let total = 0;
        let intervalId = null;
        
        $button.on('click', function() {
            $(this).prop('disabled', true).text('Processing...');
            $progress.show();
            
            // Start bulk conversion
            $.ajax({
                url: wpImageOptimizer.ajax_url,
                type: 'POST',
                data: {
                    action: 'wp_image_optimizer_bulk_convert',
                    nonce: wpImageOptimizer.nonce
                },
                success: function(response) {
                    if (response.success) {
                        processId = response.data.process_id;
                        total = response.data.total;
                        
                        // Start progress polling
                        intervalId = setInterval(pollProgress, 1000);
                    } else {
                        handleError(response.data.message);
                    }
                },
                error: function() {
                    handleError('Ajax request failed');
                }
            });
        });
        
        function pollProgress() {
            $.ajax({
                url: wpImageOptimizer.ajax_url,
                type: 'POST',
                data: {
                    action: 'wp_image_optimizer_get_progress',
                    nonce: wpImageOptimizer.nonce,
                    process_id: processId
                },
                success: function(response) {
                    if (response.success) {
                        updateProgress(response.data);
                        
                        // Check if process is complete
                        if (response.data.processed >= total) {
                            clearInterval(intervalId);
                            $button.text('Conversion Complete').removeClass('button-primary');
                            
                            // Reload after 2 seconds to update statistics
                            setTimeout(function() {
                                window.location.reload();
                            }, 2000);
                        }
                    } else {
                        handleError(response.data.message);
                    }
                },
                error: function() {
                    handleError('Ajax request failed');
                }
            });
        }
        
        function updateProgress(data) {
            const percentage = data.percentage;
            const processed = data.processed;
            
            $progressBar.css('width', percentage + '%');
            $progressText.text(percentage + '%');
            $progressCount.text('(' + processed + '/' + total + ')');
        }
        
        function handleError(message) {
            clearInterval(intervalId);
            $button.prop('disabled', false).text('Retry Conversion');
            alert('Error: ' + message);
        }
    }
    
    // Media library conversion button
    function initMediaLibraryButtons() {
        $(document).on('click', '.wp-image-optimizer-convert', function() {
            const $button = $(this);
            const attachmentId = $button.data('id');
            const $spinner = $button.find('.spinner');
            
            $button.prop('disabled', true);
            $spinner.addClass('is-active');
            
            $.ajax({
                url: wpImageOptimizer.ajax_url,
                type: 'POST',
                data: {
                    action: 'wp_image_optimizer_convert_single',
                    nonce: wpImageOptimizer.nonce,
                    attachment_id: attachmentId
                },
                success: function(response) {
                    $spinner.removeClass('is-active');
                    
                    if (response.success) {
                        $button.text(wpImageOptimizer.i18n.converted);
                        
                        // Update status indicators
                        const $webpStatus = $button.closest('.wp-image-optimizer-buttons').find('.wp-image-optimizer-webp .dashicons');
                        const $avifStatus = $button.closest('.wp-image-optimizer-buttons').find('.wp-image-optimizer-avif .dashicons');
                        
                        if (response.data.webp) {
                            $webpStatus.removeClass('dashicons-no').addClass('dashicons-yes').css('color', 'green');
                        }
                        
                        if (response.data.avif) {
                            $avifStatus.removeClass('dashicons-no').addClass('dashicons-yes').css('color', 'green');
                        }
                    } else {
                        $button.text(wpImageOptimizer.i18n.error);
                        setTimeout(function() {
                            $button.prop('disabled', false).text(wpImageOptimizer.i18n.convert);
                        }, 2000);
                    }
                },
                error: function() {
                    $spinner.removeClass('is-active');
                    $button.text(wpImageOptimizer.i18n.error);
                    setTimeout(function() {
                        $button.prop('disabled', false).text(wpImageOptimizer.i18n.convert);
                    }, 2000);
                }
            });
        });
    }
    
    // Initialize on document ready
    $(function() {
        if ($('.wp-image-optimizer-bulk').length) {
            initBulkConversion();
        }
        
        initMediaLibraryButtons();
    });
    
})(jQuery);
