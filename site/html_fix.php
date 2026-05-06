<?php

//$text = file_get_contents("4305_DONNEWS1.SCL_hrust1_1.txt");


function html_fix($txt, $mode, $id) {

$new_txt = "";

$pos_from = 0;
$pos_to = 0;
$open = 0;
$close = 0;
$span = -1;
$lenght = mb_strlen($txt);

//echo $mode."<br>".$id;
//exit;

if ($mode) {




do {
  
  $s1 = mb_substr($txt, $pos_from, 1);
  $s2 = mb_substr($txt, $pos_from, 4);
  
  If ($s1 == "<") {
    
    if ($s2 == "<spa" or $s2 == "<div" or $s2 == "<img" or "</sp" or "</di") {
            
      $new_txt = $new_txt . $s1;
	  $open = 1;
	  
    }
	else {
	  
      $new_txt = $new_txt . "&#60;"; 
	  
    }
    
  }  
  ElseIf ($s1 == ">") {
    
    If ($open == 0) {
    
      $new_txt = $new_txt . "&#62;"; 
      
	}
    Else {
      
      $new_txt = $new_txt . $s1;
	  $open = 0;
	  
    }
    
  }  
  ElseIf ($s1 == "&" And $s2 <> "&amp" And $s2 <> "&#60" and $s2 <> "&#62" and $s2 <> "&#38") {
    
    $new_txt = $new_txt . "&amp;"; 
    
  }
  Else {
   
    $new_txt = $new_txt . $s1;
    
  }
 

$pos_from = $pos_from + 1;

}
while ($pos_from < $lenght);


$new_txt = mb_ereg_replace("<img align=\'top\'  src=\'", "<img align='top' SRC='illustrations/$id/", $new_txt); 


}
else {

do {
  
  $s1 = mb_substr($txt, $pos_from, 1);
  $s2 = mb_substr($txt, $pos_from, 3);
  
  If ($s1 == "<") {
    
    If (mb_substr($txt, $pos_from, mb_strlen("<span class='RGB")) == "<span class='RGB") {
      
      If ($span == 1) {
        
        $new_txt = $new_txt . "</span>";
		
        //echo "fix not close span<br>";
        
      }
      
      $new_txt = $new_txt . $s1;
	  $open = 1;
	  
    }
	
    ElseIf (mb_substr($txt, $pos_from, mb_strlen("</span>")) == "</span>") {
     
      If ($span == 0 Or $span == -1) {
      
        $pos_from = $pos_from + mb_strlen("</span>") - 1;
        //echo "fix not open span<br>";
		
	  }
      Else {   
        
        $new_txt = $new_txt . $s1;
        $close = 1; 
        
      }
      
	}
    ElseIf (mb_substr($txt, $pos_from, mb_strlen("<span>")) == "<span>") {
      
      $pos_from = $pos_from + mb_strlen("<span>") - 1;
    }
    Else {  
      
      $new_txt = $new_txt . "&#60;"; 
	  
    }
    
  }  
  ElseIf ($s1 == ">") {
    
    If ($open == 0 And $close == 0) {
    
      $new_txt = $new_txt . "&#62;"; 
      
	}
    ElseIf ($open == 1) {
      
      $span = 1;
      $new_txt = $new_txt . $s1;
	  $open = 0;
	  
    }
    ElseIf ($close == 1) {
      
      $span = 0;
      $new_txt = $new_txt . $s1;
	  $close = 0;
      
    }
   
  }  
  ElseIf ($s1 == "&" And $s2 <> "&am" And $s2 <> "&#6") {
    
    $new_txt = $new_txt . "&amp;"; 
    
  }
  Else {
   
    $new_txt = $new_txt . $s1;
    
  }


  

$pos_from = $pos_from + 1;

}
while ($pos_from < $lenght);


If ($open == 0 And $span == 1) {
  
  $new_txt = $new_txt . "</span>";
  
  //echo "fix not close span END";
  
}

}

$new_txt = str_replace( chr(13).chr(10).chr(13).chr(10).chr(13).chr(10).chr(13).chr(10).chr(13).chr(10).chr(13).chr(10), chr(13).chr(10).chr(13).chr(10), $new_txt);
$new_txt = str_replace( chr(13).chr(10).chr(13).chr(10).chr(13).chr(10).chr(13).chr(10).chr(13).chr(10).chr(13).chr(10).chr(13).chr(10).chr(13).chr(10).chr(13).chr(10), chr(13).chr(10).chr(13).chr(10), $new_txt);
$new_txt = str_replace( chr(13).chr(10).chr(13).chr(10).chr(13).chr(10).chr(13).chr(10).chr(13).chr(10), chr(13).chr(10).chr(13).chr(10), $new_txt);


return $new_txt;

}

/** @var int html_fix() mode: RGB / span-oriented pipeline (legacy article body). */
const HTML_LEGACY_FIX_MODE_RGB = 0;
/** @var int html_fix() mode: allow img/div/span (illustrated issues). */
const HTML_LEGACY_FIX_MODE_RICH = 1;

/**
 * Single entry for article/chapter HTML before storage. Wraps legacy html_fix().
 * Allowlist is implicit in html_fix() (RGB spans, or img/div/span when mode=RICH).
 *
 * @param int $legacyHtmlFixMode HTML_LEGACY_FIX_MODE_RGB or HTML_LEGACY_FIX_MODE_RICH
 * @param int $illustrationPathId Passed to html_fix as illustration path key (issue id in admin)
 */
function html_legacy_normalize(string $html, int $legacyHtmlFixMode, int $illustrationPathId = 0): string {
	static $warnLeft = 16;
	$out = html_fix($html, $legacyHtmlFixMode, $illustrationPathId);
	if ($warnLeft > 0 && $html !== '' && $html !== $out && str_contains($html, '<')) {
		error_log('[html_legacy_normalize] WARN legacy fix altered HTML mode=' . $legacyHtmlFixMode
			. ' len_in=' . strlen($html) . ' len_out=' . strlen($out));
		$warnLeft--;
	}
	return $out;
}

//echo html_fix($text);

?>