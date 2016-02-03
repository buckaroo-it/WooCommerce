<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of Logger
 *
 * @author s.cigankovs
 */
require_once dirname(__FILE__).'/../config.php';
class BuckarooCoreLogger {
    //put your code here
    
    
    const DEBUG = '0';
    const INFO = '1';
    const WARN = '2';
    const ERROR = '3';

    static public $log_level = array(
        self::DEBUG => 'Debug',
        self::INFO => 'Info',
        self::WARN => 'Warning',
        self::ERROR => 'Error',
    );
    private $level = self::DEBUG;
    private $filename = 'logger';
    private $logtype = 'api';

    function __construct($level, $filename = 'logger') {
        $this->level = $level;
        $this->filename = $filename;
    }

    private function logEvent($info, $level, $descr = null) {

        if (BuckarooConfig::LOG && $level >= $this->level) {

            $file = fopen(dirname(__FILE__) . '/../api'.BuckarooConfig::LOG_DIR . $this->logtype.'-'.$this->filename.'-log-' . date('Y-m-d') . '.txt', 'a');
            $prefix = self::$log_level[$level] . ' ' . date('Y-m-d h:i:s') . ' ';
            $info_str = $info;
            if (!is_null($descr)) {

                if (is_object($descr) || is_array($descr)) {
                    $descr = print_r($descr, true);
                }
                $info_str .= "\nDescription:\n" . $descr . "\n";
            }
            fwrite($file, $prefix . $info_str."\n");
            fclose($file);
        }
    }

    private function logUserEvent($info) {

        $file = fopen(dirname(__FILE__) . '/../api'.BuckarooConfig::LOG_DIR .'report_log.txt', 'a');
        $prefix = date('Y-m-d h:i:s') . '|||';
        fwrite($file, $prefix . $info."\n");
        fclose($file);
    }

    
    public function logDebug($info, $descr = null)
    {     
        $this->logEvent($info, self::DEBUG, $descr);
    }
    
    public function logError($info, $descr = null)
    {     
        $this->logEvent($info, self::ERROR, $descr);
    }

    public function logForUser($info)
    {
        $this->logUserEvent($info);
    }
    
    public function logWarn($info, $descr = null)
    {     
        $this->logEvent($info, self::WARN, $descr);
    }
    
    public function logInfo($info, $descr = null)
    {     
        $this->logEvent($info, self::INFO, $descr);
    }   
}