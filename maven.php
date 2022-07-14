<?php
define('DEFAULT_GROUP_ID', "ml.pkom");
define('DEFAULT_VERSION', "1.0.0");

$DEFAULT_GROUP_ID = DEFAULT_GROUP_ID;
$DEFAULT_VERSION = DEFAULT_VERSION;

if (!function_exists('str_ends_with')) {
	function str_ends_with($haystack, $needle) {
		return $needle === ""
		 || substr($haystack, -strlen($needle)) === $needle;
	}
}

function gen_hash_file($filename) {
	file_put_contents($filename . '.md5', hash_file('md5', $filename));
	file_put_contents($filename . '.sha1', hash_file('sha1', $filename));
	file_put_contents($filename . '.sha256', hash_file('sha256', $filename));
	file_put_contents($filename . '.sha512', hash_file('sha512', $filename));
}

if (isset($_POST['group_id']) && isset($_POST['artifact_id']) && isset($_POST['version']) && isset($_FILES['upload'])) {
	$group_id = $_POST['group_id'];
	$artifact_id = $_POST['artifact_id'];
	$version = $_POST['version'];
	$path = './' . str_replace('.', '/', $group_id) . '/' . $artifact_id . '/' . $version;
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
	$versions = '';
	foreach($files_arr as $value) {
		if (is_dir($value)) {
			$versions .= '<version>' . basename($value) . '</version>' . "\n";
		}
	}
	$versions = substr($versions, 0, -1);
$meta_source = <<<EOD
<?xml version="1.0" encoding="utf-8"?>
<metadata>
  <groupId>{$group_id}</groupId>
  <artifactId>{$artifact_id}</artifactId>
  <versioning>
    <release>{$version}</release>
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
	echo "created. <a href=\"./{$path}/\">[move]</a>";
	exit;
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
			artifact id: <input type="text" name="artifact_id" value="" placeholder="example" required /><br />
			version: <input type="text" name="version" value="<?php echo $DEFAULT_VERSION; ?>" placeholder="1.0.0" required /><br />
			files: <input type="file" name="upload[]" id="upload" onchange="checkfile()" multiple required /><br />
			<pre id="fileview">&nbsp;&nbsp;&nbsp;file: xxx.jar<br />&nbsp;&nbsp;&nbsp;source: xxx-sources.jar<br />&nbsp;&nbsp;&nbsp;pom: xxx.pom (any)<br /></pre>
		<button type="submit">Create</button>
	</body>
</html>