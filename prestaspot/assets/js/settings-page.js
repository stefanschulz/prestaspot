( function () {
    // Hidden companion input for checkboxes (see the settings page's
    // "checkbox persistence" pattern) shares the same name - excluded here
    // so a checkbox's default is only ever compared/applied against the
    // actual checkbox, never its hidden sibling.
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
            return;
        }
        if ( 'radio' === first.type ) {
            fields.forEach( function ( field ) {
                field.checked = field.value === defaultValue;
            } );
            return;
        }
        first.value = defaultValue;
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
