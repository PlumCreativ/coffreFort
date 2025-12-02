<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>CoffreFort</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="style.css">
</head>
    <body>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>

        
        <header>

            <nav class="navbar">

                   
                <!-- Icon -->
                    <div class="d-flex justify-content-center logo">
                        <img src="img/logo.jpeg" width="100" height="100" alt="Logo">
                        </img>
                    </div>

            </nav>

        </header>

    <!-- Hero Section -->
    <section class="hero">
        <h1>Welcome to CryptoVault</h1>
        <p>Your secure file storage solution</p>
    </section>



        <main class="container my-5">
            <div class="row">
                <div class="col-md-4">
                    <div class="card text-center">
                        <div class="card-body">
                            <i class="bi bi-folder-fill display-4 mb-3"></i>
                            <h5 class="card-title">Organize Your Files</h5>
                            <p class="card-text">Create folders and manage your files efficiently.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card text-center">
                        <div class="card-body">
                            <i class="bi bi-shield-lock-fill display-4 mb-3"></i>
                            <h5 class="card-title">Secure Storage</h5>
                            <p class="card-text">Your files are encrypted and stored securely.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card text-center">
                        <div class="card-body">
                            <i class="bi bi-cloud-arrow-up-fill display-4 mb-3"></i>
                            <h5 class="card-title">Easy Uploads</h5>
                            <p class="card-text">Upload files quickly and access them from anywhere.</p>
                        </div>
                    </div>
                </div>
            </div>
        </main> 


        <section class="features-table">
            <div class="table-container">

                <div class="table-parameters py-3">
    
                    <div class="search-container">
                        <input type="text" placeholder="Rechercher un fichier ou dossier">
                        <i class="bi bi-search search-icon"></i>
                    </div>
    
                    <div class="option-container">
                        <select class="" aria-label="Trier">
                            <option selected>Trier</option>
                            <option value="1">les plus récent</option>
                            <option value="2">les plus anciens</option>
                            <option value="3">modifier récemment</option>
                        </select>
                        <i class="bi bi-chevron-down option-ico"></i>
                        
                    </div>
                    <div>
                        <button type="button" class="button-secondary"><i class="bi bi-share"></i> Partager</button>
                    </div>
                    <div>
                        <button type="button" class="button-secondary"> <i class="bi bi-cloud-arrow-up"></i> Téléverser</button>
                    </div>

                </div>

                <div class="progress m-3" role="progressbar" aria-label="Animated striped example" aria-valuenow="75" aria-valuemin="0" aria-valuemax="100">
                    <div class="progress-bar progress-bar-striped progress-bar-animated bg-danger" style="width: 75%">540 MB</div>
                </div>


                <table class="table table-bordered my- 3">
                    <thead>
                        <tr>
                            <th scope="col">nom fichier/dossier</th>
                            <th scope="col">taille</th>
                            <th scope="col">taille compteur d’usages</th>
                            <th scope="col">message d’expiration</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>File Upload</td>
                            <td>Securely upload your files to the vault with encryption.</td>
                            <td>Securely upload your files to the vault with encryption.</td>
                            <td>Securely upload your files to the vault with encryption.</td>
                        </tr>
                        <tr>
                            <td>Folder Management</td>
                            <td>Create, delete, and organize folders for better file management.</td>
                            <td>Create, delete, and organize folders for better file management.</td>
                            <td>Create, delete, and organize folders for better file management.</td>
                        </tr>
                        <tr>
                            <td>User Authentication</td>
                            <td>Register and log in to access your secure file vault.</td>
                            <td>Register and log in to access your secure file vault.</td>
                            <td>Register and log in to access your secure file vault.</td>
                        </tr>
                        <tr>
                            <td>Quota Management</td>
                            <td>Set and monitor your storage quota to manage space effectively.</td>
                            <td>Set and monitor your storage quota to manage space effectively.</td>
                            <td>Set and monitor your storage quota to manage space effectively.</td>
                        </tr>
                        <tr>
                            <td>Activity Tracking</td>
                            <td>Monitor your file access and upload history for security.</td>
                            <td>Monitor your file access and upload history for security.</td>
                            <td>Monitor your file access and upload history for security.</td>
                        </tr>
                        <tr>
                            <td>Statistics Overview</td>
                            <td>View usage statistics and storage details of your vault.</td>
                            <td>View usage statistics and storage details of your vault.</td>
                            <td>View usage statistics and storage details of your vault.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>



    <!-- Files header -->
    <footer class="files-header">
        <h2>Your Files</h2>
        <p>Manage and access your files securely</p>
    </footer>
    </body>
</html>