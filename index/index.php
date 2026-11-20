<?php
// Canonical 301 redirect from /index/ to /
header("HTTP/1.1 301 Moved Permanently");
header("Location: /");
exit();
