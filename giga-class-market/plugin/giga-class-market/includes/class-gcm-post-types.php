<?php
/**
 * Custom post types, taxonomy, and course meta.
 *
 * @package GigaClassMarket
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers content types.
 */
class GCM_Post_Types {

	/**
	 * Register CPTs and taxonomies.
	 *
	 * @return void
	 */
	public function register() {
		register_post_type(
			'gcm_course',
			array(
				'labels'       => array(
					'name'          => __( 'Courses', 'giga-class-market' ),
					'singular_name' => __( 'Course', 'giga-class-market' ),
					'add_new_item'  => __( 'Add New Course', 'giga-class-market' ),
					'edit_item'     => __( 'Edit Course', 'giga-class-market' ),
				),
				'public'       => true,
				'has_archive'  => true,
				'rewrite'      => array( 'slug' => 'courses' ),
				'menu_icon'    => 'dashicons-welcome-learn-more',
				'supports'     => array( 'title', 'editor', 'thumbnail', 'excerpt' ),
				'show_in_rest' => true,
				'capability_type' => 'post',
			)
		);

		register_post_type(
			'gcm_testimonial',
			array(
				'labels'       => array(
					'name'          => __( 'Testimonials', 'giga-class-market' ),
					'singular_name' => __( 'Testimonial', 'giga-class-market' ),
				),
				'public'       => false,
				'show_ui'      => true,
				'show_in_menu' => false,
				'supports'     => array( 'title', 'editor', 'thumbnail' ),
				'show_in_rest' => true,
			)
		);

		register_post_type(
			'gcm_slide',
			array(
				'labels'       => array(
					'name'          => __( 'Hero Slides', 'giga-class-market' ),
					'singular_name' => __( 'Hero Slide', 'giga-class-market' ),
				),
				'public'       => false,
				'show_ui'      => true,
				'show_in_menu' => false,
				'supports'     => array( 'title', 'editor', 'thumbnail', 'excerpt' ),
				'show_in_rest' => true,
			)
		);

		register_taxonomy(
			'gcm_category',
			array( 'gcm_course' ),
			array(
				'labels'       => array(
					'name'          => __( 'Course Categories', 'giga-class-market' ),
					'singular_name' => __( 'Course Category', 'giga-class-market' ),
				),
				'public'       => true,
				'hierarchical' => true,
				'rewrite'      => array( 'slug' => 'course-category' ),
				'show_in_rest' => true,
			)
		);

		$this->seed_terms();
		$this->register_meta();
	}

	/**
	 * Register post meta for REST/schema compatibility.
	 *
	 * @return void
	 */
	private function register_meta() {
		$meta = array(
			'_gcm_price'                => 'number',
			'_gcm_discount_price'       => 'number',
			'_gcm_sale_label'           => 'string',
			'_gcm_faq'                  => 'string',
			'_gcm_bundle_ids'           => 'string',
			'_gcm_community_whatsapp'   => 'string',
			'_gcm_duration'             => 'string',
			'_gcm_instructor'           => 'string',
			'_gcm_what_you_learn'       => 'string',
			'_gcm_requirements'         => 'string',
			'_gcm_featured'             => 'boolean',
			'_gcm_featured_priority'    => 'integer',
			'_gcm_rating'               => 'number',
		);

		foreach ( $meta as $key => $type ) {
			register_post_meta(
				'gcm_course',
				$key,
				array(
					'type'              => $type,
					'single'            => true,
					'sanitize_callback' => array( $this, 'sanitize_registered_meta' ),
					'show_in_rest'      => true,
					'auth_callback'     => function() {
						return current_user_can( 'gcm_manage_courses' ) || current_user_can( 'edit_posts' );
					},
				)
			);
		}
	}

