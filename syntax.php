<?php 
/** 
 * Include Plugin: displays a wiki page within another 
 * Usage: 
 * {{randominc>namespace}} to random include a page from "namespace"
 * {{randomincsec>namespace}} see Include plugin
 * 
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html) 
 * @author     Esther Brunner <wikidesign@gmail.com>
 * @author     Christopher Smith <chris@jalakai.co.uk>
 */ 
 
if(!defined('DOKU_INC')) define('DOKU_INC',realpath(dirname(__FILE__).'/../../').'/'); 
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/'); 
require_once(DOKU_PLUGIN.'syntax.php'); 
  
/** 
 * All DokuWiki plugins to extend the parser/rendering mechanism 
 * need to inherit from this class 
 */ 
class syntax_plugin_randominc extends DokuWiki_Syntax_Plugin { 
 
  function getInfo(){ 
    return array( 
      'author' => 'Vittorio Rigamonti',
      'email'  => 'rigazilla at gmail dot com',
      'date'   => '2007-12-20',
      'name'   => 'Random Include Plugin (helper class)',
      'desc'   => 'Functions to randomically include another page in a wiki page',
      'url'    => '',
    ); 
  } 
  
  function getType(){ return 'substition'; }
  function getSort(){ return 303; }
  function getPType(){ return 'block'; }
  
  function connectTo($mode){  
    $this->Lexer->addSpecialPattern("{{randominc>.+?}}", $mode, 'plugin_randominc');  
    $this->Lexer->addSpecialPattern("{{randomincsec>.+?}}", $mode, 'plugin_randominc'); 
  } 
 
  function handle($match, $state, $pos, &$handler){
  
    $match = substr($match, 2, -2); // strip markup
    list($match, $flags) = explode('&', $match, 2);
 
    // break the pattern up into its constituent parts 
    list($include, $id, $section) = preg_split('/>|#/u', $match, 3); 
    return array($include, $id, cleanID($section), explode('&', $flags)); 
  }

  function _randompage($ns) {
  require_once(DOKU_INC.'inc/search.php');
  global $conf;
  global $ID;
 
  $dir = $conf['datadir'];
  $ns  = cleanID($ns);
 
 
  #fixme use appropriate function
  if(empty($ns)){
    $ns = dirname(str_replace(':','/',$ID));
    if($ns == '.') $ns ='';
  }


 $dir = $conf['datadir'].'/'.str_replace(':','/',$ns);
 $ns = str_replace('/',':',$ns);

 
 
  $data = array();
  search($data,$dir,'search_allpages',array('ns' => $ns));
 
  $page = $data[array_rand($data, 1)][id];
  return $page;
 
}
 
//Function from Php manual to get  a random number in a Array
    function array_rand($array, $lim=1) {
        mt_srand((double) microtime() * 1000000);
        for($a=0; $a<=$lim; $a++){
            $num[] = mt_srand(0, count($array)-1);
        }
        return @$num;
    }

  function render($mode, &$renderer, $data){
    global $ID;
 
    list($type, $raw_id, $section, $flags) = $data; 
 
    $id = $this->_applyMacro($raw_id);
    
    // prevent caching if macros were used or some users are not allowed to read the page
    $nocache = ($id != $raw_id);
     
    resolve_pageid(getNS($ID), $id, $exists); // resolve shortcuts
    $ns=getNS($id.':dummy');    
    // load the include class
    $include =& plugin_load('helper', 'randominc');
    
    $include->setMode($type);
    $include->setFlags($flags);
    $a_page=$this->_randompage($ns);
    $an_id=$ns.':'.$a_page;
    resolve_pageid(getNS($ID), $an_id, $exists); // resolve shortcuts
    $ok = $include->setPage(array(
      'id'      => $an_id,
      'section' => $section,
      'exists'  => $exists,
    ));
    if (!$ok) return false; // prevent recursion
    
    $nocache = ($nocache || (auth_aclcheck($an_id, '', array()) < AUTH_READ));

    if ($mode == 'xhtml'){
      if ($nocache) $renderer->info['cache'] = false;

    
      // current section level
      $clevel = 0;
      preg_match_all('|<div class="level(\d)">|i', $renderer->doc, $matches, PREG_SET_ORDER);
      $n = count($matches) - 1;
      if ($n > -1) $clevel = $matches[$n][1];
      $include->setLevel($clevel);
      // close current section
      if ($clevel && ($type == 'section'))
        $renderer->doc .= '</div>';
      // include the page now
      $include->renderXHTML($renderer);
      
      // resume current section
      if ($clevel && ($type == 'section'))
        $renderer->doc .= '<div class="level'.$clevel.'">';
      
      return true;
       
    // for metadata renderer
    } elseif ($mode == 'metadata'){
      if (!$flg_macro) $renderer->meta['relation']['haspart'][$an_id] = $exists;
      $include->pages = array(); // clear filechain - important!

      return true;
    }
 
    return false;  
  }

/* ---------- Util Functions ---------- */
    
  /**
   * Makes user or date dependent includes possible
   */
  function _applyMacro($id){
    global $INFO, $auth;
    
    $user     = $_SERVER['REMOTE_USER'];
    $userdata = $auth->getUserData($user);
    $group    = $userdata['grps'][0];
 
    $replace = array( 
      '@USER@'  => cleanID($user), 
      '@NAME@'  => cleanID($INFO['userinfo']['name']),
      '@GROUP@' => cleanID($group),
      '@YEAR@'  => date('Y'), 
      '@MONTH@' => date('m'), 
      '@DAY@'   => date('d'), 
    ); 
    return str_replace(array_keys($replace), array_values($replace), $id); 
  }
          
}

//Setup VIM: ex: et ts=4 enc=utf-8 :
