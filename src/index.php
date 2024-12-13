<?php 

$password = 'admin';
$config_file = '../config.php';

// Authorization 
if (!isset($_COOKIE['m3cd4']) || $_COOKIE['m3cd4'] !== $password){
	die("<html><head><script>var pwd=prompt('Enter password','');document.cookie='m3cd4='+encodeURIComponent(pwd);location.reload();</script></head></html>");
}

$_version = '1.1';

// Error Handler
function error_handler($errno, $errstr, $errfile, $errline) {
	global $log, $config;
	
	switch ($errno) {
		case E_NOTICE:
		case E_USER_NOTICE:
			$error = 'Notice';
			break;
		case E_WARNING:
		case E_USER_WARNING:
			$error = 'Warning';
			break;
		case E_ERROR:
		case E_USER_ERROR:
			$error = 'Fatal Error';
			break;
		default:
			$error = 'Unknown';
			break;
	}

	echo('PHP ' . $error . ':  ' . $errstr . ' in ' . $errfile . ' on line ' . $errline . PHP_EOL);
	array_walk(debug_backtrace(), create_function('$a,$b','print "{$a[\'function\']}()(".basename($a[\'file\']).":{$a[\'line\']}); ";'));

	return true;
}

// set_error_handler('error_handler');

// Load config
if (file_exists($config_file)) {
	require_once $config_file;

	$db = new DB(DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE);
	$db_name = DB_DATABASE;
	define('DIR_CATALOG', realpath(DIR_IMAGE . '../catalog') . '/');
	define('DIR_ADMIN', realpath(DIR_IMAGE . '../admin') . '/');
	define('DIR_ROOT', realpath(DIR_IMAGE . '../'));
}else{
	die('Can\t find ' . $config_file);
}

// Database
final class DB

{
	private $mysqli_handler;
	public

	function __construct($hostname, $username, $password, $database)
	{
		$this->mysqli_handler = new mysqli($hostname, $username, $password, $database);
		if ($this->mysqli_handler->connect_error) {
			trigger_error('Error: Could not make a database link (' . $this->mysqli_handler->connect_errno . ') ' . $this->mysqli_handler->connect_error);
		}

		$this->mysqli_handler->query("SET NAMES 'utf8'");
		$this->mysqli_handler->query("SET CHARACTER SET utf8");
		$this->mysqli_handler->query("SET CHARACTER_SET_CONNECTION=utf8");
	}

	public

	function query($sql)
	{
		$result = $this->mysqli_handler->query($sql, MYSQLI_STORE_RESULT);
		if ($result !== FALSE) {
			if (is_object($result)) {
				$i = 0;
				$data = array();
				while ($row = $result->fetch_array(MYSQLI_ASSOC)) {
					$data[$i] = $row;
					$i++;
				}

				$result->close();
				$query = new stdClass();
				$query->row = isset($data[0]) ? $data[0] : array();
				$query->rows = $data;
				$query->num_rows = count($data);
				unset($data);
				return $query;
			}
			else {
				return true;
			}
		}
		else {
			trigger_error('Error: ' . $this->mysqli_handler->error . '<br />Error No: ' . $this->mysqli_handler->errno . '<br />' . $sql);
			exit();
		}
	}

	public

	function escape($value)
	{
		return $this->mysqli_handler->real_escape_string($value);
	}

	public

	function countAffected()
	{
		return $this->mysqli_handler->affected_rows;
	}

	public

	function getLastId()
	{
		return $this->mysqli_handler->insert_id;
	}

	public

	function __destruct()
	{
		$this->mysqli_handler->close();
	}
}
/**
 * @name OpenCart Modification class
 * @author halfhope
 * @email <talgatks@gmail.com>
 */
class OCMod
{
	private $db;
	private $mods;

	public function __construct($db)
	{
		$this->db = $db;
		$this->mods = array();
	}

