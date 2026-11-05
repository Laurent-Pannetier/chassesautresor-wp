🔹 Groupe : paramètre de la chasse
🆔 ID : 27
🔑 Key : group_67b58c51b9a49
📦 Champs trouvés : 23

— chasse_principale_image —
Type : image
Label : image chasse
Instructions : (vide)
Requis : non
----------------------------------------
— chasse_principale_description —
Type : wysiwyg
Label : Description de la chasse
Instructions : (vide)
Requis : oui
----------------------------------------
— chasse_infos_recompense_titre —
Type : text
Label : Titre de la récompense
Instructions : (vide)
Requis : non
----------------------------------------
— chasse_infos_recompense_valeur —
Type : number
Label : valeur en €
Instructions : (vide)
Requis : non
----------------------------------------
— chasse_infos_cout_points —
Type : number
Label : coût en points
Instructions : (vide)
Requis : non
----------------------------------------
— chasse_infos_recompense_texte —
Type : wysiwyg
Label : Description de la récompense
Instructions : (vide)
Requis : non
----------------------------------------
— chasse_infos_date_debut —
Type : date_time_picker
Label : Date de début
Instructions : (vide)
Requis : non
----------------------------------------
— chasse_infos_date_fin —
Type : date_picker
Label : date de fin
Instructions : (vide)
Requis : non
----------------------------------------
— chasse_infos_duree_illimitee —
Type : true_false
Label : Durée illlimitée
Instructions : (vide)
Requis : non
----------------------------------------
— chasse_principale_liens —
Type : repeater
Label : liens publics de la chasse
Instructions : (vide)
Requis : non
Contenu imbriqué :
  — chasse_principale_liens_type —
  Type : select
  Label : Type de lien
  Instructions : (vide)
  Requis : non
  Choices :
    - site_web : Site Web
    - discord : Discord
    - facebook : Facebook
    - twitter : Twitter/X
    - instagram : Instagram
  ----------------------------------------
  — chasse_principale_liens_url —
  Type : url
  Label : url lien
  Instructions : (vide)
  Requis : non
  ----------------------------------------
----------------------------------------
— chasse_mode_fin —
Type : radio
Label : chasse mode fin
Instructions : (vide)
Requis : non
Choices :
  - manuelle : Manuelle
  - automatique : Automatique
----------------------------------------
— chasse_infos_nb_max_gagants —
Type : number
Label : Nombre maximum de gagants
Instructions : (vide)
Requis : non
----------------------------------------
— chasse_cache_gagnants —
Type : text
Label : Gagnants
Instructions : (vide)
Requis : non
----------------------------------------
— chasse_cache_date_decouverte —
Type : date_time_picker
Label : Date de découverte
Instructions : Permet de terminer manuellement la chasse.
Requis : non
----------------------------------------
— chasse_cache_enigmes —
Type : relationship
Label : Énigmes associées
Instructions : Sélectionnez les énigmes associées à cette chasse
Requis : non
----------------------------------------
— chasse_cache_organisateur —
Type : relationship
Label : organisateur chasse
Instructions : (vide)
Requis : non
----------------------------------------
— chasse_cache_statut_validation —
Type : select
Label : statut_validation
Instructions : (vide)
Requis : non
Choices :
  - creation : Création
  - en_attente : En attente
  - valide : Valide
  - correction : Correction
  - banni : Banni
----------------------------------------
— chasse_cache_statut —
Type : select
Label : Statut de la chasse
Instructions : (vide)
Requis : non
Choices :
  - revision : en cours de révision
  - a_venir : à venir
  - payante : payante
  - termine : terminée
  - en_cours : en cours
----------------------------------------
— chasse_cache_complet —
Type : true_false
Label : chasse_cache_complet
Instructions : (vide)
Requis : non
----------------------------------------
— chasse_cache_has_solutions —
Type : true_false
Label : chasse_cache_has_solutions
Instructions : (vide)
Requis : non
----------------------------------------
— chasse_cache_has_indices —
Type : true_false
Label : chasse_cache_has_indices
Instructions : (vide)
Requis : non
----------------------------------------

