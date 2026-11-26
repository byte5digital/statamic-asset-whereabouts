<?php

declare(strict_types=1);

namespace Byte5\Whereabouts\Usage;

/** What kind of content an asset is used in. */
enum Kind: string
{
    case Entry = 'entry';
    case Term = 'term';
    case GlobalSet = 'global';
    case User = 'user';
    case Nav = 'nav';
}
