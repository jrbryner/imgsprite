<?php
class Image_Sprite {

	// Paths and Filenames
	private $image_file;
	private $dest_file;
	private $convert_file;
	private $image_path        = 'src_image';
	private $sprite_path       = 'src_sprites';
	private $dest_path         = 'out';

	// Color information
	private $color_depth;
	private $color_sections;

	// Image Handlers
	private $image_im;
	private $sprite_im;
	private $dest_im;

	// EXIF Image Types
	private $allowed_source_types = array(1, 2, 3); 
	private $image_type;
	private $allowed_sprite_types = array(1, 2, 3);
	private $sprite_type;

	private $sprites     = array();
	private $sprite_info = array();
	private $errors      = array();

	function __construct($image_filename, $sprite_name) 
	{
		$this->image_file = $this->image_path .'/'. basename($image_filename);
		$this->dest_file = $this->dest_path .'/'. basename($image_filename);
		$this->convert_file = $this->image_path .'/convert_'. basename($image_filename);
		$this->sprite_path .= '/'. $sprite_name;

		echo "Checking files\n";

		if (!$this->check_begin()) 
		{
			$this->output_errors_exit();
		} else {
			echo "No errors so far... continuing\n";
		}
		

	}

	public function load() {
		if ($this->load_sprites()) 
		{
			$this->set_sections();
			$this->convert_image();
			$this->load_source_image();
		} else {
			$this->output_errors_exit();
		}
	}

	public function process() 
	{
		echo "Processing image\n";

		$cols = imagesx($this->image_im);
		$rows = imagesy($this->image_im);
		
		echo "Cols: {$cols}, Rows: {$rows}\n";
		
		$this->create_dest_image($cols, $rows);

		for ($y = 0; $y < $rows; $y++) 
		{
			for($x = 0; $x < $cols; $x++)
			{
				$color_index = imagecolorat($this->image_im, $x, $y);
				$color_tran = imagecolorsforindex($this->image_im, $color_index);

				$pixel_color_depth = $color_tran['red'];

				for($i = 0; $i < $this->color_sections; $i++) 
				{
					if (($pixel_color_depth > ($i * $this->color_sections))  && ($pixel_color_depth < (($i+1) * $this->color_sections)) )
					{
						$sprite_index = $i;
						break;
					}
				}
				imagecopy($this->dest_im, $this->sprites[$sprite_index], ($x * $this->sprite_info[0]), ($y * $this->sprite_info[1]), 0, 0, $this->sprite_info[0], $this->sprite_info[1]);
			}
		}
	}

	public function save() 
	{
		
		$ret_val = true;

		echo "Destination type: {$this->image_type}\n";
		switch($this->image_type)
		{
			case 1:
				imagegif($this->dest_im, $this->dest_file);
				break;
			case 2:
				imagejpeg($this->dest_im, $this->dest_file, 100);
				break;
			case 3:
				imagepng($this->dest_im, $this->dest_file, 100);
				break;
			default:
				$ret_val = false;
				break;
		}
		//@unlink($this->convert_file);
		if ($ret_val === true) {
			echo "File written: {$this->dest_file}\n";
		} else {
			echo "File could not be written\n";
		}

		return $ret_val;

	}

	private function convert_image()
	{
		$cmd = "convert {$this->image_file} -colorspace GRAY {$this->convert_file}";
		exec($cmd);

		$cmd = "convert {$this->convert_file} -colors {$this->color_depth} {$this->convert_file}";
		exec($cmd);
	}


	private function create_dest_image($cols, $rows) 
	{
		$dest_width = $cols * $this->sprite_info[0];
		$dest_height = $rows * $this->sprite_info[1];

		$this->dest_im = imagecreatetruecolor($dest_width, $dest_height);

		echo "Final image dimenstions: {$dest_width} x {$dest_height}\n";
	}

	private function load_source_image() 
	{
		$ret_val = true;

		switch($this->image_type) 
		{
			case 1:
				$this->image_im = imagecreatefromgif($this->convert_file);
				break;
			case 2:
				$this->image_im = imagecreatefromjpeg($this->convert_file);
				break;
			case 3:
				$this->image_im = imagecreatefrompng($this->convert_file);
				break;
			default:
				$ret_val = false;
				break;
		}

		return $ret_val;

	}

