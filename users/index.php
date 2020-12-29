<!--includes -->

<?php
ob_start();
require_once '../includes/load.php';

if ($session->isUserLoggedIn()) { redirect('../users/home.php', false);}
?>
<?php include_once '../layouts/header.php'; ?>


<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <!--animation link -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.compat.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.compat.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.css">
</head>
<body>

<div class="animate__animated animate__backInDown" >
<div class="login-page">
    <div class="text-center">
       <h1 class="animate__animated animate__flip animate__delay-2s" > Welcome</h1>
       <p>Sign in to start your session</p>
     </div>
     <?php echo display_msg($msg); ?>

      <form method="post" action="../users/auth.php" class="clearfix">
        <div class="form-group">
        
              <label for="username" class="control-label">Username</label>
              <input type="name" class="form-control animate__animated animate__lightSpeedInLeft animate__delay-3s" name="username" placeholder="Username">
        </div>
        <div class="form-group">       
            <label for="Password" class="control-label">Password</label>
            <input type="password"  id="UserInput" name= "password" class="form-control animate__animated animate__lightSpeedInLeft animate__delay-3s " placeholder="Password">

            <br>
            <input type="checkbox" onclick="myFunction()"> Show Password
            <!-- show password -->
                                <script>
                    function myFunction() {
                      var x = document.getElementById("UserInput");
                      if (x.type === "password") {
                        x.type = "text";
                      } else {
                        x.type = "password";
                      }
                    }
                    </script>

        </div>

        

        <div class="form-group">
                <button type="submit" class="btn btn-info  pull-right">Login</button>
        </div>
    </form>
    <div class="text-center">
       <p></p>
     </div>
</div>

</div>




<?php include_once '../layouts/footer.php'; ?>
</body>
</html>



