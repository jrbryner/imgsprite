<?php
/*

Use 3 dimensional array $array[$r][$g][$b] to index sprites

*/



$img_file = '../images/src_sprites/pennies/12.jpg';

$im = imagecreatefromjpeg($img_file);

$dim_x = imagesx($im);
$dim_y = imagesy($im);

$total_pixesl = 0;

$p_r = 0;
$p_g = 0;
$p_b = 0;

for ($x = 0; $x < $dim_x; $x++) 
{
	for ($y = 0; $y < $dim_y; $y++)
	{
		$rgb = imagecolorat($im, $x, $y);

		$tval = (($rgb >> 16) & 0xFF);
		$p_r += $tval;

		$tval = (($rgb >> 8) & 0xFF);
		$p_g += $tval;

		$tval = ($rgb & 0xFF);
		$p_b += $tval;

		++$total_pixels;

		echo "x: {$x} y: {$y} rgb: {$rgb} r: {$p_r} g: {$p_g} b:{$p_b} pixels: {$total_pixels}\n";
	}

}

$p_r /= $total_pixels;
$p_g /= $total_pixels;
$p_b /= $total_pixels;

$rgb = sprintf("%02x%02x%02x", $p_r, $p_g, $p_b);

echo "r: {$p_r} g: {$p_g} b: {$p_b} rgb: {$rgb} \n";


?>
