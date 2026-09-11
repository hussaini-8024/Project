<?php
if (!defined('ABSPATH')) {
    exit;
}

class PKC_Auth {
    const COOKIE = 'pkc_session';
    protected static $current = null;
    protected static $loaded = false;

    public static function current() {
        if (self::$loaded) {
            return self::$current;
        }
        self::$loaded = true;
        $raw = $_COOKIE[self::COOKIE] ?? '';
        if (!$raw || strpos($raw, ':') === false) {
            self::$current = null;
            return null;
        }
        list($selector, $validator) = explode(':', $raw, 2);
        if (!ctype_xdigit($selector) || !ctype_xdigit($validator)) {
            self::$current = null;
            return null;
        }
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . PKC_DB::sessions() . ' WHERE selector = %s AND expires_at > %s',
            $selector,
            PKC_DB::now()
        ));
        if (!$row || !hash_equals($row->token_hash, hash('sha256', $validator))) {
            self::$current = null;
            return null;
        }
        $account = self::load_account($row->account_type, (int) $row->account_id);
        if (!$account || $account->status !== 'active') {
            self::$current = null;
            return null;
        }
        $account->type = $row->account_type;
        $account->csrf = $row->csrf_token;
        $account->session_id = (int) $row->id;
        self::$current = $account;
        $wpdb->update(PKC_DB::sessions(), array('last_seen' => PKC_DB::now()), array('id' => (int) $row->id));
        return self::$current;
    }

    public static function load_account($type, $id) {
        global $wpdb;
        $table = $type === 'teacher' ? PKC_DB::teachers() : PKC_DB::students();
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id));
    }

    public static function login($login, $password, $portal = 'student', $remember = false) {
        $login = trim((string) $login);
        $password = (string) $password;
        if ($login === '' || $password === '') {
            return new WP_Error('pkc_login', 'Enter your username/email and password.');
        }
        if (PKC_Security::too_many_attempts($login)) {
            PKC_Security::record_attempt($login, false);
            return new WP_Error('pkc_login', 'Too many attempts. Please wait 15 minutes and try again.');
        }

        // Admin credentials through the same custom form — server-side only.
        $wp_user = self::try_wp_admin($login, $password);
        if ($wp_user instanceof WP_User) {
            PKC_Security::record_attempt($login, true);
            wp_set_current_user($wp_user->ID);
            wp_set_auth_cookie($wp_user->ID, $remember, is_ssl());
            PKC_Audit::log('admin_login', 'user', $wp_user->ID);
            return (object) array('type' => 'admin', 'wp_user' => $wp_user, 'redirect' => admin_url('admin.php?page=pkcouncil'));
        }

        $preferred = $portal === 'teacher' ? 'teacher' : 'student';
        $order = $preferred === 'teacher' ? array('teacher', 'student') : array('student', 'teacher');
        $account = null;
        $type = null;
        foreach ($order as $try) {
            $row = self::find_account($try, $login);
            if ($row) {
                $account = $row;
                $type = $try;
                break;
            }
        }

        if (!$account) {
            PKC_Security::record_attempt($login, false);
            return new WP_Error('pkc_login', 'Invalid login details.');
        }
        if ($account->status !== 'active') {
            PKC_Security::record_attempt($login, false);
            return new WP_Error('pkc_login', 'This account is disabled. Contact PKCouncil support.');
        }
        if (!empty($account->locked_until) && strtotime($account->locked_until) > time()) {
            return new WP_Error('pkc_login', 'This account is temporarily locked. Try again later.');
        }
        if (!password_verify($password, $account->password_hash)) {
            self::fail_account($type, $account);
            PKC_Security::record_attempt($login, false);
            return new WP_Error('pkc_login', 'Invalid login details.');
        }

        self::issue_session($type, (int) $account->id, $remember);
        self::touch_login($type, $account);
        PKC_Security::record_attempt($login, true);
        PKC_Audit::log($type . '_login', $type, (int) $account->id);
        $redirect = $type === 'teacher' ? pkc_url('teacher/') : pkc_url('student/');
        if ((int) $account->must_change_password) {
            $redirect = $type === 'teacher' ? pkc_url('teacher/profile/') : pkc_url('student/profile/');
        }
        return (object) array('type' => $type, 'account' => $account, 'redirect' => $redirect);
    }

    protected static function try_wp_admin($login, $password) {
        $user = wp_authenticate($login, $password);
        if (is_wp_error($user)) {
            return null;
        }
        if (!user_can($user, 'manage_options')) {
            // Do not allow ordinary WP users through this portal.
            return null;
        }
        return $user;
    }

    public static function find_account($type, $login) {
        global $wpdb;
        $table = $type === 'teacher' ? PKC_DB::teachers() : PKC_DB::students();
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE username = %s OR email = %s LIMIT 1",
            $login,
            $login
        ));
    }

    protected static function fail_account($type, $account) {
        global $wpdb;
        $table = $type === 'teacher' ? PKC_DB::teachers() : PKC_DB::students();
        $fails = (int) $account->failed_logins + 1;
        $data = array('failed_logins' => $fails, 'updated_at' => PKC_DB::now());
        if ($fails >= 10) {
            $data['locked_until'] = gmdate('Y-m-d H:i:s', time() + 30 * MINUTE_IN_SECONDS);
        }
        $wpdb->update($table, $data, array('id' => (int) $account->id));
    }

    protected static function touch_login($type, $account) {
        global $wpdb;
        $table = $type === 'teacher' ? PKC_DB::teachers() : PKC_DB::students();
        $wpdb->update($table, array(
            'failed_logins' => 0,
            'locked_until'  => null,
            'last_login'    => PKC_DB::now(),
            'last_login_ip' => PKC_Security::client_ip(),
            'updated_at'    => PKC_DB::now(),
        ), array('id' => (int) $account->id));
    }

    public static function issue_session($type, $id, $remember = false) {
        global $wpdb;
        $selector = bin2hex(random_bytes(8));
        $validator = bin2hex(random_bytes(16));
        $csrf = bin2hex(random_bytes(16));
        $days = $remember ? 30 : 2;
        $expires_ts = time() + $days * DAY_IN_SECONDS;
        $wpdb->insert(PKC_DB::sessions(), array(
            'account_type' => $type,
            'account_id'   => $id,
            'selector'     => $selector,
            'token_hash'   => hash('sha256', $validator),
            'csrf_token'   => $csrf,
            'expires_at'   => gmdate('Y-m-d H:i:s', $expires_ts),
            'remember'     => $remember ? 1 : 0,
            'ip'           => PKC_Security::client_ip(),
            'user_agent'   => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
            'created_at'   => PKC_DB::now(),
            'last_seen'    => PKC_DB::now(),
        ));
        $secure = is_ssl();
        setcookie(self::COOKIE, $selector . ':' . $validator, array(
            'expires'  => $expires_ts,
            'path'     => COOKIEPATH ? COOKIEPATH : '/',
            'domain'   => COOKIE_DOMAIN,
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ));
        $_COOKIE[self::COOKIE] = $selector . ':' . $validator;
        self::$loaded = false;
        self::$current = null;
        return true;
    }

    public static function logout() {
        $raw = $_COOKIE[self::COOKIE] ?? '';
        if ($raw && strpos($raw, ':') !== false) {
            $selector = explode(':', $raw, 2)[0];
            global $wpdb;
            $wpdb->delete(PKC_DB::sessions(), array('selector' => $selector));
        }
        setcookie(self::COOKIE, '', array(
            'expires'  => time() - 3600,
            'path'     => COOKIEPATH ? COOKIEPATH : '/',
            'domain'   => COOKIE_DOMAIN,
            'secure'   => is_ssl(),
            'httponly' => true,
            'samesite' => 'Lax',
        ));
        self::$current = null;
        self::$loaded = true;
        if (is_user_logged_in()) {
            wp_logout();
        }
    }

    public static function hash_password($plain) {
        return password_hash((string) $plain, PASSWORD_DEFAULT);
    }

    public static function set_password($type, $id, $plain, $must_change = 0) {
        global $wpdb;
        $table = $type === 'teacher' ? PKC_DB::teachers() : PKC_DB::students();
        $wpdb->update($table, array(
            'password_hash'         => self::hash_password($plain),
            'must_change_password'  => (int) $must_change,
            'updated_at'            => PKC_DB::now(),
        ), array('id' => (int) $id));
        $wpdb->delete(PKC_DB::sessions(), array('account_type' => $type, 'account_id' => (int) $id));
    }

    public static function request_reset($login, $portal = 'student') {
        $type = $portal === 'teacher' ? 'teacher' : 'student';
        $account = self::find_account($type, $login);
        if (!$account) {
            $other = $type === 'teacher' ? 'student' : 'teacher';
            $account = self::find_account($other, $login);
            if ($account) {
                $type = $other;
            }
        }
        // Always look successful.
        if (!$account) {
            return true;
        }
        global $wpdb;
        $token = bin2hex(random_bytes(32));
        $wpdb->insert(PKC_DB::password_resets(), array(
            'account_type' => $type,
            'account_id'   => (int) $account->id,
            'token_hash'   => hash('sha256', $token),
            'expires_at'   => gmdate('Y-m-d H:i:s', time() + HOUR_IN_SECONDS),
            'created_at'   => PKC_DB::now(),
        ));
        $url = pkc_url('reset-password/' . $token . '/');
        PKC_Mailer::reset_link($account->email, $account->full_name, $url);
        PKC_Audit::log('password_reset_requested', $type, (int) $account->id);
        return true;
    }

    public static function reset_with_token($token, $new_password) {
        if (strlen($new_password) < 8) {
            return new WP_Error('pkc_reset', 'Password must be at least 8 characters.');
        }
        global $wpdb;
        $hash = hash('sha256', $token);
        $row = $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . PKC_DB::password_resets() . ' WHERE token_hash = %s AND used_at IS NULL AND expires_at > %s',
            $hash,
            PKC_DB::now()
        ));
        if (!$row) {
            return new WP_Error('pkc_reset', 'This reset link is invalid or has expired.');
        }
        self::set_password($row->account_type, (int) $row->account_id, $new_password, 0);
        $wpdb->update(PKC_DB::password_resets(), array('used_at' => PKC_DB::now()), array('id' => (int) $row->id));
        PKC_Audit::log('password_reset_completed', $row->account_type, (int) $row->account_id);
        return true;
    }

    public static function require_student() {
        $a = self::current();
        if (!$a || $a->type !== 'student') {
            wp_safe_redirect(pkc_url('login/'));
            exit;
        }
        return $a;
    }

    public static function require_teacher() {
        $a = self::current();
        if (!$a || $a->type !== 'teacher') {
            wp_safe_redirect(pkc_url('login/?portal=teacher'));
            exit;
        }
        return $a;
    }
}
