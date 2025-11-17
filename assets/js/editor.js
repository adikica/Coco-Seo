/**
 * Coco SEO Plugin Block Editor Integration
 */

// WordPress dependencies
const { __ } = wp.i18n;
const { registerPlugin } = wp.plugins;
const { PluginSidebar, PluginSidebarMoreMenuItem } = wp.editPost;
const { PanelBody, TextControl, TextareaControl, SelectControl, Button, Spinner, Notice } = wp.components;
const { useState, useEffect } = wp.element;
const { useSelect } = wp.data;
const apiFetch = wp.apiFetch;

// Register the plugin
registerPlugin('coco-seo-sidebar', {
    render: CocoSeoSidebar,
});

/**
 * Main sidebar component
 */
function CocoSeoSidebar() {
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState(null);
    const [saved, setSaved] = useState(false);
    
    // Meta data state
    const [metaTitle, setMetaTitle] = useState('');
    const [metaDescription, setMetaDescription] = useState('');
    const [metaIndexFollow, setMetaIndexFollow] = useState('index follow');
    const [indexingStatus, setIndexingStatus] = useState('');
    const [indexingChecked, setIndexingChecked] = useState('');
    
    // Get post ID
    const postId = useSelect(select => select('core/editor').getCurrentPostId(), []);
    
    // Load meta data on component mount or when post ID changes
    useEffect(() => {
        if (!postId) return;
        
        setLoading(true);
        setError(null);
        
        apiFetch({ 
            path: `coco-seo/v1/meta/${postId}`,
            method: 'GET'
        })
        .then(response => {
            setMetaTitle(response.meta_title || '');
            setMetaDescription(response.meta_description || '');
            setMetaIndexFollow(response.meta_index_follow || 'index follow');
            setIndexingStatus(response.indexing_status || '');
            setIndexingChecked(response.indexing_checked || '');
            setLoading(false);
        })
        .catch(err => {
            setError(err.message || __('Failed to load SEO data.', 'coco-seo'));
            setLoading(false);
        });
    }, [postId]);
    
    // Save meta data
    const saveMeta = () => {
        if (!postId) return;
        
        setSaving(true);
        setError(null);
        setSaved(false);
        
        apiFetch({ 
            path: `coco-seo/v1/meta/${postId}`,
            method: 'POST',
            data: {
                meta_title: metaTitle,
                meta_description: metaDescription,
                meta_index_follow: metaIndexFollow
            }
        })
        .then(response => {
            setSaving(false);
            setSaved(true);
            
            // Hide saved notification after 3 seconds
            setTimeout(() => {
                setSaved(false);
            }, 3000);
        })
        .catch(err => {
            setError(err.message || __('Failed to save SEO data.', 'coco-seo'));
            setSaving(false);
        });
    };
    
    // Check indexing status
    const checkIndexingStatus = () => {
        if (!postId) return;
        
        setLoading(true);
        setError(null);
        
        // Get post permalink
        const permalink = wp.data.select('core/editor').getPermalink();
        
        // Make AJAX request to check indexing status
        jQuery.ajax({
            url: cocoSEO.ajaxUrl,
            type: 'POST',
            data: {
                action: 'coco_seo_run_check',
                post_id: postId,
                nonce: cocoSEO.nonce
            },
            success: function(response) {
                if (response.success) {
                    setIndexingStatus(response.data.status);
                    setIndexingChecked(response.data.checked);
                } else {
                    setError(response.data.message || __('Failed to check indexing status.', 'coco-seo'));
                }
                setLoading(false);
            },
            error: function() {
                setError(__('An error occurred while checking indexing status.', 'coco-seo'));
                setLoading(false);
            }
        });
    };
    
    // Calculate remainding characters
    const titleRemaining = 60 - metaTitle.length;
    const descriptionRemaining = 160 - metaDescription.length;
    
    // Check if title/description is too short or too long
    const titleWarning = metaTitle.length < 30 || metaTitle.length > 60;
    const descriptionWarning = metaDescription.length < 130 || metaDescription.length > 160;
    
    return (
        <>
            <PluginSidebarMoreMenuItem
                target="coco-seo-sidebar"
                icon="admin-site"
            >
                {__('SEO Settings', 'coco-seo')}
            </PluginSidebarMoreMenuItem>
            
            <PluginSidebar
                name="coco-seo-sidebar"
                title={__('SEO Settings', 'coco-seo')}
                icon="admin-site"
            >
                {loading ? (
                    <div className="coco-seo-loading">
                        <Spinner />
                        <p>{__('Loading SEO data...', 'coco-seo')}</p>
                    </div>
                ) : (
                    <>
                        {error && (
                            <Notice status="error" isDismissible={false}>
                                {error}
                            </Notice>
                        )}
                        
                        {saved && (
                            <Notice status="success" isDismissible={false}>
                                {__('SEO settings saved successfully.', 'coco-seo')}
                            </Notice>
                        )}
                        
                        <PanelBody title={__('SEO Meta Data', 'coco-seo')} initialOpen={true}>
                            <TextControl
                                label={__('Meta Title', 'coco-seo')}
                                help={
                                    titleWarning
                                        ? __('Recommended length: 30-60 characters.', 'coco-seo')
                                        : __('Perfect length!', 'coco-seo')
                                }
                                value={metaTitle}
                                onChange={setMetaTitle}
                                maxLength={60}
                            />
                            <p className={`coco-seo-counter ${titleWarning ? 'coco-seo-warning' : ''}`}>
                                {titleRemaining} {__('characters remaining', 'coco-seo')}
                            </p>
                            
                            <TextareaControl
                                label={__('Meta Description', 'coco-seo')}
                                help={
                                    descriptionWarning
                                        ? __('Recommended length: 130-160 characters.', 'coco-seo')
                                        : __('Perfect length!', 'coco-seo')
                                }
                                value={metaDescription}
                                onChange={setMetaDescription}
                                maxLength={160}
                                rows={4}
                            />
                            <p className={`coco-seo-counter ${descriptionWarning ? 'coco-seo-warning' : ''}`}>
                                {descriptionRemaining} {__('characters remaining', 'coco-seo')}
                            </p>
                            
                            <SelectControl
                                label={__('Search Engine Visibility', 'coco-seo')}
                                value={metaIndexFollow}
                                options={[
                                    { label: __('Index this page & follow links', 'coco-seo'), value: 'index follow' },
                                    { label: __('Index this page & don\'t follow links', 'coco-seo'), value: 'index nofollow' },
                                    { label: __('Don\'t index this page & follow links', 'coco-seo'), value: 'noindex follow' },
                                    { label: __('Don\'t index this page & don\'t follow links', 'coco-seo'), value: 'noindex nofollow' },
                                ]}
                                onChange={setMetaIndexFollow}
                            />
                            
                            <div className="coco-seo-button-container">
                                <Button
                                    isPrimary
                                    onClick={saveMeta}
                                    isBusy={saving}
                                    disabled={saving}
                                >
                                    {saving ? __('Saving...', 'coco-seo') : __('Save SEO Settings', 'coco-seo')}
                                </Button>
                            </div>
                        </PanelBody>
                        
                        <PanelBody title={__('Indexing Status', 'coco-seo')} initialOpen={true}>
                            {indexingStatus ? (
                                <div className="coco-seo-status-panel">
                                    <p>
                                        <strong>{__('Status:', 'coco-seo')}</strong> 
                                        <span className={`coco-seo-status coco-seo-${indexingStatus}`}>
                                            {indexingStatus === 'indexed' 
                                                ? __('Indexed', 'coco-seo') 
                                                : __('Not Indexed', 'coco-seo')}
                                        </span>
                                    </p>
                                    
                                    {indexingChecked && (
                                        <p>
                                            <strong>{__('Last Checked:', 'coco-seo')}</strong> 
                                            {indexingChecked}
                                        </p>
                                    )}
                                </div>
                            ) : (
                                <p>{__('This page has not been checked yet.', 'coco-seo')}</p>
                            )}
                            
                            <div className="coco-seo-button-container">
                                <Button
                                    isSecondary
                                    onClick={checkIndexingStatus}
                                    isBusy={loading}
                                    disabled={loading}
                                >
                                    {loading 
                                        ? __('Checking...', 'coco-seo') 
                                        : __('Check Indexing Status', 'coco-seo')}
                                </Button>
                            </div>
                        </PanelBody>
                    </>
                )}
            </PluginSidebar>
        </>
    );
}