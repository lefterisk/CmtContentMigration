<?php
namespace ContentMigration\Controller;

use ContentMigration\TableGateway\PermissionTable;
use Zend\View\Model\JsonModel;

class ExportController extends AbstractRestfulJsonController
{
    protected $availableMethods = array('OPTIONS', 'UPDATE');

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
        if (!$this->isAuthorised(PermissionTable::ASSET_TYPE_CONTEXT, PermissionTable::ACTION_CREATE)) {
            return $this->notAuthorisedResponse();
        }

        $formData   = $this->_getParamsFromJsonRequestPayload();
        $config     = $this->getServiceLocator()->get('config');
        $exportPath = $config['file_manager']['public_dir'] . '/media/exports';

        try {
            switch ($formData['exportType']) {
                case 'xls':
                    $exporter = $this->getServiceLocator()->get('ContentMigration\Service\CompositeContentToXls');
                    break;
                case 'csv':
                default:
                    $exporter = $this->getServiceLocator()->get('ContentMigration\Service\CompositeContentToCsv');
                    break;
            }

            $exporter->init(array('composite' => $id,'exportDirectory' => $exportPath));
            $exporter->generateExport();

            return $this->_returnJsonViewModel(array('composite' => $id, 'data' => $formData));

        } catch (\Exception $e) {
            return $this->problemResponse(array(
                'title' => 'Exception',
                'detail' => $e->getMessage(),
            ), 503);
        }
    }
}
