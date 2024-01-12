<?php
declare(strict_types=1);

require_once './vendor/autoload.php';
require_once './vendor-bin/cs-fixer/vendor/autoload.php';

use Nextcloud\CodingStandard\Config;

$config = new Config();
$config
	->getFinder()
	->notPath('build')
	->notPath('l10n')
	->notPath('src')
	->notPath('vendor')
	->notPath('js')
	->notPath('node_modules')
	->in(__DIR__);
return $config;
