<?php

namespace App\Models;

use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model as MainModel;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * Class Model
 *
 * @method static Builder<static>|Model newModelQuery()
 * @method static Builder<static>|Model newQuery()
 * @method static Builder<static>|Model query()
 * @method static Builder<static>|Model inRandomOrder()
 * @method static Builder<static>|Model orderBy($column, $direction = 'asc')
 * @method static Builder<static>|Model limit($value)
 * @method static Builder<static>|Model pluck($column, $key = null)
 * @method static Builder<static>|Model select($columns)
 * @method static Builder<static>|Model with($relations)
 * @method static Builder<static>|Model withCount($relations)
 * @method static Builder<static>|Model get()
 * @method static Builder<static>|Model first()
 * @method static Builder<static>|Model firstOrFail()
 * @method static Builder<static>|Model findOrFail($id)
 * @method static Builder<static>|Model findOrNew($id)
 * @method static Builder<static>|Model find($id)
 * @method static Builder<static>|Model exists()
 * @method static Builder<static>|Model count()
 * @method static Builder<static>|Model latest($column = 'created_at')
 * @method static Builder<static>|Model whereKey($id)
 * @method static Builder<static>|Model whereKeyNot($id)
 * @method static Builder<static>|Model whereKeyNotIn($ids)
 * @method static Builder<static>|Model whereKeyIn($ids)
 * @method static Builder<static>|Model create($data)
 * @method static Builder<static>|Model updateOrCreate($attributes, $values = [])
 * @method static Builder<static>|Model where($column, $operator = null, $value = null, $boolean = 'and')
 * @method static Builder<static>|Model whereIn($column, $values)
 * @method static Builder<static>|Model whereBetween($column, $values)
 * @method static Builder<static>|Model whereNotBetween($column, $values)
 * @method static Builder<static>|Model whereCreatedAt($value)
 * @method static Builder<static>|Model whereId($value)
 * @method static Builder<static>|Model whereStatus($value)
 * @method static Builder<static>|Model whereUpdatedAt($value)
 * @method static Builder<static>|Model whereDate($column, $operator, $value = null, $boolean = 'and')
 * @method static Builder<static>|Model whereNotIn($column, $values)
 * @method static Builder<static>|Model whereNull($column)
 * @method static Builder<static>|Model whereNotNull($column)
 * @method static Builder<static>|Model whereJsonContains($column, $value, $boolean = 'and', $not = false)
 * @method static Builder<static>|Model whereJsonDoesntContain($column, $value, $boolean = 'and')
 * @method static Builder<static>|Model whereJsonLength($column, $operator, $value)
 * @method static Builder<static>|Model whereJsonLengthStrict($column, $operator, $value)
 * @method static Builder<static>|Model whereJsonLengthLoose($column, $operator, $value)
 * @method static Builder<static>|Model whereJsonLengthNotStrict($column, $operator, $value)
 * @method static Builder<static>|Model whereJsonLengthNotLoose($column, $operator, $value)
 * @method static Builder<static>|Model whereJsonLengthStrictNotLoose($column, $operator, $value)
 * @method static Builder<static>|Model whereJsonLengthLooseNotStrict($column, $operator, $value)
 */
class Model extends MainModel
{
    /**
     * @var array|mixed
     */
    private static mixed $deletedJsonFields;

    /**
     * @throws Exception
     */
    public static function findOrCustomFail($id, $message = 'not found'): null|Model|Builder|ModelNotFoundException
    {
        try {
            if (!$id) return null;

            return static::findOrFail($id);
        } catch (ModelNotFoundException $e) {
            $modelName = class_basename(static::class);
            $customMessage = "{$modelName} {$message}";

            throw new ModelNotFoundException($customMessage, 404);
        }
    }

    /**
     * Update model with deep merge for array/json columns.
     *
     * @param array $attributes
     * @return bool
     */
    public function updateWithDeepMerge(array $attributes): bool
    {
        // Find all columns that are either JSON or array types
        $jsonColumns = $this->getJsonColumns();
        foreach ($jsonColumns as $column) {
            if (isset($attributes[$column]) && is_array($attributes[$column])) {
                // Retrieve the existing data

                $existingValue = $this->$column;

                // Perform the deep merge on the existing value and the incoming value
                $attributes[$column] = deepMerge($existingValue, $attributes[$column]);
            }
        }
        // Update the model with the merged attributes
        return $this->update($attributes);
    }

    /**
     * Delete specific key(s) from a deeply nested JSON column dynamically.
     *
     * @param string $column The JSON column name.
     * @param array $data The validated request data containing keys to delete.
     * @return bool Whether the update was successful.
     */
    public function deleteDeepJsonField(string $column, array $data): bool
    {
        // Ensure the column exists and is JSON
        if (!in_array($column, $this->getJsonColumns())) {
            throw new InvalidArgumentException("Invalid JSON column: $column");
        }

        // Get the existing JSON data from the model
        $jsonData = $this->$column;

        // Ensure it's an array before proceeding
        if (!is_array($jsonData)) {
            return false;
        }

        // Convert request data to dot notation keys for deletion
        $keysToDelete = $this->flattenArrayKeys($data);

        // Store deleted keys in a static variable
        static::$deletedJsonFields = $keysToDelete;

        // Remove keys from JSON data
        foreach ($keysToDelete as $key) {
            Arr::forget($jsonData, $key);
        }

        // Update the model with modified JSON data
        return $this->update([$column => $jsonData]);
    }

