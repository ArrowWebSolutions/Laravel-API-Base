<?php namespace App\Repositories;

abstract class DbLookupRepositoryAbstract extends DbRepositoryAbstract implements LookupRepository
{
    protected $massManaged = false;
    protected $massColumns = [];

    public function getAllForSelect($orderBy = 'name')
    {
        $all = $this->getAll($orderBy);
        $options = ['' => '-- Please Select --'];
        foreach ($all as $row) $options[$row->id] = $row->name;
        return $options;
    }

    public function canBeMassManaged()
    {
        return $this->massManaged;
    }

    public function getAdditionalMassColumns()
    {
        return $this->massColumns;
    }
}