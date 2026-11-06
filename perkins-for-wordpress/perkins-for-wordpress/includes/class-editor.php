<?php
defined( 'ABSPATH' ) || exit;

/**
 * Loads the block editor sidebar panel and pre-publish check.
 */
class ES_Editor {

	public static function init() {
		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'enqueue' ) );
	}

	public static function enqueue() {
		$types = (array) ES_Settings::get( 'post_types' );
		if ( ! $types || ! ES_Reviewer::active_criteria() ) {
			return;
		}

		wp_enqueue_script(
			'es-editor',
			ES_URL . 'assets/editor.js',
			array( 'wp-plugins', 'wp-editor', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-data', 'wp-api-fetch', 'wp-i18n', 'wp-blocks' ),
			ES_VERSION,
			true
		);
		wp_add_inline_script(
			'es-editor',
			'window.ESConfig = ' . wp_json_encode(
				array(
					'postTypes'    => array_values( $types ),
					'mode'         => ES_Settings::get( 'mode' ),
					'iconUrl'      => ES_URL . 'assets/icon.png',
					'allowRedraft' => (bool) ES_Settings::get( 'allow_redraft' ),
				)
			) . ';',
			'before'
		);
	}
}
