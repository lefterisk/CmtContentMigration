<?php
namespace ContentMigration\Service;

use Zend\Paginator\Paginator;

interface ContentAdapterInterface
{
    /**
     * @param array $initOptions
     * @return mixed
     */
    public function init(array $initOptions = array());

    /**
     * @return bool
     */
    public function isInitialised();

    /**
     * @return string
     */
    public function getContentName();

    /**
     * @return array
     */
    public function getColumns();

    /**
     * @param array $columns
     * @return array
     */
    public function getReadableColumns(array $columns = array());

    /**
     * @param $columnName
     * @return string
     */
    public function getReadableColumnName($columnName);

    /**
     * @param array $options
     * @return Paginator
     */
    public function getRows(array $options = array());

    /**
     * @return array
     */
    public function getContentValidators();

    /**
     * @param array $dataRow
     * @throws \Exception
     */
    public function save(array $dataRow);
}