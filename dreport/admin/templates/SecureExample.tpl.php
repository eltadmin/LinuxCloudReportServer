<?php
	$this->assign('title','DREPORTS Secure Example');
	$this->assign('nav','secureexample');

	$this->display('_Header.tpl.php');
?>

<div class="container">

	<?php if ($this->feedback) { ?>
		<div class="alert alert-error">
			<button type="button" class="close" data-dismiss="alert">×</button>
			<?php $this->eprint($this->feedback); ?>
		</div>
	<?php } ?>
	
	<!-- #### this view/tempalate is used for multiple pages.  the controller sets the 'page' variable to display differnet content ####  -->
	
	<?php if ($this->page == 'login') { ?>
	
		<div class="hero-unit">
			<h1>DREPORT Login</h1>
			<p>You need user and password to use admin panel.</p>
			<p>
<!--            
				<a href="secureuser" class="btn btn-primary btn-large">User Page</a>
				<a href="secureadmin" class="btn btn-primary btn-large">Admin Page</a>
-->                
				<?php if (isset($this->currentUser)) { ?>
					<a href="logout" class="btn btn-primary btn-large">Logout</a>
				<?php } ?>
			</p>
		</div>
	
		<form class="well" method="post" action="login">
			<fieldset>
			<legend>Enter your credentials</legend>
				<div class="control-group">
				<input id="username" name="username" type="text" placeholder="Username..." />
				</div>
				<div class="control-group">
				<input id="password" name="password" type="password" placeholder="Password..." />
				</div>
				<div class="control-group">
				<button type="submit" class="btn btn-primary">Login</button>
				</div>
			</fieldset>
		</form>
	
	<?php } else { ?>
	
		<div class="hero-unit">
<!--        
			<h1>Secure <?php $this->eprint($this->page == 'userpage' ? 'User' : 'Admin'); ?> Page</h1>
-->
            <h1>Login successfull</h1>
			<p>This page is accessible only to <?php $this->eprint($this->page == 'userpage' ? 'authenticated users' : 'administrators'); ?>.  
			You are currently logged in as '<strong><?php $this->eprint($this->currentUser->Username); ?></strong>'</p>
			<p>
<!--            
				<a href="secureuser" class="btn btn-primary btn-large">User Page</a>
				<a href="secureadmin" class="btn btn-primary btn-large">Admin Page</a>
-->                
				<a href="logout" class="btn btn-primary btn-large">Logout</a>
			</p>
		</div>
	<?php } ?>

</div> <!-- /container -->

<?php
	$this->display('_Footer.tpl.php');
?>