	/**
	 * Sanitize registered meta.
	 *
	 * @param mixed  $value Meta value.
	 * @param string $key Meta key.
	 * @return mixed
	 */
	public function sanitize_registered_meta( $value, $key ) {
		switch ( $key ) {
			case '_gcm_price':
			case '_gcm_discount_price':
			case '_gcm_rating':
				return (float) $value;
			case '_gcm_featured':
				return $value ? 1 : 0;
			case '_gcm_featured_priority':
				return absint( $value );
			case '_gcm_what_you_learn':
			case '_gcm_requirements':
			case '_gcm_faq':
				return sanitize_textarea_field( $value );
			case '_gcm_community_whatsapp':
				$url = esc_url_raw( $value );
				return $url ? $url : sanitize_text_field( $value );
			default:
				return sanitize_text_field( $value );
		}
	}

	/**
	 * Seed default course categories.
	 *
	 * @return void
	 */
	private function seed_terms() {
		$terms = array(
			'Networking',
			'Cyber Security',
			'Programming',
			'Web Development',
			'App Development',
			'Database',
			'Cloud',
			'DevOps',
			'IT & Technology',
			'Other',
		);

		foreach ( $terms as $term ) {
			if ( ! term_exists( $term, 'gcm_category' ) ) {
				wp_insert_term( $term, 'gcm_category' );
			}
		}
	}

	/**
	 * Register course meta box.
	 *
	 * @return void
	 */
	public function register_meta_boxes() {
		add_meta_box(
			'gcm_course_details',
			__( 'Course Details', 'giga-class-market' ),
			array( $this, 'render_course_meta_box' ),
			'gcm_course',
			'normal',
			'high'
		);

		add_meta_box(
			'gcm_course_curriculum',
			__( 'Course Curriculum', 'giga-class-market' ),
			array( $this, 'render_curriculum_meta_box' ),
			'gcm_course',
			'normal',
			'default'
		);
	}

