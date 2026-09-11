<?php
if (!defined('ABSPATH')) {
    exit;
}

class PKC_Coupons {
    public static function find($code) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . PKC_DB::coupons() . ' WHERE code = %s',
            strtoupper(sanitize_text_field($code))
        ));
    }

    public static function allowed_course_ids($coupon_id) {
        global $wpdb;
        return array_map('intval', $wpdb->get_col($wpdb->prepare(
            'SELECT course_id FROM ' . PKC_DB::coupon_courses() . ' WHERE coupon_id = %d',
            (int) $coupon_id
        )));
    }

    public static function quote($code, $course, $email = '') {
        $code = trim((string) $code);
        if ($code === '') {
            return array(
                'ok'       => true,
                'coupon'   => null,
                'discount' => 0,
                'total'    => self::course_price($course),
            );
        }
        $coupon = self::find($code);
        if (!$coupon || !(int) $coupon->is_active) {
            return array('ok' => false, 'error' => 'This coupon is not valid.');
        }
        $now = time();
        if (!empty($coupon->starts_at) && strtotime($coupon->starts_at) > $now) {
            return array('ok' => false, 'error' => 'This coupon is not active yet.');
        }
        if (!empty($coupon->expires_at) && strtotime($coupon->expires_at) < $now) {
            return array('ok' => false, 'error' => 'This coupon has expired.');
        }
        if ((int) $coupon->max_uses > 0 && (int) $coupon->used_count >= (int) $coupon->max_uses) {
            return array('ok' => false, 'error' => 'This coupon has reached its usage limit.');
        }
        $allowed = self::allowed_course_ids($coupon->id);
        if ($allowed && !in_array((int) $course->id, $allowed, true)) {
            return array('ok' => false, 'error' => 'This coupon does not apply to the selected course.');
        }
        $price = self::course_price($course);
        if ((float) $coupon->min_amount > 0 && $price < (float) $coupon->min_amount) {
            return array('ok' => false, 'error' => 'This coupon requires a higher course price.');
        }
        if ($email && (int) $coupon->max_per_user > 0) {
            global $wpdb;
            $used = (int) $wpdb->get_var($wpdb->prepare(
                'SELECT COUNT(*) FROM ' . PKC_DB::coupon_usage() . ' WHERE coupon_id = %d AND student_email = %s',
                (int) $coupon->id,
                $email
            ));
            if ($used >= (int) $coupon->max_per_user) {
                return array('ok' => false, 'error' => 'You have already used this coupon.');
            }
        }
        $discount = 0;
        if ($coupon->discount_type === 'percent') {
            $discount = round($price * ((float) $coupon->discount_value / 100), 2);
        } else {
            $discount = (float) $coupon->discount_value;
        }
        $discount = min($discount, $price);
        return array(
            'ok'       => true,
            'coupon'   => $coupon,
            'discount' => $discount,
            'total'    => max(0, round($price - $discount, 2)),
        );
    }

    public static function course_price($course) {
        if (!empty($course->sale_price) && (float) $course->sale_price > 0 && (float) $course->sale_price < (float) $course->price) {
            return (float) $course->sale_price;
        }
        return (float) $course->price;
    }

    public static function record_usage($coupon_id, $payment_id, $email) {
        global $wpdb;
        $wpdb->insert(PKC_DB::coupon_usage(), array(
            'coupon_id'     => (int) $coupon_id,
            'payment_id'    => (int) $payment_id,
            'student_email' => $email,
            'used_at'       => PKC_DB::now(),
        ));
        $wpdb->query($wpdb->prepare(
            'UPDATE ' . PKC_DB::coupons() . ' SET used_count = used_count + 1 WHERE id = %d',
            (int) $coupon_id
        ));
    }
}
