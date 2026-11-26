<?php
/**
 * Inventaire des images réellement utilisées : pages Elementor, templates Elementor
 * (en-tête, pied de page, boucles) et images mises en avant des contenus publics.
 * Sortie TSV : id, fichier, alt actuel, title actuel, utilisations.
 *
 * Usage :
 *   wp eval-file images-inventaire.php --allow-root          → photos uniquement (SVG exclus)
 *   wp eval-file images-inventaire.php svg --allow-root      → SVG inclus
 *   wp eval-file images-inventaire.php ids --allow-root      → seulement la liste des ids (pour contact-sheets.php)
 */
$with_svg = in_array('svg', $args, true);
$ids_only = in_array('ids', $args, true);
$map = [];

$collect = function ($node, $label) use (&$collect, &$map) {
	if (!is_array($node)) return;
	if (isset($node['id'], $node['url']) && is_numeric($node['id']) && strpos((string) $node['url'], '/uploads/') !== false) {
		$map[(int) $node['id']][$label] = true;
	}
	foreach ($node as $v) if (is_array($v)) $collect($v, $label);
};

$sources = get_posts(['post_type' => ['page', 'elementor_library'], 'post_status' => 'publish', 'numberposts' => -1]);
foreach ($sources as $p) {
	$raw = get_post_meta($p->ID, '_elementor_data', true);
	if (!$raw) continue;
	$data = is_array($raw) ? $raw : json_decode($raw, true);
	$label = $p->post_type === 'page' ? $p->post_name : 'tpl:' . $p->post_title;
	$collect($data, $label);
}
foreach (get_post_types(['public' => true]) as $pt) {
	if ($pt === 'attachment') continue;
	foreach (get_posts(['post_type' => $pt, 'post_status' => 'publish', 'numberposts' => -1, 'fields' => 'ids']) as $pid) {
		$t = get_post_thumbnail_id($pid);
		if ($t) $map[(int) $t]["une:$pt:$pid"] = true;
	}
}
ksort($map);

$out_ids = [];
if (!$ids_only) echo "id\tfichier\talt\ttitle\tutilisations\n";
foreach ($map as $id => $uses) {
	if (get_post_type($id) !== 'attachment') continue;
	$f = basename((string) get_attached_file($id));
	if (!$with_svg && preg_match('/\.svg$/i', $f)) continue;
	$out_ids[] = $id;
	if ($ids_only) continue;
	echo implode("\t", [$id, $f, get_post_meta($id, '_wp_attachment_image_alt', true), get_the_title($id), implode(', ', array_keys($uses))]), "\n";
}
if ($ids_only) echo implode(',', $out_ids), "\n";
