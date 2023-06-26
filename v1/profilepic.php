<?php
include "header.php";
?>
<h1 class="u-align-left u-text u-text-1" style="color:#F0F0F0"> <a href="https://nostr.build">nostr profile pic creator</a> </h1>
<p style="color:#F0F0F0">&emsp;- Compresses and crops image to correct 400x400 size<BR>
   &emsp;- Adds profile image to a private folder<BR>
   &emsp;- Strips personal and location metadata<BR>
</p>
<form action="../upload.php" method="post" enctype="multipart/form-data">
   <BR>
   &ensp;<input type="file" name="fileToUpload" id="fileToUpload" style="color:#C0C0C0"> <BR><BR><BR><BR>
   &ensp;<input type="submit" value="Upload Image" name="submit_ppic">
</form>