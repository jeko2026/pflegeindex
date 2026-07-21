<?php

namespace App\Http\Controllers\Concerns;

use App\Platform\DirectoryCore\ReadModel\ListingResult;
use Illuminate\Http\Request;

trait ValidatesPublicPagination
{
    private function publicPage(Request $request): int
    {
        if (! array_key_exists('page', $request->query())) {
            return 1;
        }

        $value = $request->query('page');

        abort_unless(
            is_string($value) && preg_match('/^[1-9][0-9]*$/D', $value) === 1,
            404,
        );

        $page = filter_var($value, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);

        abort_unless(is_int($page), 404);

        return $page;
    }

    private function ensurePublicPageExists(ListingResult $result): void
    {
        abort_if($result->currentPage > $result->lastPage(), 404);
    }
}
