<?php
define('__CC__', 1);// Security
$version = "1.3";

/* Uncomment for debugging

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

*/

//Functions
require_once("config.php");
require_once(dirname(__FILE__)."/lib/api.class.php");
require_once(dirname(__FILE__)."/lib/medoo.php");

function json_headers(){
    header("Access-Control-Allow-Orgin: *");
    header("Access-Control-Allow-Methods: *");
    header("Content-Type: application/json");
}

class YoMangaAPI extends API
{
    //protected $User;


    public function __construct($request, $origin) {
        parent::__construct($request);
        
        global $api_database;
        
        if (!array_key_exists('key', $this->request)) {
            throw new Exception('No API Key provided');
        //} else if (!$APIKey->verifyKey($this->request['key'], $origin)) {
        }
        
        if (!$api_database->has("tokens", [
            "token" => $this->request['key']
        ])) {
            throw new Exception('Invalid API Key');
        }

        //$this->User = $User;
    }

    /**
     * Get list of all comics
     */
     protected function all() {
        if ($this->method == 'GET') {
            global $comic_database;
            global $url_prefix;
            
            $comics = $comic_database->select("fs_comics", [
                "id",
                "name",
                "author",
                "artist",
                "description",
                "format",
                "thumbnail",
                "stub",
                "uniqid"
            ], [
                "hidden" => 0
            ]);
            
            foreach($comics as $key => $comic){
                $comics[$key]["thumbnail"] = $url_prefix.$comic["stub"]."_".$comic["uniqid"]."/".$comic["thumbnail"];
            }
            return $comics;
        } else {
            return "Only accepts GET requests";
        }
     }
     
     /**
     * Get latest 25 chapters (all comics together)
     */
     protected function latest() {
        if ($this->method == 'GET') {
            global $comic_database;
            global $url_prefix;
            
            $latest = $comic_database->select("fs_chapters", [
                "id",
                "comic_id",
                "chapter",
                "subchapter",
                "volume",
                "language",
                "created"
            ], [
                "hidden" => 0,
                "ORDER" => "id DESC",
                "LIMIT" => 25
            ]);
            
            $comics = $comic_database->select("fs_comics", [
                "id",
                "name",
                "thumbnail",
                "stub",
                "uniqid"
            ], [
                "hidden" => 0
            ]);
            
            foreach($latest as $key => $chapter){
                foreach($comics as $comic){
                    if($comic["id"] == $chapter["comic_id"]){
                        $latest[$key]["thumbnail"] = $url_prefix.$comic["stub"]."_".$comic["uniqid"]."/".$comic["thumbnail"];
                        $latest[$key]["comic_name"] = $comic["name"];
                    }
                }
            }
            return $latest;
        } else {
            return "Only accepts GET requests";
        }
     }
     
    /**
     * Get comic's chapters
     */
     protected function comic() {
        if ($this->method == 'GET') {
            global $comic_database;
            $chapters = Array('error' => "Invalid comic ID");
            if(!empty($this->verb)  && is_numeric($this->verb)){
                $chapters = $comic_database->select("fs_chapters", [
                    "id",
                    "chapter",
                    "subchapter",
                    "volume",
                    "language",
                    "created"
                ], [
                    "AND" => [
                        "hidden" => 0,
                        "comic_id" => $this->verb
                    ]
                ]);
            }
            return $chapters;
        } else {
            return "Only accepts GET requests";
        }
     }

     
    /**
     * Get chapter's pictures
     */
     protected function chapter() {
        if ($this->method == 'GET') {
            global $comic_database;
            global $dbname;
            global $url_prefix;
            $pages = Array('error' => "Invalid chapter ID");
            if(!empty($this->verb) && is_numeric($this->verb)){                
                $chapter = $comic_database->select("fs_chapters", [
                    "stub",
                    "uniqid",
                    "comic_id"
                ], [
                    "AND" => [
                        "hidden" => 0,
                        "id" => $this->verb
                    ]
                ]);
                
                $comic = $comic_database->select("fs_comics", [
                    "stub",
                    "uniqid"
                ], [
                    "AND" => [
                        "hidden" => 0,
                        "id" => $chapter[0]["comic_id"]
                    ]
                ]);

                $pages = $comic_database->select("fs_pages", [
                    "id",
                    "filename",
                    "height",
                    "width",
                    "mime",
                    "size"
                ], [
                    "AND" => [
                        "hidden" => 0,
                        "chapter_id" => $this->verb
                    ]
                ]);
                
                foreach($pages as $key => $page){
                    $pages[$key]["url"] = $url_prefix.$comic[0]["stub"]."_".$comic[0]["uniqid"]."/".$chapter[0]["stub"]."_".$chapter[0]["uniqid"]."/".$page["filename"];
                }
            }
            
            
            return $pages;
        } else {
            return "Only accepts GET requests";
        }
     }
}

