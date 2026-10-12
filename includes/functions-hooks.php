<?php
/**
 * Action and filter hooks for the SliceWP Event Tickets integration.
 *
 * Loaded only when the integration is enabled in SliceWP settings AND the
 * Event Tickets plugin is active.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) exit;

// -----------------------------------------------------------------------
// Commission lifecycle hooks
// -----------------------------------------------------------------------

// Insert a pending commission when a Tickets Commerce checkout completes.
add_action( 'tec_tickets_commerce_order_checkout_completed', 'slicewp_et_insert_pending_commission', 10, 1 );

// Mark commission as "unpaid" (ready to pay) when the order reaches Completed.
add_action( 'tec_tickets_commerce_order_status_completed', 'slicewp_et_accept_pending_commission', 10, 3 );

// Also accept on the Approved status (some gateway flows use it).
add_action( 'tec_tickets_commerce_order_status_approved', 'slicewp_et_accept_pending_commission', 10, 3 );

// Reject commission when an order is refunded.
add_action( 'tec_tickets_commerce_order_status_refunded', 'slicewp_et_reject_commission_on_refund', 10, 3 );

// Reject commission when an order is voided or reversed.
add_action( 'tec_tickets_commerce_order_status_voided',   'slicewp_et_reject_commission_on_void', 10, 3 );
add_action( 'tec_tickets_commerce_order_status_reversed', 'slicewp_et_reject_commission_on_void', 10, 3 );

// Add a clickable reference link in the SliceWP commissions list table.
add_filter( 'slicewp_list_table_commissions_column_reference',        'slicewp_et_list_table_reference_link', 10, 2 );
add_filter( 'slicewp_list_table_payout_commissions_column_reference', 'slicewp_et_list_table_reference_link', 10, 2 );

// Store reference amount whenever a commission is inserted or updated.
add_filter( 'slicewp_pre_insert_commission_data', 'slicewp_et_add_reference_amount_to_commission' );
add_filter( 'slicewp_pre_update_commission_data', 'slicewp_et_add_reference_amount_to_commission' );


// -----------------------------------------------------------------------
// Insert pending commission
// -----------------------------------------------------------------------

/**
 * Creates a pending SliceWP commission when a Tickets Commerce checkout completes.
 *
 * @param int $order_id  The tec_tc_order post ID.
 */
