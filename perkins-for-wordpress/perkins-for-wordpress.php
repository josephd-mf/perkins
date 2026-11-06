<?php
/**
 * Plugin Name: Perkins for WordPress
 * Description: Perkins is an AI editor for WordPress. It reviews content against your editorial criteria before publication, then approves, warns or blocks based on per-criterion scores, and can offer a redraft that meets your standards.
 * Version:     1.0.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * License:     GPL-2.0-or-later
 * Text Domain: perkins-for-wordpress
 */

defined( 'ABSPATH' ) || exit;

define( 'ES_VERSION', '1.0.0' );
define( 'ES_FILE', __FILE__ );
define( 'ES_DIR', plugin_dir_path( __FILE__ ) );
define( 'ES_URL', plugin_dir_url( __FILE__ ) );

require_once ES_DIR . 'includes/class-settings.php';
require_once ES_DIR . 'includes/class-reviewer.php';
require_once ES_DIR . 'includes/class-gate.php';
require_once ES_DIR . 'includes/class-rest.php';
require_once ES_DIR . 'includes/class-editor.php';

ES_Settings::init();
ES_Gate::init();
ES_Rest::init();
ES_Editor::init();
