<?php
/**
 * Course SEO helpers — titles, descriptions, keywords, FAQ seeds.
 *
 * @package GigaClassMarket
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds and seeds search-optimized metadata for each course.
 */
class GCM_Course_SEO {

	/**
	 * Fill empty SEO fields for every published course.
	 *
	 * @return int Number of courses updated.
	 */
	public static function ensure_all_published() {
		$ids = get_posts(
			array(
				'post_type'              => 'gcm_course',
				'post_status'            => 'publish',
				'posts_per_page'         => 200,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => true,
				'update_post_term_cache' => true,
			)
		);

		$updated = 0;
		foreach ( $ids as $course_id ) {
			if ( self::ensure_course( (int) $course_id ) ) {
				++$updated;
			}
		}

		return $updated;
	}

	/**
	 * Force-apply hand-tuned SEO presets (title, description, keywords) for known courses.
	 * Used on plugin upgrade so new ranking packs land even when older meta already exists.
	 * Does not overwrite a non-empty FAQ (on-page answers stay intact).
	 *
	 * @return int Number of courses updated.
	 */
	public static function apply_known_presets() {
		$ids = get_posts(
			array(
				'post_type'              => 'gcm_course',
				'post_status'            => 'publish',
				'posts_per_page'         => 200,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => true,
				'update_post_term_cache' => true,
			)
		);

		$updated = 0;
		foreach ( $ids as $course_id ) {
			$course_id = (int) $course_id;
			$preset    = self::preset_for_course( $course_id );
			if ( empty( $preset ) ) {
				continue;
			}

			$changed = false;
			if ( ! empty( $preset['title'] ) ) {
				update_post_meta( $course_id, '_gcm_seo_title', $preset['title'] );
				$changed = true;
			}
			if ( ! empty( $preset['description'] ) ) {
				update_post_meta( $course_id, '_gcm_seo_description', self::clip( $preset['description'], 155 ) );
				$changed = true;
			}
			if ( ! empty( $preset['keyword'] ) ) {
				update_post_meta( $course_id, '_gcm_seo_focus_keyword', $preset['keyword'] );
				$changed = true;
			}
			if ( ! empty( $preset['keywords'] ) && is_array( $preset['keywords'] ) ) {
				update_post_meta( $course_id, '_gcm_seo_keywords', implode( ', ', $preset['keywords'] ) );
				$changed = true;
			}
			$faq = (string) get_post_meta( $course_id, '_gcm_faq', true );
			if ( '' === trim( $faq ) && ! empty( $preset['faq'] ) ) {
				update_post_meta( $course_id, '_gcm_faq', $preset['faq'] );
				$changed = true;
			}

			if ( $changed ) {
				++$updated;
			}
		}

		return $updated;
	}

	/**
	 * Ensure a single course has SEO title, description, focus keyword, and FAQ.
	 *
	 * @param int $course_id Course ID.
	 * @return bool True when any meta was written.
	 */
	public static function ensure_course( $course_id ) {
		$course_id = absint( $course_id );
		if ( ! $course_id || 'gcm_course' !== get_post_type( $course_id ) ) {
			return false;
		}

		$changed = false;

		$title = (string) get_post_meta( $course_id, '_gcm_seo_title', true );
		if ( '' === trim( $title ) ) {
			update_post_meta( $course_id, '_gcm_seo_title', self::build_seo_title( $course_id ) );
			$changed = true;
		}

		$desc = (string) get_post_meta( $course_id, '_gcm_seo_description', true );
		if ( '' === trim( $desc ) ) {
			update_post_meta( $course_id, '_gcm_seo_description', self::build_seo_description( $course_id ) );
			$changed = true;
		}

		$keyword = (string) get_post_meta( $course_id, '_gcm_seo_focus_keyword', true );
		if ( '' === trim( $keyword ) ) {
			update_post_meta( $course_id, '_gcm_seo_focus_keyword', self::build_focus_keyword( $course_id ) );
			$changed = true;
		}

		$keywords = (string) get_post_meta( $course_id, '_gcm_seo_keywords', true );
		if ( '' === trim( $keywords ) ) {
			$preset = self::preset_for_course( $course_id );
			if ( ! empty( $preset['keywords'] ) && is_array( $preset['keywords'] ) ) {
				update_post_meta( $course_id, '_gcm_seo_keywords', implode( ', ', $preset['keywords'] ) );
				$changed = true;
			}
		}

		$faq = (string) get_post_meta( $course_id, '_gcm_faq', true );
		if ( '' === trim( $faq ) ) {
			update_post_meta( $course_id, '_gcm_faq', self::build_default_faq( $course_id ) );
			$changed = true;
		}

		return $changed;
	}