	public function getDbMod($mid){
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "modification m WHERE modification_id = " . (int)$mid);
		return $query->row;
	}

	public function getFSMod($fmid){
		$selection[] = glob(DIR_SYSTEM . '*.xml*');
		$selection[] = glob(DIR_SYSTEM . '*.xml');
		foreach ($selection as $key => $files) {
			if ($files) {
				foreach ($files as $file) {
					$fmid2 = hash('crc32b', pathinfo($file, PATHINFO_BASENAME));
					if ($fmid2 == $fmid) {
						if (filesize($file) > 0) {
							return $this->parseFSMod($file);
						}
					}
				}
			}
		}
		return array();
	}

	public function isFsMod($id){
		return strlen($id) == 8;
	}

	public function getMod($mid){
		$result = array();
		if ($this->isFsMod($mid)) {
			$mod = $this->getFSMod($_POST['modification_id']);
			$result = array(
				'name' 	=> $mod['name'],
				'xml' 	=> $mod['xml']
			);
		}else{
			$mod = $this->getDbMod($_POST['modification_id']);
			$result = array(
				'name' 	=> $mod['name'],
				'xml' 	=> $mod['xml']
			);
		}
		return $result;
	}

	public function getMods(){
		$mods = array();

		if (!empty($this->mods)) {
			return $this->mods;
		}

		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "modification m ORDER BY m.modification_id ASC");

		if ($query->num_rows) {
			foreach ($query->rows as $key => $value) {
				$value['file'] = $value['name'];
				$mods[$value['modification_id']] = $value;
			}
		}

		// This is purly for developers so they can run mods directly and have them run without upload after each change.
		$selection[] = glob(DIR_SYSTEM . '*.xml*');
		$selection[] = glob(DIR_SYSTEM . '*.xml');
		foreach ($selection as $key => $files) {
			if ($files) {
				foreach ($files as $file) {
					if (filesize($file) > 0) {
						$fmid = hash('crc32b', pathinfo($file, PATHINFO_BASENAME));
						$mods[$fmid] = $this->parseFSMod($file);
					}
				}
			}
		}
		
		$this->mods = $mods;

		return $mods;
	}

	public function parseFSMod($filename){
		$fmid = hash('crc32b', pathinfo($filename, PATHINFO_BASENAME));
		$dom = new DOMDocument('1.0', 'UTF-8');
		$dom->preserveWhiteSpace = false;
		$xml = file_get_contents($filename);
		$dom->loadXml($xml);
		
		$name = '<span title="'.pathinfo($filename, PATHINFO_BASENAME).'">'.$dom->getElementsByTagName('name')->item(0)->textContent . '</span>';
		$author = $dom->getElementsByTagName('author')->item(0)->textContent;
		$version = $dom->getElementsByTagName('version')->length ? $dom->getElementsByTagName('version')->item(0)->textContent : '';
		$link = $dom->getElementsByTagName('link')->length ? urldecode($dom->getElementsByTagName('link')->item(0)->textContent) : '';
		$status = (int)(pathinfo($filename, PATHINFO_EXTENSION) == 'xml');

		return array(
			'modification_id' => $fmid,
			'file' => $filename,
			'name' => $name,
			'code' => $dom->getElementsByTagName('code')->item(0)->textContent,
			'author' => $author,
			'version' => $version,
			'link' => $link,
			'xml' => $xml,
			'status' => $status,
			'date_added' => date('Y-m-d H:i:s', filectime($filename))
		);
	}

	public function getModdedFilesList($modifications){

		$result = array();
		
		foreach ($modifications as $modification_id => $xml) {
			$dom = new DOMDocument('1.0', 'UTF-8');
			$dom->preserveWhiteSpace = false;
			$dom->loadXml($xml['xml']);

			// Wipe the past modification store in the backup array
			$recovery = array();

			// Set the a recovery of the modification code in case we need to use it if an abort attribute is used.
			if (isset($modification)) {
				$recovery = $modification;
			}

			if ($dom->getElementsByTagName('name')->length) {
				$name = '<span>'.$dom->getElementsByTagName('name')->item(0)->textContent . '</span>';				
			} else {
				$name = '<span>'.$dom->getElementsByTagName('id')->item(0)->textContent . '</span>';	
			}

			$files = $dom->getElementsByTagName('modification')->item(0)->getElementsByTagName('file');

			foreach ($files as $file) {
				
				$operations = $file->getElementsByTagName('operation');
				$file_line_num = $file->getLineNo();

				$files = explode('|', $file->getAttribute('path'));

				foreach ($files as $file) {
					$path = '';

					// Get the full path of the files that are going to be used for modification
					if (substr($file, 0, 7) == 'catalog') {
						$path = DIR_CATALOG . str_replace('../', '', substr($file, 8));
					}

					if (substr($file, 0, 5) == 'admin') {
						$path = DIR_ADMIN . str_replace('../', '', substr($file, 6));
					}

					if (substr($file, 0, 6) == 'system') {
						$path = DIR_SYSTEM . str_replace('../', '', substr($file, 7));
					}

					if ($path) {
						$files = glob($path, GLOB_BRACE);

						if ($files) {
							foreach ($files as $file) {
								// Get the key to be used for the modification cache filename.
								if (substr($file, 0, strlen(DIR_CATALOG)) == DIR_CATALOG) {
									$key = 'catalog/' . substr($file, strlen(DIR_CATALOG));
								}

								if (substr($file, 0, strlen(DIR_ADMIN)) == DIR_ADMIN) {
									$key = 'admin/' . substr($file, strlen(DIR_ADMIN));
								}

								if (substr($file, 0, strlen(DIR_SYSTEM)) == DIR_SYSTEM) {
									$key = 'system/' . substr($file, strlen(DIR_SYSTEM));
								}

								// If file contents is not already in the modification array we need to load it.
								if (!isset($modification[$key])) {
									$content = file_get_contents($file);

									$modification[$key] = preg_replace('~\r?\n~', "\n", $content);
									$original[$key] = preg_replace('~\r?\n~', "\n", $content);

									// Log
									$log[] = 'FILE: ' . $key;
								}

								foreach ($operations as $operation) {
									$operation_line_num = $operation->getLineNo();

									$error = $operation->getAttribute('error');

									// Ignoreif
									$ignoreif = $operation->getElementsByTagName('ignoreif')->item(0);

									if ($ignoreif) {
										if ($ignoreif->getAttribute('regex') != 'true') {
											if (strpos($modification[$key], $ignoreif->textContent) !== false) {
												continue;
											}
										} else {
											if (preg_match($ignoreif->textContent, $modification[$key])) {
												continue;
											}
										}
									}

									$status = false;

									// Search and replace
									if ($operation->getElementsByTagName('search')->item(0)->getAttribute('regex') != 'true') {
										// Search
										$search = $operation->getElementsByTagName('search')->item(0)->textContent;
										$trim = $operation->getElementsByTagName('search')->item(0)->getAttribute('trim');
										$index = $operation->getElementsByTagName('search')->item(0)->getAttribute('index');
										// Trim line if no trim attribute is set or is set to true.
										if (!$trim || $trim == 'true') {
											$search = trim($search);
										}

										// Add
										$add = $operation->getElementsByTagName('add')->item(0)->textContent;
										$trim = $operation->getElementsByTagName('add')->item(0)->getAttribute('trim');
										$position = $operation->getElementsByTagName('add')->item(0)->getAttribute('position');
										$offset = $operation->getElementsByTagName('add')->item(0)->getAttribute('offset');

										if ($offset == '') {
											$offset = 0;
										}

										// Trim line if is set to true.
										if ($trim == 'true') {
											$add = trim($add);
										}

										// Check if using indexes
										if ($index !== '') {
											$indexes = explode(',', $index);
										} else {
											$indexes = array();
										}

										// Get all the matches
										$i = 0;

										$lines = explode("\n", $modification[$key]);

										for ($line_id = 0; $line_id < count($lines); $line_id++) {
											$line = $lines[$line_id];

											// Status
											$match = false;

											// Check to see if the line matches the search code.
											if (stripos($line, $search) !== false) {
												// If indexes are not used then just set the found status to true.
												if (!$indexes) {
													$match = true;
												} elseif (in_array($i, $indexes)) {
													$match = true;
												}

												$i++;
											}

											// Now for replacing or adding to the matched elements
											if ($match) {
												switch ($position) {
													default:
													case 'replace':
														$new_lines = explode("\n", $add);

														if ($offset < 0) {
															array_splice($lines, $line_id + $offset, abs($offset) + 1, array(str_replace($search, $add, $line)));

															$line_id -= $offset;
														} else {
															array_splice($lines, $line_id, $offset + 1, array(str_replace($search, $add, $line)));
														}

														break;
													case 'before':
														$new_lines = explode("\n", $add);

														array_splice($lines, $line_id - $offset, 0, $new_lines);

														$line_id += count($new_lines);
														break;
													case 'after':
														$new_lines = explode("\n", $add);

														array_splice($lines, ($line_id + 1) + $offset, 0, $new_lines);

														$line_id += count($new_lines);
														break;
												}

												$status = true;
												$result['file'][$file][$modification_id]['name'] = $name;
												$result['file'][$file][$modification_id]['lines'][(int)$line_id] = array('search' => $search, 'line_num' => $file_line_num, 'op_line_num' => $operation_line_num);

												$result['mod'][$modification_id][$file]['name'] = $file;
												$result['mod'][$modification_id][$file]['lines'][(int)$line_id] = array('search' => $search, 'line_num' => $file_line_num, 'op_line_num' => $operation_line_num);
												$result['mods'][$modification_id] = $name;
											}
										}

										$modification[$key] = implode("\n", $lines);
									} else {
										$search = trim($operation->getElementsByTagName('search')->item(0)->textContent);
										$result['file'][$file][$modification_id]['name'] = $name;
										$result['file'][$file][$modification_id]['lines'][(int)$line_id] = array('search' => $search, 'line_num' => $file_line_num, 'op_line_num' => $operation_line_num);

										$result['mod'][$modification_id][$file]['name'] = $file;
										$result['mod'][$modification_id][$file]['lines'][(int)$line_id] = array('search' => $search, 'line_num' => $file_line_num, 'op_line_num' => $operation_line_num);
										
										$result['mods'][$modification_id] = $name;
									}
								}
							}
						}
					}
				}
			}
		}
		return $result;
	}

	// ajax functions

	public function saveMod($data){
		if ($this->isFsMod($data['modification_id'])) {
			$modification = $this->getFSMod($data['modification_id']);
			$filename = $modification['file'];
			file_put_contents($filename, $data['xml']);
		}else{
			$query = $this->db->query("UPDATE " . DB_PREFIX . "modification SET xml = '" . $this->db->escape($data['xml']) . "' WHERE modification_id = " . (int)$data['modification_id']);
		}
	}

	public function cloneMods($mids){
		foreach ($mids as $key => $value) {
			if ($this->isFsMod($value)) {
				$modification = $this->getFSMod($value);
				$info = pathinfo($modification['file']);
				$path = $info['dirname'] . '/' . $info['filename'] . '.' . $info['extension'] . '_';

				file_put_contents($path, $modification['xml']);
			} else {
				$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "modification m WHERE modification_id = " . (int)$value);
				$modification = $query->row;
				$query = $this->db->query("INSERT INTO " . DB_PREFIX . "modification VALUES('',
					'" . $this->db->escape($modification['name']) . "', 
					'" . $this->db->escape($modification['code'] . '_') . "', 
					'" . $this->db->escape($modification['author']) . "', 
					'" . $this->db->escape($modification['version']) . "', 
					'" . $this->db->escape($modification['link']) . "', 
					'" . $this->db->escape($modification['xml']) . "', 
					'" . 0 . "', 
					NOW()
				)");
			}
		}
		$this->mods = array();
	}

	public function enableMods($mids, $status){
		foreach ($mids as $key => $value) {
			if ($this->isFsMod($value)) {
					$modification = $this->getFSMod($value);
					$info = pathinfo($modification['file']);
					if ($status) {
						$extension = 'xml';
					}else{
						$extension = 'xml_';
					}

					$path = $info['dirname'] . '/' . $info['filename'] . '.' . $extension;
					if ($info['extension'] !== $extension) {
						file_put_contents($path, $modification['xml']);
						unlink($modification['file']);
					}
			} else {
				$query = $this->db->query("UPDATE " . DB_PREFIX . "modification SET status = '" . (int)$status . "' WHERE modification_id = " . (int)$value);
			}
		}
		$this->mods = array();
	}

	public function deleteMods($mids){
		foreach ($mids as $key => $value) {
			if ($this->isFsMod($value)) {
				$modification = $this->getFSMod($value);
				$info = pathinfo($modification['file']);
				unlink($modification['file']);
			}else{
				$query = $this->db->query("DELETE FROM " . DB_PREFIX . "modification WHERE modification_id = " . (int)$value);
			}
		}
		$this->mods = array();
	}

	public function downloadMods($mids){
		if (class_exists('ZipArchive')) {

			$tmp_mods_dir = 'tmp_mods';

			$archive_name = './' . $tmp_mods_dir . '/' . date('Y-m-d_H-i-s', time()) . '_mods.zip';

			@mkdir('./' . $tmp_mods_dir);
			
			$zip = new ZipArchive();
			
			$zip->open($archive_name, ZIPARCHIVE::CREATE);

			foreach ($mids as $key => $value) {
				if ($this->isFsMod($value)) {
					$modification = $this->getFSMod($value);
					if (!$modification) {
						continue;
					}
					$info = pathinfo($modification['file']);
					$path = './' . $tmp_mods_dir . '/' . $info['filename'] . '.' . $info['extension'];
				} else {
					$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "modification m WHERE modification_id = " . (int)$value);
					$modification = $query->row;
					$path = './' . $tmp_mods_dir . '/' . self::cleanFileName($modification['code'] . '.' . ($modification['status'] == 0 ? 'xml_' : 'xml'));
				}
				file_put_contents($path, $modification['xml']);
				$zip->addFile($path);
			}
			
			$zip->close();

			if(file_exists($archive_name)){
				header('Content-type: application/zip');
				header('Content-Disposition: attachment; filename="' . basename($archive_name) . '"');
				readfile($archive_name);
				@unlink($archive_name);
			}
			@unlink('./' . $tmp_mods_dir . '/*.*');
			self::rmdir_wc('./' . $tmp_mods_dir);
		}
	}

	public function searchText($query){
		$mods = $this->getMods();
		$goods = array();
		foreach ($mods as $key => $value) {
			$lines = preg_split('/\r\n|\r|\n/', $value['xml']);

			foreach($lines as $num => $line){
				$pos = strpos($line, $query);
				if($pos !== false){
					$goods[$value['modification_id']][$num] = self::highlightText(trim($line));
				}
			}
		}
		$result = array();
		foreach ($goods as $modification_id => $goods) {
			$result[$modification_id] = array(
				'modification_id' => $modification_id,
				'name' => $mods[$modification_id]['name'],
				'file' => basename($mods[$modification_id]['file']),
				'goods' => $goods
			);
		}
		return $result;
	}
	// static
	public static function formatTable($mods){
		$result = array();
		$result['cols'] = array(
			array('title' => '<input type="checkbox" id="selectall">'),
			array('title' => '#'),
			array('title' => 'Name'),
			array('title' => 'Code'),
			array('title' => 'Version'),
			array('title' => 'Author'),
			array('title' => 'Link'),
			array('title' => 'Size'),
			array('title' => 'Date added'),
			array('title' => 'Status')
		);
		foreach ($mods as $id => $mod){
			$result['rows'][] = array(
				'<input type="checkbox" value="' . $id . '" name="mids[]">', 
				$id, 
				$mod['name'], 
				$mod['code'], 
				$mod['version'], 
				$mod['author'], 
				(!empty($mod['link']) ? '<a href="'.$mod['link'].'" target="_blank">[link]</a>' : ''),
				self::humanBytes(self::getContentLength($mod['xml'])), 
				$mod['date_added'], 
				$mod['status']
			);
		}

		return $result;
	}

	public static function getContentLength($str){return ini_get('mbstring.func_overload') ? mb_strlen($str) : strlen($str);}

	public static function humanBytes($size){
		$filesizename = array(
			" Bytes",
			" KB",
			" MB",
			" GB",
			" TB",
			" PB",
			" EB",
			" ZB",
			" YB"
		);
		$size         = abs($size);
		return $size ? round($size / pow(1024, ($i = floor(log($size, 1024)))), 2) . $filesizename[$i] : '0 Bytes';
	}

	public static function highlightText($text)
	{
		$text = trim($text);
		$text = highlight_string("<?php " . $text, true);  // highlight_string() requires opening PHP tag or otherwise it will not colorize the text
		$text = trim($text);
		$text = preg_replace("|^\\<code\\>\\<span style\\=\"color\\: #[a-fA-F0-9]{0,6}\"\\>|", "", $text, 1);  // remove prefix
		$text = preg_replace("|\\</code\\>\$|", "", $text, 1);  // remove suffix 1
		$text = trim($text);  // remove line breaks
		$text = preg_replace("|\\</span\\>\$|", "", $text, 1);  // remove suffix 2
		$text = trim($text);  // remove line breaks
		$text = preg_replace("|^(\\<span style\\=\"color\\: #[a-fA-F0-9]{0,6}\"\\>)(&lt;\\?php&nbsp;)(.*?)(\\</span\\>)|", "\$1\$3\$4", $text);  // remove custom added "<?php "

		return $text;
	}
	public static function cleanFileName($filename){
		$filename = mb_ereg_replace("([^\w\s\d\-_~,;\[\]\(\).])", '', $filename);
		$filename = mb_ereg_replace("([\.]{2,})", '', $filename);
		return $filename;
	}

	public static function rmdir_wc($dir) {
		$files = array_diff(scandir($dir), array('.','..'));
		foreach ($files as $file) {
			(is_dir("$dir/$file")) ? self::rmdir_wc("$dir/$file") : unlink("$dir/$file");
		}
		return rmdir($dir);
	}
}

