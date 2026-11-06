=== Perkins for WordPress ===
Requires at least: 6.0
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later

Perkins is an AI editor for WordPress. It scores articles against your editorial criteria before publication, then approves, warns or blocks, and can offer a redraft.

== Setup ==
1. Activate the plugin, then go to Settings > Perkins.
2. Add your API key (or define ES_API_KEY in wp-config.php).
3. Tick the criteria to rate, set a minimum score for each, choose content types, and pick block vs warn.

== Developer hooks ==
* editorial_standards_bypass (bool $bypass, int $post_id, int $user_id): return true to skip review, e.g. for admins.
* editorial_standards_gate_non_admin (bool): return true to also gate wp_insert_post() calls outside wp-admin (imports, WP-CLI).