function slicewp_et_insert_pending_commission( $order_id ) {

	$order_id = absint( $order_id );

	if ( empty( $order_id ) ) {
		return;
	}

	// Add an order-scoped lock to avoid duplicate inserts during concurrent retries.
	$lock_key       = 'slicewp_et_lock_order_' . $order_id;
	$lock_timestamp = (int) get_option( $lock_key, 0 );

	if ( $lock_timestamp > 0 && ( time() - $lock_timestamp ) > 300 ) {
		delete_option( $lock_key );
	}

	if ( ! add_option( $lock_key, time(), '', false ) ) {
		slicewp_add_log( 'EVENT TICKETS: Pending commission insert skipped because the order is already being processed.' );
		return;
	}

	try {

	// Retrieve the referring affiliate from the visitor cookie / session.
	$affiliate_id = slicewp_get_referrer_affiliate_id();
	$visit_id     = slicewp_get_referrer_visit_id();

	/**
	 * Allow add-ons / custom code to override the affiliate ID for Event Tickets.
	 *
	 * @param int $affiliate_id
	 * @param int $order_id
	 */
	$affiliate_id = apply_filters( 'slicewp_referrer_affiliate_id_event_tickets', $affiliate_id, $order_id );

	if ( empty( $affiliate_id ) ) {
		return;
	}

	// Bail if the affiliate account is not valid/active.
	if ( ! slicewp_is_affiliate_valid( $affiliate_id ) ) {
		slicewp_add_log( 'EVENT TICKETS: Pending commission was not created because the affiliate is not valid.' );
		return;
	}

	// Prevent duplicate commissions for the same order.
	$existing = slicewp_get_commissions( array( 'reference' => $order_id, 'origin' => 'event_tickets' ) );

	if ( ! empty( $existing ) ) {
		slicewp_add_log( 'EVENT TICKETS: Pending commission was not created because a commission for this order already exists.' );
		return;
	}

	$order = get_post( $order_id );

	if ( empty( $order ) || $order->post_type !== 'tec_tc_order' ) {
		slicewp_add_log( 'EVENT TICKETS: Pending commission was not created because the order post could not be found.' );
		return;
	}

	// Fetch purchaser data once (used for both self-referral check and customer args).
	$purchaser_email      = get_post_meta( $order_id, '_tec_tc_order_purchaser_email', true );
	$purchaser_first_name = get_post_meta( $order_id, '_tec_tc_order_purchaser_first_name', true );
	$purchaser_last_name  = get_post_meta( $order_id, '_tec_tc_order_purchaser_last_name', true );
	$purchaser_user_id    = absint( get_post_meta( $order_id, '_tec_tc_order_purchaser_user_id', true ) );

	// Prevent self-referral unless the setting permits it.
	if ( empty( slicewp_get_setting( 'affiliate_own_commissions' ) ) ) {

		$affiliate = slicewp_get_affiliate( $affiliate_id );

		if ( is_user_logged_in() && get_current_user_id() == $affiliate->get( 'user_id' ) ) {
			slicewp_add_log( 'EVENT TICKETS: Pending commission was not created because the customer is also the affiliate.' );
			return;
		}

		if ( ! empty( $purchaser_email ) && slicewp_affiliate_has_email( $affiliate_id, $purchaser_email ) ) {
			slicewp_add_log( 'EVENT TICKETS: Pending commission was not created because the customer is also the affiliate.' );
			return;
		}

	}

	$customer_args = array(
		'email'        => sanitize_email( $purchaser_email ),
		'user_id'      => $purchaser_user_id,
		'first_name'   => sanitize_text_field( $purchaser_first_name ),
		'last_name'    => sanitize_text_field( $purchaser_last_name ),
		'affiliate_id' => $affiliate_id,
	);

	$customer_id = slicewp_process_customer( $customer_args );

	if ( $customer_id ) {
		slicewp_add_log( sprintf( 'EVENT TICKETS: Customer #%s has been successfully processed.', $customer_id ) );
	} else {
		slicewp_add_log( 'EVENT TICKETS: Customer could not be processed due to an unexpected error.' );
	}

	// Build the standardised order data array.
	$formatted = slicewp()->integrations['event_tickets']->get_formatted_order_data( $order );

	if ( empty( $formatted ) ) {
		slicewp_add_log( 'EVENT TICKETS: Pending commission was not created because the order data could not be formatted.' );
		return;
	}

	// Currency conversion.
	$active_currency = slicewp_get_setting( 'active_currency', 'USD' );
	$order_currency  = $formatted['currency'];

	$transaction_data = $formatted;

	$transaction_data['original_currency'] = $transaction_data['currency'];
	$transaction_data['original_subtotal'] = $transaction_data['subtotal'];
	$transaction_data['original_total']    = $transaction_data['total'];
	$transaction_data['original_tax']      = $transaction_data['tax'];

	$transaction_data['currency'] = $active_currency;
	$transaction_data['subtotal'] = slicewp_sanitize_amount( slicewp_maybe_convert_amount( $transaction_data['subtotal'], $order_currency, $active_currency ) );
	$transaction_data['total']    = slicewp_sanitize_amount( slicewp_maybe_convert_amount( $transaction_data['total'], $order_currency, $active_currency ) );
	$transaction_data['tax']      = slicewp_sanitize_amount( slicewp_maybe_convert_amount( $transaction_data['tax'], $order_currency, $active_currency ) );

	$transaction_data['customer_id']              = $customer_id;
	$transaction_data['currency_conversion_rate'] = slicewp_get_currency_conversion_rate( $order_currency, $active_currency );

	// Convert amounts in individual ticket items.
	foreach ( $transaction_data['items'] as $key => $item ) {

		if ( isset( $item['subtotal'] ) ) {
			$item['original_subtotal'] = $item['subtotal'];
			$item['subtotal']          = slicewp_sanitize_amount( slicewp_maybe_convert_amount( $item['subtotal'], $order_currency, $active_currency ) );
		}

		$item['original_total'] = $item['total'];
		$item['original_tax']   = $item['tax'];
		$item['total']          = slicewp_sanitize_amount( slicewp_maybe_convert_amount( $item['total'], $order_currency, $active_currency ) );
		$item['tax']            = slicewp_sanitize_amount( slicewp_maybe_convert_amount( $item['tax'], $order_currency, $active_currency ) );

		$transaction_data['items'][ $key ] = $item;

	}

	// Calculate commission amount per ticket item.
	$commission_amount = 0;
	$commission_items  = array();

	$is_commission_basis_per_order = slicewp_is_commission_basis_per_order( $affiliate_id );

	if ( ! $is_commission_basis_per_order ) {

		foreach ( $transaction_data['items'] as $item ) {

			if ( $item['type'] !== 'ticket' ) {
				continue;
			}

			$ticket_id = absint( $item['meta_data']['ticket_id'] );

			// Allow per-ticket commission disabling.
			if ( get_post_meta( $ticket_id, 'slicewp_disable_commissions', true ) ) {
				continue;
			}

			$commissionable_amount = $item['total'];

			if ( ! empty( slicewp_get_setting( 'exclude_tax', false ) ) ) {
				$commissionable_amount -= $item['tax'];
			}

			$args = array(
				'origin'       => 'event_tickets',
				'type'         => 'sale',
				'affiliate_id' => $affiliate_id,
				'product_id'   => $ticket_id,
				'quantity'     => $item['quantity'],
				'customer_id'  => $customer_id,
			);

			$commission_items[] = array(
				'commissionable_amount'   => $commissionable_amount,
				'commissionable_quantity' => $item['quantity'],
				'amount'                  => slicewp_calculate_commission_amount( $commissionable_amount, $args ),
				'transaction_item'        => $item,
			);

		}

		if ( ! empty( $commission_items ) ) {
			$commission_amount = array_sum( array_column( $commission_items, 'amount' ) );
		}

	} else {

		$args = array(
			'origin'       => 'event_tickets',
			'type'         => 'sale',
			'affiliate_id' => $affiliate_id,
			'customer_id'  => $customer_id,
		);

		$commission_amount = slicewp_calculate_commission_amount( 0, $args );

	}

	if ( $commission_amount == 0 && empty( slicewp_get_setting( 'zero_amount_commissions' ) ) ) {
		slicewp_add_log( 'EVENT TICKETS: Commission was not inserted because the commission amount is zero. Order: ' . $order_id );
		return;
	}

	// Retrieve the visit to link it.
	$visit = slicewp_get_visit( $visit_id );

	$commission_data = array(
		'affiliate_id'   => $affiliate_id,
		'visit_id'       => ( ! is_null( $visit ) && $visit->get( 'affiliate_id' ) == $affiliate_id ? $visit_id : 0 ),
		'type'           => 'sale',
		'status'         => 'pending',
		'reference'      => $order_id,
		'reference_amount' => slicewp_sanitize_amount( $transaction_data['total'] ),
		'customer_id'    => $customer_id,
		'origin'         => 'event_tickets',
		'amount'         => slicewp_sanitize_amount( $commission_amount ),
		'currency'       => $active_currency,
		'date_created'   => slicewp_mysql_gmdate(),
		'date_modified'  => slicewp_mysql_gmdate(),
	);

	$commission_id = slicewp_insert_commission( $commission_data );

	if ( ! empty( $commission_id ) ) {

		slicewp_update_commission_meta( $commission_id, '__transaction_data', $transaction_data );

		if ( ! empty( $commission_items ) ) {
			slicewp_update_commission_meta( $commission_id, '__commission_items', $commission_items );
		}

		if ( $is_commission_basis_per_order ) {
			slicewp_add_commission_meta( $commission_id, '_is_commission_basis_per_order', 1 );
		}

		if ( ! is_null( $visit ) && $visit->get( 'affiliate_id' ) == $affiliate_id && empty( $visit->get( 'commission_id' ) ) ) {
			slicewp_update_visit( $visit_id, array( 'date_modified' => slicewp_mysql_gmdate(), 'commission_id' => $commission_id ) );
		}

		slicewp_add_log( sprintf( 'EVENT TICKETS: Pending commission #%s has been successfully inserted.', $commission_id ) );

	} else {

		slicewp_add_log( 'EVENT TICKETS: Pending commission could not be inserted due to an unexpected error.' );

	}

	} finally {

		delete_option( $lock_key );

	}

}


