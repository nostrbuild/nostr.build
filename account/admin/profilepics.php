<?php
// Include config file
require_once($_SERVER['DOCUMENT_ROOT'] . '/config.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/functions/session.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/libs/permissions.class.php');

// Create new Permission object
$perm = new Permission();

if (!$perm->isAdmin()) {
    header("location: /login");
    $link->close();
    exit;
}

echo '<h1 class="u-align-left u-text u-text-1"> <a>nostr profile pics</a> </h1>';

// Fetch data from uploads_data table
$result = mysqli_query($link, "SELECT * FROM uploads_data WHERE type='profile' ORDER BY upload_date DESC");

//create file count
$filecount = mysqli_num_rows($result);
echo number_format($filecount) . " image files";
echo "<BR>";

//prints total file size
$total_size = 0;
while($row = mysqli_fetch_array($result)) $total_size += $row['file_size'];
echo number_format($total_size) . " bytes used"."<BR>";

mysqli_data_seek($result, 0); // reset data pointer

$textCnt  = "approve.ini";
$contents = file_get_contents($textCnt);
$arrfields = explode(',', $contents);

while($row = mysqli_fetch_array($result)) {
   $count = 0;
   $filename = $row['filename'];
   $filename_no_ext = pathinfo($filename, PATHINFO_FILENAME);

   for ($x = 0; $x < count($arrfields); $x++) {
    if ($filename_no_ext == ($arrfields[$x])){
     $count++;
    }
   }
   if ($count == 0){
    echo '<img height="150" src="https://nostr.build/i/p/' . $filename . '" alt="image" />' . "&nbsp; &nbsp;";
   }
}

$link->close();
