<?php

declare(strict_types=1);

namespace App\Platform\DirectoryCore\Domain;

enum EntrySort: string
{
    case Default = 'default';
    case NameAscending = 'name_asc';
    case NameDescending = 'name_desc';
}
