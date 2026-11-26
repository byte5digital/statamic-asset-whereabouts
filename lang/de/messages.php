<?php

declare(strict_types=1);

return [
    'used_in' => 'Verwendet in',
    'not_used' => 'In keinem Inhalt verwendet.',
    'all_places' => 'Alle Stellen',
    'all_sites' => 'alle Sites',
    'stays_after_delete' => 'bleibt nach dem Löschen bestehen',
    'kinds' => [
        'global' => 'Globales Set',
        'user' => 'Benutzer',
        'nav' => 'Navigation',
    ],
    'warning' => [
        'one_place' => 'Dieses Asset wird an :count Stelle verwendet. Beim Löschen wird es dort entfernt.',
        'many_places' => 'Dieses Asset wird an :count Stellen verwendet. Beim Löschen wird es dort entfernt.',
        'bulk' => ':used der :selected ausgewählten Assets werden verwendet. Beim Löschen werden sie aus den Inhalten entfernt, die sie verwenden.',
    ],
    'permission' => 'Verwendete Assets löschen',
    'refused' => [
        'one_place' => 'Dieses Asset wird an :count Stelle verwendet und kann nicht gelöscht werden. Entferne es dort zuerst.',
        'many_places' => 'Dieses Asset wird an :count Stellen verwendet und kann nicht gelöscht werden. Entferne es dort zuerst.',
        'bulk' => ':used der :selected ausgewählten Assets werden verwendet und können nicht gelöscht werden. Entferne sie zuerst aus den Inhalten, die sie verwenden.',
        'folder_one' => 'Dieser Ordner enthält :count Asset, das verwendet wird und nicht gelöscht werden kann. Entferne es zuerst aus den Inhalten, die es verwenden.',
        'folder_many' => 'Dieser Ordner enthält :count Assets, die verwendet werden und nicht gelöscht werden können. Entferne sie zuerst aus den Inhalten, die sie verwenden.',
    ],
];
