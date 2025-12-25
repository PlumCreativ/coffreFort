<!DOCTYPE html>

<html>
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title> Create Ticket </title>

        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

        <link rel="stylesheet" href="style.css">
    </head>

    <body>
        <header>
            <nav class="navbar">
                <!-- Icon -->
                <div class="d-flex justify-content-center logo">    
                    <img  src="img/logo.jpeg" width="100" height="100" alt="Logo"></img>
                </div>
            </nav>
        </header>

        <div class="my-5 row justify-content-center">
            <aside class="container-fluid col-4">
                <div class="row justify-content-center">
                
                    <!-- Sing in-->
                    <div class="frame">
                        <div class="text-center mb-4">
                            <div>
                                <H2 class="header-font H-font">Sign in</H2>
                            </div>
                        </div>

                        <!-- Form -->
                        <form class="mb-5" method="post" action="/auth/register" id="registerForm">
                            
                            <!-- Inputs -->
                            <div class="d-column justify-content-center">
                                <div class="mb-4">
                                    <label class="text-secondary" for="Your email">Your email</label>
                                    <input type="text" class="form-control border rounded p-2"
                                    id="userId" name="email" required>
                                </div>
                                    
                                <div class="mb-4">
                                    <label class="text-secondary" for="password">Your password</label>
                                    <input type="password" class="form-control border rounded p-2"
                                    id="inputPassword" name="password" required>
                                </div>

                                <div class="mb-4">
                                    <label class="text-secondary" for="passwordConfirm">Confirm your password</label>
                                    <input type="password" class="form-control border rounded p-2"
                                    id="inputPasswordConfirm" name="passwordConfirm" required>
                                </div>
                            </div>  

                            <!-- Button Log in-->
                            <div class="row justify-content-center gap-3">
                                <div class="d-grid">
                                    <button type="submit" class="button-primary">Sing in</button>
                                </div>

                                <!-- Privates links -->
                                <div class="d-grid">
                                    <P >By continuing you agree to the <a class="text-dark" href="#">Terms of use</a> and 
                                    <a  class="text-dark" href="#">Privacy Policy</a></P>
                                </div>
                            </div>
                        </form>                            
                        
                        <!-- Links -->
                        <div class="d-flex justify-content-between">
                            <div class="">
                                <a class="text-dark" href="#">Other issue with sign in</a>
                            </div>
                            <div>
                                <a class="text-dark" href="#">Forgot your password</a>
                            </div>
                        </div>
                    </div>

                    <!-- Divider -->
                    <div class="d-flex justify-content-center align-items-center">
                        <div class="divider"></div>
                            <div>
                                <P class="divider-H p-4">You have an account</P>
                            </div>
                        <div class="divider"></div>
                    </div>

                    <!-- Button Create account-->
                    <div class="row justify-content-center mb-4">
                        <div class="d-grid">
                            <a class="button-secondary" href="login.php">Log in</a>
                        </div>
                    </div>
                </div>
            </aside>
        </div>
        
        <!-- il faut mettre dans singin.js!!!! -->
        <script src="singin.js"></script>
    </body>
</html>
