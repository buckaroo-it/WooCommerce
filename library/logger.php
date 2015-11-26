<?php
/**
 * Description of Logger
 *
 * @author s.cigankovs
 */
if (!class_exists('BuckarooLogger'))
{
    require_once dirname(__FILE__).'/api/corelogger.php';

    class BuckarooLogger extends BuckarooCoreLogger {

        private $logtype = 'plugin';

    }
};
?>
