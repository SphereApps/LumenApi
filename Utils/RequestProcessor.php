<?php

namespace Sphere\Api\Utils;

use Exception;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class RequestProcessor
{
    const LIMIT_MAX = 1000;

    protected $params = [
        'filters' => null,
        'relations' => null,
        'scopes' => null,
        'sorting' => null,
        'page' => null,
        'limit' => null,
    ];

    protected $defaults = [
        'page' => 1,
    ];

    protected $allowed = [
        'filters' => true,
        'relations' => true,
        'scopes' => true,
    ];

    protected $defaultFilterOperator = 'eq';
    protected $filterOperators = [
        'ct' => ['operator' => 'LIKE', 'value' => '%%%s%%'],
        'sw' => ['operator' => 'LIKE', 'value' => '%s%%'],
        'ew' => ['operator' => 'LIKE', 'value' => '%%%s'],
        'eq' => ['operator' => '='],
        'ne' => ['operator' => '!='],
        'gt' => ['operator' => '>'],
        'ge' => ['operator' => '>='],
        'lt' => ['operator' => '<'],
        'le' => ['operator' => '<='],
        'in' => ['method' => 'whereIn', 'multiple' => true],
        'nn' => ['method' => 'whereNotNull', 'value' => false],
        'nl' => ['method' => 'whereNull', 'value' => false],
        'bw' => ['method' => 'whereBetween', 'multiple' => true],
        'nb' => ['method' => 'whereNotBetween', 'multiple' => true],
    ];

    public function __construct()
    {
    }

    public function setDefaults(array $defaults)
    {
        if ($defaults) {
            $this->defaults = array_merge($this->defaults, $defaults);
        }
    }

    public function setAllowed(array $allowed)
    {
        if ($allowed) {
            $this->allowed = $allowed;
        }
    }

    public function clearParams()
    {
        foreach ($this->params as $key => $value) {
            $this->params[$key] = $this->defaults[$key] ?? null;
        }
    }

    public function parseRequest(Request $request)
    {
        $this->clearParams();

        // Relations
        if ($request->has('filter') || $request->has('f')) {
            $this->parseFilters($request->get('filter') ?? $request->get('f'));
        }

        // Relations
        if ($request->has('include')) {
            $this->parseRelations($request->get('include'));
        }

        // Scopes
        if ($request->has('scope')) {
            $this->parseScopes($request->get('scope'));
        }

        // Sorting
        if ($request->has('sort')) {
            $this->parseSorting($request->get('sort'));
        }

        // Page
        if ($request->has('page')) {
            $this->params['page'] = max(1, (int) $request->get('page'));
        }

        // Limit
        if ($request->has('limit')) {
            $this->params['limit'] = min(static::LIMIT_MAX, max(1, (int) $request->get('limit')));
        }
    }

    public function parseFilters($requestFilters)
    {
        $this->params['filters'] = [];

        if (!is_array($requestFilters)) {
            throw new BadRequestHttpException("Query param 'filter' should be array");
        }

        foreach ($requestFilters as $field => $filterString) {
            if ($this->allow('filters', $field)) {
                $filterSegments = explode(':', $filterString, 2);
                if (count($filterSegments) == 1) {
                    $filterSegments = ['eq', $filterSegments];
                }
                [$operatorName, $value] = $filterSegments;
                if (!$operatorName) {
                    $operatorName = $this->defaultFilterOperator;
                }
                if (!array_key_exists($operatorName, $this->filterOperators)) {
                    throw new BadRequestHttpException("Not allowed operator: {$operatorName}");
                }

                $operator = $this->filterOperators[$operatorName];

                $method = $operator['method'] ?? 'where';

                $arguments = [];

                $arguments[] = $field;
                if ($operator['operator'] ?? false) {
                    $arguments[] = $operator['operator'];
                }
                $valueFormat = $operator['value'] ?? null;
                if ($valueFormat !== false) {
                    if ($valueFormat) {
                        $value = sprintf($valueFormat, $value);
                    }
                    if ($operator['multiple'] ?? false) {
                        $value = explode('|', $value);
                    }
                    $arguments[] = $value;
                }

                $this->params['filters'][] = [$method => $arguments];
            }
        }
    }

    public function parseSorting($requestValue)
    {
        $values = $this->explodeMultipleValue($requestValue);
        $this->params['sorting'] = [];
        foreach ($values as $field) {
            $direction = 'ASC';
            if ($field{
                0} === '-') {
                $direction = 'DESC';
                $field = substr($field, 1);
            }
            $this->params['sorting'][$field] = $direction;
        }
    }

    public function parseRelations($requestValue)
    {
        $relations = $this->explodeMultipleValue($requestValue);
        $this->params['relations'] = [];
        foreach ($relations as $value) {
            if ($value === '*') {
                if ($defaults = $this->defaults['relations'] ?? false) {
                    $this->params['relations'] = array_merge($this->params['relations'], $defaults);
                }
            } else {
                if ($this->allow('relations', $value)) {
                    $this->params['relations'][] = $value;
                }
            }
        }
    }

    public function parseScopes($requestValue)
    {
        $scopes = $this->explodeMultipleValue($requestValue);
        $this->params['scopes'] = [];
        foreach ($scopes as $value) {
            if ($value === '*') {
                if ($defaults = $this->defaults['scopes'] ?? false) {
                    $this->params['scopes'] = array_merge($this->params['scopes'], $defaults);
                }
            } else {
                $arguments = explode(':', $value);
                $method = array_shift($arguments);
                if ($this->allow('scopes', $method)) {
                    $this->params['scopes'][$method] = [$arguments];
                }
            }
        }
    }

    protected function allow($section, $option)
    {
        if ($this->allowed[$section] === true) {
            return true;
        }

        if ($this->allowed[$section]) {
            if (in_array($option, $this->allowed[$section])) {
                return true;
            }
        }

        throw new BadRequestHttpException("Not allowed request option - {$section}:{$option}");
    }

    protected function explodeMultipleValue($value, $delimeter = ',')
    {
        $value = trim(trim($value), ',');
        return $value ? explode($delimeter, $value) : [];
    }

    public function getRelations()
    {
        return $this->get('relations');
    }

    public function getScopes()
    {
        return $this->get('scopes');
    }

    public function getSorting()
    {
        return $this->get('sorting');
    }

    public function getFilters()
    {
        return $this->get('filters');
    }

    public function applyRelations(&$model)
    {
        if ($relations = $this->getRelations()) {
            $model = $model->with($relations);
        }
    }

    public function applyScopes(&$model)
    {
        if ($scopes = $this->getScopes()) {
            foreach ($scopes as $method => $arguments) {
                $model = call_user_func_array([$model, $method], $arguments);
            }
        }
    }

    public function applySorting(&$model)
    {
        if ($sorting = $this->getSorting()) {
            foreach ($sorting as $field => $direction) {
                $model = $model->orderBy($field, $direction);
            }
        }
    }

    public function applyFilters(&$model)
    {
        if ($filters = $this->getFilters()) {
            foreach ($filters as $filter) {
                foreach ($filter as $method => $arguments) {
                    $model = call_user_func_array([$model, $method], $arguments);
                }
            }
        }
    }

    public function applyLimit(&$model)
    {
        $limit = $this->get('limit');
        if ($limit > 0) {
            $model = $model->limit($limit);
        }
    }

    public function makePaginator($model)
    {
        $limit = $this->get('limit');
        $page = $this->get('page');

        return $model->paginate($limit, ['*'], 'page', $page);
    }


    public function has($param)
    {
        if (!array_key_exists($param, $this->params)) {
            throw new Exception("Unknown parameter: {$param}", 1);
        }

        return $this->params[$param] !== null;
    }

    public function get($param)
    {
        if ($this->has($param)) {
            return $this->params[$param];
        }

        return $this->defaults[$param] ?? null;
    }

    public function getAll()
    {
        return $this->params;
    }
}
