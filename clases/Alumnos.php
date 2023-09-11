<?php

namespace Classes;
use flight;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use PDO;
use PDOException;

class Alumnos
{

    private $conn;

    function __construct()
    {
        Flight::register(
            'db',
            'PDO',
            array('mysql:host='.$_ENV["DB_HOST"].';dbname='.$_ENV["DB_NAME"],$_ENV["DB_USER"], $_ENV["DB_PASSWORD"]),
            function ($db) {
                $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            }
        );
        $this->conn = Flight::db();
    }

    public function auth()
    {
        $usuario = Flight::request()->data->nombres;
        $query =  $this->conn->prepare("SELECT * FROM alumnos WHERE  nombres = :nombres ");
        $array = [
            "error" => "No se pudo validar su identidad, por favor intente de nuevo",
            "status" => "error"
        ];

        if ($query->execute(["nombres" => $usuario])) {
            $user = $query->fetch(PDO::FETCH_ASSOC);
            $now = time();
            $key = $_ENV["JWT_SECRET_KEY"] ;
            $payload = [
                'exp' => $now + 3600,
                'data' => $user['id']
            ];

            $jwt = JWT::encode($payload, $key, 'HS256');
            $array = ["token" => $jwt];
        }

        Flight::json($array);
    }


    function selectAll()
    {
        $sentencia =  $this->conn->prepare("SELECT * FROM ALUMNOS");
        $sentencia->execute();
        $datos = $sentencia->fetchALL(PDO::FETCH_ASSOC);
        $array = [];
        if ($datos) {
            /*  $arreglo = array("estado"=>"200","datos"=>$datos);*/

            foreach ($datos as $row) {
                $array[] = [
                    "id" => $row["id"],
                    "names" => $row["nombres"],
                    "lastname" => $row["apellidos"],
                    "creado" => $row["created_at"],
                    "actualizado" => $row["updated_at"]
                ];
            }

            $arreglo = array("estado" => "200", "total_rows" => $sentencia->rowCount(), "datos" => $array);
            flight::json($arreglo);
        } else {
            $arreglo = array("estado" => "400", "alumno" => 'No hay alumnos');
            flight::json($arreglo);
        }
    }

    function selectAllPage($page = 1)
    {
       if(!isset($page)){
            $page = 1;
        }

        if($page < 1){
            $page = 1;
        }

        $query = $this->conn->prepare("SELECT * FROM alumnos");
        $query->execute();
        $total = $query->rowCount();
        $cantidad = 2;
        $paginas = ceil($total/$cantidad);
        if($page > $paginas || $page < 1){
            Flight::halt(403, json_encode([
                "error" => "Petición incorrecta",
                "status" => "error"
            ]));
        }
        $inicio = $cantidad * ($page -1);
        $sentencia =  $this->conn->prepare("SELECT * FROM ALUMNOS LIMIT $inicio,$cantidad");
        $sentencia->execute();
        $datos = $sentencia->fetchALL(PDO::FETCH_ASSOC);
        $array = [];
        if ($datos) {
            /*  $arreglo = array("estado"=>"200","datos"=>$datos);*/
            foreach ($datos as $row) {
                $array[] = [
                    "id" => $row["id"],
                    "names" => $row["nombres"],
                    "lastname" => $row["apellidos"],
                    "creado" => $row["created_at"],
                    "actualizado" => $row["updated_at"]
                ];
            }

            $arreglo = array("estado" => "200","paginaactual"=>$page,"numeroPaginas"=>$paginas, "total_rows" => $sentencia->rowCount(), "datos" => $array);
            flight::json($arreglo);
        } else {
            $arreglo = array("estado" => "400", "alumno" => 'No hay alumnos');
            flight::json($arreglo);
        }
    }

    function selectOne($id)
    {

        $sentencia =  $this->conn->prepare("SELECT * FROM ALUMNOS where id = :id");
        $sentencia->execute(["id" => $id]);
        $data = $sentencia->fetch(PDO::FETCH_ASSOC);
        if ($data) {
            /*  $arreglo = array("estado"=>"200","datos"=>$datos);*/
            $array = [
                "id" => $data["id"],
                "names" => $data["nombres"],
                "lastname" => $data["apellidos"],
                "creado" => $data["created_at"],
                "actualizado" => $data["updated_at"]
            ];

            $arreglo = array("estado" => "200", "total_rows" => $sentencia->rowCount(), "datos" => $array);
            flight::json($arreglo);
        } else {
            $arreglo = array("estado" => "400", "alumno" => 'No hay alumnos');
            flight::json($arreglo);
        }
    }


