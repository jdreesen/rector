<?php
$variables = (new SimpleForeachController())->render();
extract($variables);

foreach ($variable1 as $variable1Single) {
    echo '<strong>' . $variable1Single . '</strong>';
}