$db;

$oc_mod = new OCMod($db);

if (isset($_GET['action'])) {
	$response = array();

	switch ($_GET['action']) {
		case 'get':
			$response = $oc_mod->getMod($_POST['modification_id']);
			break;
		case 'save':
			$oc_mod->saveMod($_POST);
			$response = array('success'=>'Saved successfully');
			break;
		case 'search':
			$result = $oc_mod->searchText($_POST['query']);
			$response = array('result' => $result);
			break;
		case 'list':
			$mods = $oc_mod->getMods();
			$response = array('success' => 'success', 'table' => OCMod::formatTable($mods));
			break;
		case 'enable':
			if (isset($_POST['mids']) && !empty($_POST['mids'])) {
				parse_str($_POST['mids'], $mids);
				$oc_mod->enableMods($mids['mids'], (int)$_POST['status']);
				$mods = $oc_mod->getMods();
				$response = array('success' => 'Edited successfully', 'table' => OCMod::formatTable($mods));
			}
			break;
		case 'clone':
			if (isset($_POST['mids']) && !empty($_POST['mids'])) {
				parse_str($_POST['mids'], $mids);
				$oc_mod->cloneMods($mids['mids']);
				$mods = $oc_mod->getMods();
				$response = array('success' => 'Cloned successfully', 'table' => OCMod::formatTable($mods));
			}
			break;
		case 'remove':
			if (isset($_POST['mids']) && !empty($_POST['mids'])) {
				parse_str($_POST['mids'], $mids);
				$oc_mod->deleteMods($mids['mids']);
				$mods = $oc_mod->getMods();
				$response = array('success' => 'Removed successfully', 'table' => OCMod::formatTable($mods));
			}
			break;
		case 'download':
			if (isset($_GET['mids']) && !empty($_GET['mids'])) {
				$oc_mod->downloadMods($_GET['mids']);
			}
			break;
		default:
			# code...
			break;
	}
	header('Content-Type: application/json');
	echo json_encode($response);
	exit();
}

