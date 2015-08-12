<?php

function github($url) {
  $url = "https://api.github.com/$url?access_token=7fcc884cd58afa4f9a1a4fe2a6777cb509e4581d";
  //
  $context = stream_context_create(
    array('http'=>array(
      'header'=>"User-Agent: Polymer-Magic-Server\r\n"
    ))
  );
  $contents = file_get_contents($url, false, $context);
  //
  return $contents;
}

echo github('rate_limit');

?>