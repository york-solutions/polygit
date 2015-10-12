<?php

// you must provide `config.php` which defines
// $github_token = <GITHUB API TOKEN>

require_once('config.php');

// use a memcache to memoize github API (version) data

if (defined('Memcache')) {
  $mem = new Memcache;
} else {
  class cacheStub {
      function get() {
        return null;
      }
      function set() {
      }
  }
  $mem = new cacheStub();
}

// support CORS

header('Access-Control-Allow-Origin: *');
header("polygit-hello: hello");

// grab up the money bits of the url

$path = $_SERVER['REQUEST_URI'];
$parts = explode('/', $path);

$ORG = '';
$VERSION = '';

// extract component path

$path = array();
while (count($parts)) {
  $part = array_pop($parts);
  if ($part === 'components') {
    break;
  }
  array_unshift($path, $part);
}

$COMPONENT = array_shift($path);
$IMPORT = implode('/', $path);

// snarf up config from rest of url

$CONFIG = array();
$l = count($parts);
while ($l--) {

  $data = array_pop($parts);
  //echo "[$data]<br>";

  $data = explode('+', $data);
  //echo "<pre>";
  //print_r($data);
  //echo "</pre>";

  $ver = array_pop($data);
  $org = (count($data) > 1) ? array_pop($data) : '';
  $comp = array_pop($data);

  //echo "testing [$comp] against [$COMPONENT]<br>";
  // shell wildcard matching
  if (fnmatch($comp, $COMPONENT)) {
    //echo "matched [$comp] to [$COMPONENT]<br>";
    $VERSION = $ver;
    $ORG = $org;
    break;
  }
}

// try to determine $ORG if not configured

if (!$ORG) {
  if ($COMPONENT == 'polymer' || $COMPONENT == 'hydrolysis' || $COMPONENT == 'web-component-tester') {
    $ORG = 'Polymer';
  } else if ($COMPONENT == 'promise-polyfill') {
    $ORG = 'PolymerLabs';
  } else if ($COMPONENT == 'marked' || $COMPONENT == 'prism' || $COMPONENT == 'iron-component-page') {
    $ORG = 'sjmiles';
  } else if (prefix($COMPONENT, 'webcomponents')) {
    $ORG = 'webcomponents';
  } else if (prefix($COMPONENT, 'web-animations-js')) {
    $ORG = 'web-animations';
  } else if (prefix($COMPONENT, 'google-')) {
    $ORG = 'GoogleWebComponents';
  } else {
    $ORG = 'PolymerElements';
  }
}

//
// desugar $VERSION
//

if (@$VERSION{0} === ':') {
  $BRANCH = substr($VERSION, 1);
  $info = github("repos/$ORG/$COMPONENT/git/refs/heads/$BRANCH");
  $VERSION = $info->object->sha;
}

if (!$VERSION || $VERSION == '*') {
  // most recent RELEASE
  $info = github("repos/$ORG/$COMPONENT/releases/latest");
  $VERSION = $info->tag_name;
}

if (!$VERSION) {
  $BRANCH = 'master';
  $info = github("repos/$ORG/$COMPONENT/git/refs/heads/$BRANCH");
  $VERSION = $info->object->sha;
  header("polygit-version: defaulting to [:master]");
}

// supply info
header("polygit-url: $ORG/$COMPONENT/$VERSION/$IMPORT");

//
// proxy actual bytes from cdn.rawgit
//
$data = rawgit("$ORG/$COMPONENT/$VERSION/$IMPORT");

// parse the response headers
$res = parseHeaders($rawgit_response);

// handle 404
if ($res['code'] == 404) {
  header($res[0]);
  echo "<!-- $ORG/$COMPONENT/$VERSION/$IMPORT -->\n";
} else {
  // forward content-type
  $type = $res['Content-Type'];
  header("Content-Type: $type");
  // forward byte stream
  echo $data;
}

//
// utilities
//

function github($url) {
  global $github_token, $git_response, $mem;
  //
  $contents = $mem->get($url);
  //
  if (!$contents) {
    $cmd = "https://api.github.com/$url?access_token=$github_token";
    //
    $contents = @file_get_contents($cmd, false,
      stream_context_create(array('http'=>array('header'=>"User-Agent: Polymer-Magic-Server\r\n"))));
    $github_response = $http_response_header;
    //
    $mem->set($url, $contents, 0, 60*60);
    //
    header("polygit-github-info: lookup [$url]");
  } else {
    header("polygit-github-info: cached [$url]");
  }
  //
  return json_decode($contents);
}

function rawgit($path) {
  global $rawgit_response;
  //
  $contents = @file_get_contents("https://cdn.rawgit.com/$path", false,
    stream_context_create(array('http'=>array('header'=>"User-Agent: Polymer-Magic-Server\r\n"))));
  $rawgit_response = $http_response_header;
  //
  return $contents;
}

function prefix($haystack, $needle) {
  return strncmp($haystack, $needle, strlen($needle)) === 0;
}

function parseHeaders($headers) {
  $head = array();
  foreach( $headers as $k=>$v ) {
    $t = explode( ':', $v, 2 );
    if(isset($t[1])) {
      $head[ trim($t[0]) ] = trim( $t[1] );
    } else {
      $head[] = $v;
      if( preg_match( "#HTTP/[0-9\.]+\s+([0-9]+)#",$v, $out ) )
        $head['code'] = intval($out[1]);
    }
  }
  return $head;
}

?>