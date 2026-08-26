( function ( wp ) {
    const { createElement, render, useState, useEffect } = wp.element;
    const { ColorPalette } = wp.components;

    // input stays type="text" (not "hidden") so the reset-button script in
    // settings-page.js still finds and drives it; only visually hidden here.
    function mount( input ) {
        input.classList.add( 'prestaspot-color-input--enhanced' );

        const wrapper = document.createElement( 'div' );
        wrapper.className = 'prestaspot-color-palette';
        input.insertAdjacentElement( 'afterend', wrapper );

        function App() {
            const [ color, setColor ] = useState( input.value );

            useEffect( function () {
                function syncFromInput() {
                    setColor( input.value );
                }
                input.addEventListener( 'change', syncFromInput );
                return function () {
                    input.removeEventListener( 'change', syncFromInput );
                };
            }, [] );

            function onChange( value ) {
                const next = value || '';
                input.value = next;
                input.dispatchEvent( new Event( 'change', { bubbles: true } ) );
                setColor( next );
            }

            return createElement( ColorPalette, {
                colors: window.prestaspotColorPicker.colors,
                value: color,
                onChange: onChange,
                clearable: false,
            } );
        }

        render( createElement( App ), wrapper );
    }

    document.addEventListener( 'DOMContentLoaded', function () {
        ( window.prestaspotColorPicker.fields || [] ).forEach( function ( id ) {
            const input = document.getElementById( id );
            if ( input ) {
                mount( input );
            }
        } );
    } );
} )( window.wp );
