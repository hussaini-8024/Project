<?php
if (!defined('ABSPATH')) {
    exit;
}

class PKC_Materials {
    public static function serve($material_id) {
        $account = PKC_Auth::current();
        if (!$account || $account->type !== 'student') {
            if (!pkc_is_admin_user()) {
                wp_die('You do not have access to this file.', 'Forbidden', array('response' => 403));
            }
        }
        global $wpdb;
        $m = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . PKC_DB::materials() . ' WHERE id = %d', (int) $material_id));
        if (!$m) {
            wp_die('File not found.', 'Not Found', array('response' => 404));
        }
        if (!pkc_is_admin_user()) {
            if (!PKC_Access::student_has_course((int) $account->id, (int) $m->course_id)) {
                wp_die('You do not have access to this file.', 'Forbidden', array('response' => 403));
            }
            PKC_Progress::track((int) $account->id, (int) $m->course_id, 'pdf_accessed', (int) $m->lesson_id, (int) $m->id);
        }
        $path = self::absolute_path($m->file_path);
        if (!$path || !is_readable($path)) {
            wp_die('This file is currently unavailable.', 'Unavailable', array('response' => 404));
        }
        nocache_headers();
        header('X-Robots-Tag: noindex, nofollow');
        header('Content-Type: ' . (self::mime($path) ?: 'application/octet-stream'));
        header('Content-Length: ' . filesize($path));
        $disp = (int) $m->is_downloadable ? 'attachment' : 'inline';
        header('Content-Disposition: ' . $disp . '; filename="' . basename($m->title) . '"');
        readfile($path);
        exit;
    }

    public static function serve_receipt($payment_id) {
        if (!pkc_is_admin_user()) {
            wp_die('Forbidden', '', array('response' => 403));
        }
        $p = PKC_Payments::get($payment_id);
        if (!$p || !$p->receipt_path) {
            wp_die('Receipt not found.', '', array('response' => 404));
        }
        $path = self::absolute_path($p->receipt_path);
        if (!$path) {
            wp_die('Receipt unavailable.', '', array('response' => 404));
        }
        nocache_headers();
        header('Content-Type: ' . (self::mime($path) ?: 'application/octet-stream'));
        header('Content-Disposition: inline; filename="receipt-' . (int) $payment_id . '"');
        readfile($path);
        exit;
    }

    public static function absolute_path($relative) {
        $relative = ltrim(str_replace('..', '', (string) $relative), '/');
        $path = WP_CONTENT_DIR . '/uploads/' . $relative;
        $real = realpath($path);
        $root = realpath(PKC_Activator::protected_dir());
        if (!$real || !$root || strpos($real, $root) !== 0) {
            return null;
        }
        return $real;
    }

    public static function mime($path) {
        $f = finfo_open(FILEINFO_MIME_TYPE);
        $m = $f ? finfo_file($f, $path) : '';
        if ($f) {
            finfo_close($f);
        }
        return $m;
    }

    public static function write_sample_pdf($filename, $title, $body) {
        PKC_Activator::ensure_protected_dir();
        $path = PKC_Activator::protected_dir() . '/' . $filename;
        $text = $title . "\n\n" . $body;
        $content = self::minimal_pdf($text);
        file_put_contents($path, $content);
        return 'pkc-protected/' . $filename;
    }

    public static function minimal_pdf($text) {
        $text = str_replace(array('\\', '(', ')'), array('\\\\', '\\(', '\\)'), $text);
        $stream = "BT /F1 12 Tf 50 750 Td (" . $text . ") Tj ET";
        $len = strlen($stream);
        return "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Count 1/Kids[3 0 R]>>endobj\n3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 612 792]/Contents 4 0 R/Resources<</Font<</F1 5 0 R>>>>>>endobj\n4 0 obj<</Length {$len}>>stream\n{$stream}\nendstream endobj\n5 0 obj<</Type/Font/Subtype/Type1/BaseFont/Helvetica>>endobj\nxref\n0 6\n0000000000 65535 f \ntrailer<</Size 6/Root 1 0 R>>\nstartxref\n0\n%%EOF";
    }
}
