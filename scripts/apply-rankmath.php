<?php
/**
 * Applique focus keywords, meta title et meta description Rank Math depuis un JSON.
 *
 * Usage :
 *   wp eval-file apply-rankmath.php metas.json --allow-root          → simulation + contrôles
 *   wp eval-file apply-rankmath.php metas.json apply --allow-root    → écriture (avec sauvegarde TSV)
 *
 * metas.json :
 * {
 *   "287": {"kw": "porte de garage,porte de garage sectionnelle,porte de garage sur mesure",
 *           "title": "Porte de garage à Valence et en Drôme-Ardèche | TSA",
 *           "desc": "Porte de garage ..."}
 * }
 * Clé absente ou null = valeur actuelle conservée. Principal en premier dans "kw".
 */
$file  = $args[0] ?? '';
$apply = in_array('apply', $args, true);
if (!$file || !is_readable($file)) { echo "Usage : wp eval-file apply-rankmath.php metas.json [apply]\n"; return; }
$rows = json_decode(file_get_contents($file), true);
if (!is_array($rows)) { echo "JSON invalide\n"; return; }

$bdir = getenv('SEO_BACKUP_DIR') ?: '/root/seo-backup';
$keys = ['kw' => 'rank_math_focus_keyword', 'title' => 'rank_math_title', 'desc' => 'rank_math_description'];
$backup = "id\t" . implode("\t", $keys) . "\n";
$errors = 0; $mains = [];

// Pré-passe anti-cannibalisation : mots-clés du fichier + ceux des autres pages du site
$kws_of = function ($s) { return array_filter(array_map('trim', explode(',', mb_strtolower((string) $s)))); };
foreach ($rows as $id => $r) if (!empty($r['kw'])) foreach ($kws_of($r['kw']) as $k) $mains[$k][(int) $id] = true;
foreach (get_posts(['post_type' => 'any', 'post_status' => 'publish', 'numberposts' => -1, 'fields' => 'ids', 'meta_key' => 'rank_math_focus_keyword']) as $oid) {
	$src = !empty($rows[$oid]['kw']) ? '' : get_post_meta($oid, 'rank_math_focus_keyword', true);
	foreach ($kws_of($src) as $k) $mains[$k][$oid] = true;
}

foreach ($rows as $id => $r) {
	$id = (int) $id;
	if (!get_post($id)) { echo "[$id] ✗ contenu introuvable\n"; $errors++; continue; }
	$cur = [];
	foreach ($keys as $k => $meta) $cur[$k] = (string) get_post_meta($id, $meta, true);
	$backup .= $id . "\t" . implode("\t", $cur) . "\n";
	$new = [];
	foreach ($keys as $k => $meta) $new[$k] = array_key_exists($k, $r) && $r[$k] !== null ? trim($r[$k]) : $cur[$k];

	$main = mb_strtolower(trim(explode(',', $new['kw'])[0]));
	$warn = [];
	if (mb_strlen($new['title']) > 60) $warn[] = 'title ' . mb_strlen($new['title']) . ' car. (> 60)';
	if (mb_strlen($new['desc']) > 160) $warn[] = 'desc ' . mb_strlen($new['desc']) . ' car. (> 160)';
	if ($main && mb_stripos($new['title'], $main) === false) $warn[] = 'KW absent du title';
	if ($main && mb_stripos($new['desc'], $main) === false) $warn[] = 'KW absent de la desc';
	foreach ($kws_of($new['kw']) as $k) if (count($mains[$k] ?? []) > 1) $warn[] = "cannibalisation : « $k » aussi sur " . implode(', ', array_diff(array_keys($mains[$k]), [$id]));
	if ($warn) $errors++;

	printf("[%d] %s\n  kw    : %s\n  title : %s (%d)\n  desc  : %s (%d)\n%s",
		$id, get_the_title($id), $new['kw'], $new['title'], mb_strlen($new['title']), $new['desc'], mb_strlen($new['desc']),
		$warn ? '  ⚠ ' . implode(' | ', $warn) . "\n" : '');

	if ($apply && !$warn) foreach ($keys as $k => $meta) if ($new[$k] !== $cur[$k]) update_post_meta($id, $meta, $new[$k]);
}

if ($apply) {
	if (!is_dir($bdir)) mkdir($bdir, 0700, true);
	$f = "$bdir/rankmath-avant-" . date('Ymd-His') . '.tsv';
	file_put_contents($f, $backup);
	echo "Écrit (pages sans ⚠). Sauvegarde : $f\nLes scores Rank Math se recalculent dans le navigateur (Outils de base de données > Recalculer les scores).\n";
} else {
	echo $errors ? "$errors page(s) avec ⚠ : elles ne seront pas écrites.\n" : '';
	echo "Simulation terminée : ajouter « apply » pour écrire.\n";
}
