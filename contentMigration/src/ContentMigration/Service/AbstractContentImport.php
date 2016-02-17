<?php
namespace ContentMigration\Service;

use CmsAdmin\Core\Utility;
use Zend\Validator\ValidatorInterface;

abstract class AbstractContentImport implements ContentImportInterface
{
    /**
     * @var ContentAdapterInterface
     */
    protected $_contentAdapter;

    /**
     * @var \PHPExcel
     */
    protected $_dataFile;

    /**
     * @var array
     */
    protected $_validationErrors = array();

    /**
     * @param ContentAdapterInterface $contentAdapter
     */
    public function __construct(ContentAdapterInterface $contentAdapter)
    {
        $this->setContentAdapter($contentAdapter);
    }

    /**
     * @param \PHPExcel $dataFile
     */
    public function setDataFile(\PHPExcel $dataFile)
    {
        $this->_dataFile = $dataFile;
    }

    /**
     * @param $path
     * @return \PHPExcel
     * @throws \PHPExcel_Reader_Exception
     */
    public function getReaderFromPath($path)
    {
        $objReader = \PHPExcel_IOFactory::createReaderForFile($path);

        if ($objReader instanceof \PHPExcel_Reader_Abstract)
            $objReader->setReadDataOnly(true);

        if ($objReader instanceof \PHPExcel_Reader_CSV && method_exists($this, 'getFieldSeparator'))
            $objReader->setDelimiter($this->getFieldSeparator());


        return $objReader->load($path);
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
     * @return ContentAdapterInterface
     * @throws \Exception
     */
    public function getContentAdapter()
    {
        return $this->_contentAdapter;
    }

    /**
     * @return \PHPExcel
     */
    public function getDataFile()
    {
        return $this->_dataFile;
    }

    /**
     * @return array
     * @throws \Exception
     */
    public function getFileColumns()
    {
        $columns = array();

        $this->_requireInitialised(__METHOD__);

        $row = $this->getDataFile()->getActiveSheet()->getRowIterator(1)->current();

        if (!$row instanceof \PHPExcel_Worksheet_Row) {
            return $columns;
        }

        $cellIterator = $row->getCellIterator();
        $cellIterator->setIterateOnlyExistingCells(false);

        foreach ($cellIterator as $cell) {
            if ($cell instanceof \PHPExcel_Cell)
                $columns[] = $cell->getValue();
        }

        return $columns;
    }

    /**
     * @param array $columns
     * @return array
     * @throws \Exception
     */
    public function getColumnHeaders(array $columns = array())
    {
        $this->_requireInitialised(__METHOD__);

        $columnHeaders    = array();
        $availableColumns = $this->getContentAdapter()->getColumns();

        foreach ($availableColumns as $column) {
            if (!empty($columns) && !in_array($column, $columns)) continue;

            $columnHeaders[] = ucwords(
                implode(' ',
                    Utility::breakCamelCase(
                        preg_replace('/_(.+)/', '', $column)
                    )
                )
            );
        }

        return $columnHeaders;
    }

    /**
     * Validates row && also populates validationErrors array
     *
     * @param array $row
     * @return bool
     */
    protected function validateRow(array $row)
    {
        $validators = $this->getContentAdapter()->getContentValidators();
        $isValid    = true;

        foreach ($row as $column => $value) {
            if (isset($validators[$column]) && is_array($validators[$column])) {
                foreach ($validators[$column] as $validator => $options) {
                    $validator = new $validator($options);

                    if ($validator instanceof ValidatorInterface && !$validator->isValid($value)) {
                        $this->_validationErrors[$column][] = implode(',', $validator->getMessages());
                        $isValid = false;
                    }
                }
            }
        }

        return $isValid;
    }

    /**
     * @return array
     */
    public function getValidationErrors()
    {
        $errors = array();

        foreach ($this->_validationErrors as $field => $concatenatedErrors) {
            $errorMsg = 'In ' . count($concatenatedErrors) . ' instance(s) the column "' . $this->getContentAdapter()->getReadableColumnName($field) . '" contained invalid data. Error: ' . $concatenatedErrors[0];

            $errors[] = array('message' => $errorMsg);
        }

        return $errors;
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
     * @param array $options
     * @return bool
     * @throws \Exception
     */
    abstract public function importData(array $options);

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
}