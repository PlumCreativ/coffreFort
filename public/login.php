<!DOCTYPE html>

<html>
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title> CryptoVault </title>

        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

        <link rel="stylesheet" href="style.css">
    </head>

    <body>
        <header>
            <nav class="navbar">
                
                <!-- Icon -->
                <div class="d-flex justify-content-center logo">
                    <img src="img/logo.jpeg" width="100" height="100" alt="Logo">
                    </img>
                </div>
            </nav>
        </header>
        <div class=" row justify-content-center mt-5">
            <aside class="container-fluid col-4">
                <div class="row justify-content-center">

                    <!-- Sing in-->
                    <div class="frame">
                        <div class="text-center" >
                            <h2 class="header-font H-font">Log In</h2>
                        </div>

                        <!-- Form -->
                        <form class="my-5" method="POST" action="/auth/login" id="loginForm">
                            
                        <!-- Inputs -->
                            <div class="d-column justify-content-center">

                                <div class="mb-4">
                                    <input type="text" class="form-control border rounded p-2"
                                    id="email" placeholder="Your Name" name="email" required>
                                </div>

                                <div class="mb-4">
                                    <div class="d-flex justify-content-end">
                                        <div>
                                            <i class="bi bi-eye-slash justify-content-sm-between"></i>
                                            <label for="inputPassword">Hide</label>
                                        </div>
                                    </div>

                                    <input type="password" class="form-control border rounded p-2"
                                    id="pass_hash" placeholder="Your password" name="password" required>

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
                                    <button type="submit" class="button-primary">Log In</button>
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
                    <p class="p-4 text-center divider-P">Don't have an account?</p>
                    <div class="d-grid">
                        <a class="button-secondary" href="singin.php">Sing up</a>
                    </div>
                </div>
            </aside>
        </div>
        <!-- il faut mettre dans login.js!!!! -->
        <script src="login.js"></script>
    </body>
</html>
