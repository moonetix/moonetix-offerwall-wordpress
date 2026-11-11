( function ( blocks, element, blockEditor, i18n ) {
	var el = element.createElement;
	var __ = i18n.__;
	blocks.registerBlockType( 'moonetix-offerwall/balance', {
		edit: function () {
			return el( 'span', blockEditor.useBlockProps(), __( '1,250 Points (balance of the logged-in user)', 'moonetix-offerwall' ) );
		},
		save: function () {
			return null;
		}
	} );
} )( window.wp.blocks, window.wp.element, window.wp.blockEditor, window.wp.i18n );
