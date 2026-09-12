<?php
if (!defined('ABSPATH')) {
    exit;
}

function pkc_settings($key = null, $default = null) {
    return PKC_Settings::get($key, $default);
}

function pkc_url($path = '') {
    $path = ltrim((string) $path, '/');
    $q = '';
    if (strpos($path, '?') !== false) {
        list($path, $q) = explode('?', $path, 2);
        $q = '?' . $q;
    }
    $path = untrailingslashit($path);
    global $wp_rewrite;
    if ($wp_rewrite instanceof WP_Rewrite && $wp_rewrite->using_index_permalinks()) {
        $base = trim($wp_rewrite->root, '/');
        $url = $path === '' ? home_url('/' . $base . '/') : home_url(user_trailingslashit($base . '/' . $path));
        return $url . $q;
    }
    $url = $path === '' ? home_url('/') : home_url(user_trailingslashit($path));
    return $url . $q;
}

function pkc_page_url($slug) {
    $slug = sanitize_title($slug);
    $pages = get_option('pkc_pages', array());
    if (!empty($pages[$slug])) {
        $link = get_permalink((int) $pages[$slug]);
        if ($link) {
            return $link;
        }
    }
    $page = get_page_by_path($slug);
    if ($page) {
        return get_permalink($page);
    }
    return pkc_url($slug . '/');
}

function pkc_login_url($portal = 'student') {
    $url = pkc_page_url('login');
    if ($portal === 'teacher') {
        return add_query_arg('portal', 'teacher', $url);
    }
    return $url;
}

function pkc_asset($file) {
    $theme = get_template_directory_uri();
    return $theme . '/assets/' . ltrim($file, '/');
}

function pkc_current_account() {
    return PKC_Auth::current();
}

function pkc_is_student() {
    $a = PKC_Auth::current();
    return $a && $a->type === 'student';
}

function pkc_is_teacher() {
    $a = PKC_Auth::current();
    return $a && $a->type === 'teacher';
}

function pkc_is_admin_user() {
    return is_user_logged_in() && current_user_can('manage_options');
}

function pkc_format_price($amount) {
    $currency = pkc_settings('currency_symbol', 'Rs');
    return $currency . ' ' . number_format((float) $amount, 0);
}

function pkc_course($id_or_slug) {
    global $wpdb;
    $table = PKC_DB::courses();
    if (is_numeric($id_or_slug)) {
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", (int) $id_or_slug));
    }
    return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE slug = %s", sanitize_title($id_or_slug)));
}

function pkc_courses($args = array()) {
    global $wpdb;
    $defaults = array(
        'search'     => '',
        'category'   => 0,
        'level'      => '',
        'type'       => '',
        'min_price'  => null,
        'max_price'  => null,
        'sort'       => 'newest',
        'featured'   => null,
        'top'        => null,
        'status'     => 'published',
        'limit'      => 24,
        'offset'     => 0,
    );
    $args = wp_parse_args($args, $defaults);
    $c = PKC_DB::courses();
    $where = array('1=1');
    $params = array();

    if ($args['status']) {
        $where[] = 'status = %s';
        $params[] = $args['status'];
    }
    if ($args['search'] !== '') {
        $like = '%' . $wpdb->esc_like($args['search']) . '%';
        $where[] = '(title LIKE %s OR excerpt LIKE %s OR description LIKE %s)';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }
    if ((int) $args['category']) {
        $where[] = 'category_id = %d';
        $params[] = (int) $args['category'];
    }
    if ($args['level'] !== '') {
        $where[] = 'level = %s';
        $params[] = $args['level'];
    }
    if ($args['type'] !== '') {
        $where[] = 'course_type = %s';
        $params[] = $args['type'];
    }
    if ($args['min_price'] !== null && $args['min_price'] !== '') {
        $where[] = 'price >= %f';
        $params[] = (float) $args['min_price'];
    }
    if ($args['max_price'] !== null && $args['max_price'] !== '') {
        $where[] = 'price <= %f';
        $params[] = (float) $args['max_price'];
    }
    if ($args['featured'] !== null) {
        $where[] = 'is_featured = %d';
        $params[] = (int) $args['featured'];
    }
    if ($args['top'] !== null) {
        $where[] = 'is_top = %d';
        $params[] = (int) $args['top'];
    }

    $order = 'created_at DESC';
    switch ($args['sort']) {
        case 'oldest':
            $order = 'created_at ASC';
            break;
        case 'price_asc':
            $order = 'price ASC';
            break;
        case 'price_desc':
            $order = 'price DESC';
            break;
        case 'popular':
            $order = 'enrolled_count DESC, created_at DESC';
            break;
        case 'featured':
            $order = 'is_featured DESC, is_top DESC, created_at DESC';
            break;
        default:
            $order = 'created_at DESC';
    }

    $sql = "SELECT * FROM {$c} WHERE " . implode(' AND ', $where) . " ORDER BY {$order}";
    $limit = max(1, min(100, (int) $args['limit']));
    $offset = max(0, (int) $args['offset']);
    $sql .= $wpdb->prepare(' LIMIT %d OFFSET %d', $limit, $offset);

    if ($params) {
        $sql = $wpdb->prepare($sql, $params);
    }
    return $wpdb->get_results($sql);
}

