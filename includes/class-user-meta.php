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

class Baba_User_Meta{
    
    public function __construct() {
        //  Add filter to add another action in users' bulk action dropdown
        add_filter( 'bulk_actions-users', array( $this, 'register_bulk_action' ) );
        
        //  Add filter to add another column header in users' listing
        add_filter( 'manage_users_columns' , array( $this, 'register_column_header' ) );
        
        //  Add filter to show output of custom column in users' listing
        add_filter( 'manage_users_custom_column', array( $this, 'output_column' ), 10, 3 );
        
        //  Add action to process bulk action request
        add_action( 'admin_init', array( $this, 'process_lock_action' ) );
    }
    
    /**
     * Add another action in bulk action drop down list on users listing screen
     * 
     * @param array $actions    Array of users bulk actions
     * @return array            Array with addition of Lock action
     */
    public function register_bulk_action( $actions ){
        $actions['lock'] = esc_html__( 'Lock', 'babatechs' );
        $actions['unlock'] = esc_html__( 'Unlock', 'babatechs' );
        return $actions;
    }
    
    /**
     * Add another column header in listing of users
     * 
     * @param array $columns    Array of columns headers
     * @return array            Array with adition of Locked column
     */
    public function register_column_header( $columns ){
        return array_merge( $columns, 
              array( 'locked' => esc_html__( 'Locked', 'babatechs' ) ) );
    }
    
    /**
     * Displaying status of user's account in list of users for Locked column
     * 
     * @param string $output        Output value of custom column
     * @param string $column_name   Column name
     * @param int $user_id          ID of user
     * @return string               Output value of custom column
     */
    public function output_column( $output, $column_name, $user_id ){
        if( 'locked' !== $column_name ) return $output;
        $locked = get_user_meta( $user_id, sanitize_key( 'baba_user_locked' ), true );
        return ( 'yes' === $locked ) ? __( 'Locked', 'babatechs' ) : __( 'Not Locked', 'babatechs' );
    }
    
      public function process_lock_action() {
          // Only process POST requests
          if ( 'POST' !== $_SERVER['REQUEST_METHOD'] ) {
              return;
          }

          // Check if nonce exists and verify it
          if ( empty( $_POST['_wpnonce'] ) || ! check_admin_referer( 'bulk-users' ) ) {
              return;
        }

        // Get the action from POST
        $action = isset( $_POST['action'] ) ? sanitize_text_field( wp_unslash( $_POST['action'] ) ) : '';

        if ( 'lock' !== $action && 'unlock' !== $action ) {
            return;
        }

        // Capability check
        if ( ! current_user_can( 'create_users' ) ) {
            return;
        }

        // Validate and sanitize user IDs
        $userids = [];
        if ( isset( $_POST['users'] ) && is_array( $_POST['users'] ) && ! empty( $_POST['users'] ) ) {
            foreach ( $_POST['users'] as $user_id ) {
                $userids[] = (int) $user_id;
            }
        } else {
            return;
        }

        // Process lock/unlock accordingly
        $current_user_id = get_current_user_id();
        if ( 'lock' === $action ) {
            foreach ( $userids as $userid ) {
                if ( $userid === $current_user_id ) {
                    continue; // Don't lock self
                }
                update_user_meta( $userid, 'baba_user_locked', 'yes' );
            }
        } elseif ( 'unlock' === $action ) {
            foreach ( $userids as $userid ) {
                update_user_meta( $userid, 'baba_user_locked', '' );
            }
        }
    }
}

new Baba_User_Meta();
