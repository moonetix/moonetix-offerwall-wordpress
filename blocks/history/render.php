<?php
/**
 * Server side output of the Moonetix Reward History block.
 *
 * @package Moonetix_Offerwall
 *
 * @var array $attributes Block attributes.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$moonetix_offerwall_limit = isset( $attributes['limit'] ) ? absint( $attributes['limit'] ) : 20;
echo wp_kses_post( Moonetix_Offerwall_Frontend::history_shortcode( array( 'limit' => $moonetix_offerwall_limit ) ) );
