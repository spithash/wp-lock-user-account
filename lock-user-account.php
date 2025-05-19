<?php
/**
 * Plugin Name: Lock User Account
 * Plugin URI: http://teknigar.com
 * Description: Lock user accounts with custom message
 * Version: 1.0.5
 * Author: teknigar
 * Author URI: http://teknigar.com
 * Text Domain: babatechs
 * Domain Path: /languages
 *
 * @package LockUserAccount
 * @author teknigar
 * @version 1.0.3
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class Baba_Lock_User_Account {

	public function __construct() {
		// Add filter to check user's account lock status during standard authentication
		add_filter( 'wp_authenticate_user', array( $this, 'check_lock' ) );

		// Add filter to check user lock status during REST API authentication
		add_filter( 'rest_authentication_errors', array( $this, 'rest_api_lock_check' ) );

		// Add filter to check user lock status during XML-RPC login
		add_filter( 'xmlrpc_login_error', array( $this, 'xmlrpc_lock_check' ), 10, 3 );

		// Add filter to check user lock status during Application Password authentication
		add_filter( 'wp_authenticate_application_password', array( $this, 'application_password_lock_check' ), 10, 4 );
	}

	/**
	 * Applying user lock filter on standard WP authentication
	 *
	 * @param WP_User|WP_Error $user WP_User object or WP_Error on failure.
	 * @return WP_User|WP_Error
	 */
	public function check_lock( $user ) {
		if ( is_wp_error( $user ) ) {
			return $user;
		}
		if ( is_object( $user ) && isset( $user->ID ) && 'yes' === get_user_meta( (int) $user->ID, sanitize_key( 'baba_user_locked' ), true ) ) {
			$error_message = get_option( 'baba_locked_message' );
			return new WP_Error( 'locked', ( $error_message ) ? $error_message : __( 'Your account is locked!', 'babatechs' ) );
		}

		return $user;
	}

	/**
	 * Prevent locked users from authenticating via REST API
	 *
	 * @param null|WP_Error $result Current authentication result or null.
	 * @return null|WP_Error
	 */
	public function rest_api_lock_check( $result ) {
		if ( ! empty( $result ) ) {
			return $result; // Authentication already failed or passed.
		}

		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return $result; // Not authenticated yet.
		}

		if ( 'yes' === get_user_meta( $user_id, 'baba_user_locked', true ) ) {
			return new WP_Error( 'rest_locked', __( 'Your account is locked.', 'babatechs' ), array( 'status' => 403 ) );
		}

		return $result;
	}

	/**
	 * Prevent locked users from authenticating via XML-RPC
	 *
	 * @param WP_Error|null $error Existing error or null.
	 * @param WP_User|null $user Authenticated user or null.
	 * @param string $password Password provided.
	 * @return WP_Error|null
	 */
	public function xmlrpc_lock_check( $error, $user, $password ) {
		if ( is_wp_error( $error ) ) {
			return $error;
		}

		if ( $user && is_a( $user, 'WP_User' ) ) {
			if ( 'yes' === get_user_meta( $user->ID, 'baba_user_locked', true ) ) {
				return new WP_Error( 'xmlrpc_locked', __( 'Your account is locked.', 'babatechs' ) );
			}
		}

		return $error;
	}

	/**
	 * Prevent locked users from authenticating using Application Passwords
	 *
	 * @param WP_User|WP_Error $user WP_User object or WP_Error.
	 * @param string $application_password Application password used.
	 * @param string $user_login User login name.
	 * @param WP_User $user_data WP_User object.
	 * @return WP_User|WP_Error
	 */
	public function application_password_lock_check( $user, $application_password, $user_login, $user_data ) {
		if ( is_wp_error( $user ) ) {
			return $user;
		}

		if ( $user && is_a( $user, 'WP_User' ) ) {
			if ( 'yes' === get_user_meta( $user->ID, 'baba_user_locked', true ) ) {
				return new WP_Error( 'app_pass_locked', __( 'Your account is locked.', 'babatechs' ) );
			}
		}

		return $user;
	}
}

new Baba_Lock_User_Account();

// Force logout of locked users even if they're already logged in
add_action( 'init', 'baba_logout_locked_user' );
function baba_logout_locked_user() {
	// Check for any cookie that starts with "wp_loginasuser_"
	foreach ( $_COOKIE as $cookie_name => $cookie_value ) {
		if ( strpos( $cookie_name, 'wp_loginasuser_' ) === 0 ) {
			// Admin is impersonating a user — skip lock check
			return;
		}
	}

	if ( is_user_logged_in() ) {
		$user_id = get_current_user_id();
		$is_locked = get_user_meta( $user_id, 'baba_user_locked', true );
		if ( $is_locked === 'yes' ) {
			wp_logout();
			wp_redirect( home_url( '/?account_locked=1' ) );
			exit;
		}
	}
}

add_filter( 'login_message', 'baba_locked_account_login_message' );
function baba_locked_account_login_message( $message ) {
	if ( isset( $_GET['account_locked'] ) && $_GET['account_locked'] == 1 ) {
		$error_message = get_option( 'baba_locked_message' );
		$message .= '<div class="error"><strong>' . esc_html( $error_message ? $error_message : 'Your account is locked.' ) . '</strong></div>';
	}
	return $message;
}

// Prevent locked users from resetting their password
add_filter( 'allow_password_reset', 'baba_disallow_locked_user_password_reset', 10, 2 );
function baba_disallow_locked_user_password_reset( $allow, $user_id ) {
	if ( 'yes' === get_user_meta( (int) $user_id, 'baba_user_locked', true ) ) {
		return false; // Disallow password reset
	}
	return $allow;
}

// Optional: Show error message on reset form for locked users
add_action( 'validate_password_reset', 'baba_show_locked_user_reset_error', 10, 2 );
function baba_show_locked_user_reset_error( $errors, $user ) {
	if ( is_a( $user, 'WP_User' ) && 'yes' === get_user_meta( $user->ID, 'baba_user_locked', true ) ) {
		$error_message = get_option( 'baba_locked_message' );
		$errors->add( 'locked', ( $error_message ) ? $error_message : __( 'Your account is locked and cannot reset password.', 'babatechs' ) );
	}
}

//  Load user meta and settings files in only admin panel
if ( is_admin() ) {
	//  Load user meta file
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-user-meta.php';

	//  Load settings message file
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-settings-field.php';
}

