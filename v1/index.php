<?php                 
// TODO: This needs to go, no longer needed.
// We should add an interface on the main index to upload profile images.
include "header.php";

echo '<h1 class="u-align-left u-text u-text-1" style="color:#F0F0F0" >&nbsp;<a href="https://nostr.build"><img src="walker1.png" width="27"> nostr media uploader</a> </h1>';

$directory = "../i/*.*";
$dirppics = "../i/p/*.*";
$av = "../av/*.*";

//prints total file size and uploads
echo '&ensp;<a style="color:#F0F0F0">'.number_format((array_sum(array_map('filesize', glob('../i/*.*' . "*"))) + array_sum(array_map('filesize', glob('../av/*.*' . "*"))))/1000000000, 2, '.') . ' GB used - ';
echo number_format(count(glob($directory . "*")) + count(glob($dirppics . "*")) + count(glob($av . "*"))) . " total uploads</a>";

?>
  <p style="color:#F0F0F0">
  &emsp; - Uploading agrees to our <a href="tos" target="_blank">Terms of Service</a><BR>
  &emsp; - Supports: jpg, png, gif, mov, or mp4 <BR>
  &emsp; - Removes metadata, free, nostr focused<BR>
    <a href="profilepic.php" class="flash">&emsp;<B>** PROFILE PICS -> CLICK HERE **</B></a>
      </p>
      <form action="../upload.php" method="post" enctype="multipart/form-data"><BR>
        &ensp;<input type="file" name="fileToUpload" id="fileToUpload" style="color:#C0C0C0"><BR>
        &ensp;<input type="text" name="img_url" id="img_url" placeholder="OR paste image URL to import" style="width: 250px;"><BR>

        &ensp;<input type="submit" value="Upload" name="submit" class="sbtn btn btn-secondary btn-c">
        <div class="loader">
          <div class="loading">
          </div>
        </div>
        </form>
<BR>