    /**
     * Convert a nested array into dot notation keys.
     *
     * @param array $array The nested request data.
     * @param string $prefix The prefix for recursion (used internally).
     * @return array Flattened keys in dot notation.
     */
    private function flattenArrayKeys(array $array, string $prefix = ''): array
    {
        $keys = [];
        foreach ($array as $key => $value) {
            $fullKey = $prefix ? "$prefix.$key" : $key;
            if (is_array($value)) {
                $keys = array_merge($keys, $this->flattenArrayKeys($value, $fullKey));
            } else {
                $keys[] = $fullKey;
            }
        }
        return $keys;
    }

    /**
     * Get all columns that are either JSON or array types.
     *
     * @return array
     */
    private function getJsonColumns(): array
    {
        return collect($this->casts)
            ->filter(fn ($type) => in_array($type, ['json', 'array']))
            ->keys()
            ->toArray();
    }

    /**
     * Get the last deleted JSON fields in a simple format.
     *
     * @return string
     */
    public static function getLastDeletedSuccessMessage(): string
    {
        $grouped = [];

        // Group by parent
        foreach (static::$deletedJsonFields as $field) {
            $parts = explode('.', $field);
            $parent = array_shift($parts);
            $child = implode('.', $parts);

            if (!isset($grouped[$parent])) {
                $grouped[$parent] = [];
            }

            $grouped[$parent][] = $child;
        }

        // Format output into a simple string
        $formatted = [];
        foreach ($grouped as $parent => $children) {
            if (!empty($children)) {
                $formatted[] = implode(', ', $children) . " from $parent";
            }
        }

        return 'successfully deleted ' . implode(', ', $formatted);
    }
    /**
     * Advanced JSON search with multiple operators and nested conditions
     *
     * @param Builder $query
     * @param string $column JSON column name
     * @param array $conditions Search conditions
     * @return Builder
     */
    public function scopeJsonSearch(Builder $query, string $column, array $conditions): Builder
    {
        return $query->where(function ($query) use ($column, $conditions) {
            $this->buildJsonSearch($query, $column, $conditions);
        });
    }

    /**
     * Recursively build JSON search conditions
     *
     * @param Builder $query
     * @param string $column
     * @param array $conditions
     * @param string $path
     */
    private function buildJsonSearch(Builder $query, string $column, array $conditions, string $path = ''): void
    {
        foreach ($conditions as $key => $condition) {
            $currentPath = $path ? "{$path}.{$key}" : $key;

            if (is_array($condition) && !$this->isOperatorArray($condition)) {
                // Nested array - recurse
                $this->buildJsonSearch($query, $column, $condition, $currentPath);
                continue;
            }

            if (is_array($condition)) {
                // Handle operator conditions
                $operator = $condition['operator'] ?? '=';
                $value = $condition['value'] ?? null;
                $boolean = $condition['boolean'] ?? 'and';

                switch ($operator) {
                    case 'contains':
                        $query->where("{$column}->{$currentPath}", 'like', "%{$value}%", $boolean);
                        break;
                    case 'starts_with':
                        $query->where("{$column}->{$currentPath}", 'like', "{$value}%", $boolean);
                        break;
                    case 'ends_with':
                        $query->where("{$column}->{$currentPath}", 'like', "%{$value}", $boolean);
                        break;
                    case 'in':
                        $query->whereIn("{$column}->{$currentPath}", (array)$value, $boolean);
                        break;
                    case 'not_in':
                        $query->whereNotIn("{$column}->{$currentPath}", (array)$value, $boolean);
                        break;
                    case 'between':
                        $query->whereBetween("{$column}->{$currentPath}", (array)$value, $boolean);
                        break;
                    case 'not_between':
                        $query->whereNotBetween("{$column}->{$currentPath}", (array)$value, $boolean);
                        break;
                    case 'exists':
                        $query->whereJsonContains("{$column}->{$currentPath}", $value, $boolean);
                        break;
                    case 'not_exists':
                        $query->whereJsonDoesntContain("{$column}->{$currentPath}", $value, $boolean);
                        break;
                    case 'null':
                        $query->whereNull("{$column}->{$currentPath}", $boolean);
                        break;
                    case 'not_null':
                        $query->whereNotNull("{$column}->{$currentPath}", $boolean);
                        break;
                    default:
                        $query->where("{$column}->{$currentPath}", $operator, $value, $boolean);
                }
            } else {
                // Simple equality check
                $query->where("{$column}->{$currentPath}", '=', $condition);
            }
        }
    }

    /**
     * Check if the array represents an operator condition
     *
     * @param array $array
     * @return bool
     */
    private function isOperatorArray(array $array): bool
    {
        return isset($array['operator']) || isset($array['value']);
    }
}
