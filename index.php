<?php
define("__BUTTERPHP_ALL__","R");
define("__BASE_PATH__","http://packages.0fury.de/");
require_once "./vendor/squarerootfury/butterphp/src/autoload.php";	
 //Include the autload file from /src/
$router = new \BTRRouter();
//Note: You _always_  need a default route for /
$router->Route("/", function($php){
    //do something. the $php parameter is very important
    //e. g. you can access a $_GET param "foo", with $php->get->foo
    echo "index";
});
$router->Route("/package/",function($php){
	//Abort if now package name is provided
	if (!property_exists($php->get,"name")){
		NotFound();
	}
    $needle = strtolower($php->get->name);
    if (file_exists("./vendor/gethitchhike/units/".$needle."/")){
    	PackageInfo($needle);
    }
    else{
    	NotFound();
    }
},array("name"));
$router->Execute();

function PackageInfo($packagename){
	require_once "./vendor/gethitchhike/units/$packagename/".$packagename.".php.txt";
	$foo = new $packagename();
	$fileList = array();
	$filesFromUnit = $foo->GetFiles();
	foreach($filesFromUnit as $file){
		$path = str_replace(".php",".php.txt",__BASE_PATH__."vendor/gethitchhike/units/$packagename/".$file);

		$fileList[] = $path;
	}
	$output = new \stdClass();
	$output->Name = $packagename;
	$output->Description = $foo->GetName();
	$output->Requires = $foo->Requires();
	$output->Version = $foo->GetVersion();
	$output->Files = $fileList;
	echo json_encode($output,JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}
function NotFound(){
	header("HTTP/1.0 404 Not Found");
	die();
}