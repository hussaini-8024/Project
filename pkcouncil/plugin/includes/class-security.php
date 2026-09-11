<?php
if (!defined('ABSPATH')) {
    exit;
}

class PKC_Security {
    public static function client_ip() {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        return substr(sanitize_text_field($ip), 0, 64);
    }

    public static function csrf_ok($token) {
        $account = PKC_Auth::current();
        if (!$account || empty($account->csrf)) {
            return false;
        }
        return hash_equals($account->csrf, (string) $token);
    }

    public static function require_csrf() {
        $token = $_SERVER['HTTP_X_PKC_NONCE'] ?? ($_POST['pkc_nonce'] ?? '');
        if (!self::csrf_ok($token)) {
            return new WP_Error('pkc_csrf', 'Security check failed. Please refresh and try again.', array('status' => 403));
        }
        return true;
    }

    public static function too_many_attempts($login) {
        global $wpdb;
        $ip = self::client_ip();
        $since = gmdate('Y-m-d H:i:s', time() - 15 * MINUTE_IN_SECONDS);
        $table = PKC_DB::login_attempts();
        $count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE success = 0 AND created_at >= %s AND (ip = %s OR login = %s)",
            $since,
            $ip,
            $login
        ));
        return $count >= 8;
    }

    public static function record_attempt($login, $success) {
        global $wpdb;
        $wpdb->insert(PKC_DB::login_attempts(), array(
            'ip'         => self::client_ip(),
            'login'      => substr((string) $login, 0, 190),
            'success'    => $success ? 1 : 0,
            'created_at' => PKC_DB::now(),
        ));
    }

    public static function allowed_upload($file) {
        if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return new WP_Error('pkc_upload', 'No file was uploaded.');
        }
        if (!empty($file['size']) && $file['size'] > 5 * MB_IN_BYTES) {
            return new WP_Error('pkc_upload', 'File must be 5MB or smaller.');
        }
        $check = wp_check_filetype_and_ext($file['tmp_name'], $file['name']);
        $ext = strtolower((string) ($check['ext'] ?? pathinfo($file['name'], PATHINFO_EXTENSION)));
        $allowed = array('pdf', 'jpg', 'jpeg', 'png', 'webp');
        if (!in_array($ext, $allowed, true)) {
            return new WP_Error('pkc_upload', 'Only PDF, JPG, PNG, or WEBP files are allowed.');
        }
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo ? finfo_file($finfo, $file['tmp_name']) : '';
        if ($finfo) {
            finfo_close($finfo);
        }
        $ok_mimes = array('application/pdf', 'image/jpeg', 'image/png', 'image/webp');
        if ($mime && !in_array($mime, $ok_mimes, true)) {
            return new WP_Error('pkc_upload', 'The uploaded file type is not allowed.');
        }
        return true;
    }

    public static function store_protected_upload($file, $prefix = 'file') {
        $ok = self::allowed_upload($file);
        if (is_wp_error($ok)) {
            return $ok;
        }
        PKC_Activator::ensure_protected_dir();
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $name = $prefix . '-' . wp_generate_password(16, false, false) . '.' . $ext;
        $dest = PKC_Activator::protected_dir() . '/' . $name;
        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            return new WP_Error('pkc_upload', 'Could not store the uploaded file.');
        }
        @chmod($dest, 0640);
        return 'pkc-protected/' . $name;
    }

    public static function json_error($message, $status = 400) {
        return new WP_Error('pkc_error', $message, array('status' => $status));
    }
}
