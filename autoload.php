<?php 

define("ROOT", $_SERVER['DOCUMENT_ROOT']);
define("APP_DIR", ROOT."/TicTacToeServer");

function pathof(string $path)  {
    
    return ROOT.'/'. APP_DIR.'/'.$path;
}