	/**
	 * Render course meta box.
	 *
	 * @param WP_Post $post Post.
	 * @return void
	 */
	public function render_course_meta_box( $post ) {
		wp_nonce_field( 'gcm_save_course_meta', 'gcm_course_meta_nonce' );

		$fields = array(
			'price'               => get_post_meta( $post->ID, '_gcm_price', true ),
			'discount_price'      => get_post_meta( $post->ID, '_gcm_discount_price', true ),
			'sale_label'          => get_post_meta( $post->ID, '_gcm_sale_label', true ),
			'faq'                 => get_post_meta( $post->ID, '_gcm_faq', true ),
			'bundle_ids'          => get_post_meta( $post->ID, '_gcm_bundle_ids', true ),
			'community_whatsapp'  => get_post_meta( $post->ID, '_gcm_community_whatsapp', true ),
			'duration'            => get_post_meta( $post->ID, '_gcm_duration', true ),
			'instructor'          => get_post_meta( $post->ID, '_gcm_instructor', true ),
			'what_you_learn'      => get_post_meta( $post->ID, '_gcm_what_you_learn', true ),
			'requirements'        => get_post_meta( $post->ID, '_gcm_requirements', true ),
			'featured'            => get_post_meta( $post->ID, '_gcm_featured', true ),
			'rating'              => get_post_meta( $post->ID, '_gcm_rating', true ),
		);
		?>
		<div class="gcm-meta-grid">
			<p class="gcm-meta-hint">
				<?php esc_html_e( 'Set a Course Thumbnail in the sidebar (Featured image panel). It is shown on the courses page, homepage, and course details.', 'giga-class-market' ); ?>
			</p>
			<p>
				<label for="gcm_price"><?php esc_html_e( 'Price', 'giga-class-market' ); ?></label>
				<input type="number" step="0.01" min="0" id="gcm_price" name="gcm_price" value="<?php echo esc_attr( $fields['price'] ); ?>" class="widefat" />
			</p>
			<p>
				<label for="gcm_discount_price"><?php esc_html_e( 'Sale / discount price', 'giga-class-market' ); ?></label>
				<input type="number" step="0.01" min="0" id="gcm_discount_price" name="gcm_discount_price" value="<?php echo esc_attr( $fields['discount_price'] ); ?>" class="widefat" />
			</p>
			<p>
				<label for="gcm_sale_label"><?php esc_html_e( 'Sale label', 'giga-class-market' ); ?></label>
				<input type="text" id="gcm_sale_label" name="gcm_sale_label" value="<?php echo esc_attr( $fields['sale_label'] ); ?>" class="widefat" placeholder="<?php esc_attr_e( 'Early bird', 'giga-class-market' ); ?>" />
			</p>
			<p>
				<label for="gcm_duration"><?php esc_html_e( 'Duration', 'giga-class-market' ); ?></label>
				<input type="text" id="gcm_duration" name="gcm_duration" value="<?php echo esc_attr( $fields['duration'] ); ?>" class="widefat" />
			</p>
			<p>
				<label for="gcm_instructor"><?php esc_html_e( 'Instructor', 'giga-class-market' ); ?></label>
				<input type="text" id="gcm_instructor" name="gcm_instructor" value="<?php echo esc_attr( $fields['instructor'] ); ?>" class="widefat" />
			</p>
			<p>
				<label for="gcm_rating"><?php esc_html_e( 'Rating', 'giga-class-market' ); ?></label>
				<input type="number" step="0.1" min="0" max="5" id="gcm_rating" name="gcm_rating" value="<?php echo esc_attr( $fields['rating'] ); ?>" class="widefat" />
			</p>
			<p>
				<label>
					<input type="checkbox" name="gcm_featured" value="1" <?php checked( $fields['featured'], 1 ); ?> />
					<?php esc_html_e( 'Feature on homepage', 'giga-class-market' ); ?>
				</label>
			</p>
			<p>
				<label for="gcm_what_you_learn"><?php esc_html_e( 'What students will learn', 'giga-class-market' ); ?></label>
				<textarea id="gcm_what_you_learn" name="gcm_what_you_learn" rows="4" class="widefat"><?php echo esc_textarea( $fields['what_you_learn'] ); ?></textarea>
			</p>
			<p>
				<label for="gcm_requirements"><?php esc_html_e( 'Requirements', 'giga-class-market' ); ?></label>
				<textarea id="gcm_requirements" name="gcm_requirements" rows="4" class="widefat"><?php echo esc_textarea( $fields['requirements'] ); ?></textarea>
			</p>
			<p>
				<label for="gcm_faq"><?php esc_html_e( 'FAQ (one Q/A per line: Question? | Answer)', 'giga-class-market' ); ?></label>
				<textarea id="gcm_faq" name="gcm_faq" rows="5" class="widefat"><?php echo esc_textarea( $fields['faq'] ); ?></textarea>
			</p>
			<p>
				<label for="gcm_bundle_ids"><?php esc_html_e( 'Bundle course IDs (comma-separated)', 'giga-class-market' ); ?></label>
				<input type="text" id="gcm_bundle_ids" name="gcm_bundle_ids" value="<?php echo esc_attr( $fields['bundle_ids'] ); ?>" class="widefat" placeholder="12,34,56" />
			</p>
			<p>
				<label for="gcm_community_whatsapp"><?php esc_html_e( 'Community WhatsApp (URL or number)', 'giga-class-market' ); ?></label>
				<input type="text" id="gcm_community_whatsapp" name="gcm_community_whatsapp" value="<?php echo esc_attr( $fields['community_whatsapp'] ); ?>" class="widefat" />
			</p>
		</div>
		<?php
	}

	/**
	 * Render curriculum meta box.
	 *
	 * @param WP_Post $post Post.
	 * @return void
	 */
	public function render_curriculum_meta_box( $post ) {
		$curriculum = GCM_Curriculum_Service::get_course_curriculum( $post->ID );
		?>
		<p><?php esc_html_e( 'Manage modules and lessons as JSON. Keep existing IDs to update records; omit IDs to create new modules or lessons.', 'giga-class-market' ); ?></p>
		<textarea name="gcm_curriculum_payload" rows="18" class="widefat code"><?php echo esc_textarea( wp_json_encode( $curriculum, JSON_PRETTY_PRINT ) ); ?></textarea>
		<p class="description"><?php esc_html_e( 'Lesson fields: title, content, video_url, video_attachment_id, duration_minutes, is_preview (0/1). Course access is enforced before lesson video links are displayed to students.', 'giga-class-market' ); ?></p>
		<?php
	}

