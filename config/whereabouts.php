<?php

declare(strict_types=1);

return [
    // The asset containers the addon acts on; null means every container.
    //
    // A list names them by handle. A container may carry options of its own:
    //
    //     'containers' => [
    //         'images',
    //         'files' => ['branches' => 'collapsed', 'delete' => 'permission'],
    //     ],
    //
    // branches: how the branches of the "Used in" tree start for the container's assets,
    //           'open' (the default) or 'collapsed'.
    // delete:   who may delete an asset that is in use: 'warn' - everybody who may delete
    //           assets, after the warning (the default) -, 'permission' - only roles with the
    //           "Delete assets that are in use" permission -, or 'block' - nobody.
    'containers' => null,
];
