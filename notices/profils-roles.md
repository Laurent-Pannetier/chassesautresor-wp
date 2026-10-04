# Notice — pages de profil par rôle

Fiche courte. Le cadrage complet (décisions, modes, lots, critères) est dans
[`docs/roadmap-profils-roles.md`](../../../../docs/roadmap-profils-roles.md).

## À retenir pour l’implémentation

- S’appuyer sur `chassesautresor_site_experience` / `SiteExperienceService`
  (`single_hunt`, `demo`, `platform`) — ne pas inventer un second flag.
- Menus : Accueil + (Tentatives si joueur) + Profil ; Commandes dans Profil ;
  déconnexion uniquement via la barre supérieure ; nav CPT org conservée à gauche.
- Points UI pilotée par `points_ui_enabled` / `cat_is_points_ui_enabled()` (défaut off).
- Switch Éditer/Activer sur l’accueil profil : activation directe en `demo` ;
  demande de validation + confirmation admin en `single_hunt`.
- Design : primitives Orgy uniquement (`dashboard-section`, `dashboard-card`,
  `dashboard-stat`, `dashboard-placeholder`, `dashboard-switch`).

## Lot A (livré)

- Helpers `myaccount_get_sidebar_nav_items()`, `myaccount_user_is_player()`,
  `myaccount_render_dashboard_section()`, `myaccount_render_dashboard_placeholder()`.
- Endpoint WooCommerce `tentatives` ; Tentatives retirées de l’Accueil.
- Menu compte header : `assets/js/header-account-menu.js`.
- Shells Accueil joueur / org / admin avec placeholders.

## Lot D (livré)

- Switch Éditer/Activer : `HuntLifecycleService` + AJAX `cta_toggle_hunt_lifecycle`.
- Accueil org/admin : accès rapide édition, switch, reset stats (démo).
- CTA validation retiré des fiches chasse/énigme ; messages d’éligibilité nettoyés.
- Annulation de demande rouvre bien en `correction` + `revision`.

## Lot E (livré)

- Stats par énigme V1 : `AccountHuntRiddleStatisticsRenderer` +
  `RiddleStatisticsApplicationService::overviewForHunt()`.
- Accueil admin : protection globale active ; Points / taux / ACF en zone inactive.
- Styles `.switch-control` sous `.myaccount-layout` (polish complet reporté Lot UI).

## Nav CPT hors plateforme

- Hors mode `platform` : pas de lien/icône organisateur dans le menu gauche ;
  la chasse reste visible et stylée distinctement des énigmes.
- Accès fiche `organisateur` redirigé vers l’accueil sauf admin.

## Lot UI (livré)

- Switch Éditer/Activer : pastille d’état en en-tête + gros commutateur Orgy.
- Labels Éditer/Activer mis en avant selon l’état (`is-current`).

## Lot F — file admin (livré)

- Accueil admin : carte `Actions en attente` (Correction / Bannir).
- Valider reste sur le switch ; correction/bannir retirés de la fiche chasse.
- Après action : retour Accueil (`/mon-compte/`).
