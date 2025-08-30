<?php
$host = "localhost";
$user = "root";       
$pass = "";           
$db   = "library_tracker";

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

function getInput() {
    return json_decode(file_get_contents("php://input"), true);
}

function respond($data, $status = 200) {
    http_response_code($status);
    header("Content-Type: application/json");
    echo json_encode($data);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$path   = explode('/', trim($_SERVER['PATH_INFO'] ?? '', '/'));

if ($path[0] !== "books") {
    respond(["error" => "Invalid endpoint"], 404);
}

$id = $path[1] ?? null;

switch ($method) {

    case "POST":
        $data = getInput();
        if (!isset($data["title"], $data["author"])) {
            respond(["error" => "Title and Author are required"], 400);
        }

        $stmt = $conn->prepare("INSERT INTO books (title, author, availability) VALUES (?, ?, ?)");
        $avail = isset($data["availability"]) ? (int)$data["availability"] : 1;
        $stmt->bind_param("ssi", $data["title"], $data["author"], $avail);
        $stmt->execute();
        $bookId = $stmt->insert_id;

        if (!empty($data["genres"])) {
            foreach ($data["genres"] as $genre) {
                $stmt = $conn->prepare("INSERT INTO genres (name) VALUES (?) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)");
                $stmt->bind_param("s", $genre);
                $stmt->execute();
                $genreId = $conn->insert_id;
                $conn->query("INSERT IGNORE INTO book_genres (book_id, genre_id) VALUES ($bookId, $genreId)");
            }
        }

        respond(["message" => "Book added", "id" => $bookId], 201);
        break;

   
    case "GET":
        if ($id) {
            
            $sql = "SELECT b.id, b.title, b.author, b.availability, b.createdAt,
                           GROUP_CONCAT(g.name) AS genres
                    FROM books b
                    LEFT JOIN book_genres bg ON b.id = bg.book_id
                    LEFT JOIN genres g ON bg.genre_id = g.id
                    WHERE b.id = $id
                    GROUP BY b.id";
            $res = $conn->query($sql);
            respond($res->fetch_assoc() ?: ["error" => "Book not found"], $res->num_rows ? 200 : 404);
        } elseif (isset($_GET["genre"])) {
        
            $genre = $conn->real_escape_string($_GET["genre"]);
            $sql = "SELECT b.id, b.title, b.author, b.availability, b.createdAt,
                           GROUP_CONCAT(g.name) AS genres
                    FROM books b
                    JOIN book_genres bg ON b.id = bg.book_id
                    JOIN genres g ON bg.genre_id = g.id
                    WHERE g.name = '$genre'
                    GROUP BY b.id";
            $res = $conn->query($sql);
            $books = [];
            while ($row = $res->fetch_assoc()) $books[] = $row;
            respond($books);
        } else {
            
            $sql = "SELECT b.id, b.title, b.author, b.availability, b.createdAt,
                           GROUP_CONCAT(g.name) AS genres
                    FROM books b
                    LEFT JOIN book_genres bg ON b.id = bg.book_id
                    LEFT JOIN genres g ON bg.genre_id = g.id
                    GROUP BY b.id";
            $res = $conn->query($sql);
            $books = [];
            while ($row = $res->fetch_assoc()) $books[] = $row;
            respond($books);
        }
        break;


    case "PUT":
        if (!$id) respond(["error" => "Book ID required"], 400);

        $data = getInput();
        $sql = "UPDATE books SET title=?, author=?, availability=? WHERE id=?";
        $stmt = $conn->prepare($sql);
        $avail = isset($data["availability"]) ? (int)$data["availability"] : 1;
        $stmt->bind_param("ssii", $data["title"], $data["author"], $avail, $id);
        $stmt->execute();

       
        if (!empty($data["genres"])) {
            $conn->query("DELETE FROM book_genres WHERE book_id=$id");
            foreach ($data["genres"] as $genre) {
                $stmt = $conn->prepare("INSERT INTO genres (name) VALUES (?) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)");
                $stmt->bind_param("s", $genre);
                $stmt->execute();
                $genreId = $conn->insert_id;
                $conn->query("INSERT IGNORE INTO book_genres (book_id, genre_id) VALUES ($id, $genreId)");
            }
        }

        respond(["message" => "Book updated"]);
        break;


    case "DELETE":
        if (!$id) respond(["error" => "Book ID required"], 400);
        $conn->query("DELETE FROM books WHERE id=$id");
        respond(["message" => "Book deleted"]);
        break;

    default:
        respond(["error" => "Unsupported method"], 405);
}
?>
