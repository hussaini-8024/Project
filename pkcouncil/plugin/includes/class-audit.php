<?php
if (!defined('ABSPATH')) {
    exit;
}

class PKC_Audit {
    public static function log($action, $object_type = '', $object_id = 0, $details = array()) {
        global $wpdb;
        $actor_type = 'system';
        $actor_id = 0;
        if (pkc_is_admin_user()) {
            $actor_type = 'admin';
            $actor_id = get_current_user_id();
        } else {
            $acc = PKC_Auth::current();
            if ($acc) {
                $actor_type = $acc->type;
                $actor_id = (int) $acc->id;
            }
        }
        $wpdb->insert(PKC_DB::audit_logs(), array(
            'actor_type'  => $actor_type,
            'actor_id'    => $actor_id,
            'action'      => substr($action, 0, 80),
            'object_type' => substr((string) $object_type, 0, 40),
            'object_id'   => (int) $object_id,
            'details'     => wp_json_encode($details),
            'ip'          => PKC_Security::client_ip(),
            'created_at'  => PKC_DB::now(),
        ));
    }
}
