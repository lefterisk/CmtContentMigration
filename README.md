# Content import/export experiment
Experiment for Ivy 2 Content management toolkit

## Dependencies

### Required
- ZF2
- Ivy 2
- PHPExcel

## Installation

In your existing ZF2 project in the composer.json add
```
"repositories": [
    ....
    {
        "type": "vcs",
        "url": "https://github.com/lefterisk/CmtContentMigration"
    }
    ....
],
"minimum-stability": "dev",
"require": {
    .....
    "acknet/lefterisk" :"dev-master"
    .....
}
```

######application.config.php
```
'modules' => array(
    //.......
    //Add these
    'CmsUi',
    'CmsAdmin',
    'AssetManager',
    'PhinxModule'
    'ContentMigration' // <---- Add this
),
```