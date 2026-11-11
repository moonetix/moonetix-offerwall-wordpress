( function ( blocks, element, blockEditor, components, i18n ) {
	var el = element.createElement;
	var __ = i18n.__;
	blocks.registerBlockType( 'moonetix-offerwall/offerwall', {
		edit: function ( props ) {
			var height = props.attributes.height || 0;
			return el(
				element.Fragment,
				null,
				el(
					blockEditor.InspectorControls,
					null,
					el(
						components.PanelBody,
						{ title: __( 'Size', 'moonetix-offerwall' ) },
						el( components.RangeControl, {
							label: __( 'Height in pixels (0 uses the plugin setting)', 'moonetix-offerwall' ),
							value: height,
							min: 0,
							max: 3000,
							step: 50,
							onChange: function ( value ) {
								props.setAttributes( { height: value || 0 } );
							}
						} )
					)
				),
				el(
					'div',
					blockEditor.useBlockProps( { className: 'moonetix-offerwall-placeholder' } ),
					el( 'strong', null, __( 'Moonetix Offerwall', 'moonetix-offerwall' ) ),
					el( 'p', null, __( 'Logged-in visitors see the offerwall here. Logged-out visitors see a login link.', 'moonetix-offerwall' ) )
				)
			);
		},
		save: function () {
			return null;
		}
	} );
} )( window.wp.blocks, window.wp.element, window.wp.blockEditor, window.wp.components, window.wp.i18n );