— chasse_region —
Type : taxonomy
Label : chasse_region
Instructions : (vide)
Requis : non
Taxonomie : chasse_region
Mode de sélection : select (simple)
Retour : objet (WP_Term)
Load Terms : oui
Save Terms : oui
----------------------------------------

— chasse_theme —
Type : taxonomy
Label : chasse_theme
Instructions : (vide)
Requis : non
Taxonomie : theme_chasse
Mode de sélection : select (multiple)
Retour : objet (WP_Term)
Load Terms : oui
Save Terms : oui
----------------------------------------

🔹 Groupe : Paramètres de l’énigme
🆔 ID : 9
🔑 Key : group_67b58134d7647
📦 Champs trouvés : 32

— enigme_visuel_image —
Type : gallery
Label : image principale
Instructions : (vide)
Requis : non
----------------------------------------
— enigme_visuel_texte —
Type : wysiwyg
Label : texte enigme
Instructions : (vide)
Requis : non
----------------------------------------
— enigme_mode_validation —
Type : radio
Label : enigme_mode_validation
Instructions : (vide)
Requis : non
Choices :
  - aucune : Aucune validation
  - manuelle : Validation manuelle
  - automatique : Validation automatique
----------------------------------------
— enigme_visuel_legende —
Type : text
Label : sous titre
Instructions : Texte court facultatif affiché sous l’image principale.
Requis : non
----------------------------------------
— enigme_style_affichage —
Type : select
Label : enigme_style_affichage
Instructions : (vide)
Requis : non
Choices :
  - defaut : Défaut
  - pirate : Pirate
  - vintage : Vintage
----------------------------------------
— enigme_tentative_cout_points —
Type : number
Label : coût en points d'une tentative
Instructions : coût en points de l'énigme
Requis : non
----------------------------------------
— enigme_tentative_max —
Type : number
Label : Nb max de tentatives quotidiennes
Instructions : Nb max de tentatives quotidiennes
Requis : non
----------------------------------------
— enigme_reponse_bonne —
Type : textarea
Label : bonne(s) réponse(s)
Instructions : (vide)
Requis : non
----------------------------------------
— enigme_reponse_casse —
Type : true_false
Label : Respecter la casse
Instructions : (vide)
Requis : non
----------------------------------------
— texte_1 —
Type : text
Label : texte_1
Instructions : (vide)
Requis : non
----------------------------------------
— message_1 —
Type : text
Label : message 1
Instructions : (vide)
Requis : non
----------------------------------------
— respecter_casse_1 —
Type : true_false
Label : respecter casse 1
Instructions : (vide)
Requis : non
----------------------------------------
— texte_2 —
Type : text
Label : texte 2
Instructions : (vide)
Requis : non
----------------------------------------
— message_2 —
Type : text
Label : message 2
Instructions : (vide)
Requis : non
----------------------------------------
— respecter_casse_2 —
Type : true_false
Label : respecter casse 2
Instructions : (vide)
Requis : non
----------------------------------------
— texte_3 —
Type : text
Label : texte 3
Instructions : (vide)
Requis : non
----------------------------------------
— message_3 —
Type : text
Label : message 3
Instructions : (vide)
Requis : non
----------------------------------------
— respecter_casse_3 —
Type : true_false
Label : respecter casse 3
Instructions : (vide)
Requis : non
----------------------------------------
— texte_4 —
Type : text
Label : texte 4
Instructions : (vide)
Requis : non
----------------------------------------
— message_4 —
Type : text
Label : message 4
Instructions : (vide)
Requis : non
----------------------------------------
— respecter_casse_4 —
Type : true_false
Label : respecter casse 4
Instructions : (vide)
Requis : non
----------------------------------------
— enigme_acces_condition —
Type : radio
Label : conditions de déblocage
Instructions : (vide)
Requis : non
Choices :
  - immediat : Immédiat
  - date_programmee : Date Programmée
  - pre_requis : Pré Requis
