<?php
namespace ContentMigration\Controller;

use ContentMigration\TableGateway\PermissionTable;
use Zend\View\Model\JsonModel;

class ImportController extends AbstractRestfulJsonController
{
    protected $availableMethods = array('OPTIONS', 'UPDATE', 'GET');

    /**
     * Options, maps to the OPTIONS http verb for self description
     *
     * @return JsonModel
     */
    public function options()
    {
        $this->response->setStatusCode(200);
        $this->response->getHeaders()->addHeaderLine('Allow', implode($this->availableMethods));
        $this->response->getHeaders()->addHeaderLine('Content-Type', 'application/json');
        $data = array(
            'api'           => 'cms-api',
            'authenticated' => $this->isAuthenticated()
        );

        $viewModel = new JsonModel($data);
        return $this->response->setContent($viewModel->serialize());
    }

    /**
     * Update method maps to the http verb PUT expects id defined
     * in route and form data in the request body
     *
     * @param int $id
     * @param mixed $formData
     * @return JsonModel
     */
    public function update($id, $formData)
    {
        if (!$this->isAuthorised(PermissionTable::ASSET_TYPE_COMPOSITE_CONTENT, PermissionTable::ACTION_UPDATE, $id)) {
            return $this->notAuthorisedResponse();
        }

        $formData   = $this->_getParamsFromJsonRequestPayload();
        $config     = $this->getServiceLocator()->get('config');
        $publicPath = $config['file_manager']['public_dir'];

        $mediaTable = $this->getServiceLocator()->get('MediaTable');
        try {
            $file   = $mediaTable->get($formData['mediaId']);
        } catch(\Exception $e) {
            $this->addError(40);
            return $this->_returnJsonViewModel(array());
        }

        $filePath   = $publicPath . $file->public_path;
        $path_parts = pathinfo($filePath);

        if (strtolower($path_parts['extension']) != $formData['fileType']) {
            $this->addError(39);
            return $this->_returnJsonViewModel(array());
        }

        $fieldMappings = array();
        if (array_key_exists('mappings', $formData) && is_array($formData['mappings'])) {
            foreach ($formData['mappings'] as $mapping) {
                if (isset($mapping['contentField']['value']) && isset($mapping['fileField']['value']))
                    $fieldMappings[$mapping['contentField']['value']] = $mapping['fileField']['value'];
            }
        }

        if (empty($fieldMappings)) {
            $this->addError(42);
            return $this->_returnJsonViewModel(array());
        }

        $importErrors = array();

        try {
            switch ($formData['fileType']) {
                case 'xls':
                    $importer = $this->getServiceLocator()->get('ContentMigration\Service\XlsToCompositeContent');
                    break;
                case 'csv':
                default:
                    $importer = $this->getServiceLocator()->get('ContentMigration\Service\CsvToCompositeContent');
                    break;
            }

            $importer->init(array('composite' => $id, 'filePath' => $filePath));

            if (!$importer->importData(array('mappings' => $fieldMappings)))
                $importErrors = $importer->getValidationErrors();

            return $this->_returnJsonViewModel(array(
                'composite' => $id,
                'data'      => $formData,
                'path'      => $filePath,
                'mappings'  => $fieldMappings,
                'errors'    => $importErrors
            ));

        } catch (\Exception $e) {
            return $this->problemResponse(array(
                'title' => 'Exception',
                'detail' => $e->getMessage(),
            ), 503);
        }
    }

    /**
     * Get method maps to the http verb GET expects id defined
     * in route
     *
     * @param $id
     * @return mixed
     */
    public function get($id)
    {
        if (!$this->isAuthorised(PermissionTable::ASSET_TYPE_COMPOSITE_CONTENT, PermissionTable::ACTION_READ, $id)) {
            return $this->notAuthorisedResponse();
        }

        $mediaId    = $this->params()->fromQuery('mediaId');
        $fileType = $this->params()->fromQuery('fileType');

        $config     = $this->getServiceLocator()->get('config');
        $publicPath = $config['file_manager']['public_dir'];

        $mediaTable = $this->getServiceLocator()->get('MediaTable');
        try {
            $file   = $mediaTable->get($mediaId);
        } catch(\Exception $e) {
            $this->addError(40);
            return $this->_returnJsonViewModel(array());
        }

        $filePath   = $publicPath . $file->public_path;
        $path_parts = pathinfo($filePath);

        if (strtolower($path_parts['extension']) != $fileType) {
            $this->addError(39);
            return $this->_returnJsonViewModel(array());
        }

        try {
            switch ($fileType) {
                case 'xls':
                    $importer = $this->getServiceLocator()->get('ContentMigration\Service\XlsToCompositeContent');
                    break;
                case 'csv':
                default:
                    $importer = $this->getServiceLocator()->get('ContentMigration\Service\CsvToCompositeContent');
                    break;
            }

            $importer->init(array('composite' => $id, 'filePath' => $filePath));

            return $this->_returnJsonViewModel(array(
                'fileColumns'      => $importer->getFileColumns(),
                'compositeColumns' => $importer->getContentAdapter()->getReadableColumns(),
                'requiredColumns'  => $importer->getContentAdapter()->getRequiredColumns()
            ));
        } catch (\Exception $e) {
            return $this->problemResponse(array(
                'title' => 'Exception',
                'detail' => $e->getMessage(),
            ), 503);
        }
    }
}
