<?php
/**
 * Planches contact (12 vignettes numérotées par planche) pour regarder les photos avant
 * de rédiger les alt. Nécessite l'extension GD.
 *
 * Usage : wp eval-file contact-sheets.php <dossier_sortie> <ids séparés par des virgules> --allow-root
 *   ex : wp eval-file contact-sheets.php /tmp/seo/sheets "$(wp eval-file images-inventaire.php ids --allow-root)" --allow-root
 * Puis lire chaque planche JPG avec l'outil de lecture d'images.
 */
$dir = rtrim($args[0] ?? '', '/');
$ids = array_filter(array_map('intval', explode(',', $args[1] ?? '')));
if (!$dir || !$ids) { echo "Usage : wp eval-file contact-sheets.php <dossier> <id,id,...>\n"; return; }
if (!function_exists('imagecreatetruecolor')) { echo "Extension GD absente\n"; return; }
if (!is_dir($dir)) mkdir($dir, 0755, true);

$cols = 4; $rows = 3; $tw = 330; $th = 250; $head = 28;
foreach (array_chunk($ids, $cols * $rows) as $n => $chunk) {
	$im = imagecreatetruecolor($cols * $tw, $rows * ($th + $head));
	$white = imagecolorallocate($im, 255, 255, 255); $black = imagecolorallocate($im, 0, 0, 0); $red = imagecolorallocate($im, 200, 0, 0);
	imagefill($im, 0, 0, $white);
	foreach ($chunk as $i => $id) {
		$f = get_attached_file($id);
		$x = ($i % $cols) * $tw; $y = intdiv($i, $cols) * ($th + $head);
		$src = ($f && is_readable($f)) ? @imagecreatefromstring(file_get_contents($f)) : false;
		if ($src) {
			$w = imagesx($src); $h = imagesy($src); $r = min(($tw - 6) / $w, ($th - 6) / $h);
			$nw = (int) ($w * $r); $nh = (int) ($h * $r);
			imagecopyresampled($im, $src, $x + 3 + (int) (($tw - 6 - $nw) / 2), $y + $head + 3, 0, 0, $nw, $nh, $w, $h);
			imagedestroy($src);
		}
		imagestring($im, 5, $x + 4, $y + 6, "#$id " . substr(basename((string) $f), 0, 30), $src ? $black : $red);
	}
	$out = sprintf('%s/planche-%02d.jpg', $dir, $n + 1);
	imagejpeg($im, $out, 80); imagedestroy($im);
	echo "$out : " . implode(',', $chunk) . "\n";
}
