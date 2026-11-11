<?php
/**
 * Server side output of the Moonetix Balance block.
 *
 * @package Moonetix_Offerwall
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( get_current_user_id() > 0 ) {
	Moonetix_Offerwall_Frontend::private_page();
	echo '<span ' . get_block_wrapper_attributes() . '>' . esc_html( Moonetix_Offerwall_Frontend::balance_text( get_current_user_id() ) ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_block_wrapper_attributes() returns escaped attributes.
}
