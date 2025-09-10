<?php
/**
 * Helper Function for Managing all Functions at one place to easily modify and update.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
function postforge_get_available_user_roles(){
    // Load roles
    global $wp_roles;
    $roles = $wp_roles->roles;

    // Prepare response
    $response = array();
    foreach ( $roles as $role_key => $role_data ) {
        $response[] = array(
            'key'   => $role_key,
            'label' => translate_user_role( $role_data['name'] ),
        );
    }
    $response = apply_filters( 'postforge_get_available_user_roles', $response );
    return $response;
}