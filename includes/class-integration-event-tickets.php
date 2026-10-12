<?php
/**
 * Integration class for The Events Calendar / Event Tickets (Tickets Commerce).
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) exit;


class SliceWP_Integration_Event_Tickets extends SliceWP_Integration {

	/**
	 * Constructor: sets name and supported commission types.
	 */
	public function __construct() {

		$this->name = 'The Events Calendar / Event Tickets';

		$this->supports = apply_filters( 'slicewp_integration_supports_event_tickets', array(
			'commission_types' => array( 'sale' ),
		) );

	}


	/**
	 * Returns a standardised order-data array for a Tickets Commerce order post.
	 *
	 * @param int|WP_Post $order  Order ID or post object.
	 *
	 * @return array|null
	 */
	public function get_formatted_order_data( $order ) {

		if ( is_numeric( $order ) ) {
			$order = get_post( (int) $order );
		}

		if ( empty( $order ) || ! ( $order instanceof WP_Post ) ) {
			return null;
		}

		$order_id = $order->ID;

		// Determine type: sale or refund. Early return if invalid status.
		$status = $order->post_status;

		if ( $status === 'tec-tc-refunded' ) {
			$order_type = 'refund';
		} elseif ( in_array( $status, array( 'tec-tc-completed', 'tec-tc-approved' ), true ) ) {
			$order_type = 'sale';
		} else {
			return null;
		}

		// Currency.
		$currency = get_post_meta( $order_id, '_tec_tc_order_currency', true );
		if ( empty( $currency ) ) {
			if ( is_callable( array( '\\TEC\\Tickets\\Commerce\\Utils\\Currency', 'get_currency_code' ) ) ) {
				$currency = \TEC\Tickets\Commerce\Utils\Currency::get_currency_code();
			} elseif ( function_exists( 'get_woocommerce_currency' ) ) {
				$currency = get_woocommerce_currency();
			} else {
				$currency = slicewp_get_setting( 'active_currency', 'USD' );
			}
		}

		// Amounts (stored as integers in cents).
		$total_raw    = (int) get_post_meta( $order_id, '_tec_tc_order_total_value', true );
		$subtotal_raw = (int) get_post_meta( $order_id, '_tec_tc_order_subtotal_value', true );

		$total    = slicewp_sanitize_amount( $total_raw / 100, $currency );
		$subtotal = slicewp_sanitize_amount( $subtotal_raw / 100, $currency );
		$tax      = slicewp_sanitize_amount( 0, $currency ); // Event Tickets doesn't expose per-order tax.

		$data = array(
			'origin'    => 'event_tickets',
			'reference' => $order_id,
			'type'      => $order_type,
			'currency'  => $currency,
			'subtotal'  => $subtotal,
			'total'     => $total,
			'tax'       => $tax,
		);

		// Build line items from ticket meta.
		$items            = array();
		$ticket_meta_key  = '_tec_tc_order_tickets_in_order';
		$tickets_in_order = get_post_meta( $order_id, $ticket_meta_key );

		if ( ! empty( $tickets_in_order ) ) {

			// $tickets_in_order is an array of ticket post IDs (one per attendee slot).
			$ticket_counts = array_count_values( $tickets_in_order );

			// Warm ticket meta cache so later get_post_meta calls are cache hits.
			update_meta_cache( 'post', array_keys( $ticket_counts ) );

			// Batch-fetch ticket posts to avoid N+1 queries.
			$ticket_posts = get_posts( array(
				'post__in'   => array_keys( $ticket_counts ),
				'post_type'  => 'tribe_events_ticket',
				'numberposts' => -1,
				'orderby'    => 'post__in',
			) );

			$tickets_by_id = wp_list_pluck( $ticket_posts, 'post_title', 'ID' );

			foreach ( $ticket_counts as $ticket_post_id => $quantity ) {

				$ticket_name  = isset( $tickets_by_id[ $ticket_post_id ] ) ? $tickets_by_id[ $ticket_post_id ] : '';
				$ticket_price = (int) get_post_meta( $ticket_post_id, '_price', true );
				$ticket_total = slicewp_sanitize_amount( ( $ticket_price / 100 ) * $quantity, $currency );

				$items[] = array(
					'type'      => 'ticket',
					'name'      => sanitize_text_field( $ticket_name ),
					'quantity'  => $quantity,
					'subtotal'  => $ticket_total,
					'total'     => $ticket_total,
					'tax'       => slicewp_sanitize_amount( 0, $currency ),
					'meta_data' => array(
						'ticket_id' => absint( $ticket_post_id ),
					),
				);

			}

		}

		$data['items'] = $items;

		return $data;

	}


	/**
	 * Returns the admin edit URL for an Event Tickets order.
	 *
	 * @param int $order_id
	 *
	 * @return string
	 */
	public function get_order_admin_url( $order_id ) {

		return add_query_arg(
			array(
				'post'   => absint( $order_id ),
				'action' => 'edit',
			),
			admin_url( 'post.php' )
		);

	}

}
