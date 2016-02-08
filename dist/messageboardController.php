<?php

/**
* Class PostItAll
*/
class PostItAll
{
    private $session_id     = "";
    //DB Configuration
    private $db_host        = "127.0.0.1";
    private $db_user        = "root";
    private $db_password    = "";
    private $db_database    = "test";
    private $db_port        = "3306";
    private $mysqli         = null;

    private $table_name     = "postitall_messageboard";

    //public properties
    public $iduser          = -1;
    public $option          = "";
    public $key             = "";
    public $content         = "";

    //Constructor
    function __construct() {
        //Connection
        $this->mysqli = mysqli_connect($this->db_host, $this->db_user, $this->db_password, $this->db_database, $this->db_port) or die("Error " . mysqli_error($link));
        //Create table
        $this->createTable() or die("Table creation error " . mysqli_error($link));
        //session
        $this->session_id = session_start();
        date_default_timezone_set('UTC');
    }

    //Destructor
    function __destruct() {
        //Close connection
        $this->mysqli->close();
    }

    //Parse request
    private function getRequest() {
        //Option
        if(!isset($_REQUEST["option"]) || !$_REQUEST["option"]) {
            die("No option");
        }
        $this->option = mysqli_escape_string($this->mysqli, $_REQUEST["option"]);
        //Iduser
        $this->iduser = -1;
        if(isset($_REQUEST["iduser"]) && $_REQUEST["iduser"]) {
            $this->iduser = mysqli_escape_string($this->mysqli, $_REQUEST["iduser"]);
        }
        //Key
        $this->key = "";
        if(isset($_REQUEST["key"]) && $_REQUEST["key"]) {
            $this->key = mysqli_escape_string($this->mysqli, $_REQUEST["key"]);
        }
        //Content
        $this->content = "";
        if(isset($_REQUEST["content"]) && $_REQUEST["content"]) {
            $this->content = mysqli_escape_string($this->mysqli, $_REQUEST["content"]);
        }
    }

