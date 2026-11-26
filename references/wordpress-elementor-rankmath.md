# Appliquer l'optimisation dans WordPress (Rank Math, Elementor, médiathèque)

À lire avant la première écriture en base. Tout ce qui suit vient de cas réels rencontrés en
production chez Logia.

## Prérequis

- WP-CLI disponible (`wp --info`). En root : ajouter `--allow-root` à chaque commande.
- Se placer dans le dossier WordPress ou passer `--path=/chemin/wordpress`.
- Les scripts PHP se lancent avec `wp eval-file scripts/xxx.php [arguments] --allow-root`.
  Ils sont **en simulation par défaut** : ajouter l'argument `apply` pour écrire.
  (Ne pas utiliser `--dry` / `--apply` : WP-CLI intercepte les options en `--`.)
- Créer un dossier de sauvegarde, ex : `/root/seo-backup/` (les scripts y écrivent par défaut,
  modifiable avec la variable d'environnement `SEO_BACKUP_DIR`).

## Rank Math

Métadonnées par page (post meta) :

| Meta | Contenu |
|---|---|
| `rank_math_focus_keyword` | Mots-clés séparés par des virgules, **principal en premier** |
| `rank_math_title` | Meta title (vide = pattern global) |
| `rank_math_description` | Meta description |
| `rank_math_seo_score` | Dernier score calculé (lecture seule pour nous) |

**Le score n'est calculé que dans le navigateur** (JavaScript de Rank Math). Écrire les metas en
base ne le met pas à jour. Deux façons de le recalculer :
1. Rank Math > Statut et outils > Outils de base de données > **Recalculer les scores** (inclure
   les contenus qui ont déjà un score). Cet outil analyse `post_content` : il faut donc que
   `post_content` soit à jour (voir plus bas).
2. Ouvrir la page dans un **nouvel** onglet Elementor et cliquer sur « Mettre à jour ». Dans
   l'éditeur, Rank Math lit les widgets de l'aperçu.

Rank Math compte les occurrences **exactes** du mot-clé (pas de pluriel, pas de variante).

## Elementor

### Où est le contenu

- Contenu dans la post meta `_elementor_data` (JSON). Chaque élément a un `id`, un `elType`, un
  `widgetType` et des `settings` :
  - `heading` → `settings.title` (+ `header_size` : h1, h2...) ;
  - `text-editor` → `settings.editor` (HTML) ;
  - widgets à items (accordéons, listes) → `settings.items[n].title` / `.content`, etc. ;
  - `image` → `settings.image` {id, url, alt}.
- Les widgets maison (ex : `logia-accordion`) ont leurs propres clés : regarder le dump.
- Les en-têtes, pieds de page et boucles (`loop-item`) sont dans `elementor_library` : ils se
  répètent sur toutes les pages et comptent dans le texte analysé.
- Shortcodes (ex : `[logia nom="..."]`) : ne jamais les casser lors d'un remplacement.

### Modifier sans casser

1. Sauvegarder `_elementor_data` (le script le fait dans `SEO_BACKUP_DIR`).
2. Décoder, modifier les chaînes ciblées par `id` d'élément, réencoder :
   `update_post_meta($id, '_elementor_data', wp_slash(wp_json_encode($data)));`
   (`wp_slash` est indispensable, sinon les antislashs du JSON sont perdus).
3. **Régénérer `post_content`** :
   `\Elementor\Plugin::$instance->db->save_plain_text($id);`
   Elementor ne le fait qu'à l'enregistrement depuis l'éditeur. Sans cette étape, `post_content`
   reste vide ou ancien, et l'outil « Recalculer les scores » de Rank Math calcule sur un texte
   faux (score bloqué ou en baisse).
4. **Purger les caches** :
   - cache d'éléments (actif 24 h par défaut, il sert l'ancien HTML, ancien alt compris) :
     `DELETE FROM {prefix}postmeta WHERE meta_key = '_elementor_element_cache'` ;
   - CSS et fichiers : `\Elementor\Plugin::$instance->files_manager->clear_cache();` ;
   - cache objet : `wp cache flush` ; cache de page éventuel (WP Rocket, LiteSpeed...).
5. Vérifier le HTML servi (curl ou `audit-rendu.py`).

`edit-elementor.php` fait les étapes 1 à 4 et refuse d'enregistrer une page si un des
remplacements demandés n'est pas trouvé.

### Risque d'écrasement

Un onglet Elementor **ouvert avant** une modification en base, puis enregistré, réécrit toute la
page avec l'ancienne version. Avant d'écrire : demander à l'utilisateur de fermer ses onglets
Elementor sans enregistrer. Si une page semble revenue en arrière : comparer `_elementor_data`
avec la sauvegarde (vérifier que l'utilisateur n'a pas fait ses propres changements entre-temps)
avant de réappliquer.

### Espaces insécables

Les textes français contiennent souvent U+00A0 avant « : », « ? », « ! », « ; ». Une recherche
avec une espace normale échoue. `edit-elementor.php` essaie automatiquement les deux variantes
pour la recherche et met des insécables dans le texte de remplacement.

## Images

- Elementor affiche l'alt de la **médiathèque** (`_wp_attachment_image_alt`), pas la copie
  stockée dans le widget. Modifier la médiathèque, puis purger le cache d'éléments.
- Title = `post_title` de l'attachment, description = `post_content`, légende = `post_excerpt`
  (ne pas y toucher : elle peut s'afficher).
- Les images en **arrière-plan CSS** (backgrounds de sections, cartes de boucles) n'ont pas d'alt
  lu par Google : le signaler si des photos importantes sont affichées ainsi.
- Pictos et motifs décoratifs : alt vide, c'est correct.
- Une image utilisée sur plusieurs pages n'a qu'un alt : choisir le mot-clé de la page la plus
  importante ou une formulation qui couvre les deux.

## Accès local à la préprod

Si le serveur ne peut pas joindre son propre domaine (NAT en épingle) :
`curl -sk --resolve domaine.tld:443:127.0.0.1 https://domaine.tld/page/`.
Une préprod avec `blog_public = 0` sert toutes ses pages en `noindex` : c'est normal avant la
mise en ligne.
