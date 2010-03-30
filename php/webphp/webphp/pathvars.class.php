<?php
/**
* @package SPLIB
* @version $Id: pathvars.class.php,v 1.1.1.1 2004/12/18 12:46:13 gareth Exp $
*/
/**
* PathVars Class
* Extracts scalar variables from URL like<br />
* http://localhost/index.php/these/are/variables/
* <code>
* // Url is http://localhost/index.php/forum/view/1
* $pathVars = new PathVars('index.php');
* echo ( $pathVars->fetchByIndex(1) ); // Displays 'view'
* </code>
* @access public
* @package SPLIB
*/
class PathVars {
    /**
    * The baseUrl of the script receiving the URL
    * @access private
    * @var string
    */
    var $baseUrl;

    /**
    * The fragment of the URL after the baseUrl
    * @access private
    * @var string
    */
    var $fragment;

    /**
    * The variables extracted from the fragment
    * @access private
    * @var string
    */
    var $pathVars;

    /**
    * PathVars constructor
    * @param string receiving script url
    * @access public
    */
    function PathVars ($baseUrl) {
        $this->baseUrl=$baseUrl;
        $this->pathVars=array();
        $this->fetchFragment();
        $this->parseFragment();
    }

    /**
    * Strips out $this->baseUrl from $_SERVER['REQUEST_URI']
    * @return void
    * @access private
    */
    function fetchFragment () {
        if ( !strstr($_SERVER['REQUEST_URI'],$this->baseUrl) )
            trigger_error ('$baseUrl is invalid: '.$this->baseUrl );
        if ( $this->baseUrl != '/' )
            $this->fragment=str_replace($this->baseUrl,'',$_SERVER['REQUEST_URI']);
        else
            $this->fragment=$_SERVER['REQUEST_URI'];
    }

    /**
    * Parses the fragment into variables
    * @return void
    * @access private
    */
    function parseFragment () {
        if ( strstr ($this->fragment,'/') ) {
            $vars=explode('/',$this->fragment);
            foreach ($vars as $var) {
                if ( $var == '' )
                    continue;
                if ( strstr ($var,'=') )
                    continue;
                if ( strstr ($var,'&') )
                    continue;
                if ( strstr ($var,'?') )
                    continue;
                $this->pathVars[]=$var;
            }
        }
    }

    /**
    * Iterator for path vars
    * @return mixed
    * @access public
    */
    function fetch () {
        $var = each ( $this->pathVars );
        if ( $var ) {
            return $var['value'];
        } else {
            reset ( $this->pathVars );
            return false;
        }
    }

    /**
    * Returns $this->pathVars
    * @return array
    * @access public
    */
    function fetchAll () {
        return $this->pathVars;
    }

    /**
    * Return a value from $this->pathVars given it's index
    * @param int the index of this->pathVars to return
    * @return string
    * @access public
    */
    function fetchByIndex ($index) {
        if ( isset ($this->pathVars[$index]) )
            return $this->pathVars[$index];
        else
            return false;
    }
    /**
    * Returns the number of variables found
    * @return int
    * @access public
    */
    function size () {
        return count ( $this->pathVars );
    }
}
?>