$mods = $oc_mod->getMods();

$files = $oc_mod->getModdedFilesList($mods);

if (!$mods) die('Modifications not found in ' . DB_PREFIX . 'modification and in /system/ and database');
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="">
    <meta name="author" content="">
    <link rel="icon" href="icon.png">
	
    <title>Modification editor v<?php echo $_version ?></title>

	<link rel="stylesheet" href="assets/bootstrap.min.css" />
	<link rel="stylesheet" href="assets/codemirror.css" />
	<link rel="stylesheet" href="assets/dataTables.bootstrap4.min.css" />
	<link rel="stylesheet" href="assets/datatables.min.css" />
	<link rel="stylesheet" href="assets/fixedHeader.dataTables.min.css" />
	<link rel="stylesheet" href="assets/nprogress.min.css" />
	<link rel="stylesheet" href="assets/font-awesome/css/font-awesome.min.css" />
<style>
html{overflow-y: scroll;}
body {
  font-size: .875rem;
}

.feather {
  width: 16px;
  height: 16px;
  margin-top: 2px;
  vertical-align: text-bottom;
}

.sidebar {
  position: fixed;
  /*top: 0;*/
  bottom: 0;
  left: 0;
  z-index: 100; 
  padding: 0;
  box-shadow: inset -1px 0 0 rgba(0, 0, 0, .1);
}

.sidebar-sticky {
  position: -webkit-sticky;
  position: sticky;
  top: 48px; 
  height: calc(100vh - 48px);
  overflow-x: hidden;
  overflow-y: auto; 
}

.sidebar .nav-item+.nav-item {
	border-bottom:1px solid #ddd;
	border-top:1px solid #fafafa;
}
.sidebar .nav-link {
  font-weight: 500;
  color: #333;
  border-radius:0;
  padding: .5rem 1rem;
}

.sidebar .nav-link .feather {
  margin-right: 4px;
  color: #999;
}

.sidebar .nav-link.active {
  color: white;
}
.sidebar .nav-link:not(.active):hover {
  background: #fafafa;
  box-shadow: inset -1px 0 0 rgba(0, 0, 0, .1);
}

.sidebar .nav-link:hover .feather,
.sidebar .nav-link.active .feather {
  color: inherit;
}

.sidebar-heading {
  font-size: .75rem;
  text-transform: uppercase;
}

.navbar-brand {
  padding-top: .75rem;
  padding-bottom: .75rem;
  font-size: 1rem;
  background-color: rgba(0, 0, 0, .25);
  box-shadow: inset -1px 0 0 rgba(0, 0, 0, .25);
}
.navbar{
	box-shadow: 0px 0px 2px 1px #999;
}
.navbar .form-control {
  /*padding: .75rem 1rem;*/
  border-width: 0;
  border-radius: 0;
}

