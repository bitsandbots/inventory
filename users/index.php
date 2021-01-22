<?php
/**
 * users/index.php
 *
 * @package default
 */


?>
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

  <link rel="stylesheet" href="style.css"> <!-- adding files are disabled -->
  
  <style>
@import url('https://fonts.googleapis.com/css?family=Open+Sans&display=swap');

body {
  font-family: 'Open Sans', sans-serif;
  background: #f9faff;
  color: #3a3c47;
  line-height: 1.6;
  display: flex;
  flex-direction: column;
  align-items: center;
  margin: 0;
  padding: 0;
}

h1 {
  margin-top: 48px;
}

form {
    background: #fff;
    max-width: 360px;
    width: 100%;
    padding: 58px 44px;
    border: 1px solid ##e1e2f0;
    border-radius: 4px;
    box-shadow: 0 0 5px 0 rgba(42, 45, 48, 0.12);
    transition: all 0.3s ease;
  }

.row {
  display: flex;
  flex-direction: column;
  margin-bottom: 20px;
}

.row label {
  font-size: 13px;
  color: #8086a9;
}

.row input {
  flex: 1;
  padding: 13px;
  border: 1px solid #d6d8e6;
  border-radius: 4px;
  font-size: 16px;
  transition: all 0.2s ease-out;
}

.row input:focus {
  outline: none;
  box-shadow: inset 2px 2px 5px 0 rgba(42, 45, 48, 0.12);
}

.row input::placeholder {
  color: #C8CDDF;
}

button {
  width: 100%;
  padding: 12px;
  font-size: 18px;
  background: #15C39A;
  color: #fff;
  border: none;
  border-radius: 100px;
  cursor: pointer;
  font-family: 'Open Sans', sans-serif;
  margin-top: 15px;
  transition: background 0.2s ease-out;
}

button:hover {
  background: #55D3AC;
}

@media(max-width: 458px) {
  
  body {
    margin: 0 18px;
  }
  
  form {
    background: #f9faff;
    border: none;
    box-shadow: none;
    padding: 20px 0;
  }

}

  </style>
</head>
<body>

<!--updated -->

<form method="post" action="../users/auth.php" class="clearfix">
  <div class="row animate__animated animate__backInDown" >
      <div class="img container">
          <img src="image/avatar.png" alt="Avatar" class="avatar">
      </div>
  
          <div class = "row">
             <h1> Welcome</h1>
             <p>Sign in to start your session</p>
           </div>        
           <?php echo display_msg($msg); ?>
  
  
            <div class="row">
                    <label for="username" class="control-label">Username</label>
                    <input type="name" class="form-control animate__animated animate__lightSpeedInLeft animate__delay-1s" name="username" placeholder="Username" required >
            </div>
  
            <div class="row">
                  <label for="Password" class="control-label">Password</label>
                  <input type="password"  id="UserInput" name= "password" class="form-control animate__animated animate__lightSpeedInLeft animate__delay-2s " placeholder="Password" required>
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
  
                <div class="row">
                                <button type="submit" class="btn btn-info  pull-right">Login</button>
                </div>
    </div>
  </form>
  
<?php include_once '../layouts/footer.php'; ?>
</body>
</html>
