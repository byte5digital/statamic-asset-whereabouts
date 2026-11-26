<?php

declare(strict_types=1);

return [
    'used_in' => 'Usato in',
    'not_used' => 'Non usato in nessun contenuto.',
    'all_places' => 'Tutti i posti',
    'all_sites' => 'tutti i siti',
    'stays_after_delete' => 'resta dopo un\'eliminazione',
    'kinds' => [
        'global' => 'Set globale',
        'user' => 'Utente',
        'nav' => 'Navigazione',
    ],
    'warning' => [
        'one_place' => 'Questo asset è usato in :count posto. Eliminandolo viene rimosso da lì.',
        'many_places' => 'Questo asset è usato in :count posti. Eliminandolo viene rimosso da lì.',
        'bulk' => ':used dei :selected asset selezionati sono in uso. Eliminandoli vengono rimossi dai contenuti che li usano.',
    ],
    'permission' => 'Eliminare gli asset in uso',
    'refused' => [
        'one_place' => 'Questo asset è usato in :count posto e non può essere eliminato. Rimuovilo prima da lì.',
        'many_places' => 'Questo asset è usato in :count posti e non può essere eliminato. Rimuovilo prima da lì.',
        'bulk' => ':used dei :selected asset selezionati sono in uso e non possono essere eliminati. Rimuovili prima dai contenuti che li usano.',
        'folder_one' => 'Questa cartella contiene :count asset in uso che non può essere eliminato. Rimuovilo prima dai contenuti che lo usano.',
        'folder_many' => 'Questa cartella contiene :count asset in uso che non possono essere eliminati. Rimuovili prima dai contenuti che li usano.',
    ],
];
