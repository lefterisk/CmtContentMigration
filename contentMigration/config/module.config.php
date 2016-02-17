<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/ZendSkeletonApplication for the canonical source repository
 * @copyright Copyright (c) 2005-2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

return array(
    'router' => array(
        'routes' => array(
            'cms-api' => array(
                'type'    => 'Literal',
                'options' => array(
                    'route'    => '/cms-api',
                    'defaults' => array(
                        '__NAMESPACE__' => 'ContentMigration\Controller',
                        'controller'    => 'Index',
                    ),
                ),
                'may_terminate' => true,
                'child_routes' => array(
                    'content-export' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/export/:id',
                            'defaults' => array(
                                'controller' => 'Export',
                            ),
                            'constraints' => array(
                                'id' => '[0-9]+',
                            )
                        ),
                    ),
                    'content-import' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/import/:id',
                            'defaults' => array(
                                'controller' => 'Import',
                            ),
                            'constraints' => array(
                                'id' => '[0-9]+',
                            )
                        ),
                    )
                ),
            ),
        ),
    ),
    'service_manager' => array(
        'abstract_factories' => array(
            'Zend\Cache\Service\StorageCacheAbstractServiceFactory',
            'Zend\Log\LoggerAbstractServiceFactory',
        ),
        'factories' => array(
            'ContentMigration\Service\CompositeContent'      => 'ContentMigration\Factory\CompositeContentServiceFactory',
            'ContentMigration\Service\CompositeContentToCsv' => 'ContentMigration\Factory\CompositeContentToCsvServiceFactory',
            'ContentMigration\Service\CompositeContentToXls' => 'ContentMigration\Factory\CompositeContentToXlsServiceFactory',
            'ContentMigration\Service\CsvToCompositeContent' => 'ContentMigration\Factory\CsvToCompositeContentServiceFactory',
            'ContentMigration\Service\XlsToCompositeContent' => 'ContentMigration\Factory\XlsToCompositeContentServiceFactory',
        )
    ),
    'controllers' => array(
        'invokables' => array(
            'ContentMigration\Controller\Export'       => 'ContentMigration\Controller\ExportController',
            'ContentMigration\Controller\Import'       => 'ContentMigration\Controller\ImportController',
        ),
    ),
    // Placeholder for console routes
    'console' => array(
        'router' => array(
            'routes' => array(
            ),
        ),
    ),
    'view_manager' => array(
        'display_not_found_reason' => true,
        'display_exceptions'       => true,
        'strategies' => array(
            'ViewJsonStrategy',
        ),
    )
);
