<?php
if (!defined('ABSPATH')) {
    exit;
}

class PKC_Payments {
    public static function submit($data, $file = null) {
        $course = pkc_course((int) ($data['course_id'] ?? 0));
        if (!$course || $course->status !== 'published') {
            return new WP_Error('pkc_pay', 'The selected course is not available.');
        }
        $name = sanitize_text_field($data['full_name'] ?? '');
        $email = sanitize_email($data['email'] ?? '');
        $whatsapp = pkc_sanitize_phone($data['whatsapp'] ?? '');
        $address = sanitize_textarea_field($data['address'] ?? '');
        $method_id = (int) ($data['payment_method_id'] ?? 0);
        $txn = sanitize_text_field($data['transaction_id'] ?? '');
        $coupon_code = sanitize_text_field($data['coupon_code'] ?? '');

        if ($name === '' || !$email || $whatsapp === '' || $address === '' || $txn === '' || !$method_id) {
            return new WP_Error('pkc_pay', 'Please complete every required field.');
        }
        global $wpdb;
        $method = $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . PKC_DB::payment_methods() . ' WHERE id = %d AND is_active = 1',
            $method_id
        ));
        if (!$method) {
            return new WP_Error('pkc_pay', 'Choose a valid payment method.');
        }

        $quote = PKC_Coupons::quote($coupon_code, $course, $email);
        if (!$quote['ok']) {
            return new WP_Error('pkc_pay', $quote['error']);
        }

        $receipt = '';
        if ($file && !empty($file['tmp_name'])) {
            $stored = PKC_Security::store_protected_upload($file, 'receipt');
            if (is_wp_error($stored)) {
                return $stored;
            }
            $receipt = $stored;
        } else {
            return new WP_Error('pkc_pay', 'Please upload a payment receipt.');
        }

        $student = PKC_Auth::current();
        $student_id = ($student && $student->type === 'student') ? (int) $student->id : 0;

        $wpdb->insert(PKC_DB::payments(), array(
            'course_id'         => (int) $course->id,
            'student_id'        => $student_id,
            'full_name'         => $name,
            'email'             => $email,
            'whatsapp'          => $whatsapp,
            'address'           => $address,
            'payment_method_id' => (int) $method->id,
            'transaction_id'    => $txn,
            'receipt_path'      => $receipt,
            'coupon_id'         => $quote['coupon'] ? (int) $quote['coupon']->id : 0,
            'original_amount'   => PKC_Coupons::course_price($course),
            'discount_amount'   => $quote['discount'],
            'amount'            => $quote['total'],
            'status'            => 'pending',
            'submitted_at'      => PKC_DB::now(),
        ));
        $id = (int) $wpdb->insert_id;
        if ($quote['coupon']) {
            PKC_Coupons::record_usage((int) $quote['coupon']->id, $id, $email);
        }
        PKC_Audit::log('payment_submitted', 'payment', $id, array('course_id' => (int) $course->id, 'amount' => $quote['total']));
        PKC_Notifications::notify_admins('Payment submitted', $name . ' submitted a payment for ' . $course->title, 'payment', admin_url('admin.php?page=pkcouncil-payments&id=' . $id));
        return $id;
    }

    public static function approve($payment_id) {
        global $wpdb;
        $p = self::get($payment_id);
        if (!$p || $p->status !== 'pending') {
            return new WP_Error('pkc_pay', 'This payment cannot be approved.');
        }
        $wpdb->update(PKC_DB::payments(), array(
            'status'      => 'approved',
            'reviewed_at' => PKC_DB::now(),
            'reviewed_by' => get_current_user_id(),
        ), array('id' => (int) $payment_id));
        PKC_Audit::log('payment_approved', 'payment', (int) $payment_id);
        $course = pkc_course((int) $p->course_id);
        PKC_Mailer::payment_status($p->email, $p->full_name, 'approved', $course ? $course->title : 'your course');
        PKC_Notifications::notify_email_account($p, 'Payment approved', 'Your PKCouncil payment was approved. An administrator will create your account shortly if you do not already have one.');
        return true;
    }

    public static function reject($payment_id, $reason = '') {
        global $wpdb;
        $p = self::get($payment_id);
        if (!$p || $p->status !== 'pending') {
            return new WP_Error('pkc_pay', 'This payment cannot be rejected.');
        }
        $wpdb->update(PKC_DB::payments(), array(
            'status'            => 'rejected',
            'rejection_reason'  => sanitize_textarea_field($reason),
            'reviewed_at'       => PKC_DB::now(),
            'reviewed_by'       => get_current_user_id(),
        ), array('id' => (int) $payment_id));
        PKC_Audit::log('payment_rejected', 'payment', (int) $payment_id, array('reason' => $reason));
        $course = pkc_course((int) $p->course_id);
        PKC_Mailer::payment_status($p->email, $p->full_name, 'rejected', $course ? $course->title : 'your course', $reason);
        return true;
    }

    public static function create_student_from_payment($payment_id) {
        global $wpdb;
        $p = self::get($payment_id);
        if (!$p || $p->status !== 'approved') {
            return new WP_Error('pkc_account', 'Approve the payment before creating an account.');
        }
        if ((int) $p->created_account_id) {
            return new WP_Error('pkc_account', 'An account has already been created for this payment.');
        }

        $existing = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . PKC_DB::students() . ' WHERE email = %s', $p->email));
        $password = (string) pkc_settings('default_student_pass', 'Student@PKCouncil');
        if ($existing) {
            $student_id = (int) $existing->id;
        } else {
            $username = pkc_generate_username($p->full_name, PKC_DB::students());
            $wpdb->insert(PKC_DB::students(), array(
                'username'              => $username,
                'email'                 => $p->email,
                'password_hash'         => PKC_Auth::hash_password($password),
                'full_name'             => $p->full_name,
                'whatsapp'              => $p->whatsapp,
                'address'               => $p->address,
                'status'                => 'active',
                'must_change_password'  => 1,
                'created_at'            => PKC_DB::now(),
                'updated_at'            => PKC_DB::now(),
            ));
            $student_id = (int) $wpdb->insert_id;
            PKC_Mailer::credentials($p->email, $p->full_name, $username, $password, 'student');
            PKC_Audit::log('student_created', 'student', $student_id, array('payment_id' => (int) $p->id));
        }

        PKC_Access::grant_course($student_id, (int) $p->course_id, (int) $p->id);
        $wpdb->update(PKC_DB::payments(), array(
            'student_id'         => $student_id,
            'created_account_id' => $student_id,
        ), array('id' => (int) $p->id));

        PKC_Notifications::add('student', $student_id, 'Welcome to PKCouncil', 'Your course access is ready. Please change your password after first login.', 'account', pkc_url('student/'));
        return $student_id;
    }

    public static function get($id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . PKC_DB::payments() . ' WHERE id = %d', (int) $id));
    }
}
