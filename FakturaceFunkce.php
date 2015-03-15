<?php

/*
 * All right reserved to: Jan Cejka <posta@jancejka.cz>, http://jancejka.cz
 */

/**
 * Description of FakturaceFunkce
 *
 * @author Merlin
 */
class FakturaceFunkce {

    static public function activateUser($email, $role, $firstName = '', $lastName = '', $userInfo = array()) {
        global $wpdb;

        // USER 

        $email = strtolower($email);
        
        $user = get_user_by('email', $email);
        
        if ($user === FALSE) {
            $password = wp_generate_password($length = 12, $include_standard_special_chars = false);
            $user_id = wp_create_user($email, $password, $email);

            update_user_meta($user_id, "user_first_name", $firstName);
            update_user_meta($user_id, "user_last_name", $lastName);
            update_user_meta($user_id, "user_registered", Date("Y-m-d H:i:s", time()));

            foreach ($userInfo as $uiKey => $uiValue) {
                update_user_meta($user_id, $uiKey, $uiValue);
            }
            $user = get_userdata($user_id);

            // ROLES

            if( !user_can( $user->ID, 'administrator' ) ) {
                wp_update_user(array('ID' => $user->ID, 'role' => $role));
                do_action('profile_update');
            }

        } else {
            $password = "<i>(již bylo zasláno dříve)</i>";
        }

        return $password;
    }

}
