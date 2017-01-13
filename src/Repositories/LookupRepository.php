<?php

namespace Arrow\ApiBase\Repositories;

interface LookupRepository extends Repository
{
    public function getAllForSelect($orderBy = 'name');
    public function canBeMassManaged();
    public function getAdditionalMassColumns();
}