.form-control-dark {
  color: #fff;
  background-color: rgba(255, 255, 255, .1);
  border-color: rgba(255, 255, 255, .1);
}

.form-control-dark:focus {
  border-color: transparent;
  box-shadow: 0 0 0 3px rgba(255, 255, 255, .25);
}
.border-top { border-top: 1px solid #e5e5e5; }
.border-bottom { border-bottom: 1px solid #e5e5e5; }
pre{
	margin: 5px;
	background: #f7f7f7;
	padding: 5px;
	margin-left: 0px;
}
table.dataTable thead th:before, table.dataTable thead th:after{
	/*content: ""!important;*/
}
table.dataTable, table.dataTable thead>tr>th{
	font-size: 10pt;
}
.CodeMirror{
    font-size: 9pt;
    font-family: Consolas;
    font-weight: normal;
    text-rendering: optimizeLegibility!important;
}
table.dataTable thead th{
    border-top: none;
}
table.datatable.fixedHeader-floating{
    position: fixed;
    display: block;
    top: 40px !important;
    background: #fff;
}
.btn:focus,.btn:active {
   outline: none !important;
   box-shadow: none;
}
.tab-content .tab-buttons{
	display: none;
}
table.dataTable.display tbody tr.selected>.sorting_1, table.dataTable.order-column.stripe tbody tr.selected>.sorting_1 {
    background-color: #d4d4d4 !important;
}
table.dataTable.stripe tbody tr.selected, table.dataTable.display tbody tr.selected {
    background-color: #ececec !important;
}
table.dataTable td.select-checkbox{
	padding: 10px 18px;
}
table.dataTable td.select-checkbox input:hover{
	cursor:pointer;
}

table.dataTable, table.th.sorting{
	color: #333!important;
}
nav.sidebar .fa{
	position: absolute;
    padding-top: 3px;
    right: 10px;
}
.nav-item.editor{

}
.nav-item.opened{
	background: #eee;
}
.modded_file{
    padding: 5px;
    /*background: #fafafa;*/
    /*margin-bottom: 10px;	*/
}
.mod_link{
	cursor: pointer;
    font-size: 1em;
    margin: 5px 0px;
}
.filename{
	font-size: 1.1em;
}
.mod_lines pre{
	cursor: pointer;
}
#byfile, #bymod{
	padding-top: 1em;
}
</style>
	<script type="text/javascript" src="assets/nprogress.min.js"></script>
  </head>

  <body>
	<nav class="navbar navbar-dark sticky-top bg-dark flex-md-nowrap p-0">
		<a class="navbar-brand col-sm-2 mr-0 " href="<?php echo basename(__FILE__) ?>">Modification editor v<?php echo $_version ?></a>
		<div class="col-sm-6 mr-0" id="btns-wrapper">
			<button type="button" class="btn btn-primary btn-sm open" onclick="openMods()">Open</button>
			<button type="button" class="btn btn-primary btn-sm clone" onclick="cloneMods()">Clone</button>
			<button type="button" class="btn btn-primary btn-sm enable" onclick="enableMods(1)">Enable</button>
			<button type="button" class="btn btn-primary btn-sm disable" onclick="enableMods(0)">Disable</button>
			<button type="button" class="btn btn-danger btn-sm remove" onclick="removeMods()">Remove</button>
			<button type="button" class="btn btn-info btn-sm reload" onclick="reloadTable()">Reload</button>
			<?php if (class_exists('ZipArchive')): ?>
			<button type="button" class="btn btn-info btn-sm download" onclick="downloadMods()">Download zip</button>
			<?php endif ?>
		</div>
		<div class="col-sm-4 mr-0">
			<div class="float-right">
				<div class="btn-group btn-group-primary" role="group" aria-label="Select">
					<button class="btn btn-success btn-sm updateocmod" data-toggle="tooltip" title="Copy refresh button link and paste it here [Ctrl]+[I]">Refresh modifications</button>
					<button class="btn btn-success btn-sm settingocmod">...</button>
				</div>
			</div>
		</div>
    </nav>

    <div class="container-fluid">
      <div class="row">
        <nav class="col-md-2 d-none d-md-block bg-light sidebar">
          <div class="sidebar-sticky">
            <ul class="nav flex-column nav-pills" id="navs-wrapper" role="tablist">
            	<li class="nav-item">
            		<a class="nav-link active" id="table-tab" data-toggle="pill" href="#table" role="tab" aria-controls="table" aria-selected="true">Modifications</a>
            	</li>
            	<li class="nav-item">
            		<a class="nav-link" id="files-tab" data-toggle="pill" href="#files" role="tab" aria-controls="files" aria-selected="true">Modified files</a>
            	</li>
            	<li class="nav-item">
            		<a class="nav-link" id="search-tab" data-toggle="pill" href="#search" role="tab" aria-controls="search" aria-selected="true">Code search</a>
            	</li>
            </ul>

          </div>
        </nav>

        <main role="main" class="col-md-9 ml-sm-auto col-lg-10 pt-3 px-4">
        	<div class="tab-content" id="tabs-wrapper">
        		
				<div class="tab-pane show active" id="table" role="tabpanel" aria-labelledby="table-tab">
					<form action="<?php echo basename(__FILE__); ?>?action=list" method="post" enctype="multipart/form-data" id="form-table" class="form-horizontal">
						<script>
							var table_data = <?php echo json_encode(OCMod::formatTable($mods)); ?>;
						</script>
						<table class="table datatable">
							<thead>
								<tbody>
									<tr>
										<td></td>
										<td></td>
										<td></td>
										<td></td>
										<td></td>
									</tr>
								</tbody>
							</thead>
						</table>						
					</form>
					<div class="tab-buttons">
						<button type="button" class="btn btn-primary btn-sm open" data-toggle="tooltip" title="[dblclick]" onclick="openMods()">Open</button>
						<button type="button" class="btn btn-primary btn-sm clone" onclick="cloneMods()">Clone</button>
						<button type="button" class="btn btn-primary btn-sm enable" onclick="enableMods(1)">Enable</button>
						<button type="button" class="btn btn-primary btn-sm disable" onclick="enableMods(0)">Disable</button>
						<button type="button" class="btn btn-danger btn-sm remove" onclick="removeMods()">Remove</button>
						<button type="button" class="btn btn-info btn-sm reload" onclick="reloadTable()">Reload</button>
						<?php if (class_exists('ZipArchive')): ?>
						<button type="button" class="btn btn-info btn-sm download" onclick="downloadMods()">Download zip</button>
						<?php endif ?>
					</div>
				</div>
				
				<div class="tab-pane show" id="files" role="tabpanel" aria-labelledby="files-tab">
					<form action="<?php echo basename(__FILE__); ?>?action=file" method="post" enctype="multipart/form-data" id="form-files" class="form-horizontal">
						
						<ul class="nav nav-tabs">
							<li class="nav-item">
								<a class="nav-link active" data-toggle="tab" href="#byfile">Files</a>
							</li>
							<li class="nav-item">
								<a class="nav-link" data-toggle="tab" href="#bymod">Mods</a>
							</li>
						</ul>
						<div class="tab-content">
							<div class="tab-pane show active" id="byfile">
							<?php foreach ($files['file'] as $filename => $mods): ?>
								<div class="modded_file">
									<span class="filename"><b><?php echo trim(str_replace(DIR_ROOT, '', realpath($filename)), '\\') ?></b></span><br>
								<?php foreach ($mods as $mod_id => $mod_data): ?>
									<span class="mod_link badge badge-info" onClick="openMod('<?php echo $mod_id ?>', true, <?php echo (int)end($mod_data['lines'])['line_num'] ?>)"><?php echo $mod_data['name'] ?>:<?php echo (int)end($mod_data['lines'])['line_num'] ?></span>
									<div class="mod_lines">
										<?php foreach ($mod_data['lines'] as $line => $data): ?>
											<?php $id = hash('crc32b', http_build_query(array($filename,$mod_id,$line,$data['search']))); ?>
											<pre onClick="openMod('<?php echo $mod_id ?>', true, <?php echo (int)end($mod_data['lines'])['op_line_num'] ?>)"><code><?php echo $line ?>:<?php echo OCMod::highlightText(trim($data['search']), true) ?></code></pre>
										<?php endforeach ?>
									</div>
								<?php endforeach ?>
								</div>
							<?php endforeach ?>
							</div>
							<div class="tab-pane" id="bymod">
							<?php foreach ($files['mod'] as $mod_id => $files2): ?>
								<div class="modded_file">
									<span class="mod_link badge badge-info" onClick="openMod('<?php echo $mod_id ?>', true);"><b><?php echo $files['mods'][$mod_id] ?></b></span><br>
								<?php foreach ($files2 as $file => $mod_data): ?>
									<span class="filename" onClick="openMod('<?php echo $mod_id ?>', true, <?php echo (int)end($mod_data['lines'])['line_num'] ?>)"><b><?php echo $mod_data['name'] ?>:<?php echo (int)end($mod_data['lines'])['line_num'] ?></b></span>
									<div class="mod_lines">
										<?php foreach ($mod_data['lines'] as $line => $data): ?>
											<?php $id = hash('crc32b', http_build_query(array($file,$mod_id,$line,$data['search']))); ?>
											<pre onClick="openMod('<?php echo $mod_id ?>', true, <?php echo (int)end($mod_data['lines'])['op_line_num'] ?>)"><code><?php echo $line ?>:<?php echo OCMod::highlightText(trim($data['search']), true) ?></code></pre>
										<?php endforeach ?>
									</div>
								<?php endforeach ?>
								</div>
							<?php endforeach ?>
							</div>
						</div>  
					</form>
					<div class="tab-buttons">
					</div>
				</div>

				<div class="tab-pane show" id="search" role="tabpanel" aria-labelledby="search-tab">
					<div id="search_result">
					</div>
					<div class="tab-buttons">
						<form action="<?php echo basename(__FILE__); ?>?action=search" method="post" enctype="multipart/form-data" id="form-search" class="form-horizontal">
							<input type="text" name="query" value="" placeholder="Search code in modifications" class="form-control form-control-sm col-sm-12" type="search" autocomplete="off" aria-label="Search" />
						</form>
					</div>
				</div>

        	</div>

        </main>
      </div>
    </div>

<script type="text/javascript" src="assets/jquery-3.4.1.min.js"></script>
<script type="text/javascript" src="assets/ajaxQueue.min.js"></script>
<script type="text/javascript" src="assets/popper.min.js"></script>
<script type="text/javascript" src="assets/bootstrap.min.js"></script>
<script type="text/javascript" src="assets/datatables.min.js"></script>
<script type="text/javascript" src="assets/dataTables.bootstrap4.min.js"></script>
<script type="text/javascript" src="assets/dataTables.fixedHeader.min.js"></script>
<script type="text/javascript" src="assets/Sortable.min.js"></script>
<script type="text/javascript" src="assets/codemirror.js"></script>
<script type="text/javascript" src="assets/codemirror.active-line.js"></script>
<script type="text/javascript" src="assets/codemirror.autorefresh.js"></script>
<script type="text/javascript" src="assets/xml.min.js"></script>
<script>
	var OCMod
	var editors = new Object();
	var editors_modded = new Object();
	var datatable = null;
	var sortable;
	var megamenu_atom;
	var modlink = null;

	editors.active = 0;
	
	NProgress.start();
	
	$(document).ready(function($) {
		updateDatatable(table_data);
		sortable = Sortable.create(document.getElementById('navs-wrapper'));
		modlink = localStorage.getItem('modlink');
		$('.datatable tbody').on('dblclick', 'tr', function (e) {
			event.preventDefault();
			if (event.target.nodeName !== 'INPUT') {
				var mid = $(this).find('input[name="mids\[\]"]').val();
				openMod(mid);
			}
		});
		
		$('#selectall').on('click', function (e) {
			tableSelectItems($('#selectall').prop('checked'));
		});

		$('#table-tab').on('shown.bs.tab', function (e) {
			datatable.fixedHeader.enable();
		});
		$('#files-tab').on('shown.bs.tab', function (e) {
			datatable.fixedHeader.disable();
		});
		
		$('nav.navbar button.updateocmod').on('click', function(event) {
			event.preventDefault();
			updateOcmod();
		});
		$('nav.navbar button.settingocmod').on('click', function(event) {
			event.preventDefault();
			modlink = null;
			localStorage.setItem('modlink', null);
			updateOcmod();
		});

		$(window).resize(function(event) {
			$('.dataTables_wrapper').css('height', ($(window).height()-60) +'px');
		});
		
		$('#form-search').on('submit', function(event) {
			event.preventDefault();
			search();
		});

		$('a[data-toggle="pill"]').on('mousemove', function(){
			clearTimeout(megamenu_atom);
			megamenu_atom = setTimeout(function(elem){$(elem).tab('show')}, 50, this);
		}).on('mouseleave', function(event) {
			clearTimeout(megamenu_atom);
		}).on('show.bs.tab', function (e) {
			var tabid = $(e.target).attr('href');
			$('#btns-wrapper').html($(tabid).find('.tab-buttons').html());
			$('#form-search').on('submit', function(event) {
				event.preventDefault();
				search();
			});
		});

		$('[data-toggle="tooltip"]').tooltip();
		NProgress.done();
	});

	function updateDatatable(data){

		var rows = data.rows;
		var cols = data.cols;

		if (datatable !== null) {
			datatable.destroy();
		}

		datatable = $('.datatable').DataTable({
			data: rows,
			responsive: true,
			fixedHeader: {
				header: true,
				footer: false,
				headerOffset: $('.navbar.navbar-dark').outerHeight()
			},
			columnDefs: [ {
				orderable: false,
				className: 'select-checkbox',
				targets:   0
			}],
			select: {
				style:    'os',
				selector: 'td:first-child'
			},
			columns: cols,
			paging:false,
			info:false,
			searching:false,
			initComplete : function(settings, json){

			}
		});
		tableSelectItems(false);
	}

	function addEditorTab(prefix, mid, data){
		$('#navs-wrapper #table-tab').parent().after('<li class="nav-item opened mod_' + mid + '"><a class="nav-link" id="' + prefix + '-' + mid + '-tab" data-toggle="pill" data-modification="' + mid + '" href="#' + prefix + '-' + mid + '" role="tab" aria-controls="' + prefix + '-' + mid + '" aria-selected="false">'+data['name']+'<i class="float-right fa fa-close" onClick="event.preventDefault();removeMod(\'' + mid + '\');"></i></a></li>');
		$('#tabs-wrapper').append('<div class="tab-pane mod_' + mid + '" id="' + prefix + '-' + mid + '" role="tabpanel" aria-labelledby="' + prefix + '-' + mid + '-tab"><textarea id="xml_'+mid+'" name="xml_'+mid+'">' + data['xml'] + '</textarea></div>');
		$('#' + prefix + '-' + mid).append('<div class="tab-buttons mod_' + mid + '"><div id="button-panel">'
			+ '<button type="button" class="btn btn-success btn-sm save" data-toggle="tooltip" data-placement="bottom" title="[Ctrl]+[S]" onclick="saveMod(\''+mid+'\')">Save</button> ' 
			+ '<button type="button" class="btn btn-primary btn-sm reload" data-toggle="tooltip" data-placement="bottom" title="[Ctrl]+[R]" onclick="reloadMod(\''+mid+'\')">Reload</button>  ' 
			+ '<button type="button" class="btn btn-primary btn-sm undo" data-toggle="tooltip" data-placement="bottom" title="[Ctrl]+[Z]" onclick="undoMod(\''+mid+'\')">Undo</button> ' 
			+ '<button type="button" class="btn btn-primary btn-sm redo" data-toggle="tooltip" data-placement="bottom" title="[Ctrl]+[Y]" onclick="redoMod(\''+mid+'\')">Redo</button> ' 
			+ '</div></div>');

		// feather.replace();
	}

	function updateEditorTab(prefix, mid, data){
		var xmid = 'xml_' + mid;
		$('#navs-wrapper #'+prefix+'-' + mid + '-tab').html(data['name']+'<i class="float-right fa fa-close" onClick="event.preventDefault();removeMod(\'' + mid + '\');"></i>');
		editors[xmid].setValue(data['xml']);
		editors[xmid].refresh();
		editors[xmid].clearHistory();
	}

	function initCodeMirror(xmid){
		editors[xmid] = CodeMirror.fromTextArea(document.getElementById(xmid), {
			mode: "xml",
			integer: 2,
			lineNumbers: true,
			viewportMargin: Infinity,
			autofocus: true,
			autoRefresh:true,
			alignCDATA: true,
			lineWrapping: true,
			indentWithTabs: true,
			indentUnit: 2,
			styleActiveLine: true,
			styleActiveSelected: true,
			autoCloseTags: true,
			extraKeys: {
				"Ctrl-R": editorCtrlR,
				"Ctrl-S": editorCtrlS,
				"Ctrl-I": editorCtrlI,
			}
		});
		editors[xmid].setSize("100%", "100%");
		editors[xmid].on('change', function(event) {
			updateButtons(xmid);
		});
		editors[xmid].focus();
	}
	
	function editorCtrlR(e) {
    	reloadMod(editors.active);
	}

    function editorCtrlS(e) {
		saveMod(editors.active);
    }

    function editorCtrlI(e) {
		updateOcmod();
    }

	function removeMod(mid){
		$('.mod_' + mid).remove();
		var xmid = 'xml_' + mid;
		editors[xmid] = undefined;
		$('#table-tab').tab('show');
	}

	function openMod(mid, activate = true, line = 0){
		var xmid = 'xml_' + mid;

		if (editors[xmid] !== undefined) {
			if (activate) {
				showEditor(mid, line);
			}
		}else{
			NProgress.start();

			datatable.fixedHeader.disable();
			
			var json = new Array;
			json['name'] = 'Loading...';
			json['xml'] = '';

			addEditorTab('mod', mid, json);
			
			initCodeMirror(xmid);

			$.ajaxQueue({
				url: '<?php echo basename(__FILE__) . "?action=get" ?>',
				type: 'POST',
				dataType: 'json',
				data: {modification_id: mid},
				success: function(json){
					updateEditorTab('mod', mid, json);
					if (activate) {
						setTimeout(function(){
							showEditor(mid, line);
						}, 10);
					}
					updateButtons(xmid);
					$('a[data-toggle="pill"][data-modification="'+mid+'"]').on('shown.bs.tab', function (e) {
						var t_mid = $(e.target).data('modification');
						editors.active = t_mid;
						t_xmid = 'xml_' + t_mid;
						editors[t_xmid].focus();
						datatable.fixedHeader.disable();
						updateButtons(xmid);
						$('#btns-wrapper button[data-toggle="tooltip"]').tooltip();
					}).on('mousemove', function(){
						clearTimeout(megamenu_atom);
						megamenu_atom = setTimeout(function(elem){$(elem).tab('show')}, 150, this);
					}).on('mouseleave', function(event) {
						clearTimeout(megamenu_atom);
					}).on('show.bs.tab', function (e) {
						var tabid = $(e.target).attr('href');
						$('#btns-wrapper').html($(tabid).find('.tab-buttons').html());
					});
				}
			});
			NProgress.done();
		}
	}

	function saveMod(mid){		
		$('#btns-wrapper').find('button.save').prop('disabled', true);

		var xmid = 'xml_' + mid;

		NProgress.start();
		$.ajaxQueue({
			url: '<?php echo basename(__FILE__) . "?action=save" ?>',
			type: 'POST',
			dataType: 'json',
			data: {
				modification_id: mid,
				xml: editors[xmid].getValue()
			},
			success: function(json){			
				NProgress.done();
			}
		});
		$('#btns-wrapper').find('button.save').prop('disabled', false);
	}

	function reloadMod(mid){
		$('#btns-wrapper').find('button.reload').prop('disabled', true);

		var xmid = 'xml_' + mid;

		NProgress.start();
		$.ajaxQueue({
			url: '<?php echo basename(__FILE__) . "?action=get" ?>',
			type: 'POST',
			dataType: 'json',
			data: {
				modification_id: mid,
			},
			success: function(json){
				editors[xmid].setValue(json['xml']);
				editors[xmid].refresh();
				editors[xmid].clearHistory();
				updateButtons(xmid);
				NProgress.done();
			}
		});

		$('#btns-wrapper').find('button.reload').prop('disabled', false);
	}

	function search(){
		NProgress.start();
		$.ajaxQueue({
			url: '<?php echo basename(__FILE__) . "?action=search" ?>',
			type: 'POST',
			dataType: 'json',
			data: {
				query: $('input[name=query]').val(),
			},
			success: function(json){
				html = '';
				$.each(json['result'], function(index, val) {
					html += '<span class="filename"><b>' + val['name'] + ' | ' + val['file'] + '</b></span></br>';
					html += '<div class="mod_lines">';
					$.each(val['goods'], function(index2, val2) {
						html += '<pre onClick="openMod(\'' + val['modification_id'] + '\', true, ' + index2 + ')">';
						html += '<code>' + index2 + ':' + val2 + '</code>';
						html += '</pre>';
					});
					html += '</div>';
				});
				
				if (html == '') {
					html = '<span class="filename"><b>Empty</b></span>';
				}

				$('#search_result').html(html);
				NProgress.done();
			}
		});
		NProgress.done();
	}

	function updateButtons(xmid){
		if (editors[xmid] !== undefined) {
			var history = editors[xmid].historySize();
			$('#btns-wrapper .undo').html('Undo [' + history.undo + ']');
			$('#btns-wrapper .redo').html('Redo [' + history.redo + ']');
		}
	}

	function undoMod(mid){
		var xmid = 'xml_' + mid;
		editors[xmid].undo();
		updateButtons(xmid);
	}

	function redoMod(mid){
		var xmid = 'xml_' + mid;
		editors[xmid].redo();
		updateButtons(xmid);
	}

	function openMods(){
		$('#btns-wrapper').find('button.open').prop('disabled', true);
		NProgress.start();
		datatable.rows().eq(0).each( function ( index ) {
			$checkbox = $(datatable.row(index).node()).find('input[name="mids\[\]"]');
	    	if ($checkbox.prop('checked')) {
	    		openMod($(datatable.row(index).node()).find('input[name="mids\[\]"]').val(), false);
	    	}
		});
		tableSelectItems(false);
		NProgress.done();
		$('#btns-wrapper').find('button.open').prop('disabled', false);
	}

	function cloneMods(){
		$('#btns-wrapper').find('button.clone').prop('disabled', true);
		NProgress.start();
		$.ajaxQueue({
			url: '<?php echo basename(__FILE__) . "?action=clone" ?>',
			type: 'POST',
			dataType: 'json',
			data: {
				mids: $('form#form-table').serialize(),
			},
			success: function(json){
				updateDatatable(json['table']);
				NProgress.done();
			}
		});
		NProgress.done();
		$('#btns-wrapper').find('button.clone').prop('disabled', false);
	}

	function enableMods(status = false){
		$('#btns-wrapper').find('button.enable,button.disable').prop('disabled', true);
		NProgress.start();
		$.ajaxQueue({
			url: '<?php echo basename(__FILE__) . "?action=enable" ?>',
			type: 'POST',
			dataType: 'json',
			data: {
				status: status,
				mids: $('form#form-table').serialize(),
			},
			success: function(json){
				updateDatatable(json['table']);
				NProgress.done();
			}
		});
		NProgress.done();
		$('#btns-wrapper').find('button.enable,button.disable').prop('disabled', false);
	}

	function removeMods(){
		$('#btns-wrapper').find('button.remove').prop('disabled', true);
		if (confirm("Are you shure?")) {
			NProgress.start();
			$.ajaxQueue({
				url: '<?php echo basename(__FILE__) . "?action=remove" ?>',
				type: 'POST',
				dataType: 'json',
				data: {
					mids: $('form#form-table').serialize(),
				},
				success: function(json){
					updateDatatable(json['table']);
					NProgress.done();
				}
			});
			NProgress.done();
		}
		$('#btns-wrapper').find('button.remove').prop('disabled', false);
	}
	
	function downloadMods(){
		$('#btns-wrapper').find('button.download').prop('disabled', true);
		window.location.href = '<?php echo basename(__FILE__) . "?action=download" ?>&' + $('form#form-table').serialize();
		$('#btns-wrapper').find('button.download').prop('disabled', false);
	}
	
	function reloadTable(){
		$('#btns-wrapper').find('button.reload').prop('disabled', true);
		NProgress.start();
		$.ajaxQueue({
			url: '<?php echo basename(__FILE__) . "?action=list" ?>',
			type: 'POST',
			dataType: 'json',
			success: function(json){
				updateDatatable(json['table']);
				NProgress.done();
			}
		});
		NProgress.done();
		$('#btns-wrapper').find('button.reload').prop('disabled', false);
	}

	function showEditor(mid, line = 0){
		$('#mod-'+mid+'-tab').tab('show');
		var xmid = 'xml_' + mid;
		updateButtons(xmid);
		if (line >= 1) {
			editors[xmid].setCursor(line);
		}
		editors[xmid].focus();
	}
	
	function tableSelectItems(mode = false){
		if (!mode) {
			$('#selectall').prop('checked', false);
		}
		datatable.rows().eq(0).each( function ( index ) {
	    	var node = datatable.row(index).node();
	    	var row = $(node).find('input[name="mids\[\]"]').prop('checked', mode);
		});
	}

	function updateOcmod(){

		if (modlink == null || modlink == "null") {
			modlink = prompt('Enter refresh link button below');
			if (modlink == '' || modlink == null) {
				modlink = null;
			} else {
				localStorage.setItem('modlink', modlink);
				NProgress.start();
				updateOcmodQuery();
				NProgress.done();
			}
		}else{
			localStorage.setItem('modlink', modlink);
			NProgress.start();
			updateOcmodQuery();
			NProgress.done();
		}
	}

	function updateOcmodQuery(){
		$.ajaxQueue({
			url: modlink,
			type: 'GET',
			dataType: 'HTML',
			success : function (html){
				if ($(html).find('.alert.alert-success').length) {
					alert($(html).find('.alert.alert-success').text());
				}else{
					modlink = null;
					alert($(html).find('.alert.alert-danger').text());
					updateOcmod();
				}
			}
		});				
	}
</script>
    <!-- Icons -->
    <!-- <script src="https://unpkg.com/feather-icons/dist/feather.min.js"></script> -->
    <script>
      // feather.replace()
    </script>
  </body>

</html>