function pkc_course_count($args = array()) {
    $args['limit'] = 1000;
    $args['offset'] = 0;
    return count(pkc_courses($args));
}

function pkc_categories() {
    global $wpdb;
    return $wpdb->get_results('SELECT * FROM ' . PKC_DB::categories() . ' ORDER BY sort_order ASC, name ASC');
}

function pkc_category($id) {
    global $wpdb;
    return $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . PKC_DB::categories() . ' WHERE id = %d', (int) $id));
}

function pkc_course_teachers($course_id) {
    global $wpdb;
    return $wpdb->get_results($wpdb->prepare(
        'SELECT t.* FROM ' . PKC_DB::teachers() . ' t
         INNER JOIN ' . PKC_DB::teacher_courses() . ' tc ON tc.teacher_id = t.id
         WHERE tc.course_id = %d AND t.status = %s
         ORDER BY t.full_name ASC',
        (int) $course_id,
        'active'
    ));
}

function pkc_course_modules($course_id) {
    global $wpdb;
    return $wpdb->get_results($wpdb->prepare(
        'SELECT * FROM ' . PKC_DB::modules() . ' WHERE course_id = %d ORDER BY sort_order ASC, id ASC',
        (int) $course_id
    ));
}

function pkc_module_lessons($module_id) {
    global $wpdb;
    return $wpdb->get_results($wpdb->prepare(
        'SELECT * FROM ' . PKC_DB::lessons() . ' WHERE module_id = %d ORDER BY sort_order ASC, id ASC',
        (int) $module_id
    ));
}

function pkc_course_lessons($course_id) {
    global $wpdb;
    return $wpdb->get_results($wpdb->prepare(
        'SELECT * FROM ' . PKC_DB::lessons() . ' WHERE course_id = %d ORDER BY sort_order ASC, id ASC',
        (int) $course_id
    ));
}

function pkc_course_materials($course_id) {
    global $wpdb;
    return $wpdb->get_results($wpdb->prepare(
        'SELECT * FROM ' . PKC_DB::materials() . ' WHERE course_id = %d ORDER BY id ASC',
        (int) $course_id
    ));
}

function pkc_json($value, $default = array()) {
    if (is_array($value)) {
        return $value;
    }
    if (!is_string($value) || $value === '') {
        return $default;
    }
    $decoded = json_decode($value, true);
    return is_array($decoded) ? $decoded : $default;
}

