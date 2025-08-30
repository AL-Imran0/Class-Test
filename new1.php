<?php
header('Content-Type: application/json');

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "library";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    http_response_code(500);
    die(json_encode(["error" => "Connection failed: " . $conn->connect_error]));
}

$method = $_SERVER['REQUEST_METHOD'];
$request_uri = $_SERVER['REQUEST_URI'];
$path_segments = explode('/', trim(parse_url($request_uri, PHP_URL_PATH), '/'));

if (empty($path_segments) || $path_segments[0] !== 'books') {
    http_response_code(404);
    die(json_encode(["error" => "Invalid API endpoint."]));
}

$id = isset($path_segments[1]) ? (int)$path_segments[1] : null;

switch ($method) {
    case 'GET':
        if ($id) {
            $sql = "SELECT * FROM books WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            $book = $result->fetch_assoc();
            echo json_encode($book);
        } else {
            $genre_filter = $_GET['genre'] ?? null;
            if ($genre_filter) {
                $sql = "SELECT * FROM books WHERE JSON_CONTAINS(genres, ?)";
                $stmt = $conn->prepare($sql);
                $json_genre = json_encode($genre_filter);
                $stmt->bind_param("s", $json_genre);
            } else {
                $sql = "SELECT * FROM books";
                $stmt = $conn->prepare($sql);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            $books = [];
            while ($row = $result->fetch_assoc()) {
                $books[] = $row;
            }
            echo json_encode($books);
        }
        }