( function () {
    // Excludes the hidden companion input checkboxes have (same name).
    function getFields( name ) {
        return Array.from( document.getElementsByName( name ) ).filter( function ( field ) {
            return 'hidden' !== field.type;
        } );
    }

    function currentValue( fields ) {
        const first = fields[ 0 ];
        if ( 'checkbox' === first.type ) {
            return first.checked ? '1' : '0';
        }
        if ( 'radio' === first.type ) {
            const checked = fields.find( function ( field ) {
                return field.checked;
            } );
            return checked ? checked.value : '';
        }
        return first.value;
    }

    function applyDefault( fields, defaultValue ) {
        const first = fields[ 0 ];
        if ( 'checkbox' === first.type ) {
            first.checked = '1' === defaultValue;
        } else if ( 'radio' === first.type ) {
            fields.forEach( function ( field ) {
                field.checked = field.value === defaultValue;
            } );
        } else {
            first.value = defaultValue;
        }

        // Lets other scripts (e.g. settings-color-picker.js) that watch a field
        // via 'change' notice a reset even though it wasn't a real user edit.
        fields.forEach( function ( field ) {
            field.dispatchEvent( new Event( 'change', { bubbles: true } ) );
        } );
    }

    function initResetButton( button ) {
        const name = button.getAttribute( 'data-target' );
        const defaultValue = button.getAttribute( 'data-default' );
        const fields = getFields( name );
        if ( ! fields.length ) {
            return;
        }

        function refresh() {
            button.classList.toggle( 'is-visible', currentValue( fields ) !== defaultValue );
        }

        refresh();

        fields.forEach( function ( field ) {
            field.addEventListener( 'input', refresh );
            field.addEventListener( 'change', refresh );
        } );

        button.addEventListener( 'click', function () {
            applyDefault( fields, defaultValue );
            refresh();
        } );
    }

    document.addEventListener( 'DOMContentLoaded', function () {
        Array.from( document.querySelectorAll( '.prestaspot-field-reset' ) ).forEach( initResetButton );
    } );
} )();
