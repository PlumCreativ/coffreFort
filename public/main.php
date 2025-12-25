<!doctype html>
<html lang="fr">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>CryptoVault</title>

        <!-- Bootstrap -->
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

        <!-- Custom main -->
        <link rel="stylesheet" href="main.css">
    </head>

    <body>
        <header class="py-3 border-bottom bg-white">
            <nav class="container d-flex justify-content-evenly justify-items-center">
                <div>
                    <button class="btn btn-dark" type="button" data-bs-toggle="offcanvas" 
                        data-bs-target="#offcanvasWithBothOptions" aria-controls="offcanvasWithBothOptions">Dossier
                    </button>
                </div>
                <div class="offcanvas offcanvas-start" data-bs-scroll="true" tabindex="-1" 
                    id="offcanvasWithBothOptions" aria-labelledby="offcanvasWithBothOptionsLabel">
                    <div class="offcanvas-header">
                        <h5 class="offcanvas-title" id="offcanvasWithBothOptionsLabel">Les dossier sont afficher ici</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
                    </div>
                    <div class="offcanvas-body">
                        <p>Try scrolling the rest of the page to see this option in action.</p>
                    </div>
                </div>
                <div>
                    <img src="img/logo.jpeg" width="80" class="logo" alt="CryptoVault logo">
                </div>
                <div class="">
                    <button class="btn btn-outline-dark" type="button">
                        Quitter
                    </button>
                </div>
            </nav>
        </header>

        <!-- Hero -->
        <section class="hero text-center py-5">
            <h1 class="fw-bold">Welcome to CryptoVault</h1>
            <p class="text-muted">Your secure file storage solution</p>
        </section>

        <!-- Features -->
        <section class="container my-5">
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="cv-card text-center p-4">
                        <i class="bi bi-folder-fill feature-icon"></i>
                        <h5 class="mt-3">Organize Your Files</h5>
                        <p class="text-muted">Create folders and manage your files efficiently.</p>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="cv-card text-center p-4">
                        <i class="bi bi-shield-lock-fill feature-icon"></i>
                        <h5 class="mt-3">Secure Storage</h5>
                        <p class="text-muted">Your files are encrypted and stored securely.</p>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="cv-card text-center p-4">
                        <i class="bi bi-cloud-arrow-up-fill feature-icon"></i>
                        <h5 class="mt-3">Easy Uploads</h5>
                        <p class="text-muted">Upload files quickly and access them anywhere.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Table + Search -->
        <section class="container my-5">
            
            <div class="cv-table-header d-flex align-items-center gap-3 flex-wrap mb-3">
                
                <!-- Search -->
                <div class="cv-search flex-grow-1">
                    <i class="bi bi-search"></i>
                    <input type="text" placeholder="Rechercher un fichier ou dossier">
                </div>

                <!-- Sort -->
                <div class="cv-select">
                    <select>
                        <option selected>Trier</option>
                        <option value="1">Les plus récents</option>
                        <option value="2">Les plus anciens</option>
                        <option value="3">Modifiés récemment</option>
                    </select>
                    <i class="bi bi-chevron-down"></i>
                </div>

                <!-- Buttons -->
                <button class="btn btn-outline-dark">
                    <i class="bi bi-share"></i> Partager
                </button>

                <button class="btn btn-dark">
                    <i class="bi bi-cloud-arrow-up"></i> Téléverser
                </button>
            </div>

            <!-- Progress -->
            <div class="progress my-4" role="progressbar" aria-label="Danger example" aria-valuenow="100" aria-valuemin="0" aria-valuemax="100">
            <div class="progress-bar text-bg-danger" style="width: 100%">100%</div>
            </div>

            <!-- Table -->
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Nom fichier/dossier</th>
                            <th>Taille</th>
                            <th>Compteur d'usages</th>
                            <th>Expiration</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>File Upload</td>
                        </tr>
                        <tr>
                            <td>Folder Management</td>
                        </tr>
                        <tr>
                            <td>User Authentication</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Footer -->
        <footer class="text-center py-5 bg-light mt-5">
            <h2>Your Files</h2>
            <p class="text-muted">Manage and access your files securely</p>
        </footer>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>

        <!-- il faut mettre dans main.js!!! -->
        <script src=main.js></script>
    </body>
</html>
