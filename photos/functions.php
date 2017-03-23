
<?php
// config
$WELCOME = '<h3>Photo gallery</h3><p>Please select a gallery from the left.</p>';
$COLS=4;
$ROWS=3;
$THUMB_OFFSET="thumbs";
$THUMB_PREFIX="tn_";
$THUMB_WIDTH=96;
$THUMB_HEIGHT=96;
$PREVIOUS_ICON = '&laquo;';
$NEXT_ICON = '&raquo;';

// apparently we shouldn't use $PHP_SELF? seems ok here.
$ROOT_URL = dirname($PHP_SELF);
$ROOT_DIR = dirname($PATH_TRANSLATED);

// function definitions

// these are the most important functions:

// print a tabular structure representing the subdirectories below the root.
// highlights the currently selected $gal
function printDirectoryStructure($gal){
	global $ROOT_DIR, $PHP_SELF;
	static $alldirs;
	step($ROOT_DIR, $alldirs);

	if (sizeof($alldirs)>0){

		sort($alldirs);

		$rootSize = sizeof(explode('/',$ROOT_DIR));
		$explodedGal = explode('/', $gal);
	
		print '<table>';
		foreach($alldirs as $adir){
			print '<tr>';
			$explodedDir = explode('/',$adir);
			$currSize = sizeof($explodedDir);

			$display = 'class=\'on\'';	
			
			// we want to highlight the branch currently investigated
			$link='';
			//print $rootSize . "," . $currSize;
			for ($i=$rootSize;$i<$currSize;$i++){
				// if we're no longer on the path to the selected gallery.
				if ($explodedDir[$i]!=$explodedGal[$i+1]) $display='';
				$link = $link .'/'. $explodedDir[$i];
				//print "<td><a $display href='$PHP_SELF?gal=" . rawurlencode($link) . "'>$explodedDir[$i]</a></td>";
				//print $link + ",";
			}
			$link = rawurlencode($link);
			print "<td style='text-indent:" . ($currSize-$rootSize-1)*10 . "px;'><a $display href='$PHP_SELF?gal=$link'>".$explodedDir[sizeof($explodedDir)-1].'</a></td>';//</div>';
		}
		print '</tr>';
		endTable();
		return;
	}
}

// prints either a link to an image, or a tabular thumbnail structure
function printGallery(){
	global $gal, $file, $pg, $WELCOME, $ROOT_DIR, $ROOT_URL, $ROWS, $COLS, $THUMB_OFFSET, $THUMB_PREFIX, $THUMB_WIDTH, $THUMB_HEIGHT;
	
	if (isset($gal)){ // decode $gal_to_display
		// strip it of any dots or dotdots.
		// de-encode it
		$gal_to_display = '/'.rawurldecode($gal);
		if (eregi("\.", $gal_to_display)) {
			$gal_to_display='/';
		}

		// ensure that we have a page to show.
		if (!isset($pg)||($pg<0)) {
			$pg=0;
		}

		if (isset($file)){ // if we're looking explicitely at a file
		  print "<a href='$PHP_SELF?gal=$gal&pg=$pg'><img width='100%' src='$ROOT_URL$gal_to_display/$file'></img></a>";
		}  else {
			// get the handle for files for this directory
			$files=getFiles($ROOT_DIR.$gal_to_display);

			// ensure we have thumbnails for the files.
			if (sizeof($files)>0) sort($files);
			checkThumbs($ROOT_DIR.$gal_to_display, $files);

			$end = ($ROWS*$COLS)*($pg);

			startGalleryTable();
			// if the last file to display is actually available
			if ($end<sizeof($files)){
			  for ($r=0; $r<$ROWS;$r++){
			    print "<tr>";
			    for ($c=0; $c<$COLS;$c++){
			      $i = ($pg*$ROWS*$COLS) + ($r*$COLS) + $c;
			      if ($files[$i]!=""){
				print "<td><a href='$PHP_SELF?gal=$gal&pg=$pg&file=".rawurlencode($files[$i])."'><img width='$THUMB_WIDTH' height='$THUMB_HEIGHT' src='$ROOT_URL$gal_to_display/$THUMB_OFFSET/".rawurlencode($THUMB_PREFIX.$files[$i])."'></img></a></td>";
			      }
			      else {
				print "<td></td>";
			      }
			    }
			    print "</tr>";
			  } 
			  $isLast = false;
			  if ($end+($ROWS*$COLS)>sizeof($files)) $isLast=true;
			  navBars($gal,$pg, $isLast);
			}
			else {
			  print "<tr><td height='$THUMB_HEIGHT'><code>No images in this gallery</code></td></tr>";
			}
			endTable();
		} 
	  }else { // we have no gal to display. Therefore just display the welcome message
			print $WELCOME;
	}
	return;
}

/* Below follow the sub-functions */


// returns an array of Strings representing the files present in the directory
// takes a String representing the dir to change to.
function getFiles($dir){
  //print "examining $dir";
  @chdir($dir);
  $handle=@opendir($dir);
  $files = NULL;
  while ($file = readdir($handle)){
    if (is_file($file)){
      if ( (eregi("jpg$",$file))||(eregi("png$",$file))){//||(eregi("gif$",$file))){
        $files[]=$file;
      }	
    }
  }
  closedir($handle);
  return $files;
}

