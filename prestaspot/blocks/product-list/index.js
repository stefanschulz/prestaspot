( function ( blocks, element, blockEditor, components, ServerSideRender, i18n ) {
    const el = element.createElement;
    const __ = i18n.__;
    const registerBlockType = blocks.registerBlockType;
    const InspectorControls = blockEditor.InspectorControls;
    const PanelColorSettings = blockEditor.PanelColorSettings;
    const useBlockProps = blockEditor.useBlockProps;
    const PanelBody = components.PanelBody;
    const RangeControl = components.RangeControl;
    const TextControl = components.TextControl;
    const ToggleControl = components.ToggleControl;

    const LAYOUT_OPTIONS = [
        { value: 'image_name_description', order: [ 'image', 'name', 'description' ], label: __( 'Image, Name, Description', 'prestaspot' ) },
        { value: 'name_image_description', order: [ 'name', 'image', 'description' ], label: __( 'Name, Image, Description', 'prestaspot' ) },
        { value: 'name_description_image', order: [ 'name', 'description', 'image' ], label: __( 'Name, Description, Image', 'prestaspot' ) },
    ];

    const VIEW_MODE_OPTIONS = [
        { value: 'grid', cells: 4, label: __( 'Grid', 'prestaspot' ) },
        { value: 'list', cells: 3, label: __( 'List', 'prestaspot' ) },
    ];

    const LINK_STYLE_OPTIONS = [
        { value: 'link', label: __( 'Link', 'prestaspot' ) },
        { value: 'button', label: __( 'Button', 'prestaspot' ) },
    ];

    function renderLinkStylePicker( linkStyle, buttonColor, radioGroupName, onChange ) {
        return el(
            'div',
            { className: 'prestaspot-layout-picker' },
            LINK_STYLE_OPTIONS.map( function ( option ) {
                return el(
                    'label',
                    { key: option.value, className: 'prestaspot-layout-option' },
                    el( 'input', {
                        type: 'radio',
                        name: radioGroupName,
                        value: option.value,
                        checked: linkStyle === option.value,
                        onChange: function () {
                            onChange( option.value );
                        },
                    } ),
                    el(
                        'span',
                        { className: 'prestaspot-linkstyle-preview prestaspot-linkstyle-preview--' + option.value },
                        el( 'span', 'button' === option.value ? { style: { backgroundColor: buttonColor } } : {} )
                    ),
                    el( 'span', { className: 'prestaspot-layout-label' }, option.label )
                );
            } )
        );
    }

    function renderViewModePicker( viewMode, radioGroupName, onChange ) {
        return el(
            'div',
            { className: 'prestaspot-layout-picker' },
            VIEW_MODE_OPTIONS.map( function ( option ) {
                return el(
                    'label',
                    { key: option.value, className: 'prestaspot-layout-option' },
                    el( 'input', {
                        type: 'radio',
                        name: radioGroupName,
                        value: option.value,
                        checked: viewMode === option.value,
                        onChange: function () {
                            onChange( option.value );
                        },
                    } ),
                    el(
                        'span',
                        { className: 'prestaspot-viewmode-preview prestaspot-viewmode-preview--' + option.value },
                        Array.from( { length: option.cells } ).map( function ( _, index ) {
                            return el( 'span', { key: index } );
                        } )
                    ),
                    el( 'span', { className: 'prestaspot-layout-label' }, option.label )
                );
            } )
        );
    }

    function renderLayoutPicker( layout, radioGroupName, onChange ) {
        return el(
            'div',
            { className: 'prestaspot-layout-picker' },
            LAYOUT_OPTIONS.map( function ( option ) {
                return el(
                    'label',
                    { key: option.value, className: 'prestaspot-layout-option' },
                    el( 'input', {
                        type: 'radio',
                        name: radioGroupName,
                        value: option.value,
                        checked: layout === option.value,
                        onChange: function () {
                            onChange( option.value );
                        },
                    } ),
                    el(
                        'span',
                        { className: 'prestaspot-layout-preview' },
                        option.order.map( function ( elementName ) {
                            return el( 'span', {
                                key: elementName,
                                className: 'prestaspot-layout-block prestaspot-layout-block--' + elementName,
                            } );
                        } )
                    ),
                    el( 'span', { className: 'prestaspot-layout-label' }, option.label )
                );
            } )
        );
    }

    registerBlockType( 'prestaspot/product-list', {
        edit: function ( props ) {
            const attributes = props.attributes;
            const setAttributes = props.setAttributes;
            const blockProps = useBlockProps();

            return el(
                'div',
                blockProps,
                el(
                    InspectorControls,
                    {},
                    el(
                        PanelBody,
                        { title: __( 'Display Mode', 'prestaspot' ) },
                        renderViewModePicker( attributes.viewMode, 'prestaspot-viewmode-' + props.clientId, function ( value ) {
                            setAttributes( { viewMode: value } );
                        } ),
                        el( 'p', { className: 'components-base-control__help' }, __( 'Grid shows products as cards; list stacks them as rows with a smaller image.', 'prestaspot' ) )
                    ),
                    el(
                        PanelBody,
                        { title: __( 'Product List Settings', 'prestaspot' ) },
                        el( RangeControl, {
                            label: __( 'Number of Products', 'prestaspot' ),
                            value: attributes.productCount,
                            onChange: function ( value ) {
                                setAttributes( { productCount: value } );
                            },
                            min: 1,
                            max: 24,
                        } ),
                        el( RangeControl, {
                            label: __( 'Columns', 'prestaspot' ),
                            help: __( 'Only used in grid display mode.', 'prestaspot' ),
                            value: attributes.columns,
                            onChange: function ( value ) {
                                setAttributes( { columns: value } );
                            },
                            min: 1,
                            max: 6,
                        } ),
                        el( TextControl, {
                            label: __( 'Category ID', 'prestaspot' ),
                            type: 'number',
                            value: attributes.categoryId,
                            help: __( '0 shows products regardless of category.', 'prestaspot' ),
                            onChange: function ( value ) {
                                setAttributes( { categoryId: parseInt( value, 10 ) || 0 } );
                            },
                        } ),
                        el( ToggleControl, {
                            label: __( 'On Sale Only', 'prestaspot' ),
                            checked: attributes.onSale,
                            onChange: function ( value ) {
                                setAttributes( { onSale: value } );
                            },
                        } )
                    ),
                    el(
                        PanelBody,
                        { title: __( 'Card Layout', 'prestaspot' ) },
                        renderLayoutPicker( attributes.layout, 'prestaspot-layout-' + props.clientId, function ( value ) {
                            setAttributes( { layout: value } );
                        } ),
                        el( 'p', { className: 'components-base-control__help' }, __( 'The "View in shop" link always renders last.', 'prestaspot' ) )
                    ),
                    el(
                        PanelBody,
                        { title: __( 'Product Elements', 'prestaspot' ) },
                        el( ToggleControl, {
                            label: __( 'Show Image', 'prestaspot' ),
                            checked: attributes.showImage,
                            onChange: function ( value ) {
                                setAttributes( { showImage: value } );
                            },
                        } ),
                        el( ToggleControl, {
                            label: __( 'Show Name', 'prestaspot' ),
                            checked: attributes.showName,
                            onChange: function ( value ) {
                                setAttributes( { showName: value } );
                            },
                        } ),
                        el( ToggleControl, {
                            label: __( 'Show Description', 'prestaspot' ),
                            checked: attributes.showDescription,
                            onChange: function ( value ) {
                                setAttributes( { showDescription: value } );
                            },
                        } ),
                        el( ToggleControl, {
                            label: __( 'Show Price', 'prestaspot' ),
                            checked: attributes.showPrice,
                            onChange: function ( value ) {
                                setAttributes( { showPrice: value } );
                            },
                        } )
                    ),
                    el(
                        PanelBody,
                        { title: __( 'Shop Link', 'prestaspot' ) },
                        el( TextControl, {
                            label: __( 'Label', 'prestaspot' ),
                            value: attributes.linkText,
                            placeholder: __( 'View in shop', 'prestaspot' ),
                            onChange: function ( value ) {
                                setAttributes( { linkText: value } );
                            },
                        } ),
                        renderLinkStylePicker( attributes.linkStyle, attributes.buttonColor, 'prestaspot-linkstyle-' + props.clientId, function ( value ) {
                            setAttributes( { linkStyle: value } );
                        } )
                    ),
                    'button' === attributes.linkStyle && el( PanelColorSettings, {
                        title: __( 'Shop Link Button Color', 'prestaspot' ),
                        initialOpen: false,
                        colorSettings: [
                            {
                                value: attributes.buttonColor,
                                onChange: function ( color ) {
                                    setAttributes( { buttonColor: color || '#2271b1' } );
                                },
                                label: __( 'Background Color', 'prestaspot' ),
                            },
                        ],
                    } )
                ),
                el( ServerSideRender, {
                    block: 'prestaspot/product-list',
                    attributes: attributes,
                } )
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
    window.wp.serverSideRender,
    window.wp.i18n
);
