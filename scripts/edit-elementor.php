<?php
/**
 * Remplacements de texte dans les données Elementor, page par page, avec sauvegarde,
 * régénération de post_content et purge des caches.
 *
 * Usage :
 *   wp eval-file edit-elementor.php edits.json --allow-root          → simulation (rien n'est écrit)
 *   wp eval-file edit-elementor.php edits.json apply --allow-root    → écriture
 *
 * edits.json :
 * {
 *   "287": [
 *     {"el": "3f9a2c1", "path": "title", "new": "Porte de garage à Valence et en Drôme-Ardèche"},
 *     {"el": "8b77d02", "path": "editor", "old": "nos portes sur mesure", "new": "nos portes de garage sur mesure"},
 *     {"el": "c41e9aa", "path": "items.2.content", "old": "...", "new": "..."}
 *   ]
 * }
 * - "el"   : id de l'élément (donné par dump-page.php)
 * - "path" : chemin du champ dans settings (donné par dump-page.php)
 * - "old"  : sous-chaîne à remplacer (toutes ses occurrences dans le champ). Absent = remplace tout le champ.
 * - "nbsp" : false pour ne pas convertir les espaces avant : ? ! ; en insécables dans "new" (true par défaut)
 * Si un remplacement d'une page n'est pas trouvé, la page n'est PAS enregistrée.
 */
$file  = $args[0] ?? '';
$apply = in_array('apply', $args, true);
if (!$file || !is_readable($file)) { echo "Usage : wp eval-file edit-elementor.php edits.json [apply]\n"; return; }
$edits = json_decode(file_get_contents($file), true);
if (!is_array($edits)) { echo "JSON invalide : $file\n"; return; }

$bdir = getenv('SEO_BACKUP_DIR') ?: '/root/seo-backup';
if ($apply && !is_dir($bdir)) mkdir($bdir, 0700, true);
$stamp = date('Ymd-His');

function fr_nbsp($s) {
	$s = preg_replace('/ ([:?!;»])/u', "\u{00A0}$1", $s);
	return preg_replace('/« /u', "«\u{00A0}", $s);
}
function &settings_ref(array &$settings, string $path) {
	$ref = &$settings; $null = null;
	foreach (explode('.', $path) as $k) {
		if (!is_array($ref) || !array_key_exists($k, $ref)) return $null;
		$ref = &$ref[$k];
	}
	return $ref;
}
function &find_el(array &$els, string $id) {
	$null = null;
	foreach ($els as &$el) {
		if (($el['id'] ?? '') === $id) return $el;
		if (!empty($el['elements'])) { $r = &find_el($el['elements'], $id); if ($r !== null) return $r; }
	}
	return $null;
}
$short = function ($s, $focus = '') {
	$s = trim(preg_replace('/\s+/u', ' ', wp_strip_all_tags($s)));
	$f = $focus !== '' ? trim(preg_replace('/\s+/u', ' ', wp_strip_all_tags($focus))) : '';
	$pos = $f !== '' ? mb_strpos($s, mb_substr($f, 0, 40)) : false;
	$start = $pos === false ? 0 : max(0, $pos - 60);
	return ($start ? '…' : '') . mb_substr($s, $start, 200) . (mb_strlen($s) > $start + 200 ? '…' : '');
};

global $wpdb;
$changed = 0;
foreach ($edits as $pid => $list) {
	$pid = (int) $pid;
	$raw = get_post_meta($pid, '_elementor_data', true);
	if (!$raw) { echo "[$pid] ✗ pas de données Elementor\n"; continue; }
	$data = is_array($raw) ? $raw : json_decode($raw, true);
	$ok = true; $done = 0;
	echo "== [$pid] " . get_the_title($pid) . "\n";

	foreach ($list as $i => $e) {
		$el = &find_el($data, (string) $e['el']);
		if ($el === null) { echo "  ✗ #$i élément {$e['el']} introuvable\n"; $ok = false; unset($el); continue; }
		$val = &settings_ref($el['settings'], (string) $e['path']);
		if (!is_string($val)) { echo "  ✗ #$i champ {$e['el']}.{$e['path']} introuvable\n"; $ok = false; unset($el, $val); continue; }
		$new = ($e['nbsp'] ?? true) ? fr_nbsp($e['new']) : $e['new'];
		$before = $val; $hit = null;
		if (!isset($e['old'])) {
			$val = $new;
		} else {
			$hit = null;
			foreach (array_unique([$e['old'], fr_nbsp($e['old']), str_replace("\u{00A0}", ' ', $e['old'])]) as $variant) {
				if ($variant !== '' && mb_strpos($val, $variant) !== false) { $hit = $variant; break; }
			}
			if ($hit === null) { echo "  ✗ #$i texte non trouvé dans {$e['el']}.{$e['path']} : « " . $short($e['old']) . " »\n"; $ok = false; unset($el, $val); continue; }
			$val = str_replace($hit, $new, $val);
		}
		echo "  ✓ #$i {$e['el']}.{$e['path']}\n      avant : " . $short($before, $hit ?? '') . "\n      après : " . $short($val, $new) . "\n";
		$done++;
		unset($el, $val);
	}

	if (!$ok) { echo "  → page NON enregistrée (corriger les ✗ puis relancer)\n"; continue; }
	if (!$apply) { echo "  → simulation : $done modification(s) prête(s)\n"; continue; }

	file_put_contents("$bdir/elementor-$pid-$stamp.json", is_array($raw) ? wp_json_encode($raw) : $raw);
	update_post_meta($pid, '_elementor_data', wp_slash(wp_json_encode($data)));
	if (class_exists('\Elementor\Plugin')) \Elementor\Plugin::$instance->db->save_plain_text($pid);
	clean_post_cache($pid);
	echo "  → enregistré ($done modif.), sauvegarde : $bdir/elementor-$pid-$stamp.json\n";
	$changed++;
}

if ($apply && $changed) {
	$wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key = '_elementor_element_cache'");
	if (class_exists('\Elementor\Plugin')) \Elementor\Plugin::$instance->files_manager->clear_cache();
	wp_cache_flush();
	echo "Caches purgés. Penser au cache de page éventuel et au recalcul des scores Rank Math.\n";
}
if (!$apply) echo "Simulation terminée : ajouter « apply » pour écrire.\n";
