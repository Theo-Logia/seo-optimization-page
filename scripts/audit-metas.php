<?php
/**
 * Inventaire / audit SEO des pages : slug, focus keyword, title, description, longueurs,
 * mots et densité (sur post_content), score Rank Math.
 *
 * Usage :
 *   wp eval-file audit-metas.php --allow-root            → tableau texte
 *   wp eval-file audit-metas.php tsv --allow-root        → TSV (à sauvegarder avant modifs)
 *   wp eval-file audit-metas.php map --allow-root        → JSON {"/slug/": "mot-clé"} pour audit-rendu.py
 *   wp eval-file audit-metas.php page,post --allow-root  → autres post types (séparés par des virgules)
 */
$mode = 'table'; $types = ['page'];
foreach ($args as $a) {
	if (in_array($a, ['tsv', 'map'], true)) $mode = $a;
	elseif (preg_match('/^[a-z0-9_,-]+$/', $a)) $types = explode(',', $a);
}
$posts = get_posts(['post_type' => $types, 'post_status' => 'publish', 'numberposts' => -1, 'orderby' => 'menu_order title', 'order' => 'ASC']);
$front = (int) get_option('page_on_front');

function seo_words($t) { preg_match_all('/[\p{L}\p{N}’\'-]+/u', $t, $m); return count($m[0]); }
function seo_count($t, $kw) {
	if ($kw === '') return 0;
	return preg_match_all('/(?<![\p{L}\p{N}])' . preg_quote($kw, '/') . '(?![\p{L}\p{N}])/u', $t);
}

$map = [];
if ($mode === 'tsv') echo "id\tslug\tfocus_keyword\ttitle\tdescription\tscore\n";
foreach ($posts as $p) {
	$kw    = (string) get_post_meta($p->ID, 'rank_math_focus_keyword', true);
	$main  = mb_strtolower(trim(explode(',', $kw)[0]));
	$t     = (string) get_post_meta($p->ID, 'rank_math_title', true);
	$d     = (string) get_post_meta($p->ID, 'rank_math_description', true);
	$score = (string) get_post_meta($p->ID, 'rank_math_seo_score', true);
	$txt   = mb_strtolower(str_replace("\u{00A0}", ' ', html_entity_decode(wp_strip_all_tags($p->post_content), ENT_QUOTES)));
	$wc    = seo_words($txt);
	$n     = seo_count($txt, $main);
	$path  = $p->ID === $front ? '/' : wp_make_link_relative(get_permalink($p));

	if ($mode === 'map') { if ($main !== '') $map[$path] = $main; continue; }
	if ($mode === 'tsv') { echo implode("\t", [$p->ID, $p->post_name, $kw, $t, $d, $score]), "\n"; continue; }

	$warn = [];
	if ($main === '') $warn[] = 'PAS DE FOCUS KW';
	if ($t === '') $warn[] = 'title vide (pattern)';
	elseif (mb_strlen($t) > 60) $warn[] = 'title > 60';
	if ($d === '') $warn[] = 'desc vide';
	elseif (mb_strlen($d) > 160) $warn[] = 'desc > 160';
	if ($main !== '' && $t !== '' && mb_stripos($t, $main) === false) $warn[] = 'KW absent du title';
	if ($main !== '' && $d !== '' && mb_stripos($d, $main) === false) $warn[] = 'KW absent de la desc';
	if ($wc === 0) $warn[] = 'post_content VIDE (lancer save_plain_text)';
	elseif ($wc < 300) $warn[] = "contenu court ($wc mots)";
	$dens = $wc ? $n * 100 / $wc : 0;
	if ($main !== '' && $wc && ($dens < 1 || $dens > 2.5)) $warn[] = sprintf('densité %.2f %%', $dens);

	printf("%-5d %-38s kw=%-32s title=%2d desc=%3d mots=%4d occ=%2d dens=%5.2f%% score=%-3s %s\n",
		$p->ID, mb_substr($path, 0, 38), $main ?: '-', mb_strlen($t), mb_strlen($d), $wc, $n, $dens, $score ?: '-',
		$warn ? '⚠ ' . implode(' | ', $warn) : 'OK');
}
if ($mode === 'map') echo wp_json_encode($map, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), "\n";
if ($mode === 'table') {
	// Doublons de focus keywords principaux (cannibalisation)
	$seen = [];
	foreach ($posts as $p) { $m = mb_strtolower(trim(explode(',', (string) get_post_meta($p->ID, 'rank_math_focus_keyword', true))[0])); if ($m !== '') $seen[$m][] = $p->ID; }
	foreach ($seen as $k => $ids) if (count($ids) > 1) echo "⚠ Cannibalisation : « $k » est le principal de " . implode(', ', $ids) . "\n";
	echo 'blog_public = ' . get_option('blog_public') . (get_option('blog_public') ? '' : ' (site en noindex)') . "\n";
}
