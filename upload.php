<?php
// Define the directory to store uploaded JAR files
$target_dir = "jars/";
$target_file = $target_dir . basename($_FILES["fileToUpload"]["name"]);
$uploadOk = 1;
$fileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

// Check if the file is a JAR file
if (isset($_POST["upload"])) {
    if ($fileType != "jar") {
        echo "Sorry, only JAR files are allowed.";
        $uploadOk = 0;
    }
}

// Check if $uploadOk is set to 0 by an error
if ($uploadOk == 0) {
    echo "Sorry, your file was not uploaded.";
// If everything is ok, try to upload the file
} else {
    if (move_uploaded_file($_FILES["fileToUpload"]["tmp_name"], $target_file)) {
        echo "The file " . htmlspecialchars(basename($_FILES["fileToUpload"]["name"])) . " has been uploaded.";
        updateListFile($target_file);
    } else {
        echo "Sorry, there was an error uploading your file.";
    }
}

// Function to update the list.json file with the uploaded file
function updateListFile($filePath) {
    $listFile = 'list.json';
    $relativePath = str_replace($_SERVER['DOCUMENT_ROOT'] . '/a/j2me/', '', $filePath);
    file_put_contents($listFile, $relativePath . PHP_EOL, FILE_APPEND | LOCK_EX);
}

// Function to refresh list.json with current JAR files in the jars/ directory
function refreshListFile() {
    $listFile = 'list.json';
    $jarFiles = glob("jars/*.jar");
    $content = "";

    foreach ($jarFiles as $file) {
        $relativePath = str_replace($_SERVER['DOCUMENT_ROOT'] . '/a/j2me/', '', $file);
        $content .= $relativePath . PHP_EOL;
    }

    file_put_contents($listFile, $content);
}

// Check if the refresh button was clicked
if (isset($_POST["refresh"])) {
    refreshListFile();
    echo "list.json has been updated with the current JAR files.";
}

// Function to get the list of uploaded games from list.json 
function getUploadedGames() {
    $listFile = 'list.json';
    if (file_exists($listFile)) {
        return file($listFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    }
    return [];
}

$uploadedGames = getUploadedGames();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload JAR Games</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 20px;
        }
        h1 {
            color: #333;
        }
        form {
            background: #fff;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }
        input[type="file"] {
            margin: 10px 0;
        }
        input[type="submit"] {
            background: #5cb85c;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 5px;
            cursor: pointer;
        }
        input[type="submit"]:hover {
            background: #4cae4c;
        }
        footer {
            margin-top: 20px;
            padding: 10px;
            background: #fff;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>
<body>

<h1>Upload Your Java JAR Games</h1>
<form action="" method="post" enctype="multipart/form-data">
    Select JAR file to upload:
    <input type="file" name="fileToUpload" id="fileToUpload" required>
    <input type="submit" value="Upload JAR" name="upload">
</form>

<h1>Refresh JAR List</h1>
<form action="" method="post">
    <input type="submit" value="Update List" name="refresh">
</form>

<footer>
    <h2>Uploaded Games</h2>
    <ul>
        <?php foreach ($uploadedGames as $game): ?>
            <li><?php echo htmlspecialchars($game); ?></li>
        <?php endforeach; ?>
    </ul>
</footer>

</body>
</html>