// returns an array of Strings representing the sub-directories present in the directory
// takes a String representing the dir to change to.
function getDirs($dir){
global $THUMB_OFFSET;
  //print "examining $dir";
  @chdir($dir);
  $handle = @opendir($dir);
  $dirs = NULL;
  while ($file = readdir($handle) ) {
    if (is_dir($file) & !eregi("^\.",$file) & ($file!=$THUMB_OFFSET) ){
      $dirs[]=$file;
    }
  }
  closedir($handle);
  return $dirs;
}

function step($dir, &$alldirs){
	// get all directories.
		$dirs = @getDirs($dir);
	// call ourselves on those directories.
	if (sizeof($dirs)>0){
		foreach ($dirs as $next_dir){
			$alldirs[] = $dir.'/'.$next_dir;	
			step($dir.'/'.$next_dir, $alldirs);
		}
	}
	return;
}

function checkThumbs($directory, $files){
	// for each file, check whether a file exists in the thumb_offset directory
	// if not, make the thumbnail and store it there
	global $THUMB_OFFSET, $THUMB_PREFIX;
	if (sizeof($files)>0){
		if (!file_exists($directory.'/'.$THUMB_OFFSET)) mkdir("$directory/$THUMB_OFFSET", 0755);
		foreach ($files as $file){
			if (!file_exists($directory . '/' . $THUMB_OFFSET . '/'. $THUMB_PREFIX . $file)){
				// we might not be able to do this, if we're in safe mode.
				@set_time_limit(30);
				pngThumbnail($directory . '/' . $file, $directory . '/' . $THUMB_OFFSET . '/'. $THUMB_PREFIX . $file);
			}
		}
	}
	return;
}

function startGalleryTable(){
	global $THUMB_WIDTH, $COLS;
	print "<table style='width:100%;min-width:".($THUMB_WIDTH*$COLS)."px;text-align:center;'>";
	return;
}

function startTable(){
	print "<table style='width:100%;text-align:center;'>";
	return;
}

function endTable(){
	print "</table>";
	return;
}

function navBars($gal, $pg, $isLast){
	global $COLS, $ROWS, $PREVIOUS_ICON, $NEXT_ICON;
	if ($pg>0){
		print "<tr><td><a href='$PHP_SELF?gal=$gal&pg=".($pg-1)."'>$PREVIOUS_ICON</a></td>";
	} else {
		print "<tr><td></td>";
	}
	for($i=0;$i<$COLS-2;$i++){
		print "<td></td>";
	}
	if ($isLast==true){
		print "<td></td></tr>";
	} else{
		print "<td><a href='$PHP_SELF?gal=$gal&pg=".($pg+1)."'>$NEXT_ICON</a></td></tr>";
	}
	return;
}

function pngThumbnail($file, $outputFile) {
	global $THUMB_WIDTH, $THUMB_HEIGHT;

	if (function_exists('imagecreatetruecolor')) {
		$im = imagecreatetruecolor(112, 112);
		$white = imagecolorclosest ( $im, 255, 255, 255);
		$frame = imagecolorclosest ( $im, 208, 208, 208);
		$black = imagecolorclosest ( $im, 0, 0, 0);
	} else {
		$im = imagecreate(112, 112);
		$white = imagecolorallocate ($im, 255, 255, 255);
		$frame = imagecolorallocate ( $im, 208, 208, 208);
		$black = imagecolorallocate ( $im, 0, 0, 0);
	}
	imagefilledrectangle ( $im, 0, 0, 112, 112, $white);

	$size = @getimagesize($file); // determine the filetype
	switch($size[2]) {
		case '':
			return;
			break;
		case 2:
			$si = imagecreatefromjpeg($file);
			break;
		case 3:
			$si = imagecreatefrompng($file);
			break;
		case 1:
			if (function_exists('imagecreatefromgif')){
				$si = imagecreatefromgif($file);
				break;
			}
		default :
			return;
			$arr = split('/', $size['mime']);
			//$ri = imgTXT(strtoupper($arr[1]));
			$si = $ri['image'];
			$size[0] = $ri['width'];
			$size[1] = $ri['width'];
	}
	$im_w = $size[0];
	$im_h = $size[1];
	if ( $im_w > $im_h ) {
		$dx = 9;
		$dw = 94;
		$dh = floor(94 * $im_h/$im_w);
		$dy = floor((96 - $dh)/2);
	} else {
		$dy = 1;
		$dh = 94;
		$dw = floor(94 * $im_w/$im_h);
		$dx = floor((112 - $dw)/2);
	}
	if (function_exists('imagecopyresampled')) {
		imagecopyresampled( $im, $si, $dx, $dy, 0, 0, $dw, $dh, $im_w, $im_h);
	} else {
		imagecopyresized( $im, $si, $dx, $dy, 0, 0, $dw, $dh, $im_w, $im_h);
	}
	imagerectangle ( $im, 8, 0, 103, 95, $frame);
	$px = (112 - imagefontwidth($font) * strlen($name)) / 2;
	if ($px < 0) $px = 0;
	imagestring($im, $font, $px, 98, $name, $black);

	imageinterlace($im, 1);
	imagepng($im, $outputFile);
	imagedestroy($im);
	imagedestroy($si);
	return;
}
?>
