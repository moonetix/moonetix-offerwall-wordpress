<?php
/**
 * Server side output of the Moonetix Offerwall block.
 *
 * @package Moonetix_Offerwall
 *
 * @var array $attributes Block attributes.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$moonetix_offerwall_height = isset( $attributes['height'] ) ? absint( $attributes['height'] ) : 0;
echo wp_kses( Moonetix_Offerwall_Frontend::render( $moonetix_offerwall_height ), Moonetix_Offerwall_Frontend::allowed_html() );