    //Create table
    private function createTable() {
        $createdb = "CREATE TABLE IF NOT EXISTS `" . $this->table_name . "` (
                      `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
                      `iduser` varchar(50) NOT NULL DEFAULT '',
                      `idnote` varchar(50) NOT NULL,
                      `content` text,
                      `created` varchar(14) NOT NULL DEFAULT '',
                      `updated` varchar(14) NOT NULL DEFAULT '',
                      `deleted` varchar(14) NOT NULL DEFAULT '',
                      PRIMARY KEY (`id`)
                  ) ENGINE=MyISAM DEFAULT CHARSET=utf8;";
        return $this->mysqli->query($createdb);
    }

    //Main method
    public function main() {
        $error = false;
        $ret = "";

        //Get Request
        $this->getRequest();

        header('Content-Type: application/json');

        switch ($this->option) {

            case 'test':
                if($this->mysqli != null) {
                    $_SESSION["lastEntry"] = date("YmdHis");
                    $ret = "test ok";
                } else {
                    $error = true;
                    $ret = "test ko";
                }
                break;

            case 'reload':
                $ret = $this->reload();
                break;

            case 'getlength':
                $ret = $this->getLength();
                break;

            case 'getLengthUser':
                $ret = $this->getLengthUser($this->iduser);
                break;

            case 'getLengthTotal':
                $ret = $this->getLengthTotal();
                break;

            case 'getlengthTotalUser':
                $ret = $this->getLengthTotal() . "|" . $this->getLengthUser($this->iduser);
                break;

            case 'get':
                $ret = $this->get($this->iduser, $this->key);
                break;

            case 'add':
                if(!$this->add($this->iduser, $this->key, $this->content))
                    $ret = "Error saving note";
                break;

            case 'key':
                $ret = $this->key($this->iduser, $this->key);
                break;

            case 'remove':
                $ret = $this->removeNote($this->iduser, $this->key);
                break;

            default:
                $error = true;
                $ret = "Option ".$this->option." not implemented";
                break;
        }

        if($error) {
            echo json_encode(array('status' => 'error', 'message'=> $ret));
        } else {
            echo json_encode(array('status' => 'success', 'message'=> $ret));
        }
    }

    protected function reload() {
        $ret = "";
        $sql = "select max(updated) as lastUpdated from postitall_messageboard";
        $resultado = $this->mysqli->query($sql);
        $array = $resultado->fetch_array();
        if($array["lastUpdated"] > $_SESSION["lastEntry"]) {
            $sql = "select idnote, deleted from postitall_messageboard where (updated > '".$_SESSION["lastEntry"]."' and iduser <> '".$this->iduser."') group by idnote";
            $resultado = $this->mysqli->query($sql);
            while($row = $resultado->fetch_array()) {
                if($row["deleted"] != "") {
                    $ret .= "x";
                }
                $ret .= $row['idnote'] . "|";
            }
            if($ret && $ret != "-1")
                $ret = substr($ret, 0, -1);
            //print $sql . "*". $ret;die;
            $_SESSION["lastEntry"] = $array["lastUpdated"];
        }
        return strtolower($ret);
    }

    protected function getLength() {
        $sql = "select count(*) as total from " . $this->table_name;// . " where deleted is null ";// . " where iduser='" . $idUser . "'";
        $resultado = $this->mysqli->query($sql);
        $array = $resultado->fetch_array();
        return intval($array["total"]);
    }

    protected function getLengthUser($idUser) {
        $sql = "select count(*) as total from " . $this->table_name . " where deleted = '' and iduser='" . $idUser . "'";
        $resultado = $this->mysqli->query($sql);
        $array = $resultado->fetch_array();
        return intval($array["total"]);
    }

    protected function getLengthTotal() {
        $sql = "select count(*) as total from " . $this->table_name . " where deleted = ''";
        $resultado = $this->mysqli->query($sql);
        $array = $resultado->fetch_array();
        return intval($array["total"]);
    }

    protected function get($idUser, $idNote) {
        $sql = "select iduser, content from " . $this->table_name . " where idnote='" . $idNote . "' and deleted = ''";//" and iduser='" . $idUser . "'";
        $resultado = $this->mysqli->query($sql);
        $array = $resultado->fetch_array();
        if($array["content"]) {
            $note = json_decode($array["content"]);
            if($note != null && $note->features != null) {
                if($array["iduser"] != $idUser) {
                    $note->features->toolbar = false;
                    //$note->features->draggable = false;
                    $note->features->savable = false;
                    $note->features->resizable = false;
                    $note->features->editable = false;
                } else {
                    $note->features->toolbar = true;
                    $note->features->draggable = true;
                    $note->features->savable = true;
                    $note->features->resizable = true;
                    $note->features->editable = true;
                }
                return json_encode($note);
            }
        }
        return "";
    }

    protected function getNoteUser($idUser, $idNote) {
        $sql = "select content from " . $this->table_name . " where idnote='" . $idNote . "' and iduser='" . $idUser . "' and deleted = ''";
        $resultado = $this->mysqli->query($sql);
        $array = $resultado->fetch_array();
        return $array["content"];
    }


    protected function add($idUser, $idNote, $content) {
        return $this->save($idUser, $idNote, $content);
    }

    protected function exists($idUser, $idNote) {
        if($this->get($idUser, $idNote)) {
            return true;
        }
        return false;
    }

    protected function key($idUser, $key) {
        if(!$key) $key = "0";
        $sql = "select idnote from " . $this->table_name . " where deleted  = ''";// . " where iduser='" . $idUser . "'";
        $sql .= " limit " . $key . ",1";
        if($resultado = $this->mysqli->query($sql)) {
            $array = $resultado->fetch_array();
            return $array["idnote"];
        }
        return "";
    }

    public function getData($idUser) {
        $sql = "select content from " . $this->table_name;// . " where iduser = " . $idUser;
        $resultado = $this->mysqli->query($sql);
        $array = $resultado->fetch_array();
        return $array["content"];
    }

    protected function save($idUser, $idNote, $content)
    {
        //$json = json_decode($content);
        //if (json_last_error() === JSON_ERROR_NONE) {
            if($this->getNoteUser($idUser, $idNote))
                return $this->updateNote($idUser, $idNote, $content);
            else
            {
                if(!$this->exists($idUser, $idNote))
                {
                    return $this->insertNote($idUser, $idNote, $content);
                }
            }
        //} else {
        //    return false;
        //}
        return false;
    }

    private function insertNote($idUser, $idNote, $content) {
        $_SESSION["lastEntry"] = date("YmdHis");
        $sql = "insert into " . $this->table_name . " (iduser, idnote, content, created, updated) ";
        $sql .= " values ('".$idUser."','".$idNote."','".$content."', '".date("YmdHis")."', '".date("YmdHis")."')";
        return $this->mysqli->query($sql);
    }

    private function updateNote($idUser, $idNote, $content) {
        $_SESSION["lastEntry"] = date("YmdHis");
        $sql = "update " . $this->table_name . " set content='".$content."', updated='".date("YmdHis")."' where iduser='".$idUser."' and idNote='".$idNote."' ";
        return $this->mysqli->query($sql);
    }

    private function removeNote($idUser, $idNote) {
        $sql = "update " . $this->table_name . " set updated='".date("YmdHis")."', deleted='".date("YmdHis")."' where iduser='".$idUser."' and idNote='".$idNote."'";
        return $this->mysqli->query($sql);
    }
}

$pia = new PostItAll();
echo $pia->main();

?>
