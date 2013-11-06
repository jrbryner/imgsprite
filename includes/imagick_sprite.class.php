<?php
class Imagick_Sprite 
{
	// Paths and Filenames
	protected $image_file;
	protected $dest_file;
	protected $convert_file;
	protected $image_path    = 'images/src_image';
	protected $sprite_path   = 'images/src_sprites';
	protected $dest_path     = 'images/out';
	protected $temp_path     = 'tmp';

	// Color information
	protected $color_depth;
	protected $color_sections;

	// Internal timer
	protected $timer;

	// Image Handlers
	protected $image_im;

	// EXIF Image Types
	protected $allowed_source_types = array(1, 2, 3);
	protected $image_type;
	protected $allowed_sprite_types = array(1, 2, 3);
	protected $sprite_type;

	protected $sprites     = array();
	protected $sprite_info = array();
	protected $errors      = array();

	/////////////////////
	// Constructor
	/////////////////////

	/*
	Function: constructor

	Called upon object creation

	Parameters:
		[string $image_filename] - filename of image to output too
		[string $sprite_name]    - name of sprite folder to use

	*/
	public function __construct($image_filename, $sprite_name) 
	{
		$this->timer = microtime(true);
		echo "Begin - {$this->timer}\n";

		$this->image_file   = $this->image_path .'/'. basename($image_filename);
		$this->dest_file    = $this->dest_path  .'/'. basename($image_filename);
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

	/////////////////////
	// Public
	/////////////////////

	/*
	Function: load
	
	Deletes any leftover temporary images, converts source image and loads info

	Parameters: none

	*/	
	public function load() 
	{
		if ($this->load_sprites())
		{
			$this->dump_temp();
			$this->convert_image();
			$this->load_source_image();
		} else {
			$this->output_errors_exit();
		}
	}

	/* 
	Function: process

	Processes source image.  
	
	Parameters: none

	*/
	public function process()
	{
		echo "Processing image\n";

		$cols = imagesx($this->image_im);
		$rows = imagesy($this->image_im);

		echo "Cols: {$cols} :: Rows: {$rows}\n";
		
		// Go through each row
		for ($y = 0; $y < $rows; $y++)
		{
			$row_indexes = array();

			// Go through each column in a row
			for ($x = 0; $x < $cols; $x++)
			{
				// Get color information for pixel at x/y
				$color_index = imagecolorat($this->image_im, $x, $y);
				$color_tran  = imagecolorsforindex($this->image_im, $color_index);

				// Get average color for pixel. For B&W image, all colors should be same value
				$pixel_color_depth = ($color_tran['red'] + $color_tran['green'] + $color_tran['blue']) / 3;

				// Determine sprite index
				for($i = 0; $i < $this->color_sections; $i++)
				{
					if (($pixel_color_depth > ($i * $this->color_sections)) && ($pixel_color_depth < (($i+1) * $this->color_sections)) )
					{
						$sprite_index = $i;
						break;
					}
				}
				$row_indexes[] = $sprite_index;
			}
			// Create row of images
			$this->create_row($row_indexes, $y);
			unset($row_indexes);
		}

		// Merge rows and dump the temp images
		$this->merge_rows();
		$this->dump_temp();
	}

	/* 
	Function: check_image_name

	Checks that image filename passed through command line is valid

	Paramters:
		[string $image] - Filename of output file

	Returns:
		boolean - false on error

	*/
        public static function check_image_name($image)
        {
		if (strstr($image, '.'))
		{
			return true;
		} else {
			return false;
		}
	}

	/*
	Function: check_sprite_name

	Checks that the sprite name passed through command line is valid

	Parameters:
		[string $sprite] - Folder to use for sprite images
	
	Returns:
		boolean - false on error

	*/
	public static function check_sprite_name($sprite)
	{
		if (!strstr($sprite, '.'))
		{
			return true;
		} else {
			return false;		
		}
	}

	/////////////////////
	// Protected
	/////////////////////

	/*
	Function: merge_rows

	Uses ImageMagick (convert -append) to join rows created in this->process

	Parameters: none

	Returns:
		boolean - false on error
	
	*/
	protected function merge_rows()
	{
		$ret_val = true;
		$ignore_files = array('.', '..');

		// Check can open temp directory
		echo 'Opening temp directory: '. $this->temp_path ." - ". $this->get_time() ."s\n";
		if ($dh = opendir($this->temp_path))
		{
			// Read through temp directory for row image filenames
			echo 'Reading temp directory: '. $this->temp_path ." - ". $this->get_time() ."s\n";
			
			while(($file = readdir($dh)) !== false)
			{
				echo $file ."\n";
				if (!in_array($file, $ignore_files))
				{
					$rows[] = $this->temp_path .'/'. $file;
				}
			}
			closedir($dh);

			// Join filenames into string for ImageMagick
			$file_string = implode(' ', $rows);
			$cmd = "convert -append {$file_string} -quality 100 -monitor {$this->dest_file}";
			passthru($cmd);
		} else {
			$ret_val = false;
			$this->error[] = "Could not open temp directory";
		}

		return $ret_val;

	}

	/*
	Function: create_row

	Creates temporary row for use in this->merge_rows

	Parameters:
		[array $row_indexes] - array of sprite indexes
		[int $y]             - row number

	*/
	protected function create_row($row_indexes, $y)
	{
		if (is_array($row_indexes) && count($row_indexes) > 0)
		{
			$append_list_string = '';

			// Filename of temporary row image
			$temp_row = $this->temp_path .'/'. $y .'.png';

			// Create string of sprite filenames
			foreach($row_indexes as $index)
			{
				$append_list_string .= $this->sprites[$index] .' ';
			}

			// Join images using ImageMagick
			$cmd = "convert +append {$append_list_string} -quality 100 -monitor {$temp_row}";
			echo "Creating row: {$temp_row} - {$this->get_time()}s\n";
			exec($cmd);
		}
	}

	/*
	Function: convert_image

	Uses ImageMagick to convert source image to grayscale with color depth of number of sprites (this->color_depth)

	Parameters: none
	
	*/
	protected function convert_image()
	{
		$cmd = "convert {$this->image_file} -colorspace GRAY {$this->convert_file}";
		exec($cmd);
		
		$cmd = "convert {$this->convert_file} -colors {$this->color_depth} {$this->convert_file}";
		exec($cmd);
	}

	/*
	Function: dump_temp

	Deletes temporary images from temporary directory

	Parameters: none

	Returns:
		boolean - false on error

	*/
	protected function dump_temp() 
	{
		$ret_val = true;
		$ignore_files = array('.', '..');

		echo 'Dumping temp directory: '. $this->temp_path ." - ". $this->get_time() ."s\n";

		if ($dh = opendir($this->temp_path))
		{
			while(($file = readdir($dh)) !== false)
			{
				if (!in_array($file, $ignore_files))
				{
					@unlink($this->temp_path .'/'. $file);
				}
			}
			closedir($dh);
		} else {
			$ret_val = false;
			$this->error[] = 'Could not open temp directory';
		}
		
		return $ret_val;
	}

	/*
	Function: load_source_image

	Created this->image_im using GD

	Parameters: none

	Returns:
		boolean - false on error

	*/
	protected function load_source_image()
	{
		$ret_val = true;

		// Creates image using GD depending on image type
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
	
	/*
	Function: load_sprites

	Loads sprite filenames into this->sprites array

	Paratmers: none

	Returns:
		boolean - false on error

	*/
	protected function load_sprites()
	{
		$ret_val = true;
		$ignore_files = array('.', '..');

		// Open sprite directory
		echo 'Opening sprite path: '. $this->sprite_path ." - ". $this->get_time() ."s\n";
		if ($dh = opendir($this->sprite_path))
		{
			echo 'Reading sprite directory: '. $this->sprite_path ." - ". $this->get_time() ."s\n";

			// Read through sprite directory handle
			while(($file = readdir($dh)) !== false)
			{
				if (!in_array($file, $ignore_files))
				{
					// Create filename
					$sprite_file_path = $this->sprite_path .'/'. $file;
					$sprite_file_path_info = pathinfo($sprite_file_path);
					echo 'Reading: '. $sprite_file_path ."\n";					

					// Get sprite index from filename (2.jpg = 2)
					$sprite_index = basename($sprite_file_path, '.'.$sprite_file_path_info['extension']);
					$this->sprites[$sprite_index] = $sprite_file_path;
				}
			}
			closedir($dh);
		} else {
			$ret_val = false;
			$this->error[] = 'Could not open sprite directory';
		}

		// Check that some sprites were read in
		if (count($this->sprites) > 0)
		{
			echo 'Sprites read: '. count($this->sprites) ." - ". $this->get_time() ."s\n";
		} else {
			$ret_val = false;
			$this->error[] = 'No sprites read';
		}

		// Set this->color_depth to number of sprites
		$this->color_depth = count($this->sprites);	
		$this->set_sections();

		return $ret_val;
	}

	/*
	Function: set_sections

	Determine number of sections. "Scales" according to this->color_depth

	Parameters: none

	*/
	protected function set_sections() 
	{
		$this->color_sections = 255 / $this->color_depth;

		echo 'Color sections: '. $this->color_sections ."\n";
	}

	/* 
	Function: check_begin

	Check status of source files and paths before beginning

	Parameters: none

	Returns:
		boolean - false on error
	
	*/
	protected function check_begin() 
	{
		$continue = true;

		// Check that the source image exists
		if (!$this->check_image_exists())
		{
			$continue = false;
			$this->error[] = 'Source image does not exist';
		} else {
			
			// If source file does exists, check that it's an allowed type
			if (!$this->check_allowed_source())
			{
				$continue = false;
				$this->error[] = 'Source image is not an allowed file type';
			}
		}

		// Check that the sprite source path exists
		if (!$this->check_sprite_path())
		{
			$continue = false;
			$this->errors[] = 'Sprite source directory does not exist';
		} else {
			
			// If path exists, check that there are images in that directory
			if (!$this->check_number_sprites())
			{
				$continue = false;
				$this->errors[] = 'No sprites in directory';
			} else {
				if (!$this->check_sprite_info())
				{
					$continue = false;
					$this->errors[] = 'Sprites are not an allowed file type';
				}
			}
		}

		// Check that the out path exists
		if (!$this->check_out_path()) 
		{
			$continue = false;
			$this->errors[] = 'Could not open output folder: '. $this->dest_path;
		}

		return $continue;
	}

	/*
	Function: check_allowed_source

	Check that the source file is an allowed image type

	Parameters: none

	*/	
	protected function check_allowed_source()
	{
		$this->image_type = exif_imagetype($this->image_file);

		if (in_array($this->image_type, $this->allowed_source_types))
		{
			return true;
		} else {
			return false;
		}

	}
	
	/*
	Function: check_sprite_info

	Get dimensions of sprites

	Parameters: none

	*/
	protected function check_sprite_info()
	{
		$dir_scan = scandir($this->sprite_path, 1);
		echo 'Reading sprite: '. $this->sprite_path .'/'. $dir_scan[0] ." - ". $this->get_time() ."s\n";

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

	/*
	Function: check_image_exists
	
	Check that the source file exists

	Parameters: none

	Returns:
		boolean - true if image exists

	*/
	protected function check_image_exists()
	{
		
		if (file_exists($this->image_file))
		{
			return true;
		} else {
			return false;
		}
	}

	/*
	Function: check_sprite_path

	Check that the sprite path exists

	Parameters: none

	Returns:
		boolean - true if path exists
	
	*/
	protected function check_sprite_path()
	{
		echo 'Sprite source directory: '. $this->sprite_path ." - ". $this->get_time() ."s\n";
		if (is_dir($this->sprite_path))
		{
			return true;
		} else {
			return true;
		}
	}

	/*
	Function: check_number_sprites

	Checks that there are images in the sprite directory

	Parameters: none

	Returns:
		boolean - true if there are images

	*/
	protected function check_number_sprites()
	{
		$dir_scan = scandir($this->sprite_path);

		if (count($dir_scan) > 2) 
		{
			return true;
		} else {
			return false;
		}
	}

	/*
	Function: check_out_path

	Check that the destination path exists

	Parameters: none

	Returns:
		boolean - true if path exists
	
	*/
	protected function check_out_path()
	{
		if (is_dir($this->dest_path)) 
		{
			return true;
		} else {
			return false;
		}
	}

	/*
	Function: output_erros_exit

	Output this->erros array and exit

	Parameter: none

	*/
	protected function output_erros_exit()
	{
		if (count($this->erros) >0)
		{
			foreach($this->erros as $err)
			{
				echo $err ."\n";
			}
		}
		exit();
	}

	/* 
	Function: get_time

	Returns time taken since script begain

	Parameters: none

	*/
	protected function get_time()
	{
		$ret_val = microtime(true) - $this->timer;

		return $ret_val;
	}
}
?>
