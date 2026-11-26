<?php

declare(strict_types=1);

return [
    'used_in' => 'Utilisé dans',
    'not_used' => 'Utilisé dans aucun contenu.',
    'all_places' => 'Tous les endroits',
    'all_sites' => 'tous les sites',
    'stays_after_delete' => 'reste après une suppression',
    'kinds' => [
        'global' => 'Ensemble global',
        'user' => 'Utilisateur',
        'nav' => 'Navigation',
    ],
    'warning' => [
        'one_place' => 'Cet asset est utilisé à :count endroit. Le supprimer le retire de cet endroit.',
        'many_places' => 'Cet asset est utilisé à :count endroits. Le supprimer le retire de ces endroits.',
        'bulk' => ':used des :selected assets sélectionnés sont utilisés. Les supprimer les retire des contenus qui les utilisent.',
    ],
    'permission' => 'Supprimer les assets utilisés',
    'refused' => [
        'one_place' => 'Cet asset est utilisé à :count endroit et ne peut pas être supprimé. Retirez-le d\'abord de cet endroit.',
        'many_places' => 'Cet asset est utilisé à :count endroits et ne peut pas être supprimé. Retirez-le d\'abord de ces endroits.',
        'bulk' => ':used des :selected assets sélectionnés sont utilisés et ne peuvent pas être supprimés. Retirez-les d\'abord des contenus qui les utilisent.',
        'folder_one' => 'Ce dossier contient :count asset utilisé qui ne peut pas être supprimé. Retirez-le d\'abord des contenus qui l\'utilisent.',
        'folder_many' => 'Ce dossier contient :count assets utilisés qui ne peuvent pas être supprimés. Retirez-les d\'abord des contenus qui les utilisent.',
    ],
];
