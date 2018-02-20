<?php
/**
 * Entry point for rendering.
 *
 * @copyright 2014-2016 Roman Parpalak
 * @license   http://www.opensource.org/licenses/mit-license.php MIT
 * @package   S2 Latex Service
 * @link      http://tex.s2cms.ru
 */

require '../vendor/autoload.php';
require '../config.php';

$isDebug = defined('DEBUG') && DEBUG;
error_reporting($isDebug ? E_ALL : -1);

if (!defined('LATEX_BINARY')) {
    define('LATEX_BINARY', realpath(TEX_PATH) . '/latex');
}
if (!defined('DVISVGM_BINARY')) {
    define('DVISVGM_BINARY', realpath(TEX_PATH) . '/dvisvgm');
}
if (!defined('DVIPNG_BINARY')) {
    define('DVIPNG_BINARY', realpath(TEX_PATH) . '/dvipng');
}
if (!defined('SVGO_BINARY')) {
    define('SVGO_BINARY', realpath(SVGO_PATH) . '/svgo');
}
if (!defined('GZIP_BINARY')) {
    define('GZIP_BINARY', 'gzip');
}
if (!defined('OPTIPNG_BINARY')) {
    define('OPTIPNG_BINARY', 'optipng');
}
if (!defined('PNGOUT_BINARY')) {
    define('PNGOUT_BINARY', 'pngout');
}

// Setting up external commands
define('LATEX_COMMAND', LATEX_BINARY . ' -output-directory=' . TMP_DIR);
define('DVISVG_COMMAND', DVISVGM_BINARY . ' %1$s -o %1$s.svg -n --exact -v0 --relative --zoom=' . OUTER_SCALE);
// define('DVIPNG_COMMAND', DVIPNG_BINARY . ' -T tight %1$s -o %1$s.png -D ' . (96 * OUTER_SCALE)); // outdated
define('SVG2PNG_COMMAND', 'rsvg-convert %1$s.svg -d 96 -p 96 -b white'); // stdout

define('SVGO_COMMAND', SVGO_BINARY . ' -i %1$s -o %1$s.new; rm %1$s; mv %1$s.new %1$s');
define('GZIP_COMMAND', GZIP_BINARY . ' -cn6 %1$s > %1$s.gz.new; rm %1$s.gz; mv %1$s.gz.new %1$s.gz');
define('OPTIPNG_COMMAND', OPTIPNG_BINARY . ' %1$s');
define('PNGOUT_COMMAND', PNGOUT_BINARY . ' %1$s');

function error400($error = 'Invalid formula')
{
	header($_SERVER['SERVER_PROTOCOL'] . ' 400 Bad Request');
	include '400.php';
}


//ignore_user_abort();
ini_set('max_execution_time', 10);
header('X-Powered-By: S2 Latex Service');

$templater = new \S2\Tex\Templater(TPL_DIR);

$renderer = new \S2\Tex\Renderer($templater, TMP_DIR, LATEX_COMMAND, DVISVG_COMMAND);
$renderer
	->setSVG2PNGCommand(SVG2PNG_COMMAND)
	->setIsDebug($isDebug)
;
if (defined('LOG_DIR')) {
	$renderer->setLogger(new \Katzgrau\KLogger\Logger(LOG_DIR));
}

$processor = new \S2\Tex\Processor($renderer, CACHE_SUCCESS_DIR, CACHE_FAIL_DIR);
$processor
	->addSVGCommand(SVGO_COMMAND)
	->addSVGCommand(GZIP_COMMAND)
	->addPNGCommand(OPTIPNG_COMMAND)
	->addPNGCommand(PNGOUT_COMMAND)
;

try {
	$processor->parseURI(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
}
catch (Exception $e) {
	error400($isDebug ? $e->getMessage() : 'Invalid formula');
	die;
}

if ($processor->prepareContent()) {
	$processor->echoContent();
}
else {
	error400($isDebug ? $processor->getError() : 'Invalid formula');
}

if (!$isDebug) {
	$processor->saveContent();
}
