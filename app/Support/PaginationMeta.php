<?php

namespace App\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

final class PaginationMeta
{
    /**
     * @param  LengthAwarePaginator<mixed>  $page
     * @return array{currentPage: int, lastPage: int, perPage: int, total: int}
     */
    public static function from(LengthAwarePaginator $page): array
    {
        return [
            'currentPage' => $page->currentPage(),
            'lastPage' => $page->lastPage(),
            'perPage' => $page->perPage(),
            'total' => $page->total(),
        ];
    }

    public static function perPage(Request $request, int $default = 10, int $max = 100): int
    {
        return min(max($request->integer('per_page', $default), 1), $max);
    }
}