----------------------------------------
— enigme_acces_date —
Type : date_picker
Label : date de déblocage
Instructions : possibilité de programmer la parution de l'énigme dans le futur
Requis : non
----------------------------------------
— enigme_acces_pre_requis —
Type : relationship
Label : pré requis
Instructions : autre(s) énigme(s) devant être résolues pour débloquer celle là
Requis : non
----------------------------------------
— enigme_chasse_associee —
Type : relationship
Label : chasse associée
Instructions : (vide)
Requis : oui
----------------------------------------
— enigme_solution_mode —
Type : radio
Label : Mode de publication des solutions
Instructions : (vide)
Requis : non
Choices :
  - pdf : Télécharger un PDF
  - texte : Rédiger la solution
----------------------------------------
— enigme_solution_delai —
Type : number
Label : délai de publication des solutions
Instructions : (vide)
Requis : non
----------------------------------------
— enigme_solution_heure —
Type : time_picker
Label : Heure de publication
Instructions : Heure à laquelle la solution sera publiée, X jours après la fin de la chasse
Requis : non
----------------------------------------
— enigme_solution_fichier —
Type : file
Label : Fichier PDF de solution
Instructions : Ajoutez un fichier PDF contenant la solution complète, si vous ne souhaitez pas utiliser l’éditeur texte.
Requis : non
----------------------------------------
— enigme_solution_explication —
Type : wysiwyg
Label : Solution expliquée
Instructions : La solution ne sera publiée que si la chasse est terminée, et selon le délai de votre choix
Requis : non
----------------------------------------
— enigme_cache_etat_systeme —
Type : select
Label : enigme_cache_etat_systeme
Instructions : (vide)
Requis : non
Choices :
  - accessible : Accessible
  - bloquee_date : Bloquée - à venir
  - bloquee_chasse : Bloquée - chasse indisponible
  - invalide : Invalide (données manquantes)
  - cache_invalide : Erreur de configuration
  - bloquee_pre_requis : bloquee_pre_requis
----------------------------------------
— enigme_cache_complet —
Type : true_false
Label : enigme_cache_complet
Instructions : (vide)
Requis : non
----------------------------------------

🔹 Groupe : Paramètres organisateur
🆔 ID : 657
🔑 Key : group_67c7dbfea4a39
📦 Champs trouvés : 8

— email_contact —
Type : email
Label : Adresse email de contact
Instructions : Adresse à laquelle les joueurs peuvent vous écrire. Si vous ne la renseignez pas, votre adresse principale sera utilisée par défaut. Elle ne sera jamais utilisée pour des envois promotionnels ou des prélèvements.
Requis : non
----------------------------------------
— logo_organisateur —
Type : image
Label : Votre Logo
Instructions : (vide)
Requis : non
----------------------------------------
— liens_publics —
Type : repeater
Label : Liens publics
Instructions : (vide)
Requis : non
Contenu imbriqué :
  — type_de_lien —
  Type : select
  Label : Type de lien
  Instructions : (vide)
  Requis : non
  Choices :
    - site_web : Site Web
    - discord : Discord
    - facebook : Facebook
    - twitter : Twitter/X
    - instagram : Instagram
  ----------------------------------------
  — url_lien —
  Type : url
  Label : url lien
  Instructions : (vide)
  Requis : non
  ----------------------------------------
