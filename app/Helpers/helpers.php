<?php

use App\Helpers\SearchParamMapper;
use App\Http\Resources\DefaultResource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use \Illuminate\Http\Request;

function getResourceClass($model): string
{
    // Derive the model class name without namespace
    $modelClassName = class_basename($model);

    // Construct the corresponding resource class name
    $resourceClass = "App\\Http\\Resources\\{$modelClassName}Resource";

    // Check if the resource class exists
    if (class_exists($resourceClass)) {
        return $resourceClass;
    }

    // Fallback to a default resource class if not found
    return DefaultResource::class;
}

/**
 * Convert boolean status to 1/0.
 *
 * @param mixed $status
 * @return int
 */
function convertStatus(mixed $status): int
{
    return $status ? 1 : 0;
}

/**
 * Perform a deep merge of two arrays, allowing forced replacement with a "forceReplace" value.
 * Includes handling for array deletions based on the forceReplace flag.
 *
 * @param array $original
 * @param array $new
 * @param string $forceReplaceIndicator
 * @return array
 */
function deepMerge(array $original, array $new, string $forceReplaceIndicator = 'forceReplace'): array
{
    foreach ($new as $key => $value) {
        // If value is marked as a forced replacement
        if ($value === $forceReplaceIndicator) {
            // Remove the key from the original array
            unset($original[$key]);
            continue;
        }

        // Skip overwriting with null/empty values
        if (is_null($value) || (is_string($value) && trim($value) === '') || (is_array($value) && empty($value))) {
            continue;
        }

        if (is_array($value) && isset($original[$key]) && is_array($original[$key])) {
            // Recursively merge arrays
            $original[$key] = deepMerge($original[$key], $value, $forceReplaceIndicator);
        } else {
            // Overwrite scalar values or arrays
            $original[$key] = $value;
        }
    }

    return $original;
}

/**
 * Process nested arrays by removing missing indexes and merging incoming data.
 *
 * @param array $existingArray
 * @param array $payloadArray
 * @return array
 */
function processNestedArray(array $existingArray, array $payloadArray): array
{
    // Map payload by unique identifier (e.g., id)
    $payloadMap = collect($payloadArray)->keyBy('id');

    // Filter existing array to retain only indexes present in the payload
    $filteredArray = collect($existingArray)
        ->filter(fn($item) => $payloadMap->has($item['id']))
        ->map(fn($item) => array_merge($item, $payloadMap->get($item['id'])))
        ->values()
        ->toArray();

    // if array fragment same to same then remove 1 index
    return array_map("unserialize", array_unique(array_map("serialize", $filteredArray)));
}

/**
 * Format error response.
 *
 * @param NotFoundHttpException|ModelNotFoundException|ErrorException|Exception|string $e
 * @param int $statusCode
 * @return JsonResponse
 */
function sendErrorResponse(NotFoundHttpException|ModelNotFoundException|ErrorException|Exception|string $e, int $statusCode): JsonResponse
{
    // Check if the environment is 'local' (for detailed error messages in dev)
    $isLocal = app()->environment('local');

    if ($isLocal) {
        return response()->json([
            'success' => false,
            'message' => is_string($e) ? $e : $e->getMessage(),
        ], 500);
    }

    if ($e instanceof QueryException) {
        $errorCode = $e->errorInfo[1];  // Get the MySQL error code

        // Check if it's a duplicate entry (error code 1062 for MySQL)
        if ($errorCode == 1062) {
            // Handle duplicate entry error
            return response()->json([
                'success' => false,
                'message' => 'Duplicate entry detected. Please ensure the value is unique.',
            ], 400);  // Send a 400 Bad Request response
        }
    }

    // Check for specific ErrorException related to roles access
    if ($e instanceof ErrorException && str_contains($e->getMessage(), "Attempt to read property \"roles\" on false")) {
        return response()->json([
            'success' => false,
            'message' => 'You do not have the necessary permissions to access this resource.',
        ], 403); // Forbidden status code
    }

    // Handle ModelNotFoundException
    if ($e instanceof ModelNotFoundException) {
        $model = class_basename($e->getModel());
        $id = $e->getIds() ? implode(',', $e->getIds()) : 'Unknown';

        return response()->json([
            'success' => false,
            'message' => $isLocal ? "{$model} with ID {$id} not found. Details: {$e->getMessage()}" : "The requested resource could not be found.",
        ], 404);
    }

    // Handle NotFoundHttpException (404)
    if ($e instanceof NotFoundHttpException) {
        return response()->json([
            'success' => false,
            'message' => $isLocal ? $e->getMessage() : 'The requested page could not be found.',
        ], 404);
    }

    // Handle QueryException (database query errors, 500)
    if ($e instanceof \Illuminate\Database\QueryException) {
        return response()->json([
            'success' => false,
            'message' => $isLocal ? $e->getMessage() : 'Database error. Please try again later.',
        ], 500);
    }

    // Handle general exceptions (500)
    if ($e instanceof Exception) {
        return response()->json([
            'success' => false,
            'message' => $isLocal ? $e->getMessage() : 'Internal Server Error. Please try again later.',
        ], 500);
    }

    // Handle string messages (fallback)
    if (is_string($e)) {
        return response()->json([
            'success' => false,
            'message' => $e,
        ], $statusCode);
    }

    // Fallback for unexpected cases (Internal Server Error)
    return response()->json([
        'success' => false,
        'message' => $isLocal ? $e->getMessage() : 'Internal Server Error',
    ], 500);
}


