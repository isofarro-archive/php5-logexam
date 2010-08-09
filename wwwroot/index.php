<?php

$path = $_SERVER['REQUEST_URI'];
$segments = explode('/', trim($path, '/'));

$dataset = $segments[0];
echo "<h1>Logexam: {$dataset}</h1>";
//echo '<pre>'; print_r($segments); echo '</pre>';


?>

<form action="#TODO">
	<fieldset>
		<legend>Filter</legend>
		<input type="text" name="filter" />
	</fieldset>
</form>