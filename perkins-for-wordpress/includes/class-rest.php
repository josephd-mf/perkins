<?php
defined( 'ABSPATH' ) || exit;

/**
 * REST endpoint used by the block editor panel to review and redraft.
 */
class ES_Rest {

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	public static function routes() {
		register_rest_route(
			'perkins/v1',
			'/review',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle' ),
				'permission_callback' => array( __CLASS__, 'can_use' ),
				'args'                => array(
					'post_id' => array( 'type' => 'integer', 'default' => 0 ),
					'title'   => array( 'type' => 'string', 'default' => '' ),
					'content' => array( 'type' => 'string', 'default' => '' ),
					'force'   => array( 'type' => 'boolean', 'default' => false ),
					'redraft' => array( 'type' => 'boolean', 'default' => false ),
				),
			)
		);
	}

	public static function can_use( $request ) {
		$id = (int) $request->get_param( 'post_id' );
		return $id ? current_user_can( 'edit_post', $id ) : current_user_can( 'edit_posts' );
	}

	public static function handle( $request ) {
		$post_id = (int) $request->get_param( 'post_id' );
		$title   = (string) $request->get_param( 'title' );
		$content = (string) $request->get_param( 'content' );

		$review = ES_Gate::evaluate( $post_id, $title, $content, (bool) $request->get_param( 'force' ) );
		if ( is_wp_error( $review ) ) {
			return new WP_Error( $review->get_error_code(), $review->get_error_message(), array( 'status' => 502 ) );
		}

		$out = array(
			'review'        => $review,
			'redraft'       => null,
			'redraftAllowed' => (bool) ES_Settings::get( 'allow_redraft' ),
		);

		if ( ! $review['passed'] && $out['redraftAllowed'] && $request->get_param( 'redraft' ) ) {
			$draft = ES_Reviewer::redraft_until_pass( $title, $content, $review );
			if ( is_wp_error( $draft ) ) {
				$out['redraftError'] = $draft->get_error_message();
			} else {
				$out['redraft'] = $draft;
				if ( $post_id ) {
					update_post_meta(
						$post_id,
						ES_Gate::META,
						array(
							'hash'   => ES_Reviewer::content_hash( $draft['title'], $draft['content'] ),
							'result' => $draft['review'],
						)
					);
				}
			}
		}
		return rest_ensure_response( $out );
	}
}
