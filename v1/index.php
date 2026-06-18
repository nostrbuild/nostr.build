<?php
// The legacy v1 browser uploader has been retired. Browser uploads are no longer
// offered; free uploads now happen through the nostr.build API. Send visitors to
// the current homepage.
header('Location: /', true, 301);
exit;
