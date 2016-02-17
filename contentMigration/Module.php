<?php
namespace ContentMigration;

/**
 * Class Module
 * @package ContentMigration
 */
class Module
{
    public function getConfig()
    {
        return include __DIR__ . '/config/module.config.php';
    }
}