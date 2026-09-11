<?php
get_header();
if (have_posts()) {
    while (have_posts()) {
        the_post();
        echo '<div class="pkc-shell pkc-page"><h1>' . esc_html(get_the_title()) . '</h1>';
        the_content();
        echo '</div>';
    }
}
get_footer();