// -----------------------------------------------------------------------
// Accept (complete) commission
// -----------------------------------------------------------------------

/**
 * Marks the commission as "unpaid" when the order reaches Completed or Approved.
 *
 * @param object   $new_status  New Status object.
 * @param object   $old_status  Old Status object.
 * @param WP_Post  $order_post  Order post.
 */
function slicewp_et_accept_pending_commission( $new_status, $old_status, $order_post ) {

	if ( ! ( $order_post instanceof WP_Post ) || $order_post->post_type !== 'tec_tc_order' ) {
		return;
	}

	$order_id = $order_post->ID;

	$commissions = slicewp_get_commissions( array( 'number' => -1, 'reference' => $order_id, 'origin' => 'event_tickets', 'order' => 'ASC' ) );

	if ( empty( $commissions ) ) {
		return;
	}

	foreach ( $commissions as $commission ) {

		if ( $commission->get( 'status' ) === 'paid' ) {
			continue;
		}

		$updated = slicewp_update_commission( $commission->get( 'id' ), array(
			'date_modified' => slicewp_mysql_gmdate(),
			'status'        => 'unpaid',
		) );

		if ( false !== $updated ) {
			slicewp_add_log( sprintf( 'EVENT TICKETS: Pending commission #%s successfully marked as completed.', $commission->get( 'id' ) ) );
		} else {
			slicewp_add_log( sprintf( 'EVENT TICKETS: Pending commission #%s could not be completed due to an unexpected error.', $commission->get( 'id' ) ) );
		}

	}

}


