<?php
header('Content-Type: application/json');

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "library_tracker";

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
        break;

    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);
        $title = $data['title'] ?? '';
        $author = $data['author'] ?? '';
        $availability = $data['availability'] ?? true;
        $genres = json_encode($data['genres'] ?? []);
        
        $sql = "INSERT INTO books (title, author, availability, genres) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssis", $title, $author, $availability, $genres);
        if ($stmt->execute()) {
            http_response_code(201);
            echo json_encode(["message" => "Book added successfully."]);
        } else {
            http_response_code(500);
            echo json_encode(["error" => "Error: " . $stmt->error]);
        }
        break;

    case 'PUT':
        if (!$id) {
            http_response_code(400);
            die(json_encode(["error" => "Book ID not provided."]));
        }
        $data = json_decode(file_get_contents('php://input'), true);
        $title = $data['title'] ?? null;
        $author = $data['author'] ?? null;
        $availability = $data['availability'] ?? null;
        $genres = isset($data['genres']) ? json_encode($data['genres']) : null;
        
        $sql = "UPDATE books SET title=?, author=?, availability=?, genres=? WHERE id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssisi", $title, $author, $availability, $genres, $id);
        if ($stmt->execute()) {
            echo json_encode(["message" => "Book updated successfully."]);
        } else {
            http_response_code(500);
            echo json_encode(["error" => "Error: " . $stmt->error]);
        }
        break;

    case 'DELETE':
        if (!$id) {
            http_response_code(400);
            die(json_encode(["error" => "Book ID not provided."]));
        }
        $sql = "DELETE FROM books WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            echo json_encode(["message" => "Book removed successfully."]);
        } else {
            http_response_code(500);
            echo json_encode(["error" => "Error: " . $stmt->error]);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(["error" => "Method not allowed."]);
        break;
}

$conn->close();
?>