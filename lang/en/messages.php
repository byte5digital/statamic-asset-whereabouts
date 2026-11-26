<?php

declare(strict_types=1);

return [
    'used_in' => 'Used in',
    'not_used' => 'Not used in any content.',
    'all_places' => 'All places',
    'all_sites' => 'all sites',
    'stays_after_delete' => 'stays after a delete',
    'kinds' => [
        'global' => 'Global set',
        'user' => 'User',
        'nav' => 'Navigation',
    ],
    'warning' => [
        'one_place' => 'This asset is used in :count place. Deleting it removes it from there.',
        'many_places' => 'This asset is used in :count places. Deleting it removes it from them.',
        'bulk' => ':used of the :selected selected assets are in use. Deleting them removes them from the content that uses them.',
    ],
    'permission' => 'Delete assets that are in use',
    'refused' => [
        'one_place' => 'This asset is used in :count place and can\'t be deleted. Remove it from there first.',
        'many_places' => 'This asset is used in :count places and can\'t be deleted. Remove it from them first.',
        'bulk' => ':used of the :selected selected assets are in use and can\'t be deleted. Remove them from the content that uses them first.',
        'folder_one' => 'This folder holds :count asset that is in use and can\'t be deleted. Remove it from the content that uses it first.',
        'folder_many' => 'This folder holds :count assets that are in use and can\'t be deleted. Remove them from the content that uses them first.',
    ],
];