	/**
	 * Resolve the best SEO document title for a course.
	 *
	 * @param int $course_id Course ID.
	 * @return string
	 */
	public static function resolve_seo_title( $course_id ) {
		$custom = trim( (string) get_post_meta( $course_id, '_gcm_seo_title', true ) );
		return $custom ? $custom : self::build_seo_title( $course_id );
	}

	/**
	 * Resolve the best meta description for a course.
	 *
	 * @param int $course_id Course ID.
	 * @return string
	 */
	public static function resolve_seo_description( $course_id ) {
		$custom = trim( (string) get_post_meta( $course_id, '_gcm_seo_description', true ) );
		return $custom ? $custom : self::build_seo_description( $course_id );
	}

	/**
	 * Resolve focus keyword.
	 *
	 * @param int $course_id Course ID.
	 * @return string
	 */
	public static function resolve_focus_keyword( $course_id ) {
		$custom = trim( (string) get_post_meta( $course_id, '_gcm_seo_focus_keyword', true ) );
		return $custom ? $custom : self::build_focus_keyword( $course_id );
	}

	/**
	 * Comma-separated keyword list for meta keywords + schema.
	 *
	 * @param int $course_id Course ID.
	 * @return string
	 */
	public static function resolve_keywords_csv( $course_id ) {
		$stored = trim( (string) get_post_meta( $course_id, '_gcm_seo_keywords', true ) );
		if ( $stored ) {
			return $stored;
		}

		$preset = self::preset_for_course( $course_id );
		if ( ! empty( $preset['keywords'] ) && is_array( $preset['keywords'] ) ) {
			return implode( ', ', $preset['keywords'] );
		}

		return self::resolve_focus_keyword( $course_id );
	}

	/**
	 * Extra Course schema fields from a preset (audience, about, areaServed, etc.).
	 *
	 * @param int $course_id Course ID.
	 * @return array
	 */
	public static function schema_extras( $course_id ) {
		$preset = self::preset_for_course( $course_id );
		return isset( $preset['schema'] ) && is_array( $preset['schema'] ) ? $preset['schema'] : array();
	}

	/**
	 * Keyword-rich title for SERPs.
	 *
	 * @param int $course_id Course ID.
	 * @return string
	 */
	public static function build_seo_title( $course_id ) {
		$post_title = get_the_title( $course_id );
		$brand      = 'Giga Class Market';
		$preset     = self::preset_for_course( $course_id );

		if ( ! empty( $preset['title'] ) ) {
			return $preset['title'];
		}

		// Keep under ~60 chars when possible while staying searchable.
		$base = sprintf(
			/* translators: 1: course title, 2: brand */
			__( '%1$s | Online Course | %2$s', 'giga-class-market' ),
			$post_title,
			$brand
		);

		return self::clip( $base, 65 );
	}

	/**
	 * Keyword-rich meta description.
	 *
	 * @param int $course_id Course ID.
	 * @return string
	 */
	public static function build_seo_description( $course_id ) {
		$preset = self::preset_for_course( $course_id );
		if ( ! empty( $preset['description'] ) ) {
			return self::clip( $preset['description'], 155 );
		}

		$course = class_exists( 'GCM_Course_Service' ) ? GCM_Course_Service::get( $course_id ) : null;
		$title  = get_the_title( $course_id );
		$excerpt = '';
		if ( $course ) {
			$excerpt = $course['excerpt'] ? wp_strip_all_tags( $course['excerpt'] ) : wp_trim_words( wp_strip_all_tags( $course['content'] ), 22 );
		}
		if ( ! $excerpt ) {
			$excerpt = sprintf(
				/* translators: %s: course title */
				__( 'Structured online training with practical lessons and verified certificates.', 'giga-class-market' ),
				$title
			);
		}

		$text = sprintf(
			/* translators: 1: course title, 2: short summary */
			__( 'Enroll in %1$s online at Giga Class Market. %2$s Learn with expert instructors and get a verified certificate.', 'giga-class-market' ),
			$title,
			$excerpt
		);

		return self::clip( $text, 155 );
	}

