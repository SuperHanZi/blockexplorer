<?php

include_once 'includes/bootstrap.inc';
include_once 'includes/http.inc';

function _unknownerror($errno,$errstr,$errfile,$errline) {
	//suppressed errors
	if(error_reporting()==0)
		return;
	
    header("Content-type: text/html");

	//clear all buffers
	for($i=ob_get_level();$i>0;$i--)
	{
		ob_end_clean();
	}
	senderror(500);

    echo "<h1>Unknown error</h1>";
    if(CONFIG_SHOW_ERRORS) {
        echo "$errstr in $errfile on line $errline";
    }

	error_log("BBE unknown error: $errstr in $errfile on line $errline");
	die();
	return true;
}
set_error_handler("_unknownerror",E_ALL^E_DEPRECATED^E_NOTICE);

class Request {
    public $page = "home";
    public $params = array();

    public $testnet = false; // testnet mode
    public $rts = false; // real-time stats

    private $path = null;
    private $query = null;

    private function parse_uri() {
        global $_SERVER;

        //set up variables for checking
        $fullpath=$_SERVER['REQUEST_URI'];
        $querystart=strpos($fullpath,"?");
        if($querystart === false) {
            $path=$fullpath;
            $query="";
        } else {
            $path=substr($fullpath,0,$querystart);
            $query="?".substr($fullpath,$querystart+1);
        }

        $this->path = $path;
        $this->query = $query;
    }

    private function redirect_canonical() {
        //redirect odd link to canonical hostname 
        global $_SERVER;

        if(!isset($_SERVER['HTTP_HOST'])) {
            return;
        }

        $senthost=$_SERVER['HTTP_HOST'];

        if(preg_match_all("/[a-zA-Z]/",$senthost,$junk) > 6 && $senthost != HOSTNAME) {
            redirect($this->path.$this->query, 301);
            die();
        }
    }

    private function redirect_trailing_slash() {
        //trailing slash

        $last=strlen($this->path)-1;
        if($last!=0 && substr($this->path,$last,1) == "/") {
            redirect(substr($this->path,0,$last).$this->query,301);
        }
    }


    private function fix_url() {
        if(REDIRECT_CANONICAL) {
            $this->redirect_canonical();
        }
        $this->redirect_trailing_slash();
    }

    function __construct() {
        $this->parse_uri();
        $this->fix_url();

        $path = trim($this->path, "/");

        function _notempty($var) {
            return !(empty($var) && $var !== 0 && $var !== "0");
        }
        $params = array_filter(explode("/", $path, 10), "_notempty");

        function _array_remove_item(&$array, $item) {
            $index = array_search($item, $array);
            if($index === false) {
                return false;
            }
            array_splice($array, $index, 1);
            return true;
        }

        if(!empty($params)) {
            if(_array_remove_item($params, "testnet")) {
                $this->testnet = true;
            }
            if(_array_remove_item($params, "q")) {
                $this->rts = true;
            }

            $this->page = $params[0];
            $this->params = array_map("urldecode", $params);
        }

        // sitemap special case
        if($this->page == "sitemap.xml") {

            $this->page = "sitemap";

        } elseif(preg_match("/^sitemap.+\.xml$/", $page)) {

            $matches=array();
            preg_match("/^sitemap-([tab])-([0-9]+)\.xml$/", $page, $matches);

            if(isset($matches[1])&&isset($matches[2]))
            {
                $this->params = array($matches[1], $matches[2]);
                $this->page="sitemap";
            }
        }
    }
}

$request = new Request();
header("Content-type: text/plain");
print_r($request);
die();

//clear away junk variables
unset($matches,$path,$query,$junk,$params,$count,$i,$number,$item);

//routing
if($rts&&$testnet)
{
	ini_set("zlib.output_compression","Off");
	require "includes/statx-testnet.php";
}
else if($rts)
{
	ini_set("zlib.output_compression","Off");
	require "includes/statx.php";
}
else if($testnet)
{
	require "includes/explore-testnet.php";
}
else
{
	require "includes/explore.php";
}
?>