----------------------------------------
— iban —
Type : text
Label : IBAN
Instructions : (vide)
Requis : non
----------------------------------------
— bic —
Type : text
Label : BIC
Instructions : (vide)
Requis : non
----------------------------------------
— utilisateurs_associes —
Type : select
Label : utilisateurs associes
Instructions : (vide)
Requis : non
----------------------------------------
— description_longue —
Type : wysiwyg
Label : Description
Instructions : (vide)
Requis : oui
----------------------------------------
— organisateur_cache_complet —
Type : true_false
Label : organisateur_cache_complet
Instructions : (vide)
Requis : non
----------------------------------------

🔹 Groupe : paramètres indices
🆔 ID : 9568
🔑 Key : group_68a1fb240748a
📦 Champs trouvés : 10

— indice_image —
Type : image
Label : image de l indice
Instructions : (vide)
Requis : non
----------------------------------------
— indice_contenu —
Type : wysiwyg
Label : texte de l indice
Instructions : (vide)
Requis : non
----------------------------------------
— indice_cible_type —
Type : radio
Label : type de contenu ciblé
Instructions : (vide)
Requis : non
Choices :
  - chasse : chasse
  - enigme : énigme
----------------------------------------
— indice_disponibilite —
Type : radio
Label : disponibilité
Instructions : (vide)
Requis : non
Choices :
  - immediate : immédiate
  - differe : différé
----------------------------------------
— indice_date_disponibilite —
Type : date_time_picker
Label : date de disponibilité
Instructions : (vide)
Requis : non
----------------------------------------
— indice_cout_points —
Type : number
Label : coût en points
Instructions : (vide)
Requis : non
----------------------------------------
— indice_enigme_linked —
Type : relationship
Label : énigme liée
Instructions : (vide)
Requis : non
----------------------------------------
— indice_chasse_linked —
Type : relationship
Label : chasse liée
Instructions : (vide)
Requis : non
----------------------------------------
— indice_cache_etat_systeme —
Type : select
Label : état système de l'indice
Instructions : (vide)
Requis : non
Choices :
  - accessible : accessible
  - programme : programmé
  - invalide : invalide
  - desactive : désactivé
----------------------------------------
— indice_cache_complet —
Type : true_false
Label : complétion de l'indice
Instructions : (vide)
Requis : non
----------------------------------------

🔹 Groupe : paramètres solution
🆔 ID : 9756
🔑 Key : group_68abd01f80aee
📦 Champs trouvés : 10

— solution_cible_type —
Type : radio
Label : for
Instructions : (vide)
Requis : non
Choices :
  - chasse : chasse
  - enigme : enigme
----------------------------------------
— solution_chasse_linked —
Type : relationship
Label : chasse liée
Instructions : (vide)
Requis : non
----------------------------------------
— solution_enigme_linked —
Type : relationship
Label : énigme liée
Instructions : (vide)
Requis : non
----------------------------------------
— solution_fichier —
Type : file
Label : fichier pdf de solution
Instructions : (vide)
Requis : non
----------------------------------------
— solution_explication —
Type : wysiwyg
Label : explication
Instructions : (vide)
Requis : non
----------------------------------------
— solution_disponibilite —
Type : radio
Label : disponibilité
Instructions : (vide)
Requis : non
Choices :
  - fin_chasse : fin de chasse
  - differee : différée
----------------------------------------
— solution_decalage_jours —
Type : number
Label : jours de décalage
Instructions : (vide)
Requis : non
----------------------------------------
— solution_heure_publication —
Type : time_picker
Label : heure de publication
Instructions : (vide)
Requis : non
----------------------------------------
— solution_cache_etat_systeme —
Type : select
Label : état système
Instructions : (vide)
Requis : non
Choices :
  - INVALIDE : invalide
  - FIN_CHASSE : fin de chasse
  - FIN_CHASSE_DIFFERE : différé
  - A_VENIR : à venir
  - EN_COURS : en cours
  - DESACTIVE : désactivé
Référence : constantes définies dans `inc/constants.php`
----------------------------------------
— solution_cache_complet —
Type : true_false
Label : complétion de la solution
Instructions : (vide)
Requis : non
----------------------------------------
