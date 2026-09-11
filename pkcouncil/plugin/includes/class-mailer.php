<?php
if (!defined('ABSPATH')) {
    exit;
}

class PKC_Mailer {
    public static function send($to, $subject, $body) {
        if (!pkc_settings('notify_email', 1)) {
            return false;
        }
        $headers = array('Content-Type: text/plain; charset=UTF-8');
        $from = pkc_settings('email', get_option('admin_email'));
        if ($from) {
            $headers[] = 'From: PKCouncil <' . $from . '>';
        }
        return wp_mail($to, $subject, $body, $headers);
    }

    public static function credentials($email, $name, $username, $password, $role = 'student') {
        $portal = $role === 'teacher' ? pkc_url('teacher/') : pkc_url('student/');
        $body = "Hello {$name},\n\nYour PKCouncil {$role} account is ready.\n\nLogin: " . pkc_url('login/') . "\nUsername: {$username}\nEmail: {$email}\nTemporary password: {$password}\n\nPlease sign in and change this password immediately.\nPortal: {$portal}\n\n— PKCouncil\n";
        return self::send($email, 'Your PKCouncil login credentials', $body);
    }

    public static function payment_status($email, $name, $status, $course, $reason = '') {
        $body = "Hello {$name},\n\nYour payment for {$course} is now: " . strtoupper($status) . ".\n";
        if ($reason) {
            $body .= "Note: {$reason}\n";
        }
        $body .= "\n— PKCouncil\n";
        return self::send($email, 'PKCouncil payment ' . $status, $body);
    }

    public static function reset_link($email, $name, $url) {
        $body = "Hello {$name},\n\nReset your PKCouncil password using this link (valid for 1 hour):\n{$url}\n\nIf you did not request this, you can ignore this email.\n\n— PKCouncil\n";
        return self::send($email, 'Reset your PKCouncil password', $body);
    }
}
