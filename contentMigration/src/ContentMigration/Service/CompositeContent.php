<?php
namespace ContentMigration\Service;

use CmsAdmin\Core\Column\AbstractColumnType;
use CmsAdmin\Core\Composite\ContentTable;
use CmsAdmin\Core\Composite\DBManager;
use CmsAdmin\Core\Composite\Record;
use CmsAdmin\Core\Content\AbstractContentElement;
use CmsAdmin\Core\ElasticSearch\ElasticSearch;
use CmsAdmin\Core\Utility;
use CmsAdmin\Factory\TableFactory;
use CmsAdmin\Hydrator\CompositeContentHydrator;
use CmsAdmin\Model\Composite as StructureComposite;
use CmsAdmin\Core\Composite\Composite as ContentComposite;
use Zend\Db\Adapter\Adapter;
use Zend\Db\ResultSet\ResultSet;
use Zend\Db\Sql\Predicate\Expression;
use Zend\Db\Sql\Select;
use Zend\Json\Decoder;
use Zend\Json\Json;
use Zend\Paginator\Adapter\DbSelect;
use Zend\Paginator\Paginator;

/**
 * Class CompositeContent
 * @package ContentMigration\Service
 */
class CompositeContent implements ContentAdapterInterface
{
    /**
     * @var int
     */
    protected $_compositeId;

    /**
     * @var Adapter
     */
    protected $_dbAdapter;

    /**
     * @var array
     */
    protected $_globalConfig;

    /**
     * @var array
     */
    protected $_compositeConfig;

    /**
     * @var StructureComposite
     */
    protected $_compositeStructure;

    /**
     * @var ContentComposite
     */
    protected $_compositeContent;

    /**
     * @var ContentTable
     */
    protected $_compositeTable;

    /**
     * @var int
     */
    protected $_defaultContext;

    /**
     * @var array
     */
    protected $_contentValidators;

    /**
     * @var Record
     */
    protected $_record;

    /**
     * @var CompositeContentHydrator
     */
    protected $_compositeHydrator;

    /**
     * @param array $config
     * @param Adapter $dbAdapter
     */
    public function __construct(array $config, Adapter $dbAdapter)
    {
        $this->_globalConfig = $config;
        $this->_dbAdapter    = $dbAdapter;
    }

    /**
     * @param array $initOptions
     * @return void
     * @throws \Exception
     */
    public function init(array $initOptions = array())
    {
        $this->_compositeId = (array_key_exists('composite', $initOptions))
            ? $initOptions['composite']
            : null;

        if (!empty($this->_compositeId)) {
            if ($config = $this->_findConfig()) {
                $this->_compositeConfig = Decoder::decode($config, Json::TYPE_ARRAY);
            } else {
                $this->_compositeConfig = $this->_getCompositeConfig();
            }
        }
    }

    /**
     * @return array
     */
    protected function _getGlobalConfig()
    {
        return $this->_globalConfig;
    }

    /**
     * @return array
     */
    protected function _getCompositeConfig()
    {
        if (!empty($this->_compositeConfig)) {
            return $this->_compositeConfig;
        }

        $this->_getCompositeTable()->getDbManager()->writeTableConfig();

        $config = include($this->_getCompositeTable()->getDbManager()->configFullPath);
        $config = $config['cmsComposites'][0];

        $this->_compositeConfig = Decoder::decode($config['relations'], Json::TYPE_ARRAY);

        return $this->_compositeConfig;
    }

    /**
     * @return ContentTable
     * @throws \Exception
     */
    protected function _getCompositeTable()
    {
        if ($this->_compositeTable instanceof ContentTable) {
            return $this->_compositeTable;
        }

        $this->_compositeTable = new ContentTable();
        $this->_compositeTable->setDbManager(new DBManager($this->getDbAdapter()));
        $this->_compositeTable->init($this->_getCompositeStructure());

        return $this->_compositeTable;
    }

