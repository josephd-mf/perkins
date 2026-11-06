<?php
defined( 'ABSPATH' ) || exit;

/**
 * Settings storage, sanitising and the admin settings page.
 */
class ES_Settings {

	const OPTION = 'editorial_standards_options';
	const GROUP  = 'editorial_standards';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( ES_FILE ), array( __CLASS__, 'action_links' ) );
	}

	public static function defaults() {
		return array(
			'provider'         => 'anthropic',
			'api_key'          => '',
			'model'            => 'claude-sonnet-5-5',
			'base_url'         => '',
			'post_types'       => array( 'post' ),
			'mode'             => 'block',
			'review_updates'   => false,
			'on_error'         => 'allow',
			'allow_redraft'    => false,
			'redraft_attempts' => 2,
			'max_chars'        => 30000,
			'criteria'         => array(
				array(
					'id'          => 'accuracy-sourcing',
					'label'       => __( 'Accuracy & sourcing', 'perkins-for-wordpress' ),
					'description' => __( 'Claims are specific, plausible and attributed to credible sources where needed. No unsupported or sensational assertions.', 'perkins-for-wordpress' ),
					'enabled'     => true,
					'min_score'   => 7,
				),
				array(
					'id'          => 'clarity',
					'label'       => __( 'Clarity & structure', 'perkins-for-wordpress' ),
					'description' => __( 'Clear headline, logical flow, short focused paragraphs, no padding or rambling.', 'perkins-for-wordpress' ),
					'enabled'     => true,
					'min_score'   => 7,
				),
				array(
					'id'          => 'tone',
					'label'       => __( 'Tone & style', 'perkins-for-wordpress' ),
					'description' => __( 'Consistent, professional, respectful tone suited to a general audience. No insults, hype or clickbait.', 'perkins-for-wordpress' ),
					'enabled'     => true,
					'min_score'   => 6,
				),
				array(
					'id'          => 'grammar',
					'label'       => __( 'Grammar & spelling', 'perkins-for-wordpress' ),
					'description' => __( 'Correct grammar, spelling and punctuation.', 'perkins-for-wordpress' ),
					'enabled'     => true,
					'min_score'   => 8,
				),
				array(
					'id'          => 'originality',
					'label'       => __( 'Originality & value', 'perkins-for-wordpress' ),
					'description' => __( 'Offers a distinct angle or genuinely useful information rather than generic filler.', 'perkins-for-wordpress' ),
					'enabled'     => false,
					'min_score'   => 6,
				),
			),
		);
	}

	/**
	 * Get all options (merged with defaults) or a single key.
	 */
	public static function get( $key = null ) {
		$o = get_option( self::OPTION );
		$o = wp_parse_args( is_array( $o ) ? $o : array(), self::defaults() );
		if ( null === $key ) {
			return $o;
		}
		return isset( $o[ $key ] ) ? $o[ $key ] : null;
	}

	public static function api_key() {
		if ( defined( 'ES_API_KEY' ) && ES_API_KEY ) {
			return ES_API_KEY;
		}
		return (string) self::get( 'api_key' );
	}

	/* ------------------------------------------------------------------ */

	public static function menu() {
		add_options_page(
			__( 'Perkins', 'perkins-for-wordpress' ),
			__( 'Perkins', 'perkins-for-wordpress' ),
			'manage_options',
			'perkins',
			array( __CLASS__, 'render_page' )
		);
	}

	public static function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'options-general.php?page=perkins' ) ) . '">' . esc_html__( 'Settings', 'perkins-for-wordpress' ) . '</a>' );
		return $links;
	}

	public static function register() {
		register_setting(
			self::GROUP,
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => self::defaults(),
			)
		);
	}

	public static function assets( $hook ) {
		if ( 'settings_page_perkins' !== $hook ) {
			return;
		}
		wp_enqueue_style( 'es-admin', ES_URL . 'assets/admin.css', array(), ES_VERSION );
		wp_enqueue_script( 'es-admin', ES_URL . 'assets/admin.js', array(), ES_VERSION, true );
	}

	public static function sanitize( $in ) {
		$in  = is_array( $in ) ? $in : array();
		$old = get_option( self::OPTION );
		$old = is_array( $old ) ? $old : array();
		$out = array();

		$out['provider'] = ( isset( $in['provider'] ) && 'openai' === $in['provider'] ) ? 'openai' : 'anthropic';

		if ( ! empty( $in['clear_api_key'] ) ) {
			$out['api_key'] = '';
		} elseif ( ! empty( $in['api_key'] ) ) {
			$out['api_key'] = sanitize_text_field( $in['api_key'] );
		} else {
			$out['api_key'] = isset( $old['api_key'] ) ? $old['api_key'] : '';
		}

		$default_model = ( 'openai' === $out['provider'] ) ? 'gpt-4o-mini' : 'claude-sonnet-5-5';
		$model         = isset( $in['model'] ) ? sanitize_text_field( $in['model'] ) : '';
		$out['model']  = '' !== $model ? $model : $default_model;

		$base            = isset( $in['base_url'] ) ? esc_url_raw( trim( $in['base_url'] ) ) : '';
		$out['base_url'] = untrailingslashit( $base );

		$public = array_keys( self::selectable_post_types() );
		$types  = isset( $in['post_types'] ) ? array_map( 'sanitize_key', (array) $in['post_types'] ) : array();
		$out['post_types'] = array_values( array_intersect( $types, $public ) );

		$out['mode']             = ( isset( $in['mode'] ) && 'warn' === $in['mode'] ) ? 'warn' : 'block';
		$out['review_updates']   = ! empty( $in['review_updates'] );
		$out['on_error']         = ( isset( $in['on_error'] ) && 'block' === $in['on_error'] ) ? 'block' : 'allow';
		$out['allow_redraft']    = ! empty( $in['allow_redraft'] );
		$out['redraft_attempts'] = max( 1, min( 3, isset( $in['redraft_attempts'] ) ? (int) $in['redraft_attempts'] : 2 ) );
		$out['max_chars']        = max( 1000, min( 100000, isset( $in['max_chars'] ) ? (int) $in['max_chars'] : 30000 ) );

		$criteria = array();
		$seen     = array();
		foreach ( isset( $in['criteria'] ) ? (array) $in['criteria'] : array() as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$label = isset( $row['label'] ) ? sanitize_text_field( $row['label'] ) : '';
			if ( '' === $label ) {
				continue;
			}
			$id = isset( $row['id'] ) ? sanitize_key( $row['id'] ) : '';
			if ( '' === $id ) {
				$id = sanitize_title( $label );
			}
			if ( '' === $id ) {
				$id = 'criterion';
			}
			$base_id = $id;
			$n       = 2;
			while ( isset( $seen[ $id ] ) ) {
				$id = $base_id . '-' . $n++;
			}
			$seen[ $id ] = true;

			$criteria[] = array(
				'id'          => $id,
				'label'       => $label,
				'description' => isset( $row['description'] ) ? sanitize_textarea_field( $row['description'] ) : '',
				'enabled'     => ! empty( $row['enabled'] ),
				'min_score'   => max( 0, min( 10, isset( $row['min_score'] ) ? (int) $row['min_score'] : 7 ) ),
			);
		}
		$out['criteria'] = $criteria;

		return $out;
	}

	public static function selectable_post_types() {
		$types = get_post_types( array( 'public' => true ), 'objects' );
		unset( $types['attachment'] );
		return $types;
	}

	/* ------------------------------------------------------------------ */

	private static function field_name( $key ) {
		return self::OPTION . '[' . $key . ']';
	}

	public static function render_criterion_row( $index, $c ) {
		$base = self::OPTION . '[criteria][' . $index . ']';
		?>
		<tr class="es-row">
			<td class="es-col-enabled">
				<input type="hidden" name="<?php echo esc_attr( $base ); ?>[id]" value="<?php echo esc_attr( $c['id'] ); ?>">
				<input type="checkbox" name="<?php echo esc_attr( $base ); ?>[enabled]" value="1" <?php checked( ! empty( $c['enabled'] ) ); ?> title="<?php esc_attr_e( 'Rate articles against this criterion', 'perkins-for-wordpress' ); ?>">
			</td>
			<td class="es-col-label">
				<input type="text" class="regular-text" name="<?php echo esc_attr( $base ); ?>[label]" value="<?php echo esc_attr( $c['label'] ); ?>" placeholder="<?php esc_attr_e( 'Criterion name', 'perkins-for-wordpress' ); ?>">
			</td>
			<td class="es-col-desc">
				<textarea rows="2" name="<?php echo esc_attr( $base ); ?>[description]" placeholder="<?php esc_attr_e( 'What does a high score look like? The AI uses this to judge.', 'perkins-for-wordpress' ); ?>"><?php echo esc_textarea( $c['description'] ); ?></textarea>
			</td>
			<td class="es-col-min">
				<input type="number" min="0" max="10" step="1" name="<?php echo esc_attr( $base ); ?>[min_score]" value="<?php echo esc_attr( (int) $c['min_score'] ); ?>">
			</td>
			<td class="es-col-remove"><button type="button" class="button-link-delete es-remove"><?php esc_html_e( 'Remove', 'perkins-for-wordpress' ); ?></button></td>
		</tr>
		<?php
	}

	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$o     = self::get();
		$types = self::selectable_post_types();
		$const = defined( 'ES_API_KEY' ) && ES_API_KEY;
		?>
		<div class="wrap es-settings">
			<div class="es-brand">
				<img src="<?php echo esc_url( ES_URL . 'assets/logo.png' ); ?>" alt="<?php esc_attr_e( 'Perkins for WordPress', 'perkins-for-wordpress' ); ?>" width="300" height="100">
			</div>
			<h1 class="screen-reader-text"><?php esc_html_e( 'Perkins for WordPress', 'perkins-for-wordpress' ); ?></h1>
			<p><?php esc_html_e( 'Perkins scores every article against the criteria below, like an editor with a style guide. Each enabled criterion must meet its minimum score (0–10) for the article to pass.', 'perkins-for-wordpress' ); ?></p>

			<?php if ( ! self::api_key() ) : ?>
				<div class="notice notice-warning inline"><p><?php esc_html_e( 'No API key is configured yet, so reviews cannot run. Add one under “AI provider” below.', 'perkins-for-wordpress' ); ?></p></div>
			<?php endif; ?>

			<form method="post" action="options.php">
				<?php settings_fields( self::GROUP ); ?>

				<h2><?php esc_html_e( 'Editorial criteria', 'perkins-for-wordpress' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Tick a criterion to include it in reviews. Set the minimum score each article must reach on it.', 'perkins-for-wordpress' ); ?></p>
				<table class="widefat striped es-criteria">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Rate', 'perkins-for-wordpress' ); ?></th>
							<th><?php esc_html_e( 'Criterion', 'perkins-for-wordpress' ); ?></th>
							<th><?php esc_html_e( 'Guidance for the reviewer', 'perkins-for-wordpress' ); ?></th>
							<th><?php esc_html_e( 'Min score', 'perkins-for-wordpress' ); ?></th>
							<th></th>
						</tr>
					</thead>
					<tbody id="es-criteria-body">
						<?php foreach ( array_values( $o['criteria'] ) as $i => $c ) : ?>
							<?php self::render_criterion_row( $i, $c ); ?>
						<?php endforeach; ?>
					</tbody>
				</table>
				<p><button type="button" class="button" id="es-add-criterion"><?php esc_html_e( 'Add criterion', 'perkins-for-wordpress' ); ?></button></p>
				<template id="es-row-template">
					<?php
					self::render_criterion_row(
						'__INDEX__',
						array(
							'id'          => '',
							'label'       => '',
							'description' => '',
							'enabled'     => true,
							'min_score'   => 7,
						)
					);
					?>
				</template>

				<h2><?php esc_html_e( 'Enforcement', 'perkins-for-wordpress' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Content types to review', 'perkins-for-wordpress' ); ?></th>
						<td>
							<?php foreach ( $types as $slug => $obj ) : ?>
								<label style="display:block;margin-bottom:4px">
									<input type="checkbox" name="<?php echo esc_attr( self::field_name( 'post_types' ) ); ?>[]" value="<?php echo esc_attr( $slug ); ?>" <?php checked( in_array( $slug, (array) $o['post_types'], true ) ); ?>>
									<?php echo esc_html( $obj->labels->name ); ?> <code><?php echo esc_html( $slug ); ?></code>
								</label>
							<?php endforeach; ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'When an article fails', 'perkins-for-wordpress' ); ?></th>
						<td>
							<label style="display:block;margin-bottom:4px"><input type="radio" name="<?php echo esc_attr( self::field_name( 'mode' ) ); ?>" value="block" <?php checked( $o['mode'], 'block' ); ?>> <?php esc_html_e( 'Block publication (the post stays a draft until it passes)', 'perkins-for-wordpress' ); ?></label>
							<label><input type="radio" name="<?php echo esc_attr( self::field_name( 'mode' ) ); ?>" value="warn" <?php checked( $o['mode'], 'warn' ); ?>> <?php esc_html_e( 'Warn only (the user is told, but can still publish)', 'perkins-for-wordpress' ); ?></label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Updates to live posts', 'perkins-for-wordpress' ); ?></th>
						<td>
							<label><input type="checkbox" name="<?php echo esc_attr( self::field_name( 'review_updates' ) ); ?>" value="1" <?php checked( ! empty( $o['review_updates'] ) ); ?>> <?php esc_html_e( 'Also review edits to posts that are already published or scheduled', 'perkins-for-wordpress' ); ?></label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'If the AI service is unavailable', 'perkins-for-wordpress' ); ?></th>
						<td>
							<label style="display:block;margin-bottom:4px"><input type="radio" name="<?php echo esc_attr( self::field_name( 'on_error' ) ); ?>" value="allow" <?php checked( $o['on_error'], 'allow' ); ?>> <?php esc_html_e( 'Allow publication (fail open) and show a notice', 'perkins-for-wordpress' ); ?></label>
							<label><input type="radio" name="<?php echo esc_attr( self::field_name( 'on_error' ) ); ?>" value="block" <?php checked( $o['on_error'], 'block' ); ?>> <?php esc_html_e( 'Block publication (fail closed). Only applies when blocking is on.', 'perkins-for-wordpress' ); ?></label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'AI redrafts', 'perkins-for-wordpress' ); ?></th>
						<td>
							<label><input type="checkbox" name="<?php echo esc_attr( self::field_name( 'allow_redraft' ) ); ?>" value="1" <?php checked( ! empty( $o['allow_redraft'] ) ); ?>> <?php esc_html_e( 'Allow the AI to produce a redraft of a failing article that meets the standards', 'perkins-for-wordpress' ); ?></label>
							<p class="description"><?php esc_html_e( 'The redraft is re-scored before it is offered, and is never applied automatically; the author chooses whether to apply it. Available in the block editor.', 'perkins-for-wordpress' ); ?></p>
							<p>
								<label><?php esc_html_e( 'Max redraft attempts:', 'perkins-for-wordpress' ); ?>
									<input type="number" min="1" max="3" step="1" style="width:60px" name="<?php echo esc_attr( self::field_name( 'redraft_attempts' ) ); ?>" value="<?php echo esc_attr( (int) $o['redraft_attempts'] ); ?>">
								</label>
							</p>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'AI provider', 'perkins-for-wordpress' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="es-provider"><?php esc_html_e( 'Provider', 'perkins-for-wordpress' ); ?></label></th>
						<td>
							<select id="es-provider" name="<?php echo esc_attr( self::field_name( 'provider' ) ); ?>">
								<option value="anthropic" <?php selected( $o['provider'], 'anthropic' ); ?>><?php esc_html_e( 'Anthropic (Claude)', 'perkins-for-wordpress' ); ?></option>
								<option value="openai" <?php selected( $o['provider'], 'openai' ); ?>><?php esc_html_e( 'OpenAI-compatible (chat completions)', 'perkins-for-wordpress' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="es-key"><?php esc_html_e( 'API key', 'perkins-for-wordpress' ); ?></label></th>
						<td>
							<?php if ( $const ) : ?>
								<p><em><?php esc_html_e( 'Set via the ES_API_KEY constant in wp-config.php.', 'perkins-for-wordpress' ); ?></em></p>
							<?php else : ?>
								<input type="password" id="es-key" class="regular-text" autocomplete="new-password" name="<?php echo esc_attr( self::field_name( 'api_key' ) ); ?>" value="" placeholder="<?php echo $o['api_key'] ? esc_attr__( '•••••••• saved (leave blank to keep)', 'perkins-for-wordpress' ) : ''; ?>">
								<?php if ( $o['api_key'] ) : ?>
									<label style="margin-left:8px"><input type="checkbox" name="<?php echo esc_attr( self::field_name( 'clear_api_key' ) ); ?>" value="1"> <?php esc_html_e( 'Remove saved key', 'perkins-for-wordpress' ); ?></label>
								<?php endif; ?>
								<p class="description"><?php esc_html_e( 'Tip: define ES_API_KEY in wp-config.php to keep the key out of the database.', 'perkins-for-wordpress' ); ?></p>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="es-model"><?php esc_html_e( 'Model', 'perkins-for-wordpress' ); ?></label></th>
						<td><input type="text" id="es-model" class="regular-text" name="<?php echo esc_attr( self::field_name( 'model' ) ); ?>" value="<?php echo esc_attr( $o['model'] ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="es-base"><?php esc_html_e( 'Base URL (optional)', 'perkins-for-wordpress' ); ?></label></th>
						<td>
							<input type="url" id="es-base" class="regular-text" name="<?php echo esc_attr( self::field_name( 'base_url' ) ); ?>" value="<?php echo esc_attr( $o['base_url'] ); ?>" placeholder="https://api.anthropic.com">
							<p class="description"><?php esc_html_e( 'Override for proxies or self-hosted OpenAI-compatible servers (e.g. https://host/v1). Leave blank for the provider default.', 'perkins-for-wordpress' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="es-max"><?php esc_html_e( 'Max characters sent', 'perkins-for-wordpress' ); ?></label></th>
						<td>
							<input type="number" id="es-max" min="1000" max="100000" step="1000" name="<?php echo esc_attr( self::field_name( 'max_chars' ) ); ?>" value="<?php echo esc_attr( (int) $o['max_chars'] ); ?>">
							<p class="description"><?php esc_html_e( 'Longer articles are truncated for review. Redrafting is skipped for articles above this length.', 'perkins-for-wordpress' ); ?></p>
						</td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>
			<p class="es-footer"><?php echo esc_html( sprintf( 'Perkins for WordPress v%s', ES_VERSION ) ); ?></p>
		</div>
		<?php
	}
}
