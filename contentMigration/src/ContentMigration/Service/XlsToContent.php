<?php
namespace ContentMigration\Service;

class XlsToContent extends AbstractContentImport
{
    /**
     * @param array $initOptions
     * @throws \Exception
     * @return void
     */
    public function init(array $initOptions = array())
    {
        foreach ($initOptions as $option => $value) {
            if (property_exists($this, $assembledProperty = '_' . $option))
                $this->$assembledProperty = $value;
        }

        if (!empty($initOptions['filePath']))
            $this->setDataFile($this->getReaderFromPath($initOptions['filePath']));

        if (array_key_exists('composite', $initOptions))
            $this->getContentAdapter()->init(array('composite' => $initOptions['composite']));

        if (!$this->isInitialised())
            throw new \Exception('Could not initialise ' . __CLASS__);
    }

    /**
     * @return bool
     */
    public function isInitialised()
    {
        if (
            $this->_dataFile instanceof \PHPExcel
            &&
            $this->getContentAdapter()->isInitialised()
        )
            return true;

        return false;
    }

    /**
     * @param array $options
     * @return bool
     * @throws \Exception
     */
    public function importData(array $options)
    {
        // Reset validation errors
        $this->_validationErrors = array();

        if (!array_key_exists('mappings', $options) || empty($options['mappings']))
            throw new \Exception('Method ' . __METHOD__ . ' requires mappings array to be passed in as part of the options array');

        $objWorksheet = $this->_dataFile->getActiveSheet();
        $highestRow   = $objWorksheet->getHighestRow();

        $data = array();
        // Assume first row is going to be headers so skip to row 2
        for ($row = 2; $row <= $highestRow; ++$row) {
            $rowData = array();

            foreach ($options['mappings'] as $cmtColumn => $col) {
                // If id is not set its an insert so no validation on Id required
                // If Context is not set it will take the default context, again if not set no validation required
                // if parent is not set it will take the default parent (0), again if not set no validation required
                if (in_array($cmtColumn, array('id','context','parent')) && $objWorksheet->getCellByColumnAndRow($col, $row)->getValue() == null)
                    continue;

                $rowData[$cmtColumn] =  $objWorksheet->getCellByColumnAndRow($col, $row)->getValue();
            }

            if ($this->validateRow($rowData))
                $data[] = $rowData;
        }

        if (count($this->getValidationErrors()) > 0)
            return false;

        foreach ($data as $row) {
            $this->getContentAdapter()->save($row);
        }

        return true;
    }
}