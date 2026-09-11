<?php
if (!defined('ABSPATH')) {
    exit;
}

class PKC_Notifications {
    public static function add($type, $id, $title, $body, $kind = 'info', $link = '') {
        global $wpdb;
        $wpdb->insert(PKC_DB::notifications(), array(
            'recipient_type' => $type,
            'recipient_id'   => (int) $id,
            'title'          => $title,
            'body'           => $body,
            'type'           => $kind,
            'is_read'        => 0,
            'link'           => $link,
            'created_at'     => PKC_DB::now(),
        ));
    }

    public static function for_account($type, $id, $limit = 20) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            'SELECT * FROM ' . PKC_DB::notifications() . ' WHERE recipient_type = %s AND recipient_id = %d ORDER BY created_at DESC LIMIT %d',
            $type,
            (int) $id,
            (int) $limit
        ));
    }

    public static function unread_count($type, $id) {
        global $wpdb;
        return (int) $wpdb->get_var($wpdb->prepare(
            'SELECT COUNT(*) FROM ' . PKC_DB::notifications() . ' WHERE recipient_type = %s AND recipient_id = %d AND is_read = 0',
            $type,
            (int) $id
        ));
    }

    public static function mark_read($id, $type, $account_id) {
        global $wpdb;
        $wpdb->update(PKC_DB::notifications(), array('is_read' => 1), array(
            'id'             => (int) $id,
            'recipient_type' => $type,
            'recipient_id'   => (int) $account_id,
        ));
    }

    public static function notify_admins($title, $body, $kind = 'info', $link = '') {
        $admins = get_users(array('role' => 'administrator', 'fields' => array('user_email', 'ID')));
        foreach ($admins as $admin) {
            PKC_Mailer::send($admin->user_email, 'PKCouncil: ' . $title, $body . ($link ? "\n\n" . $link : ''));
        }
    }

    public static function notify_email_account($payment, $title, $body) {
        PKC_Mailer::send($payment->email, 'PKCouncil: ' . $title, $body);
    }
}