    /**
     * @return CompositeContentHydrator
     */
    protected function _getCompositeHydrator()
    {
        if ($this->_compositeHydrator instanceof CompositeContentHydrator) {
            return $this->_compositeHydrator;
        }

        $this->_compositeHydrator = new CompositeContentHydrator($this->_getCompositeTable()->getDbManager());

        return $this->_compositeHydrator;
    }

    /**
     * @return int
     */
    protected function _getDefaultContext()
    {
        if (!empty($this->_defaultContext))
            return $this->_defaultContext;

        $tableFactory = new TableFactory($this->getDbAdapter());
        $contextTable = $tableFactory->create('Context');

        return $contextTable->getDefaultContext();
    }

    /**
     * @return StructureComposite
     * @throws \Exception
     */
    protected function _getCompositeStructure()
    {
        if ($this->_compositeStructure instanceof StructureComposite) {
            return $this->_compositeStructure;
        }

        $this->_compositeStructure = $this->_getComposite(false);

        if (!$this->_compositeStructure instanceof StructureComposite)
            throw new \Exception('Could not instantiate composite with id ' . $this->_compositeId);

        return $this->_compositeStructure;
    }

    /**
     * @return ContentComposite
     * @throws \Exception
     */
    protected function _getCompositeContent()
    {
        if ($this->_compositeContent instanceof ContentComposite) {
            return $this->_compositeContent;
        }

        $this->_compositeContent = $this->_getComposite(true);

        if (!$this->_compositeContent instanceof ContentComposite)
            throw new \Exception('Could not instantiate composite with id ' . $this->_compositeId);

        return $this->_compositeContent;
    }

    /**
     * @param bool $isContent
     * @return ContentComposite | StructureComposite
     */
    protected function _getComposite($isContent = false)
    {
        $tableFactory     = new TableFactory($this->getDbAdapter());
        $compositeFactory = $tableFactory->create('Composite');

        return $compositeFactory->getAssembled($this->_compositeId, $isContent);
    }

    /**
     * Find whether the composite specific config exists (merged in the global config)
     *
     * @return bool | array
     * @throws \Exception
     */
    protected function _findConfig()
    {
        $this->_requireInitialised(__METHOD__);

        if (
            array_key_exists('cmsComposites', $this->_getGlobalConfig())
            &&
            is_array( $this->_getGlobalConfig()['cmsComposites'])
        ) {
            foreach ($this->_getGlobalConfig()['cmsComposites'] as $composite) {
                if ($composite['id'] == $this->_compositeId)
                    return $composite['relations'];
            }
        }

        return false;
    }

    /**
     * @return bool
     */
    public function isInitialised()
    {
        if (empty($this->_compositeId) && is_array($this->_compositeConfig)) {
            return false;
        }

        return true;
    }

    /**
     * @return Adapter
     */
    public function getDbAdapter()
    {
        return $this->_dbAdapter;
    }

    /**
     * @return string
     * @throws \Exception
     */
    public function getContentName()
    {
        $this->_requireInitialised(__METHOD__);

        return Utility::slug($this->_compositeConfig['baseTable']['structureName']);
    }

    /**
     * @param array $options
     * @return Paginator
     * @throws \Exception
     */
    public function getRows(array $options = array())
    {
        $columns = array();
        if (array_key_exists('columns', $options) && is_array($options['columns']))
            $columns = $options['columns'];

            $this->_requireInitialised(__METHOD__);

        $select = new Select(array('base' => $this->getBaseTableName()));
        $select->group(array('base.id','base.context'));

        // If specific columns have been requested return only them
        if (!empty($columns))
            $select->columns($options['columns']);

        // Join multiple relation tables to the base-table with the related ids concatenated
        foreach ($this->getColumnDefinitions() as $columnDefinition) {
            // If specific columns have been requested and this not on of them continue
            if (!empty($columns) && !in_array($columnDefinition['name'], $columns)) continue;

            if ($columnDefinition['type'] == AbstractColumnType::COL_TYPE_RELATION_1_M) {
                $childStructure = $this->_getChildTableByStructureName($columnDefinition['name']);
                $select->join(
                    array($columnDefinition['name'] => $childStructure['name']),
                    '(' . $columnDefinition['name'] . '.fromComposite = base.id AND ' . $columnDefinition['name'] . '.context = base.context)',
                    array($columnDefinition['name'] => new Expression("GROUP_CONCAT(" . $columnDefinition['name'] . ".toComposite SEPARATOR ',')")),
                    Select::JOIN_LEFT
                );
            }
        }

        $resultSetPrototype = new ResultSet();
        // create a new pagination adapter object
        $paginationAdapter  = new DbSelect(
        // our configured select object
            $select,
            // the adapter to run it against
            $this->getDbAdapter(),
            // the result set to hydrate
            $resultSetPrototype
        );

        return new Paginator($paginationAdapter);
    }