// -----------------------------------------------------------------------
// Reject commission on refund
// -----------------------------------------------------------------------

/**
 * Rejects the commission when the order is refunded (if the setting is enabled).
 *
 * @param object   $new_status
 * @param object   $old_status
 * @param WP_Post  $order_post
 */
function slicewp_et_reject_commission_on_refund( $new_status, $old_status, $order_post ) {

	if ( ! ( $order_post instanceof WP_Post ) || $order_post->post_type !== 'tec_tc_order' ) {
		return;
	}

	if ( ! slicewp_get_setting( 'reject_commissions_on_refund', false ) ) {
		return;
	}

	$order_id    = $order_post->ID;
	$commissions = slicewp_get_commissions( array( 'number' => -1, 'reference' => $order_id, 'origin' => 'event_tickets', 'order' => 'ASC' ) );

	if ( empty( $commissions ) ) {
		return;
	}

	foreach ( $commissions as $commission ) {

		if ( $commission->get( 'status' ) === 'paid' ) {
			slicewp_add_log( sprintf( 'EVENT TICKETS: Commission #%s could not be rejected because it was already paid.', $commission->get( 'id' ) ) );
			continue;
		}

		slicewp_update_commission_meta(
			$commission->get( 'id' ),
			'_rejection_reason',
			sprintf( __( 'This commission has been rejected because the reference order #%s was refunded.', 'slicewp-event-tickets' ), $order_id )
		);

		$updated = slicewp_update_commission( $commission->get( 'id' ), array(
			'date_modified' => slicewp_mysql_gmdate(),
			'status'        => 'rejected',
		) );

		if ( false !== $updated ) {
			slicewp_add_log( sprintf( 'EVENT TICKETS: Commission #%s successfully marked as rejected after order #%s was refunded.', $commission->get( 'id' ), $order_id ) );
		} else {
			slicewp_add_log( sprintf( 'EVENT TICKETS: Commission #%s could not be rejected due to an unexpected error.', $commission->get( 'id' ) ) );
		}

	}

}


