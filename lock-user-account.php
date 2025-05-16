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

class Baba_Lock_User_Account{
    
    public function __construct() {
        //  Add filter to check user's account lock status
        add_filter( 'wp_authenticate_user', array( $this, 'check_lock' ) );
    }
    
    /**
     * Applying user lock filter on user's authentication
     * 
     * @param object $user          WP_User object
     * @return \WP_Error || $user   If account is locked then return WP_Error object, else return WP_User object
     */
    public function check_lock( $user ){
        if( is_wp_error( $user ) ){
            return $user;
        }
        if( is_object( $user ) && isset( $user->ID ) && 'yes' === get_user_meta( (int)$user->ID, sanitize_key( 'baba_user_locked' ), true ) ){
            $error_message = get_option( 'baba_locked_message' );
            return new WP_Error( 'locked', ( $error_message ) ? $error_message : __( 'Your account is locked!', 'babatechs' ) );
        }
        else{
            return $user;
        }
    }    
}

new Baba_Lock_User_Account();

//  Load user meta and settings files in only admin panel
if( is_admin() ){
    //  Load user meta file
    require_once plugin_dir_path( __FILE__ ) . 'includes/class-user-meta.php';
    
    //  Load settings message file
    require_once plugin_dir_path( __FILE__ ) . 'includes/class-settings-field.php';
}
