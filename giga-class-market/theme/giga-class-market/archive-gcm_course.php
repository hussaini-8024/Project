<?php
/**
 * Course marketplace archive.
 *
 * @package GigaClassMarket
 */

$search   = isset( $_GET['course_search'] ) ? sanitize_text_field( wp_unslash( $_GET['course_search'] ) ) : '';
$category = isset( $_GET['course_category'] ) ? sanitize_text_field( wp_unslash( $_GET['course_category'] ) ) : '';
$sort     = isset( $_GET['sort'] ) ? sanitize_key( wp_unslash( $_GET['sort'] ) ) : 'featured';
$paged    = max( 1, get_query_var( 'paged' ) );

$args = array(
	'post_type'      => 'gcm_course',
	'post_status'    => 'publish',
	'posts_per_page' => 9,
	'paged'          => $paged,
	's'              => $search,
);

if ( $category ) {
	$args['tax_query'] = array(
		array(
			'taxonomy' => 'gcm_course_category',
			'field'    => 'slug',
			'terms'    => $category,
		),
	);
}

switch ( $sort ) {
	case 'price-low':
		$args['meta_key'] = 'gcm_price';
		$args['orderby']  = 'meta_value_num';
		$args['order']    = 'ASC';
		break;
	case 'price-high':
		$args['meta_key'] = 'gcm_price';
		$args['orderby']  = 'meta_value_num';
		$args['order']    = 'DESC';
		break;
	case 'rating':
		$args['meta_key'] = 'gcm_rating';
		$args['orderby']  = 'meta_value_num';
		$args['order']    = 'DESC';
		break;
	case 'newest':
		$args['orderby'] = 'date';
		$args['order']   = 'DESC';
		break;
	default:
		$args['meta_key'] = 'gcm_featured_priority';
		$args['orderby']  = array(
			'meta_value_num' => 'ASC',
			'date'           => 'DESC',
		);
		$args['meta_query'] = array(
			'relation' => 'OR',
			array(
				'key'     => 'gcm_featured_priority',
				'compare' => 'EXISTS',
			),
			array(
				'key'     => 'gcm_featured_priority',
				'compare' => 'NOT EXISTS',
			),
		);
}

$courses = new WP_Query( $args );

get_header();
?>
<section class="gcm-page-hero">
	<div class="gcm-container">
		<p class="gcm-eyebrow"><?php esc_html_e( 'Marketplace', 'giga-class-market' ); ?></p>
		<h1><?php esc_html_e( 'Explore premium courses', 'giga-class-market' ); ?></h1>
		<p><?php esc_html_e( 'Search, compare, and enroll in polished learning experiences built for modern careers.', 'giga-class-market' ); ?></p>
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
							'taxonomy'   => 'gcm_course_category',
							'hide_empty' => true,
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
					<option value="featured" <?php selected( $sort, 'featured' ); ?>><?php esc_html_e( 'Featured', 'giga-class-market' ); ?></option>
					<option value="newest" <?php selected( $sort, 'newest' ); ?>><?php esc_html_e( 'Newest', 'giga-class-market' ); ?></option>
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
					<h2><?php esc_html_e( 'No courses found', 'giga-class-market' ); ?></h2>
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
