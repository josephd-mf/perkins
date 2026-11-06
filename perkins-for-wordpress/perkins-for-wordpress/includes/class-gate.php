<?php
defined( 'ABSPATH' ) || exit;

/**
 * Server-side enforcement. Runs when a post is about to be published.
 *
 * - Block editor / REST saves: returns a WP_Error (shown as an error in the editor).
 * - Classic editor, Quick Edit, bulk edit: downgrades the status to draft and shows an admin notice.
 */
class ES_Gate {

	const META = '_es_review';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_rest_filters' ), 20 );
		add_filter( 'wp_insert_post_data', array( __CLASS__, 'filter_insert' ), 10, 4 );
		add_action( 'admin_notices', array( __CLASS__, 'notices' ) );
	}

	public static function register_rest_filters() {
		foreach ( (array) ES_Settings::get( 'post_types' ) as $type ) {
			add_filter( "rest_pre_insert_{$type}", array( __CLASS__, 'rest_pre_insert' ), 10, 2 );
		}
	}

	/* ------------------------------------------------------------------ */

	private static function should_review( $post_type, $new_status, $old_status ) {
		if ( ! in_array( $post_type, (array) ES_Settings::get( 'post_types' ), true ) ) {
			return false;
		}
		if ( ! in_array( $new_status, array( 'publish', 'future' ), true ) ) {
			return false;
		}
		if ( in_array( $old_status, array( 'publish', 'future' ), true ) && ! ES_Settings::get( 'review_updates' ) ) {
			return false;
		}
		if ( ! ES_Reviewer::active_criteria() ) {
			return false;
		}
		return true;
	}

	/**
	 * Review with caching. Cached per post by a hash of title + content + settings.
	 */
	public static function evaluate( $post_id, $title, $content, $force = false ) {
		$hash = ES_Reviewer::content_hash( $title, $content );

		if ( $post_id && ! $force ) {
			$cached = get_post_meta( $post_id, self::META, true );
			if ( is_array( $cached ) && isset( $cached['hash'], $cached['result'] ) && $cached['hash'] === $hash ) {
				return $cached['result'];
			}
		}

		$result = ES_Reviewer::review( $title, $content );
		if ( ! is_wp_error( $result ) && $post_id ) {
			update_post_meta(
				$post_id,
				self::META,
				array(
					'hash'   => $hash,
					'result' => $result,
				)
			);
		}
		return $result;
	}

	public static function failure_message( $result ) {
		$bits = array();
		foreach ( $result['criteria'] as $r ) {
			if ( ! $r['passed'] ) {
				$bits[] = sprintf( '%s (%s/%d)', $r['label'], rtrim( rtrim( number_format( $r['score'], 1 ), '0' ), '.' ), $r['min'] );
			}
		}
		return sprintf(
			/* translators: %s: list of failed criteria */
			__( 'Publication blocked by editorial standards. Below the minimum score: %s. Open the Perkins panel for feedback.', 'perkins-for-wordpress' ),
			implode( ', ', $bits )
		);
	}

	/* ------------------------------------------------------------------ */

	/**
	 * REST (block editor) path.
	 *
	 * @param stdClass        $prepared Prepared post object.
	 * @param WP_REST_Request $request  Request.
	 */
	public static function rest_pre_insert( $prepared, $request ) {
		// In warn mode the editor panel informs the author; nothing is enforced server-side here.
		if ( 'block' !== ES_Settings::get( 'mode' ) ) {
			return $prepared;
		}

		$type    = substr( current_filter(), strlen( 'rest_pre_insert_' ) );
		$post_id = ! empty( $prepared->ID ) ? (int) $prepared->ID : 0;
		$old     = $post_id ? get_post_status( $post_id ) : 'new';
		$new     = isset( $prepared->post_status ) ? $prepared->post_status : $old;

		if ( ! self::should_review( $type, $new, $old ) ) {
			return $prepared;
		}
		if ( apply_filters( 'editorial_standards_bypass', false, $post_id, get_current_user_id() ) ) {
			return $prepared;
		}

		$title   = isset( $prepared->post_title ) ? $prepared->post_title : ( $post_id ? get_post_field( 'post_title', $post_id ) : '' );
		$content = isset( $prepared->post_content ) ? $prepared->post_content : ( $post_id ? get_post_field( 'post_content', $post_id ) : '' );

		$result = self::evaluate( $post_id, $title, $content );

		if ( is_wp_error( $result ) ) {
			if ( 'block' === ES_Settings::get( 'on_error' ) ) {
				return new WP_Error(
					'editorial_standards_unavailable',
					sprintf( __( 'Publication blocked: the editorial review could not be completed. %s', 'perkins-for-wordpress' ), $result->get_error_message() ),
					array( 'status' => 503 )
				);
			}
			return $prepared;
		}

		if ( ! $result['passed'] ) {
			return new WP_Error(
				'editorial_standards_failed',
				self::failure_message( $result ),
				array(
					'status'             => 422,
					'editorial_standards' => $result,
				)
			);
		}
		return $prepared;
	}

	/**
	 * Non-REST path: classic editor, Quick Edit, bulk edit (admin requests only).
	 */
	public static function filter_insert( $data, $postarr, $unsanitized = array(), $update = false ) {
		if ( ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) ) {
			return $data;
		}
		if ( ! is_admin() && ! apply_filters( 'editorial_standards_gate_non_admin', false ) ) {
			return $data;
		}
		if ( in_array( $data['post_type'], array( 'revision', 'auto-draft', 'nav_menu_item' ), true ) ) {
			return $data;
		}

		$post_id = isset( $postarr['ID'] ) ? (int) $postarr['ID'] : 0;
		$old     = $post_id ? get_post_status( $post_id ) : 'new';

		if ( ! self::should_review( $data['post_type'], $data['post_status'], $old ) ) {
			return $data;
		}
		if ( apply_filters( 'editorial_standards_bypass', false, $post_id, get_current_user_id() ) ) {
			return $data;
		}

		$title   = wp_unslash( $data['post_title'] );
		$content = wp_unslash( $data['post_content'] );
		$result  = self::evaluate( $post_id, $title, $content );
		$block   = 'block' === ES_Settings::get( 'mode' );

		if ( is_wp_error( $result ) ) {
			$fail_closed = $block && 'block' === ES_Settings::get( 'on_error' );
			if ( $fail_closed ) {
				$data['post_status'] = 'draft';
			}
			self::set_notice(
				array(
					'blocked' => $fail_closed,
					'title'   => $fail_closed
						? __( 'Perkins: publication blocked because the review could not be completed.', 'perkins-for-wordpress' )
						: __( 'Perkins: the review could not be completed, so the post was not checked.', 'perkins-for-wordpress' ),
					'message' => $result->get_error_message(),
				)
			);
			return $data;
		}

		if ( ! $result['passed'] ) {
			if ( $block ) {
				$data['post_status'] = 'draft';
			}
			self::set_notice(
				array(
					'blocked' => $block,
					'title'   => $block
						? __( 'Perkins: publication blocked. The post was saved as a draft.', 'perkins-for-wordpress' )
						: __( 'Perkins: this post did not meet the editorial standards but was published anyway.', 'perkins-for-wordpress' ),
					'result'  => $result,
				)
			);
		}
		return $data;
	}

	/* ------------------------------------------------------------------ */

	private static function set_notice( $notice ) {
		set_transient( 'es_notice_' . get_current_user_id(), $notice, 5 * MINUTE_IN_SECONDS );
	}

	public static function notices() {
		$key    = 'es_notice_' . get_current_user_id();
		$notice = get_transient( $key );
		if ( ! $notice ) {
			return;
		}
		delete_transient( $key );

		$class = ! empty( $notice['blocked'] ) ? 'notice-error' : 'notice-warning';
		echo '<div class="notice ' . esc_attr( $class ) . ' is-dismissible"><p><strong>' . esc_html( $notice['title'] ) . '</strong></p>';
		if ( ! empty( $notice['message'] ) ) {
			echo '<p>' . esc_html( $notice['message'] ) . '</p>';
		}
		if ( ! empty( $notice['result']['criteria'] ) ) {
			if ( ! empty( $notice['result']['summary'] ) ) {
				echo '<p>' . esc_html( $notice['result']['summary'] ) . '</p>';
			}
			echo '<ul style="list-style:disc;margin-left:20px">';
			foreach ( $notice['result']['criteria'] as $r ) {
				if ( $r['passed'] ) {
					continue;
				}
				echo '<li><strong>' . esc_html( $r['label'] ) . '</strong> ' . esc_html( sprintf( '%s/%d (min %d)', rtrim( rtrim( number_format( $r['score'], 1 ), '0' ), '.' ), 10, $r['min'] ) ) . ' &mdash; ' . esc_html( $r['feedback'] ) . '</li>';
			}
			echo '</ul>';
		}
		echo '</div>';
	}
}
