<?php

declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;
use Slim\Http\ServerRequest;
use function in_array;
use function intdiv;
use function is_array;
use function max;
use function min;
use function trim;

final readonly class DataTableRequest
{
    private const DEFAULT_LENGTH = 10;
    private const MAX_LENGTH = 100;

    private function __construct(
        public int $length,
        public int $page,
        public int $draw,
        public string $search,
        public string $orderBy,
        public string $orderDirection
    ) {
    }

    /**
     * @param list<string> $sortableColumns
     */
    public static function from(
        ServerRequest $request,
        array $sortableColumns,
        string $defaultOrderBy
    ): self {
        if (! in_array($defaultOrderBy, $sortableColumns, true)) {
            throw new InvalidArgumentException('Default DataTable order column must be sortable.');
        }

        $requestedLength = (int) $request->getParam('length', self::DEFAULT_LENGTH);
        $length = $requestedLength > 0
            ? min($requestedLength, self::MAX_LENGTH)
            : self::DEFAULT_LENGTH;
        $start = max(0, (int) $request->getParam('start', 0));
        $draw = max(0, (int) $request->getParam('draw', 0));

        $searchParam = $request->getParam('search', []);
        $search = is_array($searchParam)
            ? trim((string) ($searchParam['value'] ?? ''))
            : '';

        $orderParam = $request->getParam('order', []);
        $firstOrder = is_array($orderParam) && is_array($orderParam[0] ?? null)
            ? $orderParam[0]
            : [];
        $direction = (string) ($firstOrder['dir'] ?? 'desc');
        if (! in_array($direction, ['asc', 'desc'], true)) {
            $direction = 'desc';
        }

        $columns = $request->getParam('columns', []);
        $columnIndex = max(0, (int) ($firstOrder['column'] ?? 0));
        $requestedColumn = is_array($columns)
            && is_array($columns[$columnIndex] ?? null)
            ? (string) ($columns[$columnIndex]['data'] ?? '')
            : '';
        $orderBy = in_array($requestedColumn, $sortableColumns, true)
            ? $requestedColumn
            : $defaultOrderBy;

        return new self(
            $length,
            intdiv($start, $length) + 1,
            $draw,
            $search,
            $orderBy,
            $direction
        );
    }
}
