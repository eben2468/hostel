<?php
namespace App\Core;

/** Simple offset pagination over a count + data query pair. */
class Paginator
{
    /**
     * @param string $countSql query returning a single total count
     * @param string $dataSql  query WITHOUT a LIMIT clause (it is appended)
     * @param array  $params   bind params shared by both queries
     * @param int    $page     current 1-based page
     * @param int    $perPage  rows per page
     * @return array{rows:array, page:int, perPage:int, total:int, lastPage:int, from:int, to:int}
     */
    public static function make(string $countSql, string $dataSql, array $params, int $page, int $perPage = 15): array
    {
        $total = (int) Database::scalar($countSql, $params);
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = max(1, min($page, $lastPage));
        $offset = ($page - 1) * $perPage;

        $rows = Database::all($dataSql . " LIMIT {$perPage} OFFSET {$offset}", $params);

        return [
            'rows'     => $rows,
            'page'     => $page,
            'perPage'  => $perPage,
            'total'    => $total,
            'lastPage' => $lastPage,
            'from'     => $total ? $offset + 1 : 0,
            'to'       => min($offset + $perPage, $total),
        ];
    }

    /** Read and sanitise the current page number from the query string. */
    public static function currentPage(): int
    {
        return max(1, (int) ($_GET['page'] ?? 1));
    }
}