    /**
     * @return string
     * @throws \Exception
     */
    public function getBaseTableName()
    {
        $this->_requireInitialised(__METHOD__);

        $config = $this->_getCompositeConfig();

        return $config['baseTable']['name'];
    }

    /**
     * @param $structureName
     * @return bool | array
     * @throws \Exception
     */
    protected function _getChildTableByStructureName($structureName)
    {
        $this->_requireInitialised(__METHOD__);

        $config = $this->_getCompositeConfig();

        if (!array_key_exists('children', $config['baseTable'])) return false;

        foreach ($config['baseTable']['children'] as $childStructureDefinition) {
            if ($childStructureDefinition['structureName'] == $structureName) {
                return $childStructureDefinition;
            }
        }

        return false;
    }

    /**
     * @return array
     * @throws \Exception
     */
    public function getColumnDefinitions()
    {
        $this->_requireInitialised(__METHOD__);

        return $this->_compositeConfig['baseTable']['columns'];
    }

    /**
     * @return array
     * @throws \Exception
     */
    public function getColumns()
    {
        $this->_requireInitialised(__METHOD__);

        $columns = array('id', 'parent', 'context');

        foreach ($this->_compositeConfig['baseTable']['columns'] as $columnDefinition) {
            $columns[] = $columnDefinition['name'];
        }

        return $columns;
    }

    /**
     * @param array $columns
     * @return array
     */
    public function getReadableColumns(array $columns = array())
    {
        $readableColumns = array();

        foreach ($this->getColumns() as $column) {
            if (!empty($columns) && !in_array($column, $columns)) continue;

            $readableColumns[$column] = $this->getReadableColumnName($column);
        }

        return $readableColumns;
    }

    /**
     * @param $columnName
     * @return string
     */
    public function getReadableColumnName($columnName)
    {
        return ucwords(
            implode(' ',
                Utility::breakCamelCase(
                    preg_replace('/_(.+)/', '', $columnName)
                )
            )
        );
    }

    /**
     * @param bool $forEdit
     * @return array
     * @throws \Exception
     */
    public function getRequiredColumns($forEdit = false)
    {
        $this->_requireInitialised(__METHOD__);

        $columns = array();

        if ($forEdit)
            $columns = array('id', 'parent', 'context');

        foreach ($this->_compositeConfig['baseTable']['columns'] as $columnDefinition) {

            if ($columnDefinition['required'] == 1)
                $columns[$columnDefinition['name']] = $this->getReadableColumnName($columnDefinition['name']);
        }

        return $columns;
    }

    /**
     * @return array
     * @throws \Exception
     */
    public function getContentValidators()
    {
        $this->_requireInitialised(__METHOD__);

        if (!empty($this->_contentValidators))
            return $this->_contentValidators;

        $validators = array(
            'id'      => array(
                'Zend\I18n\Validator\IsInt' => array(),
            ),
            'parent'  => array(
                'Zend\I18n\Validator\IsInt' => array(),
            ),
            'context' => array(
                'Zend\I18n\Validator\IsInt' => array(),
            ),
        );

        foreach ($this->_compositeConfig['baseTable']['columns'] as $columnDefinition) {
            $element = AbstractContentElement::getElementFromType($columnDefinition['type']);
            $validators[$columnDefinition['name']] = $element->getValidators();
        }

        $this->_contentValidators = $validators;

        return $this->_contentValidators;
    }

