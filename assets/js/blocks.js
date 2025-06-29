/**
 * Gutenberg Blocks for Autonomous AI SEO
 */

(function(blocks, element, editor, components, i18n) {
    var el = element.createElement;
    var __ = i18n.__;
    var registerBlockType = blocks.registerBlockType;
    var InspectorControls = editor.InspectorControls;
    var PanelBody = components.PanelBody;
    var TextControl = components.TextControl;
    var TextareaControl = components.TextareaControl;
    var Button = components.Button;
    var Spinner = components.Spinner;
    var Notice = components.Notice;

    // SEO Analysis Block
    registerBlockType('aaiseo/seo-analysis', {
        title: __('AI SEO Analysis', 'autonomous-ai-seo'),
        icon: 'search',
        category: 'widgets',
        description: __('Analyze your content for SEO optimization in real-time.', 'autonomous-ai-seo'),
        
        attributes: {
            content: {
                type: 'string',
                default: ''
            },
            keywords: {
                type: 'string',
                default: ''
            }
        },

        edit: function(props) {
            var attributes = props.attributes;
            var setAttributes = props.setAttributes;
            
            var state = wp.element.useState({
                isAnalyzing: false,
                analysis: null,
                error: null
            });
            
            var currentState = state[0];
            var setState = state[1];

            function analyzeContent() {
                if (!attributes.content.trim()) {
                    setState({
                        ...currentState,
                        error: aaiseoBlocks.strings.noContent
                    });
                    return;
                }

                setState({
                    ...currentState,
                    isAnalyzing: true,
                    error: null
                });

                wp.apiFetch({
                    url: aaiseoBlocks.ajaxUrl,
                    method: 'POST',
                    data: {
                        action: 'aaiseo_analyze_content',
                        content: attributes.content,
                        keywords: attributes.keywords,
                        nonce: aaiseoBlocks.nonce
                    }
                }).then(function(response) {
                    if (response.success) {
                        setState({
                            ...currentState,
                            isAnalyzing: false,
                            analysis: response.data,
                            error: null
                        });
                    } else {
                        setState({
                            ...currentState,
                            isAnalyzing: false,
                            error: response.data || aaiseoBlocks.strings.error
                        });
                    }
                }).catch(function(error) {
                    setState({
                        ...currentState,
                        isAnalyzing: false,
                        error: aaiseoBlocks.strings.error
                    });
                });
            }

            function renderAnalysisResults() {
                if (!currentState.analysis) return null;
                
                var analysis = currentState.analysis;
                
                return el('div', {className: 'aaiseo-analysis-results'},
                    el('h4', {}, __('SEO Analysis Results', 'autonomous-ai-seo')),
                    
                    // SEO Score
                    el('div', {className: 'aaiseo-score-display'},
                        el('div', {className: 'aaiseo-score-circle'},
                            el('span', {className: 'aaiseo-score-number'}, analysis.seo_score || 0)
                        ),
                        el('span', {className: 'aaiseo-score-label'}, __('SEO Score', 'autonomous-ai-seo'))
                    ),
                    
                    // Quick Stats
                    analysis.word_count && el('div', {className: 'aaiseo-quick-stats'},
                        el('div', {className: 'aaiseo-stat'},
                            el('strong', {}, analysis.word_count),
                            el('span', {}, __(' words', 'autonomous-ai-seo'))
                        ),
                        analysis.readability_score && el('div', {className: 'aaiseo-stat'},
                            el('strong', {}, analysis.readability_score),
                            el('span', {}, __(' readability', 'autonomous-ai-seo'))
                        )
                    ),
                    
                    // Recommendations
                    analysis.recommendations && analysis.recommendations.length > 0 && el('div', {className: 'aaiseo-recommendations'},
                        el('h5', {}, __('Recommendations:', 'autonomous-ai-seo')),
                        el('ul', {},
                            analysis.recommendations.map(function(rec, index) {
                                return el('li', {key: index}, rec);
                            })
                        )
                    )
                );
            }

            return el('div', {className: 'aaiseo-seo-analysis-block'},
                el(InspectorControls, {},
                    el(PanelBody, {
                        title: __('SEO Analysis Settings', 'autonomous-ai-seo'),
                        initialOpen: true
                    },
                        el(TextControl, {
                            label: __('Target Keywords', 'autonomous-ai-seo'),
                            value: attributes.keywords,
                            onChange: function(value) {
                                setAttributes({keywords: value});
                            },
                            help: __('Enter keywords separated by commas', 'autonomous-ai-seo')
                        })
                    )
                ),
                
                el('div', {className: 'aaiseo-block-content'},
                    el('h3', {}, __('AI SEO Analysis', 'autonomous-ai-seo')),
                    
                    el(TextareaControl, {
                        label: __('Content to Analyze', 'autonomous-ai-seo'),
                        value: attributes.content,
                        onChange: function(value) {
                            setAttributes({content: value});
                        },
                        rows: 6,
                        placeholder: __('Paste your content here for SEO analysis...', 'autonomous-ai-seo')
                    }),
                    
                    el('div', {className: 'aaiseo-block-controls'},
                        el(Button, {
                            isPrimary: true,
                            disabled: currentState.isAnalyzing || !attributes.content.trim(),
                            onClick: analyzeContent
                        }, 
                            currentState.isAnalyzing ? 
                                [el(Spinner), ' ', aaiseoBlocks.strings.analyzing] :
                                __('Analyze Content', 'autonomous-ai-seo')
                        )
                    ),
                    
                    currentState.error && el(Notice, {
                        status: 'error',
                        isDismissible: false
                    }, currentState.error),
                    
                    renderAnalysisResults()
                )
            );
        },

        save: function() {
            // This block doesn't save anything to the frontend
            return null;
        }
    });

    // Content Suggestions Block
    registerBlockType('aaiseo/content-suggestions', {
        title: __('AI Content Suggestions', 'autonomous-ai-seo'),
        icon: 'lightbulb',
        category: 'widgets',
        description: __('Get AI-powered content suggestions and outlines.', 'autonomous-ai-seo'),
        
        attributes: {
            topic: {
                type: 'string',
                default: ''
            },
            targetAudience: {
                type: 'string',
                default: ''
            }
        },

        edit: function(props) {
            var attributes = props.attributes;
            var setAttributes = props.setAttributes;
            
            var state = wp.element.useState({
                isGenerating: false,
                suggestions: null,
                error: null
            });
            
            var currentState = state[0];
            var setState = state[1];

            function generateSuggestions() {
                if (!attributes.topic.trim()) {
                    setState({
                        ...currentState,
                        error: __('Please enter a topic.', 'autonomous-ai-seo')
                    });
                    return;
                }

                setState({
                    ...currentState,
                    isGenerating: true,
                    error: null
                });

                wp.apiFetch({
                    url: aaiseoBlocks.ajaxUrl,
                    method: 'POST',
                    data: {
                        action: 'aaiseo_generate_suggestions',
                        topic: attributes.topic,
                        target_audience: attributes.targetAudience,
                        nonce: aaiseoBlocks.nonce
                    }
                }).then(function(response) {
                    if (response.success) {
                        setState({
                            ...currentState,
                            isGenerating: false,
                            suggestions: response.data,
                            error: null
                        });
                    } else {
                        setState({
                            ...currentState,
                            isGenerating: false,
                            error: response.data || aaiseoBlocks.strings.error
                        });
                    }
                }).catch(function(error) {
                    setState({
                        ...currentState,
                        isGenerating: false,
                        error: aaiseoBlocks.strings.error
                    });
                });
            }

            function renderSuggestions() {
                if (!currentState.suggestions) return null;
                
                var suggestions = currentState.suggestions;
                
                return el('div', {className: 'aaiseo-suggestions-results'},
                    el('h4', {}, __('Content Suggestions', 'autonomous-ai-seo')),
                    
                    // Title Suggestions
                    suggestions.title_suggestions && el('div', {className: 'aaiseo-title-suggestions'},
                        el('h5', {}, __('Title Suggestions:', 'autonomous-ai-seo')),
                        el('ul', {},
                            suggestions.title_suggestions.map(function(title, index) {
                                return el('li', {key: index}, title);
                            })
                        )
                    ),
                    
                    // Content Outline
                    suggestions.outline && el('div', {className: 'aaiseo-content-outline'},
                        el('h5', {}, __('Content Outline:', 'autonomous-ai-seo')),
                        suggestions.outline.map(function(section, index) {
                            return el('div', {key: index, className: 'aaiseo-outline-section'},
                                el('h6', {}, section.heading),
                                section.subheadings && el('ul', {},
                                    section.subheadings.map(function(sub, subIndex) {
                                        return el('li', {key: subIndex}, sub);
                                    })
                                )
                            );
                        })
                    ),
                    
                    // Meta Description
                    suggestions.meta_description && el('div', {className: 'aaiseo-meta-description'},
                        el('h5', {}, __('Suggested Meta Description:', 'autonomous-ai-seo')),
                        el('p', {className: 'aaiseo-meta-text'}, suggestions.meta_description)
                    )
                );
            }

            return el('div', {className: 'aaiseo-content-suggestions-block'},
                el('div', {className: 'aaiseo-block-content'},
                    el('h3', {}, __('AI Content Suggestions', 'autonomous-ai-seo')),
                    
                    el(TextControl, {
                        label: __('Topic', 'autonomous-ai-seo'),
                        value: attributes.topic,
                        onChange: function(value) {
                            setAttributes({topic: value});
                        },
                        placeholder: __('Enter your content topic...', 'autonomous-ai-seo')
                    }),
                    
                    el(TextControl, {
                        label: __('Target Audience', 'autonomous-ai-seo'),
                        value: attributes.targetAudience,
                        onChange: function(value) {
                            setAttributes({targetAudience: value});
                        },
                        placeholder: __('e.g., Small business owners, Developers...', 'autonomous-ai-seo')
                    }),
                    
                    el('div', {className: 'aaiseo-block-controls'},
                        el(Button, {
                            isPrimary: true,
                            disabled: currentState.isGenerating || !attributes.topic.trim(),
                            onClick: generateSuggestions
                        }, 
                            currentState.isGenerating ? 
                                [el(Spinner), ' ', __('Generating...', 'autonomous-ai-seo')] :
                                __('Generate Suggestions', 'autonomous-ai-seo')
                        )
                    ),
                    
                    currentState.error && el(Notice, {
                        status: 'error',
                        isDismissible: false
                    }, currentState.error),
                    
                    renderSuggestions()
                )
            );
        },

        save: function() {
            return null;
        }
    });

    // Keyword Research Block
    registerBlockType('aaiseo/keyword-research', {
        title: __('AI Keyword Research', 'autonomous-ai-seo'),
        icon: 'tag',
        category: 'widgets',
        description: __('Discover semantic keywords and variations for your content.', 'autonomous-ai-seo'),
        
        attributes: {
            primaryKeyword: {
                type: 'string',
                default: ''
            }
        },

        edit: function(props) {
            var attributes = props.attributes;
            var setAttributes = props.setAttributes;
            
            var state = wp.element.useState({
                isResearching: false,
                keywords: null,
                error: null
            });
            
            var currentState = state[0];
            var setState = state[1];

            function researchKeywords() {
                if (!attributes.primaryKeyword.trim()) {
                    setState({
                        ...currentState,
                        error: __('Please enter a primary keyword.', 'autonomous-ai-seo')
                    });
                    return;
                }

                setState({
                    ...currentState,
                    isResearching: true,
                    error: null
                });

                wp.apiFetch({
                    url: aaiseoBlocks.ajaxUrl,
                    method: 'POST',
                    data: {
                        action: 'aaiseo_research_keywords',
                        primary_keyword: attributes.primaryKeyword,
                        content: '', // Could be enhanced to get current post content
                        nonce: aaiseoBlocks.nonce
                    }
                }).then(function(response) {
                    if (response.success) {
                        setState({
                            ...currentState,
                            isResearching: false,
                            keywords: response.data,
                            error: null
                        });
                    } else {
                        setState({
                            ...currentState,
                            isResearching: false,
                            error: response.data || aaiseoBlocks.strings.error
                        });
                    }
                }).catch(function(error) {
                    setState({
                        ...currentState,
                        isResearching: false,
                        error: aaiseoBlocks.strings.error
                    });
                });
            }

            function renderKeywords() {
                if (!currentState.keywords) return null;
                
                var keywords = currentState.keywords;
                
                return el('div', {className: 'aaiseo-keyword-results'},
                    el('h4', {}, __('Keyword Research Results', 'autonomous-ai-seo')),
                    
                    keywords.semantic_keywords && el('div', {className: 'aaiseo-keyword-group'},
                        el('h5', {}, __('Semantic Keywords:', 'autonomous-ai-seo')),
                        el('div', {className: 'aaiseo-keyword-tags'},
                            keywords.semantic_keywords.map(function(keyword, index) {
                                return el('span', {key: index, className: 'aaiseo-keyword-tag'}, keyword);
                            })
                        )
                    ),
                    
                    keywords.long_tail_variations && el('div', {className: 'aaiseo-keyword-group'},
                        el('h5', {}, __('Long-tail Variations:', 'autonomous-ai-seo')),
                        el('div', {className: 'aaiseo-keyword-tags'},
                            keywords.long_tail_variations.map(function(keyword, index) {
                                return el('span', {key: index, className: 'aaiseo-keyword-tag'}, keyword);
                            })
                        )
                    ),
                    
                    keywords.lsi_keywords && el('div', {className: 'aaiseo-keyword-group'},
                        el('h5', {}, __('LSI Keywords:', 'autonomous-ai-seo')),
                        el('div', {className: 'aaiseo-keyword-tags'},
                            keywords.lsi_keywords.map(function(keyword, index) {
                                return el('span', {key: index, className: 'aaiseo-keyword-tag'}, keyword);
                            })
                        )
                    )
                );
            }

            return el('div', {className: 'aaiseo-keyword-research-block'},
                el('div', {className: 'aaiseo-block-content'},
                    el('h3', {}, __('AI Keyword Research', 'autonomous-ai-seo')),
                    
                    el(TextControl, {
                        label: __('Primary Keyword', 'autonomous-ai-seo'),
                        value: attributes.primaryKeyword,
                        onChange: function(value) {
                            setAttributes({primaryKeyword: value});
                        },
                        placeholder: __('Enter your primary keyword...', 'autonomous-ai-seo')
                    }),
                    
                    el('div', {className: 'aaiseo-block-controls'},
                        el(Button, {
                            isPrimary: true,
                            disabled: currentState.isResearching || !attributes.primaryKeyword.trim(),
                            onClick: researchKeywords
                        }, 
                            currentState.isResearching ? 
                                [el(Spinner), ' ', __('Researching...', 'autonomous-ai-seo')] :
                                __('Research Keywords', 'autonomous-ai-seo')
                        )
                    ),
                    
                    currentState.error && el(Notice, {
                        status: 'error',
                        isDismissible: false
                    }, currentState.error),
                    
                    renderKeywords()
                )
            );
        },

        save: function() {
            return null;
        }
    });

})(
    window.wp.blocks,
    window.wp.element,
    window.wp.editor || window.wp.blockEditor,
    window.wp.components,
    window.wp.i18n
);