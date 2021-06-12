<?php
/**
 * users/index.php
 *
 * @package default
 */
// spaces before html can cause some servers to error.
ob_start();
require_once '../includes/load.php';
if ($session->isUserLoggedIn()) { redirect('../users/home.php', false);}
?>

<?php include_once 'header.php'; ?>

<head>
	<title>Login Form</title>
	<link rel="stylesheet" type="text/css" href="css/style.css">
	<link href="https://fonts.googleapis.com/css?family=Poppins:600&display=swap" rel="stylesheet">
	<script src="https://kit.fontawesome.com/a81368914c.js"></script>
	<meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body>
	<img class="wave" src="img/wave.png">
	<div class="container">
		<div class="img">
			<img src="img/bg.svg">
		</div>
		<div class="login-content">
      
  

          <!--start modify -->
      <?php echo display_msg($msg); ?>
      <form method="post" action="../users/auth.php" class="clearfix">
				<img src="img/inventory.png">
				<h2 class="title">Welcome</h2>
           		<div class="input-div one">
           		   <div class="i">
           		   		<i class="fas fa-user"></i>
           		   </div>
           		   <div class="div">
                  
                      <input type="name" class="form-control" name="username" value="" placeholder=""> 
           		   </div>
           		</div>
           		<div class="input-div pass">
           		   <div class="i"> 
           		    	<i class="fas fa-lock"></i>
           		   </div>
           		   <div class="div">
           		    	
           		    	 <input type="password" class="form-control" name="password" value="" placeholder="">
            	   </div>
            	</div>
            	<a href="#">Forgot Password?</a>
            	
              <button type="submit" class="btn btn-info  pull-right">Login</button>
            </form>
        </div>
    </div>
    <script type="text/javascript" src="js/main.js"></script>
</body>
<?php include_once '../layouts/footer.php'; ?>

