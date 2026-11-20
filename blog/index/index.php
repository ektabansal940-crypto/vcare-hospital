<?php
// Canonical 301 redirect from /blog/index/ to /blog/
header("HTTP/1.1 301 Moved Permanently");
header("Location: /blog/");
exit();
