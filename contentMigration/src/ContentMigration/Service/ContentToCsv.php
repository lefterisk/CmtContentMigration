<?php
namespace ContentMigration\Service;

use CmsAdmin\Model\Media;

class ContentToCsv extends AbstractContentExport
{
    /**
     * Export file extension
     */
    const FILE_EXTENSION = '.csv';

    /**
     * @var string
     */
    protected $_fieldSeparator = ',';

    /**
     * @param array $initOptions
     * @throws \Exception
     * @return void
     */
    public function init(array $initOptions = array())
    {
        foreach ($initOptions as $option => $value) {
            if (property_exists($this, $assembledProperty = '_' . $option)) {
                $this->$assembledProperty = $value;
            }
        }

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
            !empty($this->_fieldSeparator)
            &&
            !empty($this->_exportDirectory)
            &&
            $this->getContentAdapter()->isInitialised()
        )
            return true;

        return false;
    }

    /**
     * @return string
     */
    public function getFieldSeparator()
    {
        return $this->_fieldSeparator;
    }

    /**
     * @param array $options
     * @throws \Exception
     */
    public function generateExport(array $options = array())
    {
        $this->_requireInitialised(__METHOD__);

        $this->checkExportDirectory();

        $contentPaginator = $this->getContentAdapter()->getRows();
        // Paginator trick to get all items at once
        $contentPaginator->setItemCountPerPage(-1);

        $objPHPExcel = new \PHPExcel();
        $rowCounter  = 1;
        $objPHPExcel->getActiveSheet()->fromArray($this->getColumnHeaders(), '^^^', 'A'.$rowCounter );

        foreach ($contentPaginator as $contentRow) {
            $rowCounter++;
            $values = array();
            foreach ($this->getContentAdapter()->getColumns() as $column) {
                $values[] = $contentRow[$column];
            }

            $objPHPExcel->getActiveSheet()->fromArray($values, '^^^', 'A'.$rowCounter );
        }

        // Create the file
        $fileName  = $this->getExportFileName() . self::FILE_EXTENSION;
        $objWriter = new \PHPExcel_Writer_CSV($objPHPExcel);
        $objWriter->setDelimiter($this->getFieldSeparator());
        $objWriter->setSheetIndex(0);
        $objWriter->setUseBOM(true);
        $objWriter->save($this->getExportDirectory() . '/' . $fileName);

        // Save the file in the database
        $media              = new Media();
        $media->name        = $fileName;
        $media->size        = filesize($this->getExportDirectory() . '/' . $fileName);
        preg_match('/(.*)(\/media\/.*)/', $this->getExportDirectory() . '/' . $fileName, $matches);
        $media->public_path = !empty($matches[2]) ? $matches[2] : null;
        $media->extension   = self::FILE_EXTENSION;
        $media->deleted     = 0;

        $this->_mediaTable->save($media);
    }
}