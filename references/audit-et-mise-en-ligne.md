# Audit final et checklist de mise en ligne

## Audit après optimisation

Lancer `audit-metas.php` puis `audit-rendu.py` et vérifier, page par page :

| Contrôle | Attendu |
|---|---|
| Focus keyword | Renseigné, principal en premier, unique sur le site |
| Meta title | ≤ 60 car., contient le mot-clé exact |
| Meta description | ≤ 160 car., contient le mot-clé exact |
| H1 | Un seul, contient le mot-clé (sauf pages de marque) |
| H2 | Au moins un H2 contient le mot-clé ou un secondaire |
| Densité | 1 à 2,5 % |
| Longueur | 300 mots minimum hors en-tête et pied de page ; signaler les pages trop courtes |
| Photos | Toutes avec un alt ; décoratifs en alt vide |
| Slug | Contient idéalement le mot-clé (ne changer qu'avant la mise en ligne) |
| Score Rank Math | Recalculé ; 70 et plus = bon |

Restituer : tableau récapitulatif, puis les points faibles classés par priorité.

## Checklist de mise en ligne

1. **Redirections 301** de chaque URL de l'ancien site vers la page équivalente du nouveau
   (priorité aux URLs qui rankent, repérées en phase 1 avec `ranked_keywords`). Point le plus
   important d'une refonte : sans redirections, le positionnement acquis est perdu.
   Outil : Rank Math > Redirections (import CSV possible).
2. **Indexation** : Réglages > Lecture > décocher « Demander aux moteurs de recherche de ne pas
   indexer ce site » (`blog_public = 1`).
3. **Noindex** sur les pages sans valeur SEO : mentions légales, politique de confidentialité,
   CGV, plan du site HTML, archives de catégories vides ou inutiles, pages de remerciement.
4. **Sitemap XML** Rank Math : vérifier qu'il ne contient que les pages indexables, puis le
   soumettre dans la Search Console.
5. **Extraits** des articles remplis (pour le pattern `%excerpt%`).
6. **Données structurées** : LocalBusiness (Rank Math > Titres & Métas > Local SEO) avec adresse,
   téléphone, horaires et zone ; cohérence avec la fiche Google Business Profile.
7. **Contrôle visuel** des H1 rallongés (ciblage local) sur ordinateur et mobile.
8. Après mise en ligne : Search Console (couverture, erreurs 404), suivi des positions des
   mots-clés principaux.