////////////////////////////////////////


// Requests from the same server don't have a HTTP_ORIGIN header
if (!array_key_exists('HTTP_ORIGIN', $_SERVER)) {
    $_SERVER['HTTP_ORIGIN'] = $_SERVER['SERVER_NAME'];
}

//Main
try {
    if(isset($_REQUEST['q'])){
        $parts = explode('/', rtrim($_REQUEST['q'], '/'), 2);
        $dbname = null;
        $url_prefix = null;
        switch ($parts[0]) {
            case "translated":
                $dbname = $mconfig["translated"];
                $url_prefix = "http://yomanga.co/reader/content/comics/";
                break;
            case "raws":
                $dbname = $mconfig["raws"];
                $url_prefix = "http://raws.yomanga.co/content/comics/";
                break;
            case "go":
                $dbname = $mconfig["go"];
                $url_prefix = "http://gomanga.co/reader/content/comics/";
                break;
            default:
                json_headers();
                echo json_encode(Array('error' => "Invalid type", 'version' => $version, 'links' => Array(0 => Array('href' => "/translated", 'rel' => "list", 'method' => "GET"), 1 => Array('href' => "/raws", 'rel' => "list", 'method' => "GET"), 2 => Array('href' => "/go", 'rel' => "list", 'method' => "GET"))));
                die();
                break;
        }
        if(isset($parts[1])){
            
            //comic db
            $comic_database = new medoo([
                // required
                'database_type' => 'mysql',
                'database_name' => $dbname,
                'server' => $mconfig["db_host"],
                'username' => $mconfig["db_user"],
                'password' => $mconfig["db_pass"],
                'charset' => 'utf8',
             
                // optional
                'port' => $mconfig["db_pass"],
                // driver_option for connection, read more from http://www.php.net/manual/en/pdo.setattribute.php
                'option' => [
                    PDO::ATTR_CASE => PDO::CASE_NATURAL
                ]
            ]);
            
            //api db
            $api_database = new medoo([
                // required
                'database_type' => 'mysql',
                'database_name' => $mconfig["api"],
                'server' => $mconfig["db_host"],
                'username' => $mconfig["db_user"],
                'password' => $mconfig["db_pass"],
                'charset' => 'utf8',
                
                // optional
                'port' => $mconfig["db_port"],
                // driver_option for connection, read more from http://www.php.net/manual/en/pdo.setattribute.php
                'option' => [
                    PDO::ATTR_CASE => PDO::CASE_NATURAL
                ]
            ]);
            $API = new YoMangaAPI($parts[1], $_SERVER['HTTP_ORIGIN']);
            echo $API->processAPI();
        } else {
            json_headers();
            echo json_encode(Array('error' => "Invalid request", 'version' => $version, 'links' => Array(0 => Array('href' => "/all", 'rel' => "list", 'method' => "GET"), 1 => Array('href' => "/latest", 'rel' => "list", 'method' => "GET"), 2 => Array('href' => "/comic", 'rel' => "list", 'method' => "GET"), 3 => Array('href' => "/chapter", 'rel' => "list", 'method' => "GET"))));
        }
    } else {
        json_headers();
        echo json_encode(Array('error' => "No type", 'version' => $version, 'links' => Array(0 => Array('href' => "/translated", 'rel' => "list", 'method' => "GET"), 1 => Array('href' => "/raws", 'rel' => "list", 'method' => "GET"), 2 => Array('href' => "/go", 'rel' => "list", 'method' => "GET"))));
    }
} catch (Exception $e) {
    json_headers();
    echo json_encode(Array('error' => $e->getMessage()));
}
?>