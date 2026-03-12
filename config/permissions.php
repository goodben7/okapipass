<?php

declare(strict_types=1);

use App\Model\Permission;

return static function (): iterable {

    yield Permission::new('ROLE_USER_CREATE', "Créér un utilisateur");
    yield Permission::new('ROLE_USER_LOCK', "Vérouiller/Déverrouiller un utilisateur");
    yield Permission::new('ROLE_USER_CHANGE_PWD', "Modifier mot de passe");
    yield Permission::new('ROLE_USER_DETAILS', "Consulter les détails d'un utilisateur");
    yield Permission::new('ROLE_USER_LIST', "Consulter la liste des utilisateurs");
    yield Permission::new('ROLE_USER_EDIT', "Editer les informations d'un utilisateur");
    yield Permission::new('ROLE_USER_DELETE', "Supprimer un utilisateur");
    yield Permission::new('ROLE_USER_SET_PROFILE', "Modifier le profil utilisateur");

    yield Permission::new('ROLE_PROFILE_CREATE', "Créer un profil utilisateur");
    yield Permission::new('ROLE_PROFILE_LIST', "Consulter la liste des profils utilisateur");
    yield Permission::new('ROLE_PROFILE_UPDATE', "Modifier un profil utilisateur");
    yield Permission::new('ROLE_PROFILE_DETAILS', "Consulter les détails d'un profil utilisateur");

    yield Permission::new('ROLE_ACTIVITY_LIST', "Consulter la liste des activités"); 
    yield Permission::new('ROLE_ACTIVITY_VIEW', "Consulter les détails d'une activité"); 

    yield Permission::new('ROLE_CHECKPOINT_CREATE', "Créer un checkpoint");
    yield Permission::new('ROLE_CHECKPOINT_LIST', "Consulter la liste des checkpoints");
    yield Permission::new('ROLE_CHECKPOINT_UPDATE', "Modifier un checkpoint");
    yield Permission::new('ROLE_CHECKPOINT_DETAILS', "Consulter les détails d'un checkpoint");

    yield Permission::new('ROLE_GOPASS_CREATE', "Créer un GoPass");
    yield Permission::new('ROLE_GOPASS_LIST', "Consulter la liste des GoPass");
    yield Permission::new('ROLE_GOPASS_UPDATE', "Modifier un GoPass");
    yield Permission::new('ROLE_GOPASS_DETAILS', "Consulter les détails d'un GoPass");

    yield Permission::new('ROLE_TICKET_CREATE', "Créer un ticket");
    yield Permission::new('ROLE_TICKET_LIST', "Consulter la liste des tickets");
    yield Permission::new('ROLE_TICKET_DETAILS', "Consulter les détails d'un ticket");

    yield Permission::new('ROLE_PAYMENT_CREATE', "Créer un paiement");
    yield Permission::new('ROLE_PAYMENT_LIST', "Consulter la liste des paiements");
    yield Permission::new('ROLE_PAYMENT_UPDATE', "Modifier un paiement");
    yield Permission::new('ROLE_PAYMENT_DETAILS', "Consulter les détails d'un paiement");

    yield Permission::new('ROLE_AGENCY_CREATE', "Créer une agence");
    yield Permission::new('ROLE_AGENCY_LIST', "Consulter la liste des agences");
    yield Permission::new('ROLE_AGENCY_UPDATE', "Modifier une agence");
    yield Permission::new('ROLE_AGENCY_DETAILS', "Consulter les détails d'une agence");

};