	/**
	 * Primary search phrase for the course.
	 *
	 * @param int $course_id Course ID.
	 * @return string
	 */
	public static function build_focus_keyword( $course_id ) {
		$preset = self::preset_for_course( $course_id );
		if ( ! empty( $preset['keyword'] ) ) {
			return $preset['keyword'];
		}

		$title = strtolower( wp_strip_all_tags( get_the_title( $course_id ) ) );
		$title = preg_replace( '/[^a-z0-9\s\-]/', '', $title );
		$title = trim( preg_replace( '/\s+/', ' ', (string) $title ) );

		if ( '' === $title ) {
			return 'online course';
		}

		if ( false === strpos( $title, 'course' ) ) {
			$title .= ' course';
		}
		if ( false === strpos( $title, 'online' ) ) {
			$title .= ' online';
		}

		return self::clip( $title, 60 );
	}

	/**
	 * Default FAQ lines for schema + on-page accordion.
	 *
	 * @param int $course_id Course ID.
	 * @return string
	 */
	public static function build_default_faq( $course_id ) {
		$preset = self::preset_for_course( $course_id );
		if ( ! empty( $preset['faq'] ) ) {
			return $preset['faq'];
		}

		$title   = get_the_title( $course_id );
		$course  = class_exists( 'GCM_Course_Service' ) ? GCM_Course_Service::get( $course_id ) : null;
		$about   = $course && $course['excerpt'] ? wp_strip_all_tags( $course['excerpt'] ) : sprintf( __( '%s is a practical online course at Giga Class Market.', 'giga-class-market' ), $title );
		$lines   = array(
			sprintf( 'What will I learn in %s? | %s', $title, $about ),
			sprintf( 'Is %s suitable for beginners? | Yes. The course is structured from foundations to advanced practical skills so beginners and upskillers can follow along.', $title ),
			sprintf( 'How do I enroll in %s? | Open the course page, click Buy Now, complete payment, and start learning after verification.', $title ),
			'Do I get a certificate? | Yes. Giga Class Market issues a verified certificate after you complete the course requirements.',
			'Is this an online course? | Yes. Lessons, live classes (when scheduled), and progress tracking are available online through your student dashboard.',
		);

		return implode( "\n", $lines );
	}

	/**
	 * Hand-tuned SEO packs for known flagship courses.
	 *
	 * @param int $course_id Course ID.
	 * @return array
	 */
	private static function preset_for_course( $course_id ) {
		$slug  = (string) get_post_field( 'post_name', $course_id );
		$title = strtolower( (string) get_the_title( $course_id ) );
		$packs = self::presets();

		if ( isset( $packs[ $slug ] ) ) {
			return $packs[ $slug ];
		}

		foreach ( $packs as $pack ) {
			foreach ( (array) ( $pack['match'] ?? array() ) as $needle ) {
				if ( $needle && false !== strpos( $title, $needle ) ) {
					return $pack;
				}
			}
		}

		return array();
	}

