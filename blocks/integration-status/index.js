( function ( blocks, element, blockEditor, components, i18n, serverSideRender ) {
    'use strict';

    var registerBlockType = blocks.registerBlockType;
    var createElement = element.createElement;
    var Fragment = element.Fragment;
    var InspectorControls = blockEditor.InspectorControls;
    var useBlockProps = blockEditor.useBlockProps;
    var PanelBody = components.PanelBody;
    var TextControl = components.TextControl;
    var ToggleControl = components.ToggleControl;
    var Notice = components.Notice;
    var __ = i18n.__;
    var ServerSideRender = serverSideRender && serverSideRender.default
        ? serverSideRender.default
        : serverSideRender;

    var textDomain = 'wp-integration-toolkit';

    function PreviewUnavailable() {
        return createElement(
            Notice,
            {
                status: 'warning',
                isDismissible: false,
            },
            __( 'Live preview is unavailable in this editor session.', textDomain )
        );
    }

    registerBlockType( 'wp-integration-toolkit/integration-status', {
        apiVersion: 2,
        title: __( 'Integration Status', textDomain ),
        description: __(
            'Display whether WP Integration Toolkit is configured without exposing secrets or endpoint details.',
            textDomain
        ),
        category: 'widgets',
        icon: 'yes-alt',
        attributes: {
            heading: {
                type: 'string',
                default: 'Integration status',
            },
            configuredLabel: {
                type: 'string',
                default: 'Integration configured',
            },
            notConfiguredLabel: {
                type: 'string',
                default: 'Integration not configured',
            },
            showDescription: {
                type: 'boolean',
                default: true,
            },
        },
        supports: {
            html: false,
            anchor: true,
            align: [ 'wide', 'full' ],
            color: {
                text: true,
                background: true,
            },
            spacing: {
                margin: true,
                padding: true,
            },
        },
        edit: function ( props ) {
            var attributes = props.attributes;
            var setAttributes = props.setAttributes;
            var blockProps = useBlockProps( {
                className: 'wpitk-integration-status-editor',
            } );
            var Preview = ServerSideRender || PreviewUnavailable;

            return createElement(
                Fragment,
                null,
                createElement(
                    InspectorControls,
                    null,
                    createElement(
                        PanelBody,
                        {
                            title: __( 'Status content', textDomain ),
                            initialOpen: true,
                        },
                        createElement( TextControl, {
                            label: __( 'Heading', textDomain ),
                            value: attributes.heading || '',
                            onChange: function ( value ) {
                                setAttributes( { heading: value } );
                            },
                        } ),
                        createElement( TextControl, {
                            label: __( 'Configured label', textDomain ),
                            value: attributes.configuredLabel || '',
                            onChange: function ( value ) {
                                setAttributes( { configuredLabel: value } );
                            },
                        } ),
                        createElement( TextControl, {
                            label: __( 'Not configured label', textDomain ),
                            value: attributes.notConfiguredLabel || '',
                            onChange: function ( value ) {
                                setAttributes( { notConfiguredLabel: value } );
                            },
                        } ),
                        createElement( ToggleControl, {
                            label: __( 'Show status description', textDomain ),
                            checked: attributes.showDescription !== false,
                            onChange: function ( value ) {
                                setAttributes( { showDescription: value } );
                            },
                        } )
                    )
                ),
                createElement(
                    'div',
                    blockProps,
                    createElement(
                        Notice,
                        {
                            status: 'info',
                            isDismissible: false,
                        },
                        __(
                            'This is a dynamic block. The preview below is rendered by PHP using the current plugin configuration.',
                            textDomain
                        )
                    ),
                    createElement( Preview, {
                        block: 'wp-integration-toolkit/integration-status',
                        attributes: attributes,
                        skipBlockSupportAttributes: true,
                    } )
                )
            );
        },
        save: function () {
            return null;
        },
    } );
} )(
    window.wp.blocks,
    window.wp.element,
    window.wp.blockEditor,
    window.wp.components,
    window.wp.i18n,
    window.wp.serverSideRender
);
