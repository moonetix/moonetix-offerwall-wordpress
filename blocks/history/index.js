( function ( blocks, element, blockEditor, components, i18n ) {
	var el = element.createElement;
	var __ = i18n.__;
	blocks.registerBlockType( 'moonetix-offerwall/history', {
		edit: function ( props ) {
			return el(
				element.Fragment,
				null,
				el(
					blockEditor.InspectorControls,
					null,
					el(
						components.PanelBody,
						{ title: __( 'Settings', 'moonetix-offerwall' ) },
						el( components.RangeControl, {
							label: __( 'Number of rewards', 'moonetix-offerwall' ),
							value: props.attributes.limit || 20,
							min: 1,
							max: 100,
							onChange: function ( value ) {
								props.setAttributes( { limit: value || 20 } );
							}
						} )
					)
				),
				el(
					'div',
					blockEditor.useBlockProps( { className: 'moonetix-offerwall-placeholder' } ),
					el( 'strong', null, __( 'Moonetix Reward History', 'moonetix-offerwall' ) ),
					el( 'p', null, __( 'Logged-in users see their latest rewards here.', 'moonetix-offerwall' ) )
				)
			);
		},
		save: function () {
			return null;
		}
	} );
} )( window.wp.blocks, window.wp.element, window.wp.blockEditor, window.wp.components, window.wp.i18n );
