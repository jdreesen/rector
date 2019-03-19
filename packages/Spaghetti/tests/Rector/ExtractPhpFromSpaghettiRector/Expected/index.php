<?php
$variables = (new IndexController())->render();
extract($variables);
?>
<ul>
    <li><a href="<?php echo $variable1; ?>">Odkaz</a>
</ul>
