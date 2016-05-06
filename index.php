<?php
header("Access-Control-Allow-Origin: *");
define("__BUTTERPHP_ALL__","R");
define("__BASE_PATH__","http://packages.0fury.de/");
require_once "./vendor/squarerootfury/butterphp/src/autoload.php";	
require_once "./Interfaces.php";
 //Include the autload file from /src/
$router = new \BTRRouter();
$router->Route("/", function($php){
   	http_response_code(406);
});
$router->Route("/package/",function($php){
	//Abort if now package name is provided
	if (!property_exists($php->get,"name")){
		NotFound();
	}
    $needle = strtolower($php->get->name);
    if (file_exists("./vendor/gethitchhike/units/".$needle."/")){
    	PackageInfo($needle);
    	Ping($php);
    }
    else{
    	NotFound();
    }
},array("name"));
$router->Route("/list/",function($php){
	$files = array_values(array_filter(scandir("./vendor/gethitchhike/units"),function($x){
		return $x != "." && $x != ".." && $x != ".git" && $x != "composer.json";
	}));
	echo json_encode($files);
});
$router->Route("/trending/",function($php){
	echo json_encode(Trending(),JSON_PRETTY_PRINT);
});
$router->Execute();

function PackageInfo($packagename){
	require_once "./vendor/gethitchhike/units/$packagename/".$packagename.".src";
	$foo = new $packagename();
	$fileList = array();
	$filesFromUnit = $foo->GetFiles();
	foreach($filesFromUnit as $file){
		$path = str_replace(".php",".src",__BASE_PATH__."vendor/gethitchhike/units/$packagename/".$file);

		$fileList[] = $path;
	}
	$output = new \stdClass();
	$output->Name = $packagename;
	$output->Description = $foo->GetName();
	$output->Requires = $foo->Requires();
	$output->Version = $foo->GetVersion();
	$output->Tags = GetHashTags($output->Description);
	$output->Description = preg_replace("/\#(?<hashtag>[^\s]+)/", "", $output->Description);
	$output->Files = $fileList;
	$output->Hits = HitsForPackage($packagename);
	$output->Downloads = DownloadsForPackage($packagename);
	echo json_encode($output,JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}
function SetUp(){
	$db = new PDO('sqlite:./meta.sqlite');
	$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	$db->exec("CREATE TABLE IF NOT EXISTS stats (
	              id INTEGER PRIMARY_KEY,
	              timestamp INTEGER,
	              target TEXT,
	              UserAgent TEXT)");
}
function Ping($php){
	if (!file_exists("./meta.sqlite")){
		Setup();
	}
	$db = new PDO('sqlite:./meta.sqlite');
	$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	$insert = "INSERT INTO stats (timestamp,target,UserAgent) 
	        VALUES (:timestamp,:target,:ua)";
	$stmt = $db->prepare($insert);
	$stmt->bindParam(':timestamp', time());
	$stmt->bindValue(':target',$php->get->name);
	$stmt->bindValue(':ua',$_SERVER["HTTP_USER_AGENT"]);
	$stmt->execute();
	
}
function Trending(){
	$db = new PDO('sqlite:./meta.sqlite');
	$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);	
	$query = "Select target,(Select count(timestamp) from Stats where target = s.target) as hits,(Select count(timestamp) from Stats where target = s.target and UserAgent like 'hm%') as downloads from stats s group by target order by hits, downloads desc";
	$stmt = $db->prepare($query);
	$stmt->execute();
	$got = $stmt->fetchAll();
	$data = array();
	foreach($got as $value){
		$obj = new \stdClass();
		$obj->Hits = (int)$value["hits"];
		$obj->Downloads = (int)$value["downloads"];
		$data[$value["target"]] = $obj;
	}
	return $data;
}
function HitsForPackage($name){	
	$db = new PDO('sqlite:./meta.sqlite');
	$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);	
	$query = "Select count(timestamp) as hits from stats where target = :target";
	$stmt = $db->prepare($query);
	$stmt->bindParam(':target', $name); 
	$stmt->execute();
	$got = $stmt->fetchAll();
	return (int)$got[0]["hits"];
}
function DownloadsForPackage($name){	
	$db = new PDO('sqlite:./meta.sqlite');
	$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);	
	$query = "Select count(timestamp) as downloads from stats where target = :target and UserAgent like 'hm%'";
	$stmt = $db->prepare($query);
	$stmt->bindParam(':target', $name); 
	$stmt->execute();
	$got = $stmt->fetchAll();
	return (int)$got[0]["downloads"];
}
function GetHashTags($string){
	$matches = array();
	$pattern ="/\#(?<hashtag>[^\s]+)/";
	preg_match_all($pattern, $string, $matches);
	return $matches["hashtag"];
}
function NotFound(){
	header("HTTP/1.0 404 Not Found");
	die();
}
