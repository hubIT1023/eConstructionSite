<?php require_once('header.php'); ?>

<?php
// Handle profile updates
if(isset($_POST['form1'])) {
    $valid = 1;
    $error_message = "";

    if(empty($_POST['full_name'])) {
        $valid = 0;
        $error_message .= "Name cannot be empty<br>";
    }

    if(empty($_POST['email'])) {
        $valid = 0;
        $error_message .= 'Email address cannot be empty<br>';
    } else {
        if (filter_var($_POST['email'], FILTER_VALIDATE_EMAIL) === false) {
            $valid = 0;
            $error_message .= 'Email address must be valid<br>';
        } else {
            // Check email uniqueness in tbl_supplier_user
            $statement = $pdo->prepare("SELECT * FROM tbl_supplier_user WHERE email=? AND id!=?");
            $statement->execute(array($_POST['email'], $_SESSION['supplier_user']['id']));
            $total = $statement->rowCount();							
            if($total) {
                $valid = 0;
                $error_message .= 'Email address already exists<br>';
            }
        }
    }

    if($valid == 1) {
        $_SESSION['supplier_user']['full_name'] = $_POST['full_name'];
        $_SESSION['supplier_user']['email'] = $_POST['email'];

        // Update database
        $statement = $pdo->prepare("UPDATE tbl_supplier_user SET full_name=?, email=? WHERE id=?");
        $statement->execute(array($_POST['full_name'], $_POST['email'], $_SESSION['supplier_user']['id']));

        $success_message = 'Profile information updated successfully.';
    }
}

// Handle password updates
if(isset($_POST['form3'])) {
	$valid = 1;
    $error_message = "";

	if( empty($_POST['password']) || empty($_POST['re_password']) ) {
        $valid = 0;
        $error_message .= "Password cannot be empty<br>";
    }

    if( !empty($_POST['password']) && !empty($_POST['re_password']) ) {
    	if($_POST['password'] != $_POST['re_password']) {
	    	$valid = 0;
	        $error_message .= "Passwords do not match<br>";	
    	}        
    }

    if($valid == 1) {
    	$_SESSION['supplier_user']['password'] = md5($_POST['password']);

    	// Update database
		$statement = $pdo->prepare("UPDATE tbl_supplier_user SET password=? WHERE id=?");
		$statement->execute(array(md5($_POST['password']), $_SESSION['supplier_user']['id']));

    	$success_message = 'Password updated successfully.';
    }
}
?>

<section class="content-header">
	<div class="content-header-left">
		<h1>Edit Profile</h1>
	</div>
</section>

<?php
$statement = $pdo->prepare("SELECT * FROM tbl_supplier_user WHERE id=?");
$statement->execute(array($_SESSION['supplier_user']['id']));
$row = $statement->fetch(PDO::FETCH_ASSOC);

$full_name = $row['full_name'];
$email     = $row['email'];
$role      = $row['role'];
?>

<section class="content">

	<div class="row">
		<div class="col-md-12">
				
				<div class="nav-tabs-custom">
					<ul class="nav nav-tabs">
						<li class="active"><a href="#tab_1" data-toggle="tab">Update Information</a></li>
						<li><a href="#tab_3" data-toggle="tab">Update Password</a></li>
					</ul>
					<div class="tab-content">
          				<div class="tab-pane active" id="tab_1">
							
							<form class="form-horizontal" action="" method="post">
							<div class="box box-info">
								<div class="box-body">
									<div class="form-group">
										<label for="" class="col-sm-2 control-label">Name <span>*</span></label>
										<div class="col-sm-4">
											<input type="text" class="form-control" name="full_name" value="<?php echo htmlspecialchars($full_name); ?>" required>
										</div>
									</div>
									<div class="form-group">
										<label for="" class="col-sm-2 control-label">Email Address <span>*</span></label>
										<div class="col-sm-4">
											<input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($email); ?>" required>
										</div>
									</div>
									<div class="form-group">
										<label for="" class="col-sm-2 control-label">Role</label>
										<div class="col-sm-4" style="padding-top:7px;">
											<strong><?php echo htmlspecialchars($role); ?></strong>
										</div>
									</div>
									<div class="form-group">
										<label for="" class="col-sm-2 control-label"></label>
										<div class="col-sm-6">
											<button type="submit" class="btn btn-success pull-left" name="form1">Update Information</button>
										</div>
									</div>
								</div>
							</div>
							</form>
          				</div>
                          
          				<div class="tab-pane" id="tab_3">
							<form class="form-horizontal" action="" method="post">
							<div class="box box-info">
								<div class="box-body">
									<div class="form-group">
										<label for="" class="col-sm-2 control-label">Password </label>
										<div class="col-sm-4">
											<input type="password" class="form-control" name="password">
										</div>
									</div>
									<div class="form-group">
										<label for="" class="col-sm-2 control-label">Retype Password </label>
										<div class="col-sm-4">
											<input type="password" class="form-control" name="re_password">
										</div>
									</div>
							        <div class="form-group">
										<label for="" class="col-sm-2 control-label"></label>
										<div class="col-sm-6">
											<button type="submit" class="btn btn-success pull-left" name="form3">Update Password</button>
										</div>
									</div>
								</div>
							</div>
							</form>

          				</div>
          			</div>
				</div>			

		</div>
	</div>
</section>

<?php require_once('footer.php'); ?>