<?php
// License: MIT
// Author: Pitan

define('DEFAULT_GROUP_ID', "ml.pkom");
define('DEFAULT_VERSION', "1.0.6");
define('GENERATE_INDEX_HTML', true);
define('AUTO_PUSH', true);
define('NODISPLAY_FILE', array('index.html', 'maven.php', 'CNAME', 'mcpitanlibarch.php'));

$DEFAULT_GROUP_ID = DEFAULT_GROUP_ID;
$DEFAULT_VERSION = DEFAULT_VERSION;

if (!function_exists('str_ends_with')) {
	function str_ends_with($haystack, $needle) {
		return $needle === ""
		 || substr($haystack, -strlen($needle)) === $needle;
	}
}

if (isset($_GET['genindex'])) {
	gen_dirlist_html(".", true);
}

function gen_dirlist_html($dir = ".", $regen = false) {
	global $group_path, $artifact_id, $version;
	
	$links = '';
	foreach(glob($dir . '/*') as $key => $value) {
		$filename = basename($value);
		if (in_array($filename, NODISPLAY_FILE)) {
			continue;
		}
		
		$displayname = $filename;
		$len = strlen($filename);
		$namemax = 50;
		$c = ($namemax + 1) - $len;
		if ($len > $namemax) {
			$displayname = substr($filename, 0, $namemax - 3) . "..&gt;";
			$c = 1;
		}
		if (is_dir($value)) {
			$links .= '<a href="./' . $filename . '/">' . $displayname . '/</a>' . str_repeat(" ", $c - 1) . date("d-M-Y H:i", filemtime($value)) . "                   -\n";
			if (isset($_GET['genindex']) || (strpos($group_path . "/" . $artifact_id . "/" . $version, $filename) !== false)) {
				gen_dirlist_html($value);
			}
		} else {
			$links .= '<a href="./' . $filename . '">' . $displayname . '</a>' . str_repeat(" ", $c) . date("d-M-Y H:i", filemtime($value)) . str_pad(filesize($value), 20, " ", STR_PAD_LEFT) . "\n";
		}
	}
	$links = substr($links, 0, -1);
	$displaydir = substr($dir, 1) . '/';
$html = <<<EOD
<html>
	<head>
		<title>Index of {$displaydir}</title>
	</head>
	<body>
		<h1>Index of {$displaydir}</h1>
		<hr>
		<pre><a href="../">../</a>
{$links}</pre>
		<hr>
	</body>
</html>
EOD;
	file_put_contents($dir . '/index.html', $html);
}

function gen_hash_file($filename) {
	file_put_contents($filename . '.md5', hash_file('md5', $filename));
	file_put_contents($filename . '.sha1', hash_file('sha1', $filename));
	file_put_contents($filename . '.sha256', hash_file('sha256', $filename));
	file_put_contents($filename . '.sha512', hash_file('sha512', $filename));
}

