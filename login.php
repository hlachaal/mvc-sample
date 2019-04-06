<?php
if (!isset($value))
	$value = $_REQUEST;
	
$ids = array(
	"login" => unique_id(),
	"btn" => unique_id()
);
?>
<div class='row gg_authorize_login'>
	
	<form id="<?php echo $ids['login'];?>" method="post" action="<?php echo $action_url;?>"
		class='form-horizontal'>
	<div class='form-group'>
<?php
	$errors = array();
	if (validation_errors())
		$errors[] = validation_errors();
	if (isset($gg_form_error))
		$errors[] = $gg_form_error;
	if (count($errors) > 0) {
		foreach ($errors as $error) {
			?><div class='alert alert-danger' role='alert'><?php
			echo $error;
			?></div><?php
		}
	} // if
?>
		<p class='text-information'>Use this form to login.</p>
	</div>
	<div class='form-group'>
		<label class='col-md-3'>Screen Name</label>
		<div class='col-md-8'>
			<input type='text' name='screenName'
			class='form-control' value='<?php
	echo form_prep(safe_arrval("screenName", $value, ""));
			?>' _validate_rules_="trim|min[3]|max[16]|alphanumeric"/>
		</div>
	</div>
	<div class='form-group'>
		<label class='col-md-3'>Password</label>
		<div class='col-md-8'>
			<input type='password' name='password'
			class='form-control' value=''/>
		</div>
	</div>
	<div class='form-group'>
		<div class='col-md-8 col-md-offset-3'>
			<button class='btn btn-primary' id="<?php echo $ids['btn'];?>">Login</button>
		</div>
	</div>
	<div class='form-group'>
		<div class='col-md-8 col-md-offset-3'>
			<p><a href='<?php echo site_url("/authorize/register");?>'><?php
			echo htmlentities("Need to register? Click here.");
			?></p>
		</div>
	</div>
<?php
if (isset($hidden_fields)) {
	foreach ($hidden_fields as $k => $v)
		echo form_hidden($k, $v);
}
echo form_hidden("rurl", safe_arrval("rurl", $value, ""));
?>
	</form>
</div>
<?php
echo render_link(array(
	"/asset/form.js"
) );
?>
<script type='text/javascript'><!--
	jQuery(document).ready(function() {
		jQuery("#<?php echo $ids['btn'];?>").click(function() {
			jQuery("#<?php echo $ids['login'];?>").submit();
			return false;
		} );
	} );
//!--></script>
