<?php


function CreateThumb($from, $to, $width, $height, $quality, $ext) {
	
	list($width_orig, $height_orig) = getimagesize($from);

	$ratio_orig = $width_orig/$height_orig;

	if ($width/$height > $ratio_orig) {
		$width = $height*$ratio_orig;
	} else {
		$height = $width/$ratio_orig;
	}

	$image_p = imagecreatetruecolor($width, $height);
	
	if ($ext == "gif") {$image = imagecreatefromgif($from);}
	elseif ($ext == "png") {$image = imagecreatefrompng($from);}
	elseif ($ext == "jpeg") {$image = imagecreatefromjpeg($from);}
	elseif ($ext == "jpg") {$image = imagecreatefromjpeg($from);}
	
	imagecopyresampled($image_p, $image, 0, 0, 0, 0, $width, $height, $width_orig, $height_orig);
	imagejpeg($image_p, $to, $quality);

}


?>