<?php

namespace Arrow\ApiBase\Repositories;

abstract class DbRepositoryAbstract implements Repository
{
    protected $model;

    public function getById($id)
    {
        return $this->model->find($id);
    }

    public function getAll($orderBy = null, $paginate = null)
    {
        $query = ($orderBy) ? $this->model->orderBy($orderBy) : $this->model;
        return $paginate ? $query->paginate($paginate) : $query->get();
    }

    public function paginate($limit, $currentCursor = null, $orderBy = null)
    {
        if ($currentCursor)
        {
            $items = $this->model->where('id', '>', $currentCursor)->take($limit);
        }
        else
        {
            $items = $this->model->take($limit);
        }

        if ($orderBy) $items->orderBy($orderBy);

        return $items->get();
    }

    public function newInstance(array $attributes = [])
    {
        return $this->model->newInstance($attributes);
    }

    public function create(array $attributes = [])
    {
        return $this->model->create($attributes);
    }

    public function updateWithId($id, array $input)
    {
        $target = $this->getById($id);
        return $target->update($input);
    }

    public function deleteById($id)
    {
        $target = $this->getById($id);
        return $target->delete();
    }

    public function restoreById($id)
    {
        $target = $this->model->withTrashed()->find($id);
        return $target->restore();
    }

    protected function unsetIfEmpty($data, $keys)
    {
        foreach ($keys as $key)
        {
            if (isset($data[$key]) && trim((string)$data[$key]) === '') $data[$key] = null;
        }
        return $data;
    }
}