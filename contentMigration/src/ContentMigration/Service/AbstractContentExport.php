<?php
namespace ContentMigration\Service;

use CmsAdmin\TableGateway\MediaTable;

abstract class AbstractContentExport implements ContentExportInterface
{
    /**
     * @var ContentAdapterInterface
     */
    protected $_contentAdapter;

    /**
     * @var string
     */
    protected $_exportDirectory;

    /**
     * @var MediaTable
     */
    protected $_mediaTable;

    /**
     * @param ContentAdapterInterface $contentAdapter
     * @param MediaTable $mediaTable
     */
    public function __construct(ContentAdapterInterface $contentAdapter, MediaTable $mediaTable)
    {
        $this->setContentAdapter($contentAdapter);
        $this->setMediaTable($mediaTable);
    }

    /**
     * @param ContentAdapterInterface $contentAdapter
     * @return void
     */
    public function setContentAdapter(ContentAdapterInterface $contentAdapter)
    {
        $this->_contentAdapter = $contentAdapter;
    }

    /**
     * @param MediaTable $mediaTable
     * @return void
     */
    public function setMediaTable(MediaTable $mediaTable)
    {
        $this->_mediaTable = $mediaTable;
    }

    /**
     * @return ContentAdapterInterface
     * @throws \Exception
     */
    public function getContentAdapter()
    {
        return $this->_contentAdapter;
    }

    /**
     * @return string
     */
    public function getExportDirectory()
    {
        return $this->_exportDirectory;
    }

    /**
     * @throws \Exception
     */
    protected function checkExportDirectory()
    {
        if (!is_dir($this->_exportDirectory))
            if (is_writable(dirname($this->_exportDirectory)))
                mkdir($this->_exportDirectory, 0777);

        if (!is_dir($this->_exportDirectory) || !is_writable($this->_exportDirectory))
            throw new \Exception('Export directory doesnt exist or does not have correct permissions');
    }

    /**
     * @param array $columns
     * @return array
     * @throws \Exception
     */
    public function getColumnHeaders(array $columns = array())
    {
        $this->_requireInitialised(__METHOD__);

        return $this->getContentAdapter()->getReadableColumns($columns);
    }

    /**
     * @return string
     * @throws \Exception
     */
    public function getExportFileName()
    {
        $this->_requireInitialised(__METHOD__);

        return $this->getContentAdapter()->getContentName() . '_' . date('Y-m-d_h.i.s');
    }

    /**
     * @param $method
     * @throws \Exception
     */
    protected function _requireInitialised($method)
    {
        if (!$this->isInitialised())
            throw new \Exception('Initialise class' . __CLASS__ . ' before using method ' . $method);
    }

    /**
     * @param array $initOptions
     * @throws \Exception
     * @return void
     */
    abstract public function init(array $initOptions = array());


    /**
     * @return bool
     */
    abstract public function isInitialised();

    /**
     * @param array $options
     * @throws \Exception
     */
    abstract public function generateExport(array $options = array());
}