    public function insert()
    {

        if (!$this->validateToken()) $this->error_403();

        try {
            $db = $this->conn;
            $nombres = Flight::request()->data->nombres;
            $apellidos = Flight::request()->data->apellidos;

            if ($nombres == "" || $apellidos == "") {
                $array = [
                    "error" => "Complete los campos",
                    "status" => "error"
                ];
                return flight::json($array);
                
            }

            $array = [
                "error" => "hubo un error al agregar los registros",
                "status" => "error"
            ];
            $sentencia = $db->prepare("INSERT INTO ALUMNOS (nombres,apellidos) VALUES(:nombres,:apellidos)");
            if ($sentencia->execute(["nombres" => $nombres, "apellidos" => $apellidos])) {
                $array = ["estado" => "200", "datos" => [
                    "id" => $db->lastInsertId(),
                    "nombre" => $nombres,
                    "apellidos" => $apellidos
                ]];
            }
            flight::json($array);
        } catch (PDOException $e) {
            $array = [
                "error" => "hubo un error al agregar los registros por PDO",
                "status" => "error"
            ];
            flight::json($array);
        }
    }

   
    public function update()
    {

        if (!$this->validateToken()) $this->error_403();

        try {
            $db = $this->conn;
            $id = Flight::request()->data->id;
            if ($id == "") {
                $array = [
                    "error" => "El id es obligatorio",
                    "status" => "error"
                ];
                flight::json($array);
                return;
            }

            $sentencia = $db->prepare("SELECT * FROM Alumnos where id = $id");
            $sentencia->execute();
            $data = $sentencia->fetch(PDO::FETCH_ASSOC);
            $array = [
                "error" => "No hay registros con el id $id",
                "status" => "200"
            ];
            if (!$data) return flight::json($array);

            $nombres = Flight::request()->data->nombres ?? $data["nombres"];
            $apellidos = Flight::request()->data->apellidos ?? $data["apellidos"];

            $array = [
                "error" => "hubo un error al actualizar los registros",
                "status" => "error"
            ];
            $sentencia = $db->prepare("UPDATE ALUMNOS set nombres = :nombres, apellidos = :apellidos WHERE id = :id");
            if ($sentencia->execute(["nombres" => $nombres, "apellidos" => $apellidos, "id" => $id])) {
                $array = ["estado" => "200", "datos" => [
                    "id" => $id,
                    "nombre" => $nombres,
                    "apellidos" => $apellidos
                ]];
            }
            flight::json($array);
        } catch (PDOException $e) {
            $array = [
                "error" => "hubo un error al actualizar los registros por PDO",
                "status" => "error"
            ];
            flight::json($array);
        }
    }


    public function delete()
    {
        if (!$this->validateToken()) $this->error_403();

        try {
            $db = $this->conn;
            $id = Flight::request()->data->id;

            if ($id == "") {
                $array = [
                    "error" => "El id es obligatorio",
                    "status" => "error"
                ];
                flight::json($array);
                return;
            }

            $array = [
                "error" => "hubo un error al eliminar los registros",
                "status" => "error"
            ];
            $sentencia = $db->prepare("DELETE FROM alumnos where id = :id");
            if ($sentencia->execute(["id" => $id])) {
                $array = ["estado" => "200", "datos" => [
                    "id" => $id
                ]];
            }
            flight::json($array);
        } catch (PDOException $e) {
            $array = [
                "error" => "hubo un error al eliminar el registro por PDO",
                "status" => "error"
            ];
            flight::json($array);
        }
    }


    private function getToken(){
        $headers = getallheaders();
        if(!isset($headers["Authorization"])){
            Flight::halt(403,json_encode([
                "error" => "Unauthenticated request",
                "status" => "error"
            ]));
        }
        $autorization = $headers["Authorization"];
        $token = explode(' ',$autorization)[1];
        $key = $_ENV["JWT_SECRET_KEY"] ;
        try{    
            return JWT::decode($token, new Key($key, 'HS256'));
        }catch(\Throwable $th){
            Flight::halt(403,json_encode([
                "error" => $th->getMessage(),
                "status" => "error"
            ]));
        }
       
    }
    
    private function validateToken(){
        $info = $this->getToken();
        $db = $this->conn;
        $query = $db->prepare("SELECT * FROM alumnos where id = :id");
        $query->execute(["id"=> $info->data]);
        $rows = $query->fetchColumn();
        return $rows;
    }

    private function error_403(){
        Flight::halt(403, json_encode([
            "error" => "Unauthorized si",
            "status" => "error"
        ]));
    }

    
}