	/**
	 * Preset map keyed by slug.
	 *
	 * @return array
	 */
	private static function presets() {
		return array(
			'fpsc-success-mastery'                    => array(
				'match'       => array( 'fpsc' ),
				'keyword'     => 'fpsc preparation course pakistan',
				'keywords'    => array(
					'fpsc preparation course pakistan',
					'fpsc course online',
					'fpsc exam preparation',
					'fpsc coaching online pakistan',
					'fpsc mcqs course',
					'fpsc past papers',
					'federal public service commission preparation',
					'fpsc preparation online',
					'fpsc success mastery',
					'adeel ahmad fpsc',
				),
				'title'       => 'FPSC Preparation Course Pakistan | Online Success Mastery | Giga Class Market',
				'description' => 'Best FPSC preparation course online in Pakistan. Learn MCQs, past papers, English, Pakistan Affairs & current affairs with Adeel Ahmad (10+ years). Enroll at Giga Class Market.',
				'faq'         => "What is the FPSC Success Mastery course? | A complete online FPSC preparation course in Pakistan covering exam strategy, MCQs, past papers, English, Pakistan Affairs, GK, and current affairs.\nWho should take this FPSC course? | Pakistani students and candidates preparing for FPSC exams and competitive government jobs.\nIs this FPSC preparation available online? | Yes. Study fully online through Giga Class Market with structured lessons and progress tracking.\nWho teaches the FPSC course? | Adeel Ahmad — 10+ years of FPSC-related teaching experience, currently TGT Biology at WAPDA.\nDo I get a certificate after FPSC Success Mastery? | Yes. You receive a verified Giga Class Market certificate after completing the course.\nHow do I enroll in the FPSC course in Pakistan? | Open the course page, click Buy Now, complete payment in PKR, and start after verification.",
				'schema'      => array(
					'educationalLevel'             => 'Professional development',
					'competencyRequired'           => 'No prior FPSC preparation required',
					'about'                        => array(
						'FPSC',
						'Federal Public Service Commission',
						'Pakistan competitive exams',
						'MCQ preparation',
						'Pakistan Affairs',
					),
					'audience'                     => array(
						'@type'  => 'EducationalAudience',
						'educationalRole' => 'student',
						'name'   => 'FPSC aspirants in Pakistan',
					),
					'availableLanguage'            => array( 'en', 'ur' ),
					'countryOfOrigin'              => array(
						'@type' => 'Country',
						'name'  => 'Pakistan',
					),
				),
			),
			'ccna-level-from-beginner-to-professional' => array(
				'match'       => array( 'ccna' ),
				'keyword'     => 'ccna course online',
				'keywords'    => array( 'ccna course online', 'ccna training', 'cisco ccna course', 'networking course online' ),
				'title'       => 'CCNA Course Online | Beginner to Professional | Giga Class Market',
				'description' => 'Join the best CCNA course online at Giga Class Market. Learn networking from beginner to professional with practical labs, expert instructors, and a verified certificate.',
				'faq'         => "What is the CCNA course about? | This CCNA course online takes you from networking basics to professional Cisco CCNA skills with practical labs.\nWho should take this CCNA course? | Beginners, IT students, and professionals who want a career-ready CCNA certification path.\nIs this CCNA training fully online? | Yes. Study online through Giga Class Market with structured lessons and progress tracking.\nDo I get a certificate after the CCNA course? | Yes. You receive a verified Giga Class Market certificate after completing the course.\nHow do I enroll in the CCNA course? | Click Buy Now on the course page, complete payment, and start after verification.",
			),
			'ethical-hacking-entry-level-to-pro'       => array(
				'match'       => array( 'ethical hacking', 'hacking' ),
				'keyword'     => 'ethical hacking course online',
				'keywords'    => array( 'ethical hacking course online', 'cybersecurity course', 'ethical hacking training' ),
				'title'       => 'Ethical Hacking Course Online | Zero to Mastery | Giga Class Market',
				'description' => 'Learn ethical hacking online from zero to mastery at Giga Class Market. Practical cybersecurity training, real-world labs, expert guidance, and a verified certificate.',
				'faq'         => "What will I learn in this ethical hacking course? | You learn ethical hacking fundamentals through practical labs, from entry level to advanced defensive and offensive skills.\nIs this ethical hacking course for beginners? | Yes. It starts from zero and builds step-by-step toward professional cybersecurity skills.\nIs the ethical hacking course online? | Yes. Training is delivered online via Giga Class Market with structured lessons.\nDo I get a certificate? | Yes. A verified certificate is issued after you complete the course requirements.\nHow do I join the ethical hacking course? | Click Buy Now, complete payment verification, then access lessons in your student dashboard.",
			),
			'ai-codecraft-vibe-coding'                 => array(
				'match'       => array( 'codecraft', 'vibe coding', 'ai code' ),
				'keyword'     => 'ai coding course online',
				'keywords'    => array( 'ai coding course online', 'vibe coding course', 'ai codecraft', 'ai web development course' ),
				'title'       => 'AI Coding Course Online | CodeCraft Vibe Coding | Giga Class Market',
				'description' => 'Master AI coding and vibe coding online with CodeCraft at Giga Class Market. Build real websites and apps faster using AI tools, modern workflows, and verified certification.',
				'faq'         => "What is the AI CodeCraft vibe coding course? | An online AI coding course that teaches you to build real-world websites and apps faster with AI-powered development tools.\nWho is this AI coding course for? | Beginners and developers who want practical AI-assisted coding skills for modern projects.\nIs vibe coding taught online? | Yes. The full CodeCraft vibe coding experience is available online at Giga Class Market.\nDo I receive a certificate? | Yes. Completing the course earns a verified Giga Class Market certificate.\nHow do I enroll? | Buy the course, complete payment verification, and start learning from your dashboard.",
			),
		);
	}

	/**
	 * Clip text to a max length without breaking mid-word when possible.
	 *
	 * @param string $text Text.
	 * @param int    $max Max length.
	 * @return string
	 */
	private static function clip( $text, $max ) {
		$text = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( (string) $text ) ) );
		if ( strlen( $text ) <= $max ) {
			return $text;
		}
		$cut = substr( $text, 0, $max - 1 );
		$sp  = strrpos( $cut, ' ' );
		if ( false !== $sp && $sp > (int) ( $max * 0.6 ) ) {
			$cut = substr( $cut, 0, $sp );
		}
		return rtrim( $cut, '.,;:|- ' ) . '…';
	}
}
