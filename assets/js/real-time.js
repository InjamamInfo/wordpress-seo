/**
 * Real-time SEO Analysis JavaScript
 */

jQuery(document).ready(function($) {
    
    var analysisTimer;
    var lastContent = '';
    var isAnalyzing = false;
    
    // Initialize real-time analysis
    initRealTimeAnalysis();
    
    function initRealTimeAnalysis() {
        // Watch for content changes in Gutenberg editor
        if (typeof wp !== 'undefined' && wp.data && wp.data.select('core/editor')) {
            watchGutenbergEditor();
        }
        
        // Watch for content changes in Classic editor
        if ($('#content').length) {
            watchClassicEditor();
        }
        
        // Watch title changes
        $('#title').on('input', debounce(function() {
            triggerAnalysis();
        }, 1000));
        
        // Manual analysis button
        $('#aaiseo-manual-analyze').on('click', function(e) {
            e.preventDefault();
            triggerAnalysis(true);
        });
        
        // Generate suggestions button
        $('#aaiseo-generate-suggestions').on('click', function(e) {
            e.preventDefault();
            generateSuggestions();
        });
    }
    
    function watchGutenbergEditor() {
        // Subscribe to editor changes
        var unsubscribe = wp.data.subscribe(function() {
            var editor = wp.data.select('core/editor');
            if (editor) {
                var content = editor.getEditedPostContent();
                if (content !== lastContent) {
                    lastContent = content;
                    triggerAnalysis();
                }
            }
        });
        
        // Clean up subscription when page unloads
        $(window).on('beforeunload', function() {
            if (unsubscribe) {
                unsubscribe();
            }
        });
    }
    
    function watchClassicEditor() {
        // Watch TinyMCE editor
        if (typeof tinymce !== 'undefined') {
            tinymce.on('AddEditor', function(e) {
                var editor = e.editor;
                editor.on('KeyUp NodeChange', debounce(function() {
                    triggerAnalysis();
                }, 1000));
            });
        }
        
        // Watch textarea (text mode)
        $('#content').on('input', debounce(function() {
            triggerAnalysis();
        }, 1000));
    }
    
    function triggerAnalysis(force = false) {
        if (isAnalyzing && !force) {
            return;
        }
        
        var content = getEditorContent();
        var title = $('#title').val() || '';
        
        if (!content.trim() && !force) {
            return;
        }
        
        // Clear previous timer
        if (analysisTimer) {
            clearTimeout(analysisTimer);
        }
        
        // Set timer for analysis (debounced)
        analysisTimer = setTimeout(function() {
            performAnalysis(content, title);
        }, force ? 0 : 2000);
    }
    
    function getEditorContent() {
        // Get content from Gutenberg
        if (typeof wp !== 'undefined' && wp.data && wp.data.select('core/editor')) {
            var editor = wp.data.select('core/editor');
            if (editor && editor.getEditedPostContent) {
                return editor.getEditedPostContent();
            }
        }
        
        // Get content from TinyMCE
        if (typeof tinymce !== 'undefined' && tinymce.get('content')) {
            return tinymce.get('content').getContent();
        }
        
        // Get content from textarea
        return $('#content').val() || '';
    }
    
    function performAnalysis(content, title) {
        if (isAnalyzing) {
            return;
        }
        
        isAnalyzing = true;
        showLoading(true);
        
        var data = {
            action: 'aaiseo_real_time_analyze',
            content: content,
            title: title,
            post_id: aaiseoRealTime.postId,
            nonce: aaiseoRealTime.nonce
        };
        
        $.ajax({
            url: aaiseoRealTime.ajaxUrl,
            type: 'POST',
            data: data,
            success: function(response) {
                if (response.success) {
                    displayAnalysis(response.data);
                } else {
                    displayError(response.data || 'Analysis failed');
                }
            },
            error: function() {
                displayError('Connection error. Please try again.');
            },
            complete: function() {
                isAnalyzing = false;
                showLoading(false);
            }
        });
    }
    
    function displayAnalysis(analysis) {
        // Update SEO score
        updateSeoScore(analysis.seo_score);
        
        // Update metrics
        $('#aaiseo-word-count').text(analysis.word_count || 0);
        $('#aaiseo-readability').text(analysis.readability_score || '-');
        
        // Update keyword density
        var keywordText = '-';
        if (analysis.keyword_analysis && Object.keys(analysis.keyword_analysis).length > 0) {
            var densities = [];
            for (var keyword in analysis.keyword_analysis) {
                densities.push(analysis.keyword_analysis[keyword].density + '%');
            }
            keywordText = densities.join(', ');
        }
        $('#aaiseo-keyword-density').text(keywordText);
        
        // Update recommendations
        updateRecommendations(analysis.recommendations);
        
        // Store analysis globally for other functions
        window.aaiseoRealTime = window.aaiseoRealTime || {};
        window.aaiseoRealTime.lastAnalysis = analysis;
        window.aaiseoRealTime.displayAnalysis = displayAnalysis; // Make available for meta box
    }
    
    function updateSeoScore(score) {
        var $scoreCircle = $('.aaiseo-score-circle');
        var $scoreNumber = $('.aaiseo-score-number');
        var $scoreStatus = $('.aaiseo-score-status');
        
        // Update score number
        $scoreNumber.text(score);
        
        // Update score circle
        $scoreCircle.attr('data-score', score);
        $scoreCircle.css('--score', score);
        
        // Update status text and color
        var status, className;
        if (score >= 80) {
            status = aaiseoRealTime.strings.excellent;
            className = 'excellent';
        } else if (score >= 65) {
            status = aaiseoRealTime.strings.good;
            className = 'good';
        } else if (score >= 40) {
            status = aaiseoRealTime.strings.needsWork;
            className = 'needs-work';
        } else {
            status = aaiseoRealTime.strings.poor;
            className = 'poor';
        }
        
        $scoreStatus.text(status);
        $scoreCircle.removeClass('excellent good needs-work poor').addClass(className);
    }
    
    function updateRecommendations(recommendations) {
        var $container = $('#aaiseo-recommendations .aaiseo-recommendations-content');
        
        if (!recommendations || recommendations.length === 0) {
            $container.html('<p class="aaiseo-no-analysis">No recommendations at this time.</p>');
            return;
        }
        
        var html = '<ul class="aaiseo-recommendation-list">';
        recommendations.forEach(function(rec) {
            var iconClass = 'dashicons-';
            switch(rec.type) {
                case 'warning':
                    iconClass += 'warning';
                    break;
                case 'success':
                    iconClass += 'yes-alt';
                    break;
                default:
                    iconClass += 'info';
            }
            
            html += '<li class="aaiseo-recommendation aaiseo-' + rec.type + '">';
            html += '<span class="dashicons ' + iconClass + '"></span>';
            html += '<span class="aaiseo-rec-text">' + rec.message + '</span>';
            html += '</li>';
        });
        html += '</ul>';
        
        $container.html(html);
    }
    
    function generateSuggestions() {
        var content = getEditorContent();
        var title = $('#title').val() || '';
        
        if (!content.trim()) {
            alert('Please add some content first.');
            return;
        }
        
        showLoading(true, 'Generating suggestions...');
        
        var data = {
            action: 'aaiseo_generate_suggestions',
            topic: title || 'Content optimization',
            target_audience: 'general audience',
            nonce: aaiseoRealTime.nonce
        };
        
        $.ajax({
            url: aaiseoRealTime.ajaxUrl,
            type: 'POST',
            data: data,
            success: function(response) {
                if (response.success) {
                    displaySuggestions(response.data);
                } else {
                    displayError(response.data || 'Failed to generate suggestions');
                }
            },
            error: function() {
                displayError('Connection error. Please try again.');
            },
            complete: function() {
                showLoading(false);
            }
        });
    }
    
    function displaySuggestions(suggestions) {
        // Create modal or panel to display suggestions
        var modal = $('<div class="aaiseo-suggestions-modal">');
        var content = $('<div class="aaiseo-suggestions-content">');
        
        content.append('<h3>Content Suggestions</h3>');
        
        if (suggestions.title_suggestions) {
            content.append('<h4>Title Suggestions:</h4>');
            var titleList = $('<ul>');
            suggestions.title_suggestions.forEach(function(title) {
                titleList.append('<li>' + title + '</li>');
            });
            content.append(titleList);
        }
        
        if (suggestions.outline) {
            content.append('<h4>Content Outline:</h4>');
            var outlineDiv = $('<div class="aaiseo-outline">');
            suggestions.outline.forEach(function(section) {
                outlineDiv.append('<h5>' + section.heading + '</h5>');
                if (section.subheadings) {
                    var subList = $('<ul>');
                    section.subheadings.forEach(function(sub) {
                        subList.append('<li>' + sub + '</li>');
                    });
                    outlineDiv.append(subList);
                }
            });
            content.append(outlineDiv);
        }
        
        content.append('<button type="button" class="button button-primary aaiseo-close-modal">Close</button>');
        
        modal.append(content);
        $('body').append(modal);
        
        // Show modal
        modal.fadeIn();
        
        // Close modal handler
        $('.aaiseo-close-modal, .aaiseo-suggestions-modal').on('click', function(e) {
            if (e.target === this) {
                modal.fadeOut(function() {
                    modal.remove();
                });
            }
        });
    }
    
    function showLoading(show, message) {
        var $loading = $('#aaiseo-loading');
        var $loadingText = $loading.find('span:last-child');
        
        if (show) {
            if (message) {
                $loadingText.text(message);
            }
            $loading.show();
        } else {
            $loading.hide();
            $loadingText.text(aaiseoRealTime.strings.analyzing);
        }
    }
    
    function displayError(message) {
        var $container = $('#aaiseo-recommendations .aaiseo-recommendations-content');
        $container.html('<p class="aaiseo-error"><span class="dashicons dashicons-warning"></span> ' + message + '</p>');
    }
    
    function debounce(func, wait) {
        var timeout;
        return function executedFunction() {
            var context = this;
            var args = arguments;
            var later = function() {
                timeout = null;
                func.apply(context, args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }
    
    // Initialize with existing content if any
    var initialContent = getEditorContent();
    if (initialContent.trim()) {
        setTimeout(function() {
            triggerAnalysis();
        }, 1000);
    }
});