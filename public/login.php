<!DOCTYPE html>

<html>
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title> Note de Frais </title>
        <link href="css/style.css" rel="stylesheet">

        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">


    </head>

    <?php
    $mess = '';
    $userId = '';
    if( isset( $_GET['error'] ) ) {
        if( isset( $_GET['userIderror'] ) ) {
            $mess = 'Erreur : Votre identifiant est non valide !';
        }
        if( isset( $_GET['passerror'] ) ) {
            $mess = 'Erreur : Votre mot de passe est non valide !';
            if( isset( $_SESSION['userId'] ) ) {
                $userId = $_SESSION['userId'];
                session_destroy();
            }
        }
    }
    ?>


    <body>
        <header class="container-fluid">

            <nav>

                   
                <!-- Icon -->
                    <div class="d-flex justify-content-center">
                        <img src="img/logo.png" width="100" height="100" alt="Logo">
                        </img>
                    </div>

            </nav>

            <div class=" row justify-content-center mt-5">
                <aside class="container-fluid col-4">

                    <?php
                    if( isset( $_GET['error'] ) ) {
                        echo '<div class="row"><p class="col-10 alert alert-danger">' . $mess .'</p></div>';
                    }
                    ?>

                    <div class="row justify-content-center">


                    
                        <!-- Sing in-->
                        <div class="frame">



                            <div class="text-center" >
                                <H2 class="header-font H-font">Log In</H2>
                            </div>

                            <!-- Form -->
                            <form class="my-5" name="accesform" method="post" action="validlogin.php">
                                
                                <!-- Inputs -->
                                <div class="d-column justify-content-center">

                                    <div class="mb-4">
                                        <input type="text" class="form-control border rounded p-2"
                                        id="userId" value="<?=$userId?>" placeholder="Your Name" name="userId" required>
                                    </div>

                                    <div class="mb-4">
                                        <div class="d-flex justify-content-end">
                                            <div>
                                                <i class="bi bi-eye-slash justify-content-sm-between"></i>
                                                <label for="inputPassword">Hide</label>
                                            </div>
                                        </div>

                                        <input type="password" class="form-control border rounded p-2"
                                        id="inputPassword" placeholder="Your password" name="password" required>

                                        <div class="d-flex mt-1 justify-content-end">
                                            <a class="link-dark link-underline-opacity-0 " href="#">Forget your password</a>
                                        </div>
                                        <div>
                                            <i class="bi bi-check-square"></i>
                                            <label for="">Remember me</label>
                                        </div>
                                    </div>

                                </div>  

                                <!-- Button Log in-->
                                <div class="row justify-content-center">
                                    <div class="d-grid">
                                        <button type="submit" class="btn btn-primary rounded-5 p-2">Log In</button>
                                    </div>

                                </div>

                            </form>                            

                    </div>

                    <!-- Divider -->
                    <div class="d-flex justify-content-center align-items-center">

                        <div class="divider"></div>
                            
                    </div>

                    <!-- Button Create account-->
                    <div class="row justify-content-center mb-4">
                        <p class="p-4 text-center divider-P">Don’t have an account?</p>
                        <div class="d-grid">
                            <a class="btn btn-outline-primary btn-create button-H p-2" href="singin.php">Sing up</a>
                        </div>
                    </div>
                    
                </aside>
            </div>

        </header>
    </body>
</html>
