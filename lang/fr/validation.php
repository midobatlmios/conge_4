<?php

return [

    /*
    | French validation messages for the rules this app uses. Rules not listed
    | here fall back to Laravel's English messages.
    */

    'after_or_equal' => 'Le champ :attribute doit être une date égale ou postérieure à :date.',
    'before_or_equal' => 'Le champ :attribute doit être une date égale ou antérieure à :date.',
    'boolean' => 'Le champ :attribute doit être vrai ou faux.',
    'confirmed' => 'La confirmation du champ :attribute ne correspond pas.',
    'current_password' => 'Le mot de passe est incorrect.',
    'date' => 'Le champ :attribute doit être une date valide.',
    'email' => 'Le champ :attribute doit être une adresse e-mail valide.',
    'exists' => 'La valeur sélectionnée pour :attribute est invalide.',
    'in' => 'La valeur sélectionnée pour :attribute est invalide.',
    'integer' => 'Le champ :attribute doit être un nombre entier.',
    'lowercase' => 'Le champ :attribute doit être en minuscules.',
    'max' => [
        'numeric' => 'Le champ :attribute ne peut pas dépasser :max.',
        'string' => 'Le champ :attribute ne peut pas dépasser :max caractères.',
    ],
    'min' => [
        'numeric' => 'Le champ :attribute doit être au moins :min.',
        'string' => 'Le champ :attribute doit contenir au moins :min caractères.',
    ],
    'password' => [
        'letters' => 'Le champ :attribute doit contenir au moins une lettre.',
        'mixed' => 'Le champ :attribute doit contenir au moins une majuscule et une minuscule.',
        'numbers' => 'Le champ :attribute doit contenir au moins un chiffre.',
        'symbols' => 'Le champ :attribute doit contenir au moins un symbole.',
        'uncompromised' => 'Ce :attribute est apparu dans une fuite de données. Veuillez en choisir un autre.',
    ],
    'required' => 'Le champ :attribute est obligatoire.',
    'required_if' => 'Le champ :attribute est obligatoire quand :other est :value.',
    'string' => 'Le champ :attribute doit être une chaîne de caractères.',
    'unique' => 'Cette valeur de :attribute est déjà utilisée.',

    'attributes' => [
        'name' => 'nom',
        'email' => 'adresse e-mail',
        'password' => 'mot de passe',
        'password_confirmation' => 'confirmation du mot de passe',
        'current_password' => 'mot de passe actuel',
        'role' => 'rôle',
        'user_id' => 'employé',
        'date_debut' => 'date de début',
        'date_fin' => 'date de fin',
        'type_conge' => 'type de congé',
        'etat' => 'état',
        'comment' => 'commentaire',
    ],

    'values' => [
        'type_conge' => [
            'recuperation' => 'récupération',
        ],
        'date_debut' => [
            'today' => "aujourd'hui",
        ],
        'date_fin' => [
            'date_debut' => 'la date de début',
        ],
    ],

];
