( function ( blocks, element, blockEditor, components, ServerSideRender, i18n ) {
    var el = element.createElement;
    var __ = i18n.__;
    var registerBlockType = blocks.registerBlockType;
    var InspectorControls = blockEditor.InspectorControls;
    var useBlockProps = blockEditor.useBlockProps;
    var PanelBody = components.PanelBody;
    var RangeControl = components.RangeControl;
    var TextControl = components.TextControl;

    registerBlockType( 'prestaspot/product-list', {
        edit: function ( props ) {
            var attributes = props.attributes;
            var setAttributes = props.setAttributes;
            var blockProps = useBlockProps();

            return el(
                'div',
                blockProps,
                el(
                    InspectorControls,
                    {},
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
                        } )
                    )
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