	private function load_sprites() 
	{
		$ret_val = true;
		$ignore_files = array('.', '..');

		echo 'Opening sprite path: '. $this->sprite_path ."\n";
		if ($dh = opendir($this->sprite_path)) 
		{
			echo 'Reading sprite directory: '. $this->sprite_path ."\n";

			while(($file = readdir($dh)) !== false) 
			{
				if (!in_array($file, $ignore_files))
				{
					$sprite_file_path = $this->sprite_path .'/'. $file;
					echo 'Reading: '. $sprite_file_path ."\n";

					switch($this->sprite_type) 
					{
						case 1:
							$sprite_index = basename($sprite_file_path, '.gif');
							$this->sprites[$sprite_index] = imagecreatefromgif($sprite_file_path);
							break;
						case 2:
							$sprite_index = basename($sprite_file_path, '.jpg');
							$this->sprites[$sprite_index] = imagecreatefromjpeg($sprite_file_path);
							break;
						case 3:
							$sprite_index = basename($sprite_file_path, '.png');
							$this->sprites[$sprite_index] = imagecreatefrompng($sprite_file_path);
							break;
						default:
							break;
					}
				}
			}
			closedir($dh);			
		} else {
			$ret_val = false;
			$this->error[] = "Could not open sprite directory";
		}
		
		if (count($this->sprites) > 0) 
		{
			echo 'Sprites read: '. count($this->sprites) ."\n";
		} else {
			$ret_val = false;
			$this->error[] = 'No sprites read';
		}

		$this->color_depth = count($this->sprites);

		return $ret_val;
	}

	private function set_sections() {
		$this->color_sections = 255 / $this->color_depth;

		echo 'Color sections: '. $this->color_sections ."\n";
	}

	private function output_errors_exit() 
	{
		if (count($this->errors) > 0) 
		{
			foreach ($this->errors as $err) 
			{
				echo $err ."\n";
			}
		}
		exit();
	}

	private function check_begin() 
	{
		$continue = true;

		if (!$this->check_image_exists()) 
		{
			$continue = false;
			$this->errors[] = 'Source image does not exists';
		} else {

			if (!$this->check_allowed_source()) 
			{
				$continue = false;
				$this->errors[] = 'Sounce image is not an allowed file type';
			}
		}

		if (!$this->check_sprite_path()) 
		{
			$continue = false;
			$this->errors[] = 'Sprite source directory does not exist';

		} else {
			if (!$this->check_number_sprites())
			{
				$continue = false;
				$this->errors[] = 'No sprites in directory';
			} else {

				if (!$this->check_sprite_info())
				{
					$continute = false;
					$this->errors[] = 'Sprites are not an allowed file type';
				}
			}
		}

		return $continue;
	}

	private function check_allowed_source() 
	{
		$this->image_type = exif_imagetype($this->image_file);

		if (in_array($this->image_type, $this->allowed_source_types)) 
		{
			return true;
		} else {
			return false;
		}
	}

	private function check_sprite_info() 
	{
		$dir_scan = scandir($this->sprite_path, 1);
		echo 'Reading sprite: '. $this->sprite_path .'/'. $dir_scan[0] ."\n";
		
		$this->sprite_type = exif_imagetype($this->sprite_path .'/'. $dir_scan[0]);

		if (in_array($this->sprite_type, $this->allowed_sprite_types))
		{			
			$this->sprite_info = getimagesize($this->sprite_path .'/'. $dir_scan[0]);
			print_r($this->sprite_info);

			return true;
		} else {
			return false;
		}
	}

	private function check_image_exists() 
	{
		if (file_exists($this->image_file)) 
		{
			return true;
		} else {
			return false;
		}
	}

	private function check_sprite_path() 
	{
		echo 'Sprite source directory: '. $this->sprite_path ."\n";
		if (is_dir($this->sprite_path)) 
		{
			return true;
		} else {
			return false;
		}
	}

	private function check_number_sprites()
	{
		$dir_scan = scandir($this->sprite_path);

		if (count($dir_scan) > 2) {
			return true;
		} else {
			return false;
		}

	}


}



?>