	/**
	 * Save course meta.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post Post object.
	 * @return void
	 */
	public function save_course_meta( $post_id, $post ) {
		if ( ! isset( $_POST['gcm_course_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['gcm_course_meta_nonce'] ) ), 'gcm_save_course_meta' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) || 'gcm_course' !== $post->post_type ) {
			return;
		}

		$price             = isset( $_POST['gcm_price'] ) ? (float) wp_unslash( $_POST['gcm_price'] ) : 0;
		$discount_price    = isset( $_POST['gcm_discount_price'] ) ? (float) wp_unslash( $_POST['gcm_discount_price'] ) : 0;
		$sale_label        = isset( $_POST['gcm_sale_label'] ) ? sanitize_text_field( wp_unslash( $_POST['gcm_sale_label'] ) ) : '';
		$faq               = isset( $_POST['gcm_faq'] ) ? sanitize_textarea_field( wp_unslash( $_POST['gcm_faq'] ) ) : '';
		$bundle_ids        = isset( $_POST['gcm_bundle_ids'] ) ? sanitize_text_field( wp_unslash( $_POST['gcm_bundle_ids'] ) ) : '';
		$community_whatsapp = isset( $_POST['gcm_community_whatsapp'] ) ? sanitize_text_field( wp_unslash( $_POST['gcm_community_whatsapp'] ) ) : '';
		$duration          = isset( $_POST['gcm_duration'] ) ? sanitize_text_field( wp_unslash( $_POST['gcm_duration'] ) ) : '';
		$instructor        = isset( $_POST['gcm_instructor'] ) ? sanitize_text_field( wp_unslash( $_POST['gcm_instructor'] ) ) : '';
		$what_you_learn    = isset( $_POST['gcm_what_you_learn'] ) ? sanitize_textarea_field( wp_unslash( $_POST['gcm_what_you_learn'] ) ) : '';
		$requirements      = isset( $_POST['gcm_requirements'] ) ? sanitize_textarea_field( wp_unslash( $_POST['gcm_requirements'] ) ) : '';
		$rating            = isset( $_POST['gcm_rating'] ) ? (float) wp_unslash( $_POST['gcm_rating'] ) : 0;
		$featured          = isset( $_POST['gcm_featured'] ) ? 1 : 0;

		$bundle_ids = implode(
			',',
			array_filter(
				array_map(
					static function ( $id ) {
						return absint( $id );
					},
					preg_split( '/[\s,]+/', $bundle_ids )
				)
			)
		);

		update_post_meta( $post_id, '_gcm_price', max( 0, $price ) );
		update_post_meta( $post_id, '_gcm_discount_price', max( 0, $discount_price ) );
		update_post_meta( $post_id, '_gcm_sale_label', $sale_label );
		update_post_meta( $post_id, '_gcm_faq', $faq );
		update_post_meta( $post_id, '_gcm_bundle_ids', $bundle_ids );
		update_post_meta( $post_id, '_gcm_community_whatsapp', $community_whatsapp );
		update_post_meta( $post_id, '_gcm_duration', $duration );
		update_post_meta( $post_id, '_gcm_instructor', $instructor );
		update_post_meta( $post_id, '_gcm_what_you_learn', $what_you_learn );
		update_post_meta( $post_id, '_gcm_requirements', $requirements );
		update_post_meta( $post_id, '_gcm_rating', min( 5, max( 0, $rating ) ) );

		self::set_featured( $post_id, $featured );

		if ( isset( $_POST['gcm_curriculum_payload'] ) ) {
			$payload = json_decode( wp_unslash( $_POST['gcm_curriculum_payload'] ), true );
			if ( is_array( $payload ) ) {
				GCM_Curriculum_Service::save_course_curriculum( $post_id, $payload );
			}
		}

		// Bust CDN/page cache so logged-out visitors see new sale prices immediately.
		update_option( 'gcm_cache_bust', (string) time(), false );
		if ( function_exists( 'wp_cache_flush' ) ) {
			wp_cache_flush();
		}
		do_action( 'stackcache_purge_all' );
		do_action( 'gcm_purge_front_caches' );
	}

	/**
	 * Set featured state and enforce featured limit.
	 *
	 * @param int  $post_id Course ID.
	 * @param bool $featured Featured state.
	 * @return void
	 */
	public static function set_featured( $post_id, $featured ) {
		$post_id  = absint( $post_id );
		$featured = $featured ? 1 : 0;

		update_post_meta( $post_id, '_gcm_featured', $featured );

		if ( $featured ) {
			if ( ! get_post_meta( $post_id, '_gcm_featured_priority', true ) ) {
				update_post_meta( $post_id, '_gcm_featured_priority', time() );
			}
			self::enforce_featured_limit( $post_id );
		} else {
			delete_post_meta( $post_id, '_gcm_featured_priority' );
		}
	}

	/**
	 * Keep only configured number of featured courses.
	 *
	 * @param int $current_id Current course ID.
	 * @return void
	 */
	private static function enforce_featured_limit( $current_id ) {
		$settings = GCM_Settings_Service::get_settings();
		$limit    = isset( $settings['course']['featured_count'] ) ? absint( $settings['course']['featured_count'] ) : 3;
		$limit    = min( 3, max( 1, $limit ) );

		$query = new WP_Query(
			array(
				'post_type'      => 'gcm_course',
				'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_query'     => array(
					array(
						'key'   => '_gcm_featured',
						'value' => '1',
					),
				),
				'meta_key'       => '_gcm_featured_priority',
				'orderby'        => 'meta_value_num',
				'order'          => 'ASC',
			)
		);

		$featured_ids = array_map( 'absint', $query->posts );
		while ( count( $featured_ids ) > $limit ) {
			$remove_id = array_shift( $featured_ids );
			if ( $remove_id === $current_id && ! empty( $featured_ids ) ) {
				$featured_ids[] = $remove_id;
				$remove_id      = array_shift( $featured_ids );
			}
			update_post_meta( $remove_id, '_gcm_featured', 0 );
			delete_post_meta( $remove_id, '_gcm_featured_priority' );
		}
	}

	/**
	 * Relabel featured image UI as Course Thumbnail.
	 *
	 * @param object $labels Post type labels.
	 * @return object
	 */
	public function course_thumbnail_labels( $labels ) {
		$labels->featured_image        = __( 'Course Thumbnail', 'giga-class-market' );
		$labels->set_featured_image    = __( 'Set course thumbnail', 'giga-class-market' );
		$labels->remove_featured_image = __( 'Remove course thumbnail', 'giga-class-market' );
		$labels->use_featured_image    = __( 'Use as course thumbnail', 'giga-class-market' );
		return $labels;
	}

	/**
	 * Keep the course thumbnail box visible and high priority in the sidebar.
	 *
	 * @return void
	 */
	public function promote_course_thumbnail_box() {
		remove_meta_box( 'postimagediv', 'gcm_course', 'side' );
		add_meta_box(
			'postimagediv',
			__( 'Course Thumbnail', 'giga-class-market' ),
			'post_thumbnail_meta_box',
			'gcm_course',
			'side',
			'high'
		);
	}

	/**
	 * Allow course publish even when thumbnail timing is ambiguous.
	 *
	 * The block editor attaches featured media AFTER wp_insert_post_data runs, so
	 * forcing draft here incorrectly blocked Publish. Thumbnail is encouraged via
	 * admin notices instead of silently demoting to draft.
	 *
	 * @param array $data    Sanitized post data.
	 * @param array $postarr Raw post data.
	 * @return array
	 */
	public function require_course_thumbnail_on_publish( $data, $postarr ) {
		if ( empty( $data['post_type'] ) || 'gcm_course' !== $data['post_type'] ) {
			return $data;
		}

		if ( 'publish' !== ( $data['post_status'] ?? '' ) ) {
			return $data;
		}

		// Block/REST editor sets featured_media after this filter — never demote those saves.
		if ( ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) ) {
			return $data;
		}

		$post_id = isset( $postarr['ID'] ) ? absint( $postarr['ID'] ) : 0;
		$thumb   = 0;

		if ( isset( $postarr['_thumbnail_id'] ) ) {
			$thumb = absint( $postarr['_thumbnail_id'] );
		}

		if ( isset( $_POST['_thumbnail_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$thumb = absint( wp_unslash( $_POST['_thumbnail_id'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		}

		if ( $thumb <= 0 && $post_id ) {
			$thumb = (int) get_post_thumbnail_id( $post_id );
		}

		// Classic editor: still guide editors, but do not force draft — Publish must stick.
		if ( $thumb <= 0 ) {
			set_transient( 'gcm_course_thumb_required_' . get_current_user_id(), 1, 90 );
		}

		return $data;
	}

	/**
	 * After REST insert/update, remind if a published course has no thumbnail.
	 *
	 * @param WP_Post         $post     Post.
	 * @param WP_REST_Request $request  Request.
	 * @param bool            $creating Creating.
	 * @return void
	 */
	public function remind_thumbnail_after_rest( $post, $request, $creating ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		if ( ! $post instanceof WP_Post || 'gcm_course' !== $post->post_type || 'publish' !== $post->post_status ) {
			return;
		}

		if ( ! has_post_thumbnail( $post->ID ) ) {
			set_transient( 'gcm_course_thumb_required_' . get_current_user_id(), 1, 90 );
		}
	}

	/**
	 * Admin notices for course thumbnail requirements.
	 *
	 * @return void
	 */
	public function course_thumbnail_admin_notices() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'gcm_course' !== $screen->post_type ) {
			return;
		}

		$user_id = get_current_user_id();
		if ( get_transient( 'gcm_course_thumb_required_' . $user_id ) ) {
			delete_transient( 'gcm_course_thumb_required_' . $user_id );
			echo '<div class="notice notice-warning is-dismissible"><p>';
			echo esc_html__( 'Tip: add a Course Thumbnail (sidebar) so this course shows an image on the website course cards and course page.', 'giga-class-market' );
			echo '</p></div>';
		}

		if ( 'post' === $screen->base && isset( $_GET['post'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$post_id = absint( $_GET['post'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( $post_id && 'publish' === get_post_status( $post_id ) && ! has_post_thumbnail( $post_id ) ) {
				echo '<div class="notice notice-warning"><p>';
				echo esc_html__( 'Add a Course Thumbnail (sidebar) so this course displays an image on the website.', 'giga-class-market' );
				echo '</p></div>';
			}
		}
	}

	/**
	 * Add thumbnail column to course list.
	 *
	 * @param array $columns Columns.
	 * @return array
	 */
	public function course_list_columns( $columns ) {
		$new = array();
		foreach ( $columns as $key => $label ) {
			if ( 'title' === $key ) {
				$new['gcm_thumbnail'] = __( 'Thumbnail', 'giga-class-market' );
			}
			$new[ $key ] = $label;
		}
		return $new;
	}

	/**
	 * Render course list thumbnail column.
	 *
	 * @param string $column Column key.
	 * @param int    $post_id Post ID.
	 * @return void
	 */
	public function render_course_list_column( $column, $post_id ) {
		if ( 'gcm_thumbnail' !== $column ) {
			return;
		}

		if ( has_post_thumbnail( $post_id ) ) {
			echo get_the_post_thumbnail( $post_id, array( 56, 56 ), array( 'style' => 'width:56px;height:56px;object-fit:cover;border-radius:8px;' ) );
			return;
		}

		echo '<span style="display:inline-flex;width:56px;height:56px;align-items:center;justify-content:center;background:#e8eef0;border-radius:8px;color:#667;font-size:11px;">';
		echo esc_html__( 'None', 'giga-class-market' );
		echo '</span>';
	}
}
