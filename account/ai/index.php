<?php

// Redirect to the /account page
http_response_code(301);
header('Location: /account');
exit;