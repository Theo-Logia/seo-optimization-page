<?php
/**
 * Applique title, alt et description des images (médiathèque) depuis un JSON.
 * La légende (post_excerpt) n'est jamais modifiée.
 *
 * Usage :
 *   wp eval-file apply-images.php images.json --allow-root          → simulation
 *   wp eval-file apply-images.php images.json apply --allow-root    → écriture (avec sauvegarde TSV)
 *
 * images.json :
 * {
 *   "106": {"title": "Portail et portillon automatiques devant une villa contemporaine",
 *           "alt": "Portail automatique : portail et portillon en aluminium anthracite ...",
 *           "desc": "Portail automatique en aluminium ..."},
 *   "556": {"alt": ""}          ← image provisoire ou décorative : alt vide
 * }
 * Clé absente ou null = valeur actuelle conservée.
 */
$file  = $args[0] ?? '';
$apply = in_array('apply', $args, true);
if (!$file || !is_readable($file)) { echo "Usage : wp eval-file apply-images.php images.json [apply]\n"; return; }
$rows = json_decode(file_get_contents($file), true);
if (!is_array($rows)) { echo "JSON invalide\n"; return; }

$bdir = getenv('SEO_BACKUP_DIR') ?: '/root/seo-backup';
$backup = "id\ttitle\talt\tdescription\n";
$n = 0;
foreach ($rows as $id => $r) {
	$id = (int) $id;
	if (get_post_type($id) !== 'attachment') { echo "[$id] ✗ pas une image de la médiathèque\n"; continue; }
	$cur = ['title' => get_the_title($id), 'alt' => (string) get_post_meta($id, '_wp_attachment_image_alt', true), 'desc' => get_post_field('post_content', $id)];
	$backup .= "$id\t" . implode("\t", array_map(function ($v) { return str_replace(["\t", "\n"], ' ', $v); }, $cur)) . "\n";
	$warn = isset($r['alt']) && mb_strlen($r['alt']) > 125 ? ' ⚠ alt de ' . mb_strlen($r['alt']) . ' car. (viser ≤ 125)' : '';
	echo "[$id] " . basename((string) get_attached_file($id)) . "$warn\n";
	foreach (['title', 'alt', 'desc'] as $k) if (array_key_exists($k, $r) && $r[$k] !== null && $r[$k] !== $cur[$k]) echo "  $k : « {$cur[$k]} » → « {$r[$k]} »\n";
	if (!$apply) continue;

	$upd = ['ID' => $id];
	if (isset($r['title'])) $upd['post_title'] = $r['title'];
	if (isset($r['desc'])) $upd['post_content'] = $r['desc'];
	if (count($upd) > 1) { $res = wp_update_post($upd, true); if (is_wp_error($res)) { echo '  ✗ ' . $res->get_error_message() . "\n"; continue; } }
	if (array_key_exists('alt', $r) && $r['alt'] !== null) update_post_meta($id, '_wp_attachment_image_alt', $r['alt']);
	$n++;
}
if ($apply) {
	if (!is_dir($bdir)) mkdir($bdir, 0700, true);
	$f = "$bdir/images-avant-" . date('Ymd-His') . '.tsv';
	file_put_contents($f, $backup);
	global $wpdb;
	$wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key = '_elementor_element_cache'");
	if (class_exists('\Elementor\Plugin')) \Elementor\Plugin::$instance->files_manager->clear_cache();
	wp_cache_flush();
	echo "$n image(s) mise(s) à jour. Sauvegarde : $f. Cache d'éléments Elementor purgé.\n";
} else {
	echo "Simulation terminée : ajouter « apply » pour écrire.\n";
}
