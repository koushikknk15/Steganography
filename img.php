<?php
if (isset($_POST['hide'])) {
    $originalImage = $_FILES['image']['tmp_name'];
    $hiddenText = $_POST['message'];
    $outputImage = 'encoded_image.png';

    // Handle multiple files
    $hiddenFiles = $_FILES['files'];

    hideDataInImage($originalImage, $hiddenText, $hiddenFiles, $outputImage);
    echo "Data hidden in image successfully! <a href='$outputImage' download>Download encoded image</a><br>";
}

if (isset($_POST['retrieve'])) {
    $encodedImage = $_FILES['encoded_image']['tmp_name'];

    $outputTextFile = 'retrieved_text.txt';
    $outputFilesDir = 'retrieved_files/';

    retrieveDataFromImage($encodedImage, $outputTextFile, $outputFilesDir);
    echo "Data retrieved successfully! <a href='$outputTextFile' download>Download retrieved text</a><br>";
    echo "Files retrieved:<br>";

    $files = scandir($outputFilesDir);
    foreach ($files as $file) {
        if ($file !== '.' && $file !== '..') {
            echo "<a href='$outputFilesDir$file' download>$file</a><br>";
        }
    }
}

function hideDataInImage($imagePath, $text, $files, $outputPath) {
    if (!function_exists('imagecreatefrompng')) {
        die('GD library is not enabled or installed.');
    }

    $image = imagecreatefrompng($imagePath);
    if (!$image) {
        die('Unable to create image from provided file. Ensure the file is a valid PNG image.');
    }

    // Prepare data
    $textData = base64_encode($text);
    $fileData = '';

    foreach ($files['tmp_name'] as $key => $tmpName) {
        if ($files['error'][$key] == UPLOAD_ERR_OK) {
            $fileName = $files['name'][$key];
            $fileContents = file_get_contents($tmpName);
            $fileData .= base64_encode($fileName) . ':' . base64_encode($fileContents) . '|';
        }
    }

    $data = $textData . '||' . $fileData . chr(0); // Null character to signify end of data
    $dataLength = strlen($data);

    $x = 0;
    $y = 0;

    for ($i = 0; $i < $dataLength; $i++) {
        $character = ord($data[$i]);

        for ($bit = 7; $bit >= 0; $bit--) {
            $rgb = imagecolorat($image, $x, $y);
            $r = ($rgb >> 16) & 0xFF;
            $g = ($rgb >> 8) & 0xFF;
            $b = $rgb & 0xFF;

            // Encode the bit into the blue component
            $b = ($b & 0xFE) | (($character >> $bit) & 1);

            // Set the new pixel color
            $newColor = imagecolorallocate($image, $r, $g, $b);
            imagesetpixel($image, $x, $y, $newColor);

            $x++;
            if ($x >= imagesx($image)) {
                $x = 0;
                $y++;
            }
        }
    }

    imagepng($image, $outputPath);
    imagedestroy($image);
}

function retrieveDataFromImage($imagePath, $outputTextFile, $outputFilesDir) {
    if (!function_exists('imagecreatefrompng')) {
        die('GD library is not enabled or installed.');
    }

    $image = imagecreatefrompng($imagePath);
    if (!$image) {
        die('Unable to create image from provided file. Ensure the file is a valid PNG image.');
    }

    $width = imagesx($image);
    $height = imagesy($image);

    $data = '';
    $character = 0;
    $bitCount = 0;

    for ($y = 0; $y < $height; $y++) {
        for ($x = 0; $x < $width; $x++) {
            $rgb = imagecolorat($image, $x, $y);
            $b = $rgb & 0xFF;

            $character = ($character << 1) | ($b & 1);
            $bitCount++;

            if ($bitCount == 8) {
                if ($character == 0) {
                    break 2; // Null character signifies end of data
                }
                $data .= chr($character);
                $character = 0;
                $bitCount = 0;
            }
        }
    }

    imagedestroy($image);

    list($encodedText, $encodedFiles) = explode('||', $data);

    // Save the text data
    $text = base64_decode($encodedText);
    file_put_contents($outputTextFile, $text);

    // Save the file data
    if (!is_dir($outputFilesDir)) {
        mkdir($outputFilesDir);
    }

    $fileEntries = explode('|', $encodedFiles);
    foreach ($fileEntries as $entry) {
        if (empty($entry)) continue;

        list($encodedName, $encodedContents) = explode(':', $entry);
        $fileName = base64_decode($encodedName);
        $fileContents = base64_decode($encodedContents);

        file_put_contents($outputFilesDir . $fileName, $fileContents);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Image Steganography</title>
</head>
<body>
    <h1>Image Steganography</h1>

    <!-- Form to hide text and files in image -->
    <h2>Hide Text and Files in Image</h2>
    <form method="post" enctype="multipart/form-data">
        <label for="image">Select Image (PNG only):</label>
        <input type="file" name="image" accept="image/png" required>
        <br><br>
        <label for="message">Text to Hide:</label>
        <textarea name="message" required></textarea>
        <br><br>
        <label for="files">Select Files to Hide:</label>
        <input type="file" name="files[]" multiple required>
        <br><br>
        <input type="submit" name="hide" value="Hide Data">
    </form>

    <br><br>

    <!-- Form to retrieve text and files from image -->
    <h2>Retrieve Text and Files from Image</h2>
    <form method="post" enctype="multipart/form-data">
        <label for="encoded_image">Select Encoded Image (PNG only):</label>
        <input type="file" name="encoded_image" accept="image/png" required>
        <br><br>
        <input type="submit" name="retrieve" value="Retrieve Data">
    </form>
</body>
</html>