if (isset($_POST['group_id']) && isset($_POST['artifact_id']) && isset($_POST['version']) && isset($_FILES['upload'])) {
	global $group_path, $artifact_id, $version;
	$group_id = $_POST['group_id'];
	$artifact_id = $_POST['artifact_id'];
	$version = $_POST['version'];
	$group_path = str_replace('.', '/', $group_id);
	$path = './' . $group_path . '/' . $artifact_id . '/' . $version;
	$basename = $artifact_id . '-' . $version;
	$files = $_FILES['upload'];
	
	if (!is_dir($path)) {
		mkdir($path, 0777, true);
	} else {
		$glob = glob($path . '/*');
		foreach($glob as $key => $value) {
			unlink($value);
		}
	}
	
	$c = count($files['name']);
	
	$putted_jar = $putted_source = $putted_pom = false;
	
	for($i=0; $i<$c; $i++) {
		$filename = $files['name'][$i];
		$putname = $filename;
		$tmpfilepath = $files['tmp_name'][$i];
		if (str_ends_with($filename, '-sources.jar')) {
			$putname = $basename . '-sources.jar';
			$putted_source = true;
		} else if (str_ends_with($filename, '.pom')) {
			$putname = $basename . '.pom';
			$putted_pom = true;
		} else if (str_ends_with($filename, '.jar')) {
			$putname = $basename . '.jar';
			$putted_jar = true;
		}
		move_uploaded_file($tmpfilepath, $path . '/' . $putname);
	}
	$pompath = $path . '/' . $basename . '.pom';
	if (!file_exists($pompath)) {
$pom_source = <<<EOD
<?xml version="1.0" encoding="UTF-8"?>
<project xmlns="http://maven.apache.org/POM/4.0.0" xsi:schemaLocation="http://maven.apache.org/POM/4.0.0 https://maven.apache.org/xsd/maven-4.0.0.xsd" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
  <modelVersion>4.0.0</modelVersion>
  <groupId>{$group_id}</groupId>
  <artifactId>{$artifact_id}</artifactId>
  <version>{$version}</version>
</project>
EOD;
		file_put_contents($pompath, $pom_source);
	}
	$glob = glob($path . '/*');
	foreach($glob as $key => $value) {
		$filepath = $value;
		gen_hash_file($filepath);
	}
	$files = array();
	$glob = glob($path . '/*');
	foreach($glob as $key => $value) {
		$files[] = basename($value);
	}
	$arr = array(
		'group_id' => $group_id,
		'artifact_id' => $artifact_id,
		'version' => $version,
		'files' => $files
	);
	file_put_contents($path . '/' . $basename . '.json', json_encode($arr, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
	$time = date("YmdHis");
	$files_arr = array_values(glob(dirname($path) . '/*'));
	usort($files_arr, "strnatcmp");
	$versions = $release = '';
	foreach($files_arr as $value) {
		if (is_dir($value)) {
			$versions .= '<version>' . basename($value) . '</version>' . "\n";
			$release = basename($value);
		}
	}
	$versions = substr($versions, 0, -1);
$meta_source = <<<EOD
<?xml version="1.0" encoding="utf-8"?>
<metadata>
  <groupId>{$group_id}</groupId>
  <artifactId>{$artifact_id}</artifactId>
  <versioning>
    <latest>{$version}</latest>
    <release>{$release}</release>
    <versions>
      {$versions}
    </versions>
    <lastUpdated>{$time}</lastUpdated>
  </versioning>
</metadata>
EOD;
	$metafile = dirname($path) . '/maven-metadata.xml';
	file_put_contents($metafile, $meta_source);
	gen_hash_file($metafile);
	
	if (GENERATE_INDEX_HTML) {
		gen_dirlist_html();
	}
	echo "created. <a href=\"./{$path}/\">[move]</a>";
	if (AUTO_PUSH) {
		exec('git add *');
		exec('git commit -m "auto push (' . $artifact_id . '-' . $version . ')"');
		exec('git push');
		echo "pushed.";
	}
}
?>
<html>
	<head>
		<title>Maven Repository Generator</title>
	</head>
	<body>
		<script>
		function checkfile() {
			var filename = document.getElementById("upload").files;
			var filelist = "";
			for (var i=0; i<filename.length; i++){
				filelist += "&nbsp;&nbsp;&nbsp;" + filename[i].name + "<br />"
			}
			document.getElementById("fileview").innerHTML = filelist;
		}
		</script>
		<form method="post" enctype="multipart/form-data">
			group id: <input type="text" name="group_id" value="<?php echo $DEFAULT_GROUP_ID; ?>" placeholder="com.example" required /><br />
			artifact id: <input type="text" name="artifact_id" value="mcpitanlibarch-fabric+1.19" placeholder="example" required /><br />
			version: <input type="text" name="version" value="<?php echo $DEFAULT_VERSION; ?>" placeholder="1.0.0" required /><br />
			files: <input type="file" name="upload[]" id="upload" onchange="checkfile()" multiple required /><br />
			<pre id="fileview">&nbsp;&nbsp;&nbsp;file: xxx.jar<br />&nbsp;&nbsp;&nbsp;source: xxx-sources.jar<br />&nbsp;&nbsp;&nbsp;pom: xxx.pom (any)<br /></pre>
			<button type="submit">Create</button>
		</form>
		<form method="get">
			<input type="hidden" name="genindex" value="true" />
			<button type="submit">Regenerate index.html</button>
		</form>
	</body>
</html>