/**
 * Format success response.
 *
 * @param string $message
 * @param mixed|null $data
 * @param int $statusCode
 * @return JsonResponse
 */
function sendSuccessResponse(string $message, mixed $data = null, int $statusCode = 200): JsonResponse
{
    if ($data === null) {
        $data = new \stdClass();
    }
    return response()->json([
        'success' => true,
        'message' => $message,
        'data' => $data,
    ], $statusCode);
}

/**
 * Handle API request.
 *
 * @param Request $request
 * @param Builder $query
 * @param array $with
 * @return array
 * @throws Exception
 */
function handleApiRequest(Request $request, Builder $query, array $with = []): array
{
    $page = $request->query('page', 1);
    $limit = $request->query('limit', 10);
    $sortBy = $request->query('sortBy');
    $sortDirection = $request->query('sortDirection', 'asc');
    $selectFields = $request->query('select');
    $isIgnoreFilter = false;
    $request->validate([
        'operator' => 'nullable|in:=,!=,<,<=,>,>=,like,ilike'
    ]);
    $operator = $request->query('operator', 'like');

    // this class will map and inject into request the low level query from the frontend high level query
    new SearchParamMapper($request);

    // Eager load relationships
    if (!empty($with)) {
        $query->with($with);
    }

    if ($request->route()->getName() === 'students.index') {
        $filters = [
            'status' => 'status',
            'refId' => 'ref_id',
            'name' => 'name',
            'email' => 'email',
            'phone' => 'phone',
            'agentId' => 'agent_id',
            'staffId' => 'staff_id',
            'createdBy' => 'created_by',
            'dob' => 'dob',
        ];
        $i = 1;
        foreach ($filters as $queryParam => $column) {
            if ($request->query($queryParam) !== null) {
                if ($queryParam === 'createdBy') {
                    $query->whereHas('createdBy', function ($q) use ($i, $request) {
//                        if ($request->query('or') && $i > 1) {
//                            $q->orWhere('id', $request->query('createdBy'));
//                        } else {
                        $q->where('id', $request->query('createdBy'));
//                        }
                    });
                    $i++;
                    unset($filters['createdBy']);
                    continue;
                }

                if ($queryParam === 'staffId') {
                    $query->whereHas('assignStaffs', function ($q) use ($i, $request) {
//                        if ($request->query('or') && $i > 1) {
//                            $q->orWhere('id', $request->query('staffId'));
//                        } else {
                        $q->where('staffId', $request->query('staffId'));
//                        }
                    });
                    $i++;
                    unset($filters['staffId']);
                    continue;
                }

                if ($queryParam === 'agentId') {
                    $query->whereHas('agent', function ($q) use ($i, $request) {
//                        if ($request->query('or') && $i > 1) {
//                            $q->orWhere('id', $request->query('staffId'));
//                        } else {
                        $q->where('id', $request->query('agentId'));
//                        }
                    });
                    $i++;
                    unset($filters['agentId']);
                    continue;
                }
                // Apply filter to query
                if ($request->query('or') && $i > 1) {
                    $query->orWhere($column, $request->query($queryParam));
                } else {
                    $query->where($column, $request->query($queryParam));
                }
                $i++;
            }
        }
        $isIgnoreFilter = true;
    }


    // Exclude from the query
    if ($request->query('exclude')) {
        $exclude = explode(',', $request->query('exclude'));
        $query->where($exclude[0], '!=', $exclude[1]);
    }

    if (!$isIgnoreFilter) {
        // Apply filters
        foreach ($request->query() as $key => $value) {
            if (!in_array($key, ['page', 'limit', 'search', 'searchTerm', 'sortBy', 'sortDirection', 'select', 'where', 'orWhere', 'exclude', 'company', 'q', 'or', 'operator'])) {
                $query->where($key, $value);
            }
        }
    }

    // Apply search
    $searchTerm = $request->query('searchTerm');
    if ($searchTerm !== null) {
        $columns = Schema::getColumnListing($query->getModel()->getTable());

        if ($request->route()->getName() === 'students.index')
            $columns = ['name', 'email', 'phone', 'ref_id'];

//        if ($request->query('or')) {
//            $query->orWhere(function ($query) use ($operator, $searchTerm, $columns) {
//                foreach ($columns as $column) {
//                    $query->orWhere($column, $operator, "%$searchTerm%");
//                }
//            });
//        }

        $query->where(function ($query) use ($operator, $searchTerm, $columns) {
            foreach ($columns as $column) {
                $query->orWhere($column, $operator, "%$searchTerm%");
            }
        });
    }


    // Check for the 'where' parameter
//    if ($request->query('where')) {
//        $filter = $request->query('where');
//        $parts = explode(',', $filter);
//
//        if (count($parts) < 2) {
//            return ['error' => 'Invalid where format. Use where=column,value or where=with:relation,column,value'];
//        }
//
//        $relationParts = [];
//
//        // Extract multiple 'with:' relations dynamically
//        while (!empty($parts) && str_starts_with($parts[0], 'with:')) {
//            $relationParts[] = str_replace('with:', '', array_shift($parts));
//        }
//
//        $column = $parts[0] ?? null;
//        $value = $parts[1] ?? null;
//
//        if (!$column || $value === null) {
//            return ['error' => 'Invalid where format. Use where=column,value or where=with:relation,column,value'];
//        }
//
//        if (!empty($relationParts)) {
//            // Handle nested relational filtering
//            $query->whereHas(implode('.', $relationParts), function ($relationQuery) use ($column, $value) {
//                $relationQuery->where($column, 'like', $value);
//            });
//        } else {
//            // Handle standard column filtering (previous system support)
//            $query->where($column, 'like', $value);
//        }
//    }


    // Check for the 'where' parameter
    if ($request->query('where')) {
        $filters = $request->query('where');
        // Multiple where conditions can be passed as an array
        $filters = is_array($filters) ? $filters : [$filters];

        $query->where(function ($q) use ($operator, $filters) {
            foreach ($filters as $filter) {
                $parts = explode(',', $filter);

                if (count($parts) < 2) {
                    throw new Exception(response()->json([
                        'error' => 'Invalid where format. Use where=column,value or where=with:relation,column,value'
                    ], 400));
                }

                $relationParts = [];

                // Extract multiple 'with:' relations dynamically
                while (!empty($parts) && str_starts_with($parts[0], 'with:')) {
                    $relationParts[] = str_replace('with:', '', array_shift($parts));
                }

                $column = $parts[0] ?? null;
                $value = $parts[1] ?? null;

                if (!$column || $value === null) {
                    throw new Exception(response()->json([
                        'error' => 'Invalid where format. Use where=column,value or where=with:relation,column,value'
                    ], 400));
                }

                if (!empty($relationParts)) {
                    // Handle nested relational filtering with where condition
                    $q->whereHas(implode('.', $relationParts), function ($relationQuery) use ($operator, $column, $value) {
                        $relationQuery->where($column, $operator, $value);
                    });
                } else {
                    // Handle standard column filtering where
                    $q->where($column, $operator, $value);
                }
            }
        });
    }


    // Check for the 'orWhere' parameter
    if ($request->query('orWhere')) {
        $filters = $request->query('orWhere');

        // Multiple where conditions can be passed as an array
        $filters = is_array($filters) ? $filters : [$filters];
        $query->orWhere(function ($orQuery) use ($operator, $filters) {
            foreach ($filters as $filter) {
                $parts = explode(',', $filter);

                if (count($parts) < 2) {
                    return ['error' => 'Invalid orWhere format. Use orWhere=column,value or orWhere=with:relation,column,value'];
                }

                $relationParts = [];

                while (!empty($parts) && str_starts_with($parts[0], 'with:')) {
                    $relationParts[] = str_replace('with:', '', array_shift($parts));
                }

                $column = $parts[0] ?? null;
                $value = $parts[1] ?? null;

                if (!$column || $value === null) {
                    return ['error' => 'Invalid orWhere format. Use orWhere=column,value or orWhere=with:relation,column,value'];
                }

                if (!empty($relationParts)) {
                    $orQuery->orWhereHas(implode('.', $relationParts), function ($relationQuery) use ($operator, $column, $value) {
                        $relationQuery->where($column, $operator, $value);
                    });
                } else {
                    $orQuery->orWhere($column, $operator, $value);
                }
            }
        });
    }


    // Apply sorting
    if ($sortBy) {
        $query->orderBy($sortBy, $sortDirection);
    }

    $query->orderBy('created_at', 'desc');

    // Select specific fields
    if ($selectFields !== null) {
        $query->select(explode(',', $selectFields));
    }

    // Fetch results
    if ($limit === 'all') {
        $results = $query->get();
        $total = $results->count();
    } else {
        $results = $query->paginate($limit, ['*'], 'page', $page);
        $total = $results->total();
    }

    // Meta information for pagination
    $meta = [
        'page' => $page,
        'limit' => $limit === 'all' ? $total : $limit,
        'total' => $total,
        'totalPage' => $limit === 'all' ? 1 : $results->lastPage(),
    ];

    // Apply dynamic resource transformation
    $resourceClass = getResourceClass($query->getModel());

    $result = $request->query('select') !== null
        ? ($results instanceof LengthAwarePaginator ? $results->items() : $results->toArray())
        : $resourceClass::collection($results);

    return [
        'meta' => $meta,
        'result' => $result,
    ];

}

/**
 * @return boolean
 */
function isStaff(): bool
{
    return auth()->user()->hasRole('staff');
}

/**
 * @return boolean
 */
function isAgent(): bool
{
    return auth()->user()->hasRole('agent');
}
/**
 * Check if the user is an admin.
 *
 * @return bool
 */
function isAdmin(): bool
{
    return auth()->check() && auth()->user()->hasRole('admin');
}

