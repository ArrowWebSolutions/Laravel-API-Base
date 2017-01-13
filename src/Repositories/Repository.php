<?php

namespace Arrow\ApiBase\Repositories;

interface Repository
{
    public function getAll($orderBy = null, $paginate = null);
    public function getById($id);
    public function newInstance(array $attributes = []);
    public function create(array $attributes = []);
    public function updateWithId($id, array $input);
    public function deleteById($id);
}