    /**
     * @return Record
     * @throws \Exception
     */
    public function getRecord()
    {
        if ($this->_record instanceof Record)
            return $this->_record;

        $this->_record = new Record($this->_getCompositeTable(), $this->_getCompositeContent());
        $this->_record->setElasticSearch(new ElasticSearch($this->_getGlobalConfig()));

        return $this->_record;
    }

    /**
     * @param array $data
     * @throws \Exception
     */
    public function save(array $data)
    {
        $this->_requireInitialised(__METHOD__);

        $record = clone $this->getRecord();

        $id      = null;
        $context = null;

        if (isset($data['id']))
            $id = $data['id'];

        if (isset($data['context']))
            $context = $data['context'];

        $populatedData    = $this->_populateDbData($record->fetchContent($id,$context), $data);
        $hydrator         = clone $this->_getCompositeHydrator();
        $contentComposite = $hydrator->hydrate($populatedData, $this->_getCompositeContent());

        $record->setContentComposite($contentComposite);
        $record->save();
    }

    /**
     * @param array $dbData
     * @param array $fileData
     * @return array
     */
    protected function _populateDbData(array $dbData, array $fileData)
    {
        $dbData  = $this->_populateBaseTableData($dbData, $fileData);
        $context = $dbData[$this->getBaseTableName()]['context'];

        foreach ($this->_getCompositeConfig()['baseTable']['children'] as $childStructure) {
            $tableName = $childStructure['name'];

            // if column is mapped then override multiple values and relations otherwise skip
            if (!array_key_exists($childStructure['structureName'], $fileData))
                continue;

            switch ($childStructure['type']) {
                case DBManager::RELATION_TYPE_MULTIPLE :
                    //@todo handle multiples
                    break;
                case DBManager::RELATION_TYPE_RELATED :
                    $contentId          = !empty($fileData['id']) ? $fileData['id'] : $this->getBaseTableName();
                    $dbData[$tableName] = $this->_getRelationTableData($childStructure, $contentId, $context, $fileData);

                    break;
            }
        }

        return $dbData;
    }

    /**
     * @param array $dbData
     * @param array $fileData
     * @return array
     */
    protected function _populateBaseTableData(array $dbData, array $fileData)
    {
        foreach ($this->_getCompositeConfig()['baseTable']['columns'] as $columnDefinition) {
            if ($columnDefinition['type'] != AbstractColumnType::COL_TYPE_RELATION_1_M && isset($fileData[$columnDefinition['name']]))
                $dbData[$this->getBaseTableName()][$columnDefinition['name']] = $fileData[$columnDefinition['name']];
        }

        if (isset($fileData['parent']))
            $dbData[$this->getBaseTableName()]['parent'] = $fileData['parent'];

        return $dbData;
    }

    /**
     * @param array $relationConfig
     * @param int | string $contentId might be a placeholder for insert purposes where no id is available yet
     * @param int $context
     * @param array $fileData
     * @return array
     */
    protected function _getRelationTableData(array $relationConfig, $contentId, $context, array $fileData)
    {
        $data = array();

        if (isset($fileData[$relationConfig['structureName']])) {
            $relatedIds = explode(',', $fileData[$relationConfig['structureName']]);
            $order      = 0;

            foreach ($relatedIds as $id) {
                $order++;
                $data[] = array(
                    'context'       => $context,
                    'fromComposite' => $contentId,
                    'toComposite'   => $id,
                    'order'         => $order
                );
            }
        }

        return $data;
    }

    /**
     * @param $method
     * @throws \Exception
     */
    private function _requireInitialised($method)
    {
        if (!$this->isInitialised())
            throw new \Exception('Initialise class' . __CLASS__ . ' before using method ' . $method);
    }
}