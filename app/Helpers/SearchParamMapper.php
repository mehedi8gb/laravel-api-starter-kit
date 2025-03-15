<?php

namespace App\Helpers;

use \Illuminate\Http\Request;

/**
 * Search Parameter Mapper to transform simple search queries into structured filters
 */
class SearchParamMapper
{
    protected Request $request;
    protected string $finalQueryString;
    protected bool|null $applyOrWhere;

    /**
     * Constructor
     *
     * @param Request $request The request object
     */
    public function __construct(Request $request)
    {
        $this->request = $request;
        $this->applyOrWhere = in_array($this->request->query('or'), ['true', '1', 1, true]);

        $this->transform($this->request->query('q'));
    }

    /**
     * Transform frontend search parameters into backend query parameters
     *
     * @param string|null $queryString $
     * @return void Transformed parameters for the backend
     */
    function transform(string|null $queryString): void
    {
        if ($this->request->query('where'))
            return;

        // Split the query string by the pipe separator
        $filters = $queryString ? explode('|', $queryString) : [];

        $queryParts = [];
        $first = true; // Track if it's the first condition

        foreach ($filters as $filter) {
            // Split each filter by '=' to get the key-value pair
            list($key, $value) = explode('=', $filter, 2);

            // Handle relationships (if the key contains a '.')
            $keys = explode('.', $key);

            if (count($keys) > 1) {
                // Handle relationships dynamically
                $relationPath = implode(',with:', array_slice($keys, 0, -1));
                $column = end($keys);
                $queryPart = ($first ? "where" : "orWhere") . "=with:$relationPath,$column,$value";
            } else {
                // Direct field matching (non-relational)
                $queryPart = ($first ? "where" : "orWhere") . "=$key,$value";
            }

            // Add the condition to the array
            $queryParts[] = $queryPart;
            $first = false; // After the first condition, switch to "orWhere"
        }

        $this->finalQueryString = implode('&', $queryParts);
        $this->mergeWithGeneratedQuery();
    }

    // it will merge the query string with the existing query string
    function mergeWithGeneratedQuery(): void
    {
        // Initialize query parameters
        $queryParams = ['where' => [], 'orWhere' => []];

        // Split the transformed query by '&' to get individual conditions
        $conditions = explode('&', $this->finalQueryString);

        if ($this->request->query('q')) {
            foreach ($conditions as $condition) {
                // Extract key and value properly
                [$key, $value] = explode('=', $condition, 2);

                if ($this->request->query('searchTerm') && $this->applyOrWhere) {
                    $queryParams['orWhere'][] = $value;
                    continue;
                }

                // Avoid storing 'where=' or 'orWhere=' inside values
                if ($this->applyOrWhere && empty($queryParams['where'])) {
                    $queryParams['where'][] = $value; // Add first condition to where
                } else {
                    $queryParams[$this->applyOrWhere ? 'orWhere' : 'where'][] = $value;
                }
            }
        }
        // Merge the extracted query parameters into the request
        $this->request->merge($queryParams);
    }
}
