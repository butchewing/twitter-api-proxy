<?php 
require_once 'config.php';
require_once 'lib/twitter/twitteroauth.php';
 
function getConnectionWithAccessToken($cons_key, $cons_secret, $oauth_token, $oauth_token_secret) {
  $connection = new TwitterOAuth($cons_key, $cons_secret, $oauth_token, $oauth_token_secret);
  return $connection;
}

function remove_querystring_var($url, $key) { 
	$url = preg_replace('/(.*)(?|&)' . $key . '=[^&]+?(&)(.*)/i', '$1$2$4', $url . '&'); 
	$url = substr($url, 0, -1); 
	return $url; 
}

$connection = getConnectionWithAccessToken($consumerkey, $consumersecret, $accesstoken, $accesstokensecret);

$tweets = $connection->get('https://api.twitter.com/1.1/statuses/user_timeline.json?' . remove_querystring_var($_SERVER['QUERY_STRING'], 'format'));

$format = '';
if ((isset($_GET['format'])) && (!empty($_GET['format']))) {
    $format = $_GET['format'];
}

if ( $format === "xml" ) {
	// Send the xml header
	header('Content-Type: application/xml; charset=utf-8');
	$xml = '<?xml version="1.0" encoding="UTF-8"?>'.PHP_EOL;
	$xml.= '<channel>'.PHP_EOL;
	$xml.= '  <title>TAP - twitter-api-proxy</title>'.PHP_EOL;
	$xml.= '  <description>Twitter API Proxy with added XML response</description>'.PHP_EOL;
	$xml.= '  <link>'.'http://'.$_SERVER["SERVER_NAME"].'</link>'.PHP_EOL;

	// Get the status
	foreach ($tweets as $tweet) {
	  // Build the tweet url as we don't get this in the status object
	  $url = 'https://twitter.com/'.$tweet->user->screen_name.'/status/'.$tweet->id_str;
	  $date = explode(' ', $tweet->created_at);
	  
	  $xml.= '  <item>'.PHP_EOL;
	  $xml.= '    <title/>'.PHP_EOL;
	  $xml.= '    <description>'.$tweet->text.'</description>'.PHP_EOL;
	  $xml.= '    <link>'.$url.'</link>'.PHP_EOL;
	  $xml.= '    <guid ispermalink="true">'.$url.'</guid>'.PHP_EOL;
	  $xml.= '    <pubdate>'.$date[0].', '.$date[2].' '.$date[1].' '.$date[5].' '.$date[3].' '.$date[4].'</pubdate>'.PHP_EOL;
	  // Get the attached media
	  if ($tweet->entities) {
	    if (isset($tweet->entities->media)) {
	      foreach ($tweet->entities->media as $media) {
	        switch ($media->type) {
	          //Currently only photo's supported but I suspected with vine video will be along soon
	          case 'photo':
	            $enc_type = 'image/jpeg';
	            break;
	        }
	        if (!empty($enc_type)) {
	          // We need the file size for the media so try to get this from the headers
	          $headers = get_headers($media->media_url);
	          $size = '';//$headers['Content-Length'];
	          if (empty($size)) {
	            foreach ($headers as $header) {
	              $h = explode(':', $header);
	              if ($h[0] == 'Content-Length') {
	                $size = trim($h[1]);
	                break;
	              }
	            }
	          }
	          if (empty($size)) {
	            $size = 1; //This is basically a hack to make the rss validate
	          }
	          $xml.= '    <enclosure length="'.$size.'" type="'.$enc_type.'" url="'.$media->media_url.'"></enclosure>'.PHP_EOL;
	        }
	      }
	    }
	  }
	  $xml.= '  </item>'.PHP_EOL;
	}
	$xml.= '</channel>'.PHP_EOL;

	// Return the xml for the rss
	print $xml;
} else {
	echo json_encode($tweets);
}