function pkc_reviews($args = array()) {
    global $wpdb;
    $where = array("status = 'approved'");
    $params = array();
    if (!empty($args['homepage'])) {
        $where[] = 'show_on_homepage = 1';
    }
    if (!empty($args['featured'])) {
        $where[] = 'is_featured = 1';
    }
    if (!empty($args['course_id'])) {
        $where[] = 'course_id = %d';
        $params[] = (int) $args['course_id'];
    }
    $sql = 'SELECT * FROM ' . PKC_DB::reviews() . ' WHERE ' . implode(' AND ', $where) . ' ORDER BY is_featured DESC, created_at DESC';
    if (!empty($args['limit'])) {
        $sql .= $wpdb->prepare(' LIMIT %d', (int) $args['limit']);
    }
    if ($params) {
        $sql = $wpdb->prepare($sql, $params);
    }
    return $wpdb->get_results($sql);
}

function pkc_payment_methods($active_only = true) {
    global $wpdb;
    $sql = 'SELECT * FROM ' . PKC_DB::payment_methods();
    if ($active_only) {
        $sql .= ' WHERE is_active = 1';
    }
    $sql .= ' ORDER BY sort_order ASC, id ASC';
    return $wpdb->get_results($sql);
}

function pkc_whatsapp_url($message = '') {
    $num = preg_replace('/\D+/', '', (string) pkc_settings('whatsapp', '923000000000'));
    $url = 'https://wa.me/' . $num;
    if ($message !== '') {
        $url .= '?text=' . rawurlencode($message);
    }
    return $url;
}

function pkc_course_cover_svg($course) {
    $title = esc_html($course->title);
    $cat = '';
    if (!empty($course->category_id)) {
        $row = pkc_category($course->category_id);
        $cat = $row ? esc_html($row->name) : '';
    }
    $hues = array(168, 192, 210, 32, 145, 250);
    $hue = $hues[((int) $course->id) % count($hues)];
    return 'data:image/svg+xml,' . rawurlencode(
        '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 800 500"><defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1"><stop offset="0%" stop-color="hsl(' . $hue . ',42%,18%)"/><stop offset="100%" stop-color="hsl(' . ($hue + 24) . ',38%,32%)"/></linearGradient></defs><rect width="800" height="500" fill="url(#g)"/><circle cx="640" cy="90" r="120" fill="rgba(255,255,255,0.06)"/><circle cx="90" cy="420" r="90" fill="rgba(196,163,90,0.18)"/><rect x="70" y="310" width="150" height="110" rx="8" fill="#f4efe4" transform="rotate(-12 145 365)"/><rect x="95" y="300" width="150" height="110" rx="8" fill="#c4a35a" transform="rotate(-4 170 355)"/><text x="72" y="86" fill="#c4a35a" font-size="18" font-family="Georgia,serif" letter-spacing="4">' . strtoupper($cat) . '</text><text x="72" y="150" fill="#f7f4ee" font-size="42" font-family="Georgia,serif">' . $title . '</text></svg>'
    );
}

function pkc_course_image_url($course) {
    if (!empty($course->image_id)) {
        $url = wp_get_attachment_image_url((int) $course->image_id, 'large');
        if ($url) {
            return $url;
        }
    }
    if (!empty($course->image_url)) {
        return $course->image_url;
    }
    return pkc_course_cover_svg($course);
}

function pkc_noindex() {
    echo '<meta name="robots" content="noindex,nofollow,noarchive">' . "\n";
}

function pkc_sanitize_phone($value) {
    return substr(preg_replace('/[^0-9+\-\s]/', '', (string) $value), 0, 30);
}

function pkc_generate_username($full_name, $table) {
    global $wpdb;
    $base = strtolower(sanitize_user(str_replace(' ', '.', $full_name), true));
    $base = trim($base, '.');
    if ($base === '') {
        $base = 'student';
    }
    $try = $base;
    $i = 1;
    while ($wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE username = %s", $try))) {
        $try = $base . $i;
        $i++;
    }
    return $try;
}

function pkc_flash($key, $message = null) {
    if (!session_id()) {
        @session_start();
    }
    if ($message === null) {
        $val = $_SESSION['pkc_flash'][$key] ?? null;
        unset($_SESSION['pkc_flash'][$key]);
        return $val;
    }
    $_SESSION['pkc_flash'][$key] = $message;
}
