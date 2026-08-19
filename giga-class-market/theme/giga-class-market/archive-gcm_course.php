<?php
/**
 * Course marketplace archive.
 *
 * @package GigaClassMarket
 */

$search   = isset( $_GET['course_search'] ) ? sanitize_text_field( wp_unslash( $_GET['course_search'] ) ) : '';
$category = isset( $_GET['course_category'] ) ? sanitize_text_field( wp_unslash( $_GET['course_category'] ) ) : '';
$sort     = isset( $_GET['sort'] ) ? sanitize_key( wp_unslash( $_GET['sort'] ) ) : 'newest';
$paged    = max( 1, (int) get_query_var( 'paged' ), isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1 );

$args = array(
	'post_type'      => 'gcm_course',
	'post_status'    => 'publish',
	'posts_per_page' => 9,
	'paged'          => $paged,
);

if ( $search ) {
	$args['s'] = $search;
}

if ( $category ) {
	$args['tax_query'] = array(
		array(
			'taxonomy' => 'gcm_category',
			'field'    => 'slug',
			'terms'    => $category,
		),
	);
}

switch ( $sort ) {
	case 'price-low':
		$args['meta_key'] = '_gcm_price';
		$args['orderby']  = 'meta_value_num';
		$args['order']    = 'ASC';
		break;
	case 'price-high':
		$args['meta_key'] = '_gcm_price';
		$args['orderby']  = 'meta_value_num';
		$args['order']    = 'DESC';
		break;
	case 'rating':
		$args['meta_key'] = '_gcm_rating';
		$args['orderby']  = 'meta_value_num';
		$args['order']    = 'DESC';
		break;
	case 'featured':
		$args['meta_key'] = '_gcm_featured_priority';
		$args['orderby']  = 'meta_value_num';
		$args['order']    = 'DESC';
		$args['meta_query'] = array(
			array(
				'key'   => '_gcm_featured',
				'value' => '1',
			),
		);
		break;
	case 'newest':
	default:
		$args['orderby'] = 'date';
		$args['order']   = 'DESC';
		break;
}

$courses = new WP_Query( $args );

get_header();
?>
<section class="gcm-page-hero">
	<div class="gcm-container">
		<p class="gcm-eyebrow"><?php esc_html_e( 'Online Courses', 'giga-class-market' ); ?></p>
		<h1><?php esc_html_e( 'Online courses for CCNA, ethical hacking & AI coding', 'giga-class-market' ); ?></h1>
		<p><?php esc_html_e( 'Search and enroll in career-focused online courses from Giga Class Market — practical lessons, expert instructors, and verified certificates.', 'giga-class-market' ); ?></p>
	</div>
</section>

<section class="gcm-section">
	<div class="gcm-container">
		<form class="gcm-market-filters" method="get" action="<?php echo esc_url( get_post_type_archive_link( 'gcm_course' ) ); ?>">
			<label>
				<span><?php esc_html_e( 'Search courses', 'giga-class-market' ); ?></span>
				<input type="search" name="course_search" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search by skill or topic', 'giga-class-market' ); ?>">
			</label>
			<label>
				<span><?php esc_html_e( 'Category', 'giga-class-market' ); ?></span>
				<select name="course_category">
					<option value=""><?php esc_html_e( 'All categories', 'giga-class-market' ); ?></option>
					<?php
					$terms = get_terms(
						array(
							'taxonomy'   => 'gcm_category',
							'hide_empty' => false,
						)
					);
					if ( ! is_wp_error( $terms ) ) :
						foreach ( $terms as $term ) :
							?>
							<option value="<?php echo esc_attr( $term->slug ); ?>" <?php selected( $category, $term->slug ); ?>><?php echo esc_html( $term->name ); ?></option>
							<?php
						endforeach;
					endif;
					?>
				</select>
			</label>
			<label>
				<span><?php esc_html_e( 'Sort', 'giga-class-market' ); ?></span>
				<select name="sort">
					<option value="newest" <?php selected( $sort, 'newest' ); ?>><?php esc_html_e( 'Newest', 'giga-class-market' ); ?></option>
					<option value="featured" <?php selected( $sort, 'featured' ); ?>><?php esc_html_e( 'Featured', 'giga-class-market' ); ?></option>
					<option value="rating" <?php selected( $sort, 'rating' ); ?>><?php esc_html_e( 'Top rated', 'giga-class-market' ); ?></option>
					<option value="price-low" <?php selected( $sort, 'price-low' ); ?>><?php esc_html_e( 'Price: low to high', 'giga-class-market' ); ?></option>
					<option value="price-high" <?php selected( $sort, 'price-high' ); ?>><?php esc_html_e( 'Price: high to low', 'giga-class-market' ); ?></option>
				</select>
			</label>
			<button class="gcm-button" type="submit"><?php esc_html_e( 'Filter', 'giga-class-market' ); ?></button>
		</form>

		<div class="gcm-course-grid">
			<?php if ( $courses->have_posts() ) : ?>
				<?php while ( $courses->have_posts() ) : ?>
					<?php $courses->the_post(); ?>
					<?php get_template_part( 'template-parts/course/card' ); ?>
				<?php endwhile; ?>
				<?php wp_reset_postdata(); ?>
			<?php else : ?>
				<div class="gcm-empty-state">
					<h2><?php esc_html_e( 'No courses available yet.', 'giga-class-market' ); ?></h2>
					<p><?php esc_html_e( 'Try another search or check back as new premium courses are added.', 'giga-class-market' ); ?></p>
				</div>
			<?php endif; ?>
		</div>

		<div class="gcm-pagination">
			<?php
			echo wp_kses_post(
				paginate_links(
					array(
						'total'   => $courses->max_num_pages,
						'current' => $paged,
					)
				)
			);
			?>
		</div>
	</div>
</section>
<?php
get_footer();