// -----------------------------------------------------------------------
// Reject commission on void / reversal
// -----------------------------------------------------------------------

/**
 * Rejects the commission when the order is voided or reversed.
 *
 * @param object   $new_status
 * @param object   $old_status
 * @param WP_Post  $order_post
 */
function slicewp_et_reject_commission_on_void( $new_status, $old_status, $order_post ) {

	if ( ! ( $order_post instanceof WP_Post ) || $order_post->post_type !== 'tec_tc_order' ) {
		return;
	}

	$order_id    = $order_post->ID;
	$commissions = slicewp_get_commissions( array( 'number' => -1, 'reference' => $order_id, 'origin' => 'event_tickets', 'order' => 'ASC' ) );

	if ( empty( $commissions ) ) {
		return;
	}

	foreach ( $commissions as $commission ) {

		if ( $commission->get( 'status' ) === 'paid' ) {
			slicewp_add_log( sprintf( 'EVENT TICKETS: Commission #%s could not be rejected because it was already paid.', $commission->get( 'id' ) ) );
			continue;
		}

		$updated = slicewp_update_commission( $commission->get( 'id' ), array(
			'date_modified' => slicewp_mysql_gmdate(),
			'status'        => 'rejected',
		) );

		if ( false !== $updated ) {
			slicewp_add_log( sprintf( 'EVENT TICKETS: Commission #%s successfully marked as rejected after order #%s was voided/reversed.', $commission->get( 'id' ), $order_id ) );
		} else {
			slicewp_add_log( sprintf( 'EVENT TICKETS: Commission #%s could not be rejected due to an unexpected error.', $commission->get( 'id' ) ) );
		}

	}

}


// -----------------------------------------------------------------------
// Admin list table – reference link
// -----------------------------------------------------------------------

/**
 * Wraps the commission reference value with a link to the Event Tickets order.
 *
 * @param string $output
 * @param array  $item
 *
 * @return string
 */
function slicewp_et_list_table_reference_link( $output, $item ) {

	if ( empty( $item['reference'] ) || empty( $item['origin'] ) || $item['origin'] !== 'event_tickets' ) {
		return $output;
	}

	$order = get_post( absint( $item['reference'] ) );

	if ( ! empty( $order ) && $order->post_status !== 'trash' ) {

		$edit_url = add_query_arg( array( 'post' => $order->ID, 'action' => 'edit' ), admin_url( 'post.php' ) );
		$output   = '<a href="' . esc_url( $edit_url ) . '">' . esc_html( $item['reference'] ) . '</a>';

	}

	return $output;

}


// -----------------------------------------------------------------------
// Reference amount helper
// -----------------------------------------------------------------------

/**
 * Injects the reference_amount into commission data before insert/update
 * if the origin is event_tickets and the value is missing.
 *
 * @param array $commission_data
 *
 * @return array
 */
function slicewp_et_add_reference_amount_to_commission( $commission_data ) {

	if ( empty( $commission_data['origin'] ) || $commission_data['origin'] !== 'event_tickets' ) {
		return $commission_data;
	}

	if ( ! empty( $commission_data['reference_amount'] ) ) {
		return $commission_data;
	}

	if ( empty( $commission_data['reference'] ) ) {
		return $commission_data;
	}

	$total_raw = (int) get_post_meta( absint( $commission_data['reference'] ), '_tec_tc_order_total_value', true );

	if ( $total_raw ) {
		$commission_data['reference_amount'] = slicewp_sanitize_amount( $total_raw / 100 );
	}

	return $commission_data;

}
