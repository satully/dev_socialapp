<?php
require_once("Net/UserAgent/Mobile.php");
define("GMAP_API_KEY","ABQIAAAAj3IHIoLSRmyzSz8pW3AHbBSh7JF37mP6LSEerb9hwzyNHc72dhTbIovkd3xnLdbgVCtxZw0swtjeog");

$agent = &Net_UserAgent_Mobile::singleton();
switch( true )
       {
       case ($agent->isDoCoMo()):   // DoCoMoかどうか
           echo "DoCoMoだよ。";
           break;
       case ($agent->isVodafone()): // vodafoneかどうか
           echo "vodafoneだよ。";
           break;
       case ($agent->isEZweb()):    // ezwebかどうか
           echo "ezwebだよ。";
           break;
       default:
           echo "たぶんパソコン。";
           break;
       }
?>
<img src="http://maps.google.com/staticmap?center=43.068527,141.350806
&zoom=15&size=220x220&maptype=mobile&markers=43.068367,141.347646,
redy%7C43.067402,141.352697,blueb&key=<?php echo(GMAP_API_KEY); ?>" />
