<?php
namespace ContentMigration\Service;

interface ContentExportInterface
{
    /**
     * @param array $initOptions
     * @return mixed
     */
    public function init(array $initOptions = array());

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
     * @return bool
     */
    public function isInitialised();

    /**
     * @param array $options
     * @throws \Exception
     */
    public function generateExport(array $options = array());
}