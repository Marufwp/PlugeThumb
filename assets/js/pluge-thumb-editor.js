( function ( wp ) {
    const { registerPlugin } = wp.plugins;
    const { PluginSidebar, PluginSidebarMoreMenuItem } = wp.editPost;
    const { PanelBody, TextControl, Button } = wp.components;
    const { withSelect, withDispatch } = wp.data;
    const { createElement } = wp.element;

    const Sidebar = ( props ) => {
        const { meta, setMeta } = props;

        const openMedia = ( type ) => {
            const frame = wp.media({
                title: type === 'video' ? 'Select or Upload Video' : 'Select or Upload Image',
                button: { text: 'Use this ' + type },
                library: { type: type },
                multiple: false
            });

            frame.on( 'select', function () {
                const attachment = frame.state().get('selection').first().toJSON();
                if ( type === 'video' ) {
                    setMeta( { [PLUGETHUMB.metaVideo]: attachment.id } );
                    setMeta( { [PLUGETHUMB.metaYt]: attachment.url } );
                } else {
                    setMeta( { _plugethumb_image_url: attachment.url } ); // optional image meta if you use it
                }
            } );

            frame.open();
        };

        return createElement(
            PluginSidebar,
            { name: 'plugethumb-sidebar', title: PLUGETHUMB.i18n.panelTitle, icon: 'format-video' },
            createElement( PanelBody, { initialOpen: true, title: 'Video' },
                createElement( TextControl, {
                    label: PLUGETHUMB.i18n.ytLabel,
                    value: meta[ PLUGETHUMB.metaYt ] || '',
                    onChange: ( val ) => setMeta( { [PLUGETHUMB.metaYt]: val } ),
                    placeholder: 'https://www.youtube.com/watch?v=...'
                } ),
                createElement( Button, { isSecondary: true, onClick: () => openMedia( 'video' ) }, PLUGETHUMB.i18n.videoLabel )
            )
        );
    };

    const SidebarWithSelect = withSelect( ( select ) => {
        return {
            meta: select( 'core/editor' ).getEditedPostAttribute( 'meta' ) || {}
        };
    } )( Sidebar );

    const SidebarWithDispatch = withDispatch( ( dispatch ) => {
        return {
            setMeta( newMeta ) {
                const edit = dispatch( 'core/editor' ).editPost;
                edit( { meta: Object.assign( {}, wp.data.select('core/editor').getEditedPostAttribute('meta') || {}, newMeta ) } );
            }
        };
    } )( SidebarWithSelect );

    registerPlugin( 'plugethumb-sidebar', {
        render: SidebarWithDispatch
    } );
} )( window.wp );
