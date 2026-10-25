/* Demfilz Tier Pricing admin behaviour. */
( function ( $ ) {
	'use strict';

	$( function () {
		// Confirm dialogs for delete/remove links.
		$( document ).on( 'click', '.dtpw-confirm', function ( e ) {
			var message = $( this ).data( 'confirm' );
			if ( message && ! window.confirm( message ) ) {
				e.preventDefault();
			}
		} );

		// Tier form: amount hint follows the selected adjustment kind; the
		// amount row disappears for "specific prices per product" tiers,
		// which take their prices on each product's edit screen instead.
		var updateHint = function () {
			var type = $( 'input[name="tier_type"]:checked' ).val();
			var $hint = $( '.dtpw-amount-hint' );
			var $amount = $( '#dtpw-tier-amount' );
			if ( ! type ) {
				return;
			}
			if ( $hint.length ) {
				$hint.text( $hint.data( type ) || '' );
			}
			if ( $amount.length ) {
				if ( 'price' === type ) {
					$amount.closest( '.dtpw-amount-row' ).hide();
					$( '.dtpw-price-note' ).removeClass( 'dtpw-note-hidden' );
				} else {
					$amount.closest( '.dtpw-amount-row' ).show();
					$( '.dtpw-price-note' ).addClass( 'dtpw-note-hidden' );
				}
			}
		};
		$( document ).on( 'change', 'input[name="tier_type"]', updateHint );
		updateHint();

		// Product overrides: show custom fields only for Custom mode.
		var syncRow = function ( $select ) {
			var $cell = $select.closest( 'tr' ).find( '.dtpw-custom-fields' );
			if ( 'custom' === $select.val() ) {
				$cell.removeClass( 'dtpw-hidden' );
			} else {
				$cell.addClass( 'dtpw-hidden' );
			}
		};
		$( '.dtpw-overrides-table .dtpw-mode' ).each( function () {
			syncRow( $( this ) );
		} );
		$( document ).on( 'change', '.dtpw-overrides-table .dtpw-mode', function () {
			syncRow( $( this ) );
		} );

		// Override rows: a specific price must not be negative there either.
		var syncOverrideMin = function ( $select ) {
			var $input = $select.closest( '.dtpw-custom-fields' ).find( 'input[type="number"]' );
			if ( 'price' === $select.val() ) {
				$input.attr( 'min', '0' );
			} else {
				$input.removeAttr( 'min' );
			}
		};
		$( '.dtpw-custom-fields select' ).each( function () {
			syncOverrideMin( $( this ) );
		} );
		$( document ).on( 'change', '.dtpw-custom-fields select', function () {
			syncOverrideMin( $( this ) );
		} );
	} );
} )( jQuery );
