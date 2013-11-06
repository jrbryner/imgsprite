#!/usr/local/bin/php
<?php
require_once('includes/imagick_sprite.class.php');

if (count($argv) == 3) {

	$sprite = $argv[1];
	$image = $argv[2];
	
	if (Imagick_Sprite::check_image_name($image) && Imagick_sprite::check_sprite_name($sprite)) 
	{

		$ims = new Imagick_Sprite($image, $sprite);

		$ims->load();
		$ims->process();
	} else {
		print 'Arguments are not correct. Please provide sprite name and output filename';
	}
} else {
	print 'No arguments provided';
}
echo "\n";
?>
