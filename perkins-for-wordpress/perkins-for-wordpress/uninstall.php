<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}
delete_option( 'editorial_standards_options' );
delete_post_meta_by_key( '_es_review' );
