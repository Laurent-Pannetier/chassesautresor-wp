📘 Notice technique — Recalcul des statuts de chasse

🔍 Objectif

Garantir que le statut logique d'une chasse (
chasse_cache_statut) reste toujours cohérent avec les règles métiers, même si aucun utilisateur ou admin ne déclenche de mise à jour explicite (AJAX, ACF, CRON).

✅ Fonction principale

mettre_a_jour_statuts_chasse($chasse_id);

Recalcule le champ ACF chasse_cache_statut

Détermine la valeur correcte selon :

Date de début (chasse_infos_date_debut)

Date de fin (chasse_infos_date_fin)

Date de découverte (chasse_cache_date_decouverte)

Statut de validation (chasse_cache_statut_validation)

Coût en points

Déclenche les planifications PDF si la chasse devient "termine"

🧠 Appel automatique à l'affichage

Pour garantir un recalcul même sans activité admin :

verifier_ou_recalculer_statut_chasse($chasse_id);

Cette fonction :

Est appelée dans single-chasse.php

Vérifie si le statut actuel est incohérent ou périmé

Si besoin, appelle mettre_a_jour_statuts_chasse()

📄 Emplacement du code

statut-functions.php

mettre_a_jour_statuts_chasse()

verifier_ou_recalculer_statut_chasse()

Appelé depuis :

single-chasse.php

acf/save_post

AJAX forcer_recalcul_statut_chasse

Tâche cron externe (optionnel)

💡 Notes

L'appel à verifier_ou_recalculer_statut_chasse() est ultra léger (lecture ACF + condition)

Aucun recalcul n’est fait si le statut est déjà à jour

Le déclenchement par cron est recommandé en complément, pas en remplacement

🧪 Vérification manuelle (admin)

Utiliser l’endpoint AJAX :

wp.ajax.post('forcer_recalcul_statut_chasse', { post_id: 123 });

🔒 Sécurité

Toutes les fonctions sont protégées par get_post_type() et validation de l’ID

Aucun log actif en production

