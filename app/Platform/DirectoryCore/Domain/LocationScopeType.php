<?php

declare(strict_types=1);

namespace App\Platform\DirectoryCore\Domain;

enum LocationScopeType
{
    case City;
    case District;
    case State;
}
