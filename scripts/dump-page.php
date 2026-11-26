<?php
/**
 * Dump du texte d'une page Elementor, avec l'id de chaque élément et le chemin du champ
 * (à réutiliser dans edit-elementor.php), puis nombre de mots et densité du mot-clé.
 *
 * Usage : wp eval-file dump-page.php <post_id> "<mot-clé>" --allow-root
 */
$pid = (int) ($args[0] ?? 0);
$kw  = mb_strtolower(trim($args[1] ?? ''));
if (!$pid) { echo "Usage : wp eval-file dump-page.php <post_id> \"<mot-clé>\"\n"; return; }

$raw = get_post_meta($pid, '_elementor_data', true);
if (!$raw) { echo "Pas de données Elementor sur $pid : contenu classique ci-dessous.\n"; echo wp_strip_all_tags(get_post_field('post_content', $pid)), "\n"; return; }
$data = is_array($raw) ? $raw : json_decode($raw, true);

// Clés de settings qui contiennent du texte visible
$TEXT_KEYS = ['title', 'editor', 'text', 'description', 'content', 'title_text', 'description_text',
	'caption', 'tab_title', 'tab_content', 'item_title', 'item_description', 'testimonial_content',
	'testimonial_name', 'heading', 'subtitle', 'sub_heading', 'before_text', 'highlighted_text',
	'after_text', 'rotating_text', 'button_text', 'alert_title', 'alert_description', 'prefix', 'suffix'];
$all = [];

$clean = function ($s) { return trim(preg_replace('/\s+/u', ' ', html_entity_decode(wp_strip_all_tags($s), ENT_QUOTES))); };

$scan = function ($settings, $prefix, &$out) use (&$scan, $TEXT_KEYS) {
	foreach ($settings as $k => $v) {
		if (is_array($v)) {
			if (in_array($k, ['__dynamic__', '__globals__', 'typography', 'image', 'background_image', 'link', 'selected_icon'], true)) continue;
			$scan($v, $prefix . $k . '.', $out);
		} elseif (is_string($v) && (in_array($k, $TEXT_KEYS, true)) && trim($v) !== '') {
			$out[$prefix . $k] = $v;
		}
	}
};

$walk = function ($els) use (&$walk, $scan, $clean, &$all) {
	foreach ($els as $el) {
		if (($el['elType'] ?? '') === 'widget') {
			$type = $el['widgetType'] ?? '?';
			$s = $el['settings'] ?? [];
			$found = [];
			$scan($s, '', $found);
			foreach ($found as $path => $val) {
				$tag = $type === 'heading' && $path === 'title' ? strtoupper($s['header_size'] ?? 'h2') : $type;
				printf("[%s] %-14s %-22s| %s\n", $el['id'], mb_substr($tag, 0, 14), $path, mb_substr($clean($val), 0, 500));
				$all[] = $val;
			}
		}
		if (!empty($el['elements'])) $walk($el['elements']);
	}
};
$walk($data);

$txt = mb_strtolower(str_replace("\u{00A0}", ' ', $clean(implode(' ', $all))));
$txt = preg_replace('/\[[a-z_-]+[^\]]*\]/u', 'x', $txt); // shortcodes comptés comme 1 mot
preg_match_all('/[\p{L}\p{N}’\'-]+/u', $txt, $m);
$wc = count($m[0]);
$n  = $kw === '' ? 0 : preg_match_all('/(?<![\p{L}\p{N}])' . preg_quote($kw, '/') . '(?![\p{L}\p{N}])/u', $txt);
printf("== %d mots (hors en-tête et pied de page) | « %s » x%d | densité %.2f %% (cible 1 à 2,5 %%)\n", $wc, $kw, $n, $wc ? $n * 100 / $wc : 0);
