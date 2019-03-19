<?php

$values = (new IndexController)->render();
extract($values);

?>

<ul>
    <li><a href="<?php echo $variable1 ?>">Odkaz</a>
</ul>
