<?php

/* 
 * Contains functions and definations for user meta
 * 
 * @package LockUserAccount
 * @author babaTechs
 * @version 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class Baba_User_Meta {

	public function __construct() {
		// Add filters and actions
		add_filter( 'bulk_actions-users', array( $this, 'register_bulk_action' ) );
		add_filter( 'manage_users_columns', array( $this, 'register_column_header' ) );
		add_filter( 'manage_users_custom_column', array( $this, 'output_column' ), 10, 3 );
		add_action( 'admin_init', array( $this, 'process_lock_action' ) );
		add_action( 'admin_notices', array( $this, 'lock_action_notice' ) );

		// Optional: ensure nonce is output
		add_action( 'admin_footer-users.php', function () {
			if ( current_user_can( 'edit_users' ) ) {
				wp_nonce_field( 'bulk-users' );
			}
		} );
	}

	/**
	 * Register bulk actions for locking/unlocking users
	 */
	public function register_bulk_action( $actions ) {
		$actions['lock']   = esc_html__( 'Lock', 'babatechs' );
		$actions['unlock'] = esc_html__( 'Unlock', 'babatechs' );
		return $actions;
	}

	/**
	 * Add 'Locked' column to users table
	 */
	public function register_column_header( $columns ) {
		$columns['locked'] = esc_html__( 'Locked', 'babatechs' );
		return $columns;
	}

	/**
	 * Output lock status in users table
	 */
	public function output_column( $output, $column_name, $user_id ) {
		if ( 'locked' !== $column_name ) return $output;
		$locked = get_user_meta( $user_id, 'baba_user_locked', true );
		return ( 'yes' === $locked ) ? esc_html__( 'Locked', 'babatechs' ) : esc_html__( 'Not Locked', 'babatechs' );
	}

	/**
	 * Process lock/unlock bulk actions securely
	 */
	public function process_lock_action() {
		if ( ! isset( $_REQUEST['action'] ) && ! isset( $_REQUEST['action2'] ) ) {
			return;
		}

		$action = ( $_REQUEST['action'] !== '-1' ) ? $_REQUEST['action'] : $_REQUEST['action2'];

		if ( 'lock' !== $action && 'unlock' !== $action ) {
			return;
		}

		if ( ! isset( $_REQUEST['_wpnonce'] ) || ! check_admin_referer( 'bulk-users' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'babatechs' ) );
		}

		if ( ! current_user_can( 'edit_users' ) ) {
			wp_die( esc_html__( 'You are not allowed to perform this action.', 'babatechs' ) );
		}

		if ( empty( $_REQUEST['users'] ) || ! is_array( $_REQUEST['users'] ) ) {
			return;
		}

		$user_ids = array_map( 'intval', $_REQUEST['users'] );
		$current_user_id = get_current_user_id();

		if ( 'lock' === $action ) {
			foreach ( $user_ids as $user_id ) {
				if ( $user_id === $current_user_id ) {
					continue; // Don't lock yourself
				}
				update_user_meta( $user_id, 'baba_user_locked', 'yes' );
			}
		} elseif ( 'unlock' === $action ) {
			foreach ( $user_ids as $user_id ) {
				update_user_meta( $user_id, 'baba_user_locked', '' );
			}
		}

		// Redirect with success message
		$redirect_url = remove_query_arg( array( 'action', 'action2', 'users', '_wpnonce' ), wp_get_referer() );
		$redirect_url = add_query_arg( 'lock_action_done', $action, $redirect_url );
		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * Show success notice after bulk lock/unlock
	 */
	public function lock_action_notice() {
		if ( ! isset( $_GET['lock_action_done'] ) ) return;

		$action = sanitize_text_field( $_GET['lock_action_done'] );
		if ( 'lock' === $action ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Selected users have been locked.', 'babatechs' ) . '</p></div>';
		} elseif ( 'unlock' === $action ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Selected users have been unlocked.', 'babatechs' ) . '</p></div>';
		}
	}
}

new Baba_User_Meta();

