<?php
namespace ContentMigration\Service;

interface ContentImportInterface
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
     * @param ContentAdapterInterface $contentAdapter
     * @return mixed
     */
    public function setContentAdapter(ContentAdapterInterface $contentAdapter);

    /**
     * @return ContentAdapterInterface
     */
    public function getContentAdapter();

    /**
     * @param array $options
     * @throws \Exception
     */
    public function importData(array $options);

    /**
     * @return array
     */
    public function getFileColumns();

    /**
     * @param array $columns
     * @return array
     */
    public function getColumnHeaders(array $columns = array());
}