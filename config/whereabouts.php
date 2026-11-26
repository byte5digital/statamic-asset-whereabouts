<?php

declare(strict_types=1);

return [
    // The asset containers the addon acts on; null means every container.
    //
    // A list names them by handle. A container may carry options of its own:
    //
    //     'containers' => [
    //         'images',
    //         'files' => ['branches' => 'collapsed'],
    //     ],
    //
    // branches: how the branches of the "Used in" tree start for the container's assets,
    //           'open' (the default) or 'collapsed'.
    'containers